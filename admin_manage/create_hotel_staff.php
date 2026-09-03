<?php
/* ================================================================
   Create / Link Hotel Staff Account  — Admin endpoint
   POST JSON: { email, hotelId, hotelName, city }
   Returns JSON: { success, uid, email, password } or { success:false, error }

   Handles both cases:
     • Email is new  → creates Firebase Auth user + sets password
     • Email exists  → looks up UID, resets password, links as staff
================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role'] ?? '') !== 'admin') {
    // Also allow hotel_staff session (for staff-side operations)
    if (!isset($_SESSION['hotel_staff']) && !isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'No active session. Please log in again.']);
        exit;
    }
    if (isset($_SESSION['user']) && strtolower($_SESSION['user']['role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Not an admin. Role: ' . ($_SESSION['user']['role'] ?? 'none')]);
        exit;
    }
    // If only hotel_staff session exists but no user session — allow admin from either
    if (!isset($_SESSION['user']) && isset($_SESSION['hotel_staff'])) {
        echo json_encode(['success' => false, 'error' => 'Logged in as hotel staff, not admin. Go to admin login first.']);
        exit;
    }
}

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/mail_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/send_mail.php';

$input     = json_decode(file_get_contents('php://input'), true);
$email     = trim($input['email']     ?? '');
$hotelId   = trim($input['hotelId']   ?? '');
$hotelName = trim($input['hotelName'] ?? '');
$city      = trim($input['city']      ?? '');
$source    = trim($input['source']    ?? '');   // 'city' for city-based hotels

// Extra fields for city-based hotels (so we can create a full hotels document)
$hotelAddress  = trim($input['address']  ?? '');
$hotelStars    = (int)($input['stars']    ?? 0);
$hotelRating   = (float)($input['rating']   ?? 0);
$hotelReviews  = (int)($input['reviews']  ?? 0);
$hotelPrice    = (float)($input['price_per_night'] ?? 0);
$hotelImage    = trim($input['image']    ?? '');
$hotelFeatures = $input['features']      ?? [];

if (!$email || !$hotelId) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

/* ── Helpers ── */
function b64url_hs($d) {
    return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
}

function getTokenScoped($path, $scope) {
    $sa  = json_decode(file_get_contents($path), true);
    $now = time();
    $hdr = b64url_hs(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $cls = b64url_hs(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => $scope,
        'aud'   => $sa['token_uri'],
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $sig = '';
    openssl_sign("$hdr.$cls", $sig, $sa['private_key'], 'SHA256');
    $jwt = "$hdr.$cls." . b64url_hs($sig);
    $ch  = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 20,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['access_token'] ?? '';
}

function generatePassword($len = 12) {
    $chars    = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    $password = '';
    for ($i = 0; $i < $len; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function adminApiCall($method, $url, $token, $body = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($resp, true)];
}

$projectId          = FIREBASE_PROJECT_ID;
$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
$password           = generatePassword(12);

/* ── Get datastore token early — needed for the uniqueness check below ── */
$fsToken = getTokenScoped($serviceAccountPath, 'https://www.googleapis.com/auth/datastore');
$fsBase  = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents";

/* ── Reject if this email is already assigned as staff to a DIFFERENT hotel ── */
if ($fsToken) {
    $queryBody = [
        'structuredQuery' => [
            'from'  => [['collectionId' => 'hotel_staff']],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'email'],
                    'op'    => 'EQUAL',
                    'value' => ['stringValue' => $email],
                ],
            ],
            'limit' => 5,
        ],
    ];
    $ch = curl_init("{$fsBase}:runQuery");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($queryBody),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $fsToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $queryRes = json_decode(curl_exec($ch), true);
    curl_close($ch);

    foreach ((array)$queryRes as $row) {
        $fields = $row['document']['fields'] ?? null;
        if (!$fields) continue;
        $existingHotelId   = $fields['hotel_id']['stringValue']   ?? '';
        $existingHotelName = $fields['hotel_name']['stringValue'] ?? 'another hotel';
        if ($existingHotelId !== '' && $existingHotelId !== $hotelId) {
            echo json_encode([
                'success' => false,
                'error'   => "This email is already assigned as staff to \"{$existingHotelName}\". Each staff email can only be linked to one hotel — please use a different email.",
            ]);
            exit;
        }
    }
}

/* ── Get admin token (cloud-platform + identitytoolkit scope) ── */
$authToken = getTokenScoped(
    $serviceAccountPath,
    'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/identitytoolkit'
);
if (!$authToken) {
    echo json_encode(['success' => false, 'error' => 'Could not obtain admin token']);
    exit;
}

$apiBase = "https://identitytoolkit.googleapis.com/v1/projects/{$projectId}";
$uid     = null;

/* ── Step 1: Try to create the user ── */
[$createStatus, $createData] = adminApiCall(
    'POST',
    "{$apiBase}/accounts",
    $authToken,
    ['email' => $email, 'password' => $password, 'displayName' => 'Staff — ' . $hotelName, 'disabled' => false]
);

if ($createStatus === 200 && !empty($createData['localId'])) {
    // ✅ New user created
    $uid = $createData['localId'];

} elseif (($createData['error']['message'] ?? '') === 'EMAIL_EXISTS') {
    // ── Email already exists: look up UID then reset password ──

    /* Lookup UID by email */
    [$lookupStatus, $lookupData] = adminApiCall(
        'POST',
        "{$apiBase}/accounts:lookup",
        $authToken,
        ['email' => [$email]]
    );

    if ($lookupStatus !== 200 || empty($lookupData['users'][0]['localId'])) {
        echo json_encode(['success' => false, 'error' => 'Email exists but could not fetch the user record.']);
        exit;
    }
    $uid = trim($lookupData['users'][0]['localId']);

    /* ── Reset password via Identity Toolkit v3 (works with admin token + localId) ── */
    $webApiKey  = FIREBASE_API_KEY;
    $resetUrl   = "https://www.googleapis.com/identitytoolkit/v3/relyingparty/setAccountInfo?key={$webApiKey}";
    $ch = curl_init($resetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'localId'         => $uid,
            'password'        => $password,
            'displayName'     => 'Staff — ' . $hotelName,
            'returnSecureToken' => false,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $authToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resetResp   = curl_exec($ch);
    $resetStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resetData = json_decode($resetResp, true);
    if ($resetStatus !== 200) {
        $errMsg = $resetData['error']['message'] ?? "Failed to reset password (HTTP $resetStatus)";
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }

} else {
    $errMsg = $createData['error']['message'] ?? "Auth API error (HTTP $createStatus)";
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

/* ── Step 2: Email credentials directly to the staff member ── */
// Always the real public site, never wherever this request happened to run
// from (e.g. localhost during testing) — the recipient opens this link on
// their own device, disconnected from whatever server sent the email.
$loginUrl = PRODUCTION_BASE_URL . '/hotel_portal/login.php';

$emailHtml  = travelixStaffCredentialEmail($email, $hotelName, $password, $loginUrl);
$emailError = '';
$emailSent  = travelixSendMail($email, 'Your Travelix Hotel Portal Access', $emailHtml, $emailError);

/* ── Step 3: Write / overwrite hotel_staff Firestore document ── */
$staffDoc = [
    'fields' => [
        'uid'        => ['stringValue' => $uid],
        'email'      => ['stringValue' => $email],
        'hotel_id'   => ['stringValue' => $hotelId],
        'hotel_name' => ['stringValue' => $hotelName],
        'city'       => ['stringValue' => $city],
        'role'       => ['stringValue' => 'hotel_staff'],
        'status'     => ['stringValue' => 'active'],
        'updatedAt'  => ['timestampValue' => gmdate('Y-m-d\TH:i:s\Z')],
    ],
];
$ch = curl_init("{$fsBase}/hotel_staff/{$uid}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode($staffDoc),
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $fsToken, 'Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 25,
]);
curl_exec($ch);
curl_close($ch);

/* ── Step 4: Patch / create hotel document in hotels collection ── */
if ($source === 'city') {
    // City-based hotel → create a FULL document in hotels collection so staff portal works
    $featuresArr = [];
    foreach ((array)$hotelFeatures as $f) {
        $featuresArr[] = ['stringValue' => (string)$f];
    }
    $hotelPatch = [
        'fields' => [
            'name'            => ['stringValue'  => $hotelName],
            'city'            => ['stringValue'  => $city],
            'citySlug'        => ['stringValue'  => strtolower(preg_replace('/\s+/', '-', $city))],
            'address'         => ['stringValue'  => $hotelAddress],
            'stars'           => ['integerValue'  => (string)$hotelStars],
            'rating'          => ['doubleValue'   => $hotelRating],
            'reviews'         => ['integerValue'  => (string)$hotelReviews],
            'price_per_night' => ['doubleValue'   => $hotelPrice],
            'image'           => ['stringValue'  => $hotelImage],
            'features'        => ['arrayValue'   => ['values' => $featuresArr]],
            'status'          => ['stringValue'  => 'active'],
            'source'          => ['stringValue'  => 'city_based'],
            'added_by'        => ['stringValue'  => 'admin'],
            'staff_uid'       => ['stringValue'  => $uid],
            'staff_email'     => ['stringValue'  => $email],
            'updatedAt'       => ['timestampValue' => gmdate('Y-m-d\TH:i:s\Z')],
        ],
    ];
    // Use PATCH (upsert) — creates or overwrites the document
    $ch = curl_init("{$fsBase}/hotels/{$hotelId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($hotelPatch),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $fsToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
    ]);
    curl_exec($ch);
    curl_close($ch);
} else {
    // Portal hotel → only patch staff fields (hotel data already exists)
    $patchUrl   = "{$fsBase}/hotels/{$hotelId}?updateMask.fieldPaths=staff_uid&updateMask.fieldPaths=staff_email";
    $hotelPatch = [
        'fields' => [
            'staff_uid'   => ['stringValue' => $uid],
            'staff_email' => ['stringValue' => $email],
        ],
    ];
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($hotelPatch),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $fsToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

echo json_encode([
    'success'    => true,
    'uid'        => $uid,
    'email'      => $email,
    'emailSent'  => $emailSent,
    'emailError' => $emailError,
]);
