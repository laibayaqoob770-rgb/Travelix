<?php
/**
 * Shared server-side Firestore + Identity Toolkit access via the admin service
 * account. Everything here bypasses Firestore client security rules, so pages
 * never depend on what a given browser is allowed to read.
 *
 * Used by both the hotel portal and the admin pages.
 */

function hp_service_token($serviceAccountPath, $scope)
{
    static $memory = [];
    $key = hash('sha256', (string)realpath($serviceAccountPath) . '|' . $scope);
    if (!empty($memory[$key])) return $memory[$key];

    $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'travelix_oauth_' . $key . '.json';
    $handle = @fopen($cachePath, 'c+');
    if ($handle && flock($handle, LOCK_EX)) {
        rewind($handle);
        $disk = json_decode((string)stream_get_contents($handle), true);
        if (!empty($disk['access_token']) && (int)($disk['expires_at'] ?? 0) > time() + 60) {
            $memory[$key] = (string)$disk['access_token'];
            flock($handle, LOCK_UN); fclose($handle);
            return $memory[$key];
        }
    }

    $sa = json_decode(@file_get_contents($serviceAccountPath), true);
    if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
        if ($handle) { @flock($handle, LOCK_UN); @fclose($handle); }
        return '';
    }

    $b64url = function ($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $now = time();
    $header = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim  = $b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => $scope,
        'aud'   => $sa['token_uri'],
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $sig = '';
    if (!openssl_sign("$header.$claim", $sig, $sa['private_key'], 'SHA256')) {
        if ($handle) { @flock($handle, LOCK_UN); @fclose($handle); }
        return '';
    }
    $jwt = "$header.$claim." . $b64url($sig);

    $ch = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = (string)($res['access_token'] ?? '');
    if ($token !== '' && $handle) {
        rewind($handle); ftruncate($handle, 0);
        fwrite($handle, json_encode([
            'access_token' => $token,
            'expires_at' => $now + max(120, (int)($res['expires_in'] ?? 3600) - 60),
        ]));
        fflush($handle); @chmod($cachePath, 0600);
    }
    if ($handle) { @flock($handle, LOCK_UN); @fclose($handle); }
    $memory[$key] = $token;
    return $token;
}

function hp_firestore_token($serviceAccountPath)
{
    return hp_service_token($serviceAccountPath, 'https://www.googleapis.com/auth/datastore');
}

/* ── Value encoding / decoding ───────────────────────────────── */

function hp_decode_fields($fields)
{
    $out = [];
    foreach ((array)$fields as $key => $val) {
        $out[$key] = hp_decode_value($val);
    }
    return $out;
}

function hp_decode_value($val)
{
    if (!is_array($val)) return null;
    if (isset($val['stringValue']))    return $val['stringValue'];
    if (isset($val['integerValue']))   return (int)$val['integerValue'];
    if (isset($val['doubleValue']))    return (float)$val['doubleValue'];
    if (isset($val['booleanValue']))   return (bool)$val['booleanValue'];
    if (isset($val['timestampValue'])) return $val['timestampValue'];
    if (isset($val['nullValue']))      return null;
    if (isset($val['mapValue']))       return hp_decode_fields($val['mapValue']['fields'] ?? []);
    if (isset($val['arrayValue'])) {
        $items = [];
        foreach (($val['arrayValue']['values'] ?? []) as $item) {
            $items[] = hp_decode_value($item);
        }
        return $items;
    }
    return null;
}

function hp_encode_value($v)
{
    if (is_bool($v))  return ['booleanValue' => $v];
    if (is_int($v))   return ['integerValue' => (string)$v];
    if (is_float($v)) return ['doubleValue' => $v];
    if (is_null($v))  return ['nullValue' => null];
    if (is_array($v)) {
        // Treat a list as an array, an associative array as a map.
        if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
            $items = array_map('hp_encode_value', $v);
            return ['arrayValue' => ['values' => $items]];
        }
        return ['mapValue' => ['fields' => hp_encode_fields($v)]];
    }
    return ['stringValue' => (string)$v];
}

function hp_encode_fields(array $data)
{
    $fields = [];
    foreach ($data as $k => $v) {
        $fields[$k] = hp_encode_value($v);
    }
    return $fields;
}

/* ── Document read / write ───────────────────────────────────── */

/** Reads one document. $docPath e.g. "commission_payments/abc123". */
function hp_firestore_get($serviceAccountPath, $projectId, $docPath)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return null;

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$docPath}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return null;
    $json = json_decode($resp, true);
    return hp_decode_fields($json['fields'] ?? []);
}

/** Creates or overwrites one document at a known id. */
function hp_firestore_set($serviceAccountPath, $projectId, $docPath, array $data)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return false;

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$docPath}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['fields' => hp_encode_fields($data)]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

/**
 * Partially updates a document, touching only the given field paths (dot
 * notation supported, e.g. 'bookedHotel.refundStatus') — unlike
 * hp_firestore_set, which overwrites the whole document.
 */
function hp_firestore_patch($serviceAccountPath, $projectId, $docPath, array $fields)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return false;

    // Build the nested document tree the dot-paths describe, e.g.
    // 'bookedHotel.refundStatus' => 'sent' becomes ['bookedHotel' => ['refundStatus' => 'sent']],
    // since the Firestore REST API's `fields` body must mirror the actual
    // nested structure — only updateMask.fieldPaths uses dot notation directly.
    $tree = [];
    foreach ($fields as $path => $value) {
        $segments = explode('.', $path);
        $cursor = &$tree;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $cursor[$segment] = $value;
            } else {
                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                $cursor = &$cursor[$segment];
            }
        }
        unset($cursor);
    }

    $maskParams = '';
    foreach (array_keys($fields) as $path) {
        $maskParams .= '&updateMask.fieldPaths=' . urlencode($path);
    }

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$docPath}?" . ltrim($maskParams, '&'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['fields' => hp_encode_fields($tree)]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

/** Creates a document with an auto-generated id. Returns the new id or ''. */
function hp_firestore_create($serviceAccountPath, $projectId, $collection, array $data)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return '';

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['fields' => hp_encode_fields($data)]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) return '';
    $json = json_decode($resp, true);
    return isset($json['name']) ? basename($json['name']) : '';
}

/** Deletes one document at an exact path. */
function hp_firestore_delete($serviceAccountPath, $projectId, $docPath)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return false;

    $segments = array_map('rawurlencode', explode('/', trim($docPath, '/')));
    $encodedPath = implode('/', $segments);
    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$encodedPath}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status === 200 || $status === 204;
}

/** Generates a Firestore-compatible document id without a network round trip. */
function hp_firestore_auto_id($length = 20)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $id = '';
    for ($i = 0; $i < $length; $i++) $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $id;
}

/**
 * Commits several creates/patches in one atomic Firestore request.
 * Each item: ['path'=>..., 'data'=>..., 'mask'=>true for partial update].
 */
function hp_firestore_commit($serviceAccountPath, $projectId, array $items)
{
    if (!$items) return true;
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return false;
    $prefix = "projects/{$projectId}/databases/(default)/documents/";
    $writes = [];
    foreach ($items as $item) {
        $data = (array)($item['data'] ?? []);
        $tree = [];
        foreach ($data as $path => $value) {
            $segments = explode('.', (string)$path);
            $cursor = &$tree;
            foreach ($segments as $i => $segment) {
                if ($i === count($segments) - 1) $cursor[$segment] = $value;
                else { if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) $cursor[$segment] = []; $cursor = &$cursor[$segment]; }
            }
            unset($cursor);
        }
        $write = ['update' => [
            'name' => $prefix . implode('/', array_map('rawurlencode', explode('/', trim((string)$item['path'], '/')))),
            'fields' => hp_encode_fields($tree),
        ]];
        if (!empty($item['mask'])) $write['updateMask'] = ['fieldPaths' => array_keys($data)];
        $writes[] = $write;
    }
    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:commit");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['writes'=>$writes]),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
    curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $status >= 200 && $status < 300;
}

/**
 * Runs an equality query over a collection, or lists it all when $field is null.
 * Returns a list of decoded docs, each including its 'id'.
 */
function hp_firestore_query($serviceAccountPath, $projectId, $collection, $field = null, $value = null, $limit = 0)
{
    $token = hp_firestore_token($serviceAccountPath);
    if (!$token) return [];

    $query = ['from' => [['collectionId' => $collection]]];
    if ($field !== null) {
        $query['where'] = [
            'fieldFilter' => [
                'field' => ['fieldPath' => $field],
                'op'    => 'EQUAL',
                'value' => hp_encode_value($value),
            ],
        ];
    }
    if ($limit > 0) $query['limit'] = $limit;

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['structuredQuery' => $query]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 25,
    ]);
    $rows = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $out = [];
    foreach ((array)$rows as $row) {
        $doc = $row['document'] ?? null;
        if (!$doc) continue;
        $item = hp_decode_fields($doc['fields'] ?? []);
        $item['id'] = basename($doc['name']);
        $out[] = $item;
    }
    return $out;
}

/**
 * Accepts direct UID ownership, plus the legacy duplicate-profile case where
 * the booking UID and PHP-session UID differ but both records belong to the
 * same normalized email address.
 */
function hp_booking_belongs_to_session($serviceAccountPath, $projectId, array $booking, array $sessionUser)
{
    $ownerUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
    $sessionUid = (string)($sessionUser['uid'] ?? '');
    if ($ownerUid !== '' && hash_equals($ownerUid, $sessionUid)) return true;

    $sessionEmail = strtolower(trim((string)($sessionUser['email'] ?? '')));
    if ($sessionEmail === '' && $sessionUid !== '') {
        $sessionProfile = hp_firestore_get($serviceAccountPath, $projectId, 'users/' . $sessionUid);
        $sessionEmail = strtolower(trim((string)($sessionProfile['email'] ?? '')));
    }
    $bookingEmail = strtolower(trim((string)($booking['userEmail'] ?? '')));
    if ($sessionEmail !== '' && $bookingEmail !== '' && hash_equals($bookingEmail, $sessionEmail)) return true;

    if ($ownerUid === '' || $sessionEmail === '') return false;
    $ownerProfile = hp_firestore_get($serviceAccountPath, $projectId, 'users/' . $ownerUid);
    $ownerEmail = strtolower(trim((string)($ownerProfile['email'] ?? '')));
    return $ownerEmail !== '' && hash_equals($ownerEmail, $sessionEmail);
}

/* ── Identity Toolkit (account / password administration) ────── */

function hp_identity_token($serviceAccountPath)
{
    return hp_service_token(
        $serviceAccountPath,
        'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/identitytoolkit'
    );
}

/** Sets a new password on a Firebase Auth account. Returns [ok, errorMessage]. */
function hp_set_user_password($serviceAccountPath, $webApiKey, $uid, $newPassword)
{
    $token = hp_identity_token($serviceAccountPath);
    if (!$token) return [false, 'Could not obtain admin token.'];

    $ch = curl_init("https://www.googleapis.com/identitytoolkit/v3/relyingparty/setAccountInfo?key={$webApiKey}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'localId'           => $uid,
            'password'          => $newPassword,
            'returnSecureToken' => false,
        ]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200) return [true, ''];

    $data = json_decode($resp, true);
    return [false, $data['error']['message'] ?? "Password update failed (HTTP $status)"];
}

/** Looks up a Firebase Auth uid by email. Returns uid or ''. */
function hp_lookup_auth_uid($serviceAccountPath, $projectId, $email)
{
    $token = hp_identity_token($serviceAccountPath);
    if (!$token) return '';

    $ch = curl_init("https://identitytoolkit.googleapis.com/v1/projects/{$projectId}/accounts:lookup");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['email' => [$email]]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $data = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $data['users'][0]['localId'] ?? '';
}

/** Verifies a password by attempting a sign-in. Returns true when correct. */
function hp_verify_password($webApiKey, $email, $password)
{
    $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key={$webApiKey}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => false,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status === 200;
}
