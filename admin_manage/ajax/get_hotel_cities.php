<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    respond(false, 'Session expired. Please log in again.');
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    respond(false, 'Only admin can access hotel cities.');
}

$configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
if (!file_exists($configPath)) {
    respond(false, 'firebase_config.php file not found.');
}
require_once $configPath;

if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
    respond(false, 'FIREBASE_PROJECT_ID is missing in firebase_config.php.');
}

$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    respond(false, 'firebase-service-account.json file not found.');
}

$projectId = FIREBASE_PROJECT_ID;

function base64url_encode_custom($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token($serviceAccountPath)
{
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

    if (!$serviceAccount) {
        throw new Exception('Unable to read firebase-service-account.json.');
    }

    $clientEmail = $serviceAccount['client_email'] ?? '';
    $privateKey = $serviceAccount['private_key'] ?? '';
    $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

    if ($clientEmail === '' || $privateKey === '') {
        throw new Exception('Invalid service account file.');
    }

    $now = time();

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    $claimSet = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => $tokenUri,
        'exp' => $now + 3600,
        'iat' => $now
    ];

    $jwtHeader = base64url_encode_custom(json_encode($header));
    $jwtClaimSet = base64url_encode_custom(json_encode($claimSet));
    $signatureInput = $jwtHeader . '.' . $jwtClaimSet;

    $signature = '';
    $success = openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
    if (!$success) {
        throw new Exception('Failed to sign JWT with service account private key.');
    }

    $jwt = $signatureInput . '.' . base64url_encode_custom($signature);

    $postFields = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || !$response) {
        throw new Exception('Failed to get Google access token.');
    }

    $json = json_decode($response, true);
    $accessToken = $json['access_token'] ?? '';

    if ($accessToken === '') {
        throw new Exception('Google access token missing in token response.');
    }

    return $accessToken;
}

function firestore_request($method, $url, $accessToken, $body = null)
{
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $response];
}

function get_string($field, $default = '')
{
    if (!is_array($field)) return $default;
    if (isset($field['stringValue'])) return (string)$field['stringValue'];
    if (isset($field['timestampValue'])) return (string)$field['timestampValue'];
    return $default;
}

function get_number($field, $default = 0)
{
    if (!is_array($field)) return $default;
    if (isset($field['doubleValue'])) return (float)$field['doubleValue'];
    if (isset($field['integerValue'])) return (float)$field['integerValue'];
    return $default;
}

try {
    $accessToken = get_google_access_token($serviceAccountPath);

    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations?pageSize=200";
    [$status, $response] = firestore_request('GET', $url, $accessToken);

    if ($status < 200 || $status >= 300 || !$response) {
        respond(false, 'Failed to fetch hotel cities from Firestore.');
    }

    $json = json_decode($response, true);
    if ($json === null) {
        respond(false, 'Invalid Firestore response.');
    }

    $cities = [];

    foreach (($json['documents'] ?? []) as $doc) {
        $fields = $doc['fields'] ?? [];
        $centerFields = $fields['center']['mapValue']['fields'] ?? [];
        $hotels = [];

        foreach (($fields['hotels']['arrayValue']['values'] ?? []) as $hotelValue) {
            $map = $hotelValue['mapValue']['fields'] ?? [];
            $hotels[] = [
                'id' => get_string($map['id'] ?? null),
                'name' => get_string($map['name'] ?? null)
            ];
        }

        $cities[] = [
            'city' => get_string($fields['city'] ?? null),
            'slug' => get_string($fields['slug'] ?? null),
            'center' => [
                'lat' => get_number($centerFields['lat'] ?? null),
                'lng' => get_number($centerFields['lng'] ?? null)
            ],
            'hotels' => $hotels
        ];
    }

    usort($cities, function ($a, $b) {
        return strcasecmp($a['city'], $b['city']);
    });

    respond(true, 'Hotel cities loaded successfully.', [
        'cities' => $cities
    ]);

} catch (Throwable $e) {
    respond(false, $e->getMessage());
}