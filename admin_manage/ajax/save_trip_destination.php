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
    ], $extra));
    exit;
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    respond(false, 'Session expired. Please log in again.');
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    respond(false, 'Only admin can save cities.');
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

$cityName = trim((string)($_POST['cityName'] ?? ''));
$citySlug = trim((string)($_POST['citySlug'] ?? ''));
$centerLat = isset($_POST['centerLat']) && $_POST['centerLat'] !== '' ? (float)$_POST['centerLat'] : 0;
$centerLng = isset($_POST['centerLng']) && $_POST['centerLng'] !== '' ? (float)$_POST['centerLng'] : 0;
$cityDescription = trim((string)($_POST['cityDescription'] ?? ''));
$bulkMode = (string)($_POST['bulkMode'] ?? '0') === '1';

if ($cityName === '') {
    respond(false, 'City name is required.');
}

if ($citySlug === '') {
    $citySlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $cityName), '-'));
}

if ($centerLat == 0 || $centerLng == 0) {
    respond(false, 'Valid city center latitude and longitude are required.');
}

$locationImagesDir = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/location_images/';
if (!is_dir($locationImagesDir) && !mkdir($locationImagesDir, 0777, true)) {
    respond(false, 'Could not create location_images folder.');
}

function normalize_name($value)
{
    return strtolower(trim((string)$value));
}

function upload_single_image($fileArray, $targetDir, $baseName)
{
    if (!isset($fileArray['tmp_name']) || !is_uploaded_file($fileArray['tmp_name'])) {
        return null;
    }

    $originalName = $fileArray['name'] ?? 'image.jpg';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'jpg';
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($baseName));
    $storedName = $safeBase . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $targetDir . $storedName;

    if (!move_uploaded_file($fileArray['tmp_name'], $destination)) {
        return null;
    }

    return $storedName;
}

function download_remote_image($url, $targetDir, $baseName)
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)'
        ]
    ]);

    $data = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status !== 200 || !$data || stripos($contentType, 'image/') !== 0) {
        return null;
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];
    $ext = $extMap[strtolower($contentType)] ?? 'jpg';

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($baseName));
    $storedName = $safeBase . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $targetDir . $storedName;

    if (file_put_contents($destination, $data) === false) {
        return null;
    }

    return $storedName;
}

function parse_bulk_dataset($filePath)
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception('Could not read bulk dataset file.');
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (!$lines || count($lines) < 2) {
        throw new Exception('Bulk dataset must contain header row and at least one data row.');
    }

    $headers = array_map(
        fn($v) => strtolower(trim((string)$v)),
        str_getcsv(array_shift($lines))
    );

    $requiredHeaders = [
        'city',
        'city_slug',
        'city_description',
        'center_lat',
        'center_lng',
        'location_name',
        'image_name',
        'lat',
        'lng',
        'description',
        'why_go',
        'know_before_you_go'
    ];

    foreach ($requiredHeaders as $required) {
        if (!in_array($required, $headers, true)) {
            throw new Exception("Dataset is missing required column: {$required}");
        }
    }

    $rows = [];
    foreach ($lines as $index => $line) {
        if (trim($line) === '') {
            continue;
        }

        $values = str_getcsv($line);
        $row = [];

        foreach ($headers as $i => $header) {
            $row[$header] = $values[$i] ?? '';
        }

        $row['__row'] = $index + 2;
        $rows[] = $row;
    }

    return $rows;
}

function split_pipe_values($value)
{
    $parts = explode('|', (string)$value);
    $clean = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }

    return $clean;
}

function base64url_encode($data)
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

    $jwtHeader = base64url_encode(json_encode($header));
    $jwtClaimSet = base64url_encode(json_encode($claimSet));
    $signatureInput = $jwtHeader . '.' . $jwtClaimSet;

    $signature = '';
    $success = openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
    if (!$success) {
        throw new Exception('Failed to sign JWT with service account private key.');
    }

    $jwt = $signatureInput . '.' . base64url_encode($signature);

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
        CURLOPT_TIMEOUT => 20
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $response];
}

try {
    $accessToken = get_google_access_token($serviceAccountPath);

    $newPlaces = [];

    if ($bulkMode) {
        if (!isset($_FILES['bulkFile']) || empty($_FILES['bulkFile']['tmp_name'])) {
            respond(false, 'Please upload a bulk dataset file.');
        }

        $rows = parse_bulk_dataset($_FILES['bulkFile']['tmp_name']);

        $uploadedBulkImages = [];
        if (isset($_FILES['bulkImages']) && isset($_FILES['bulkImages']['name']) && is_array($_FILES['bulkImages']['name'])) {
            foreach ($_FILES['bulkImages']['name'] as $i => $name) {
                if (($_FILES['bulkImages']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $file = [
                    'name' => $_FILES['bulkImages']['name'][$i],
                    'tmp_name' => $_FILES['bulkImages']['tmp_name'][$i],
                    'error' => $_FILES['bulkImages']['error'][$i]
                ];

                $storedName = upload_single_image($file, $locationImagesDir, pathinfo($name, PATHINFO_FILENAME));
                if ($storedName) {
                    $uploadedBulkImages[normalize_name($name)] = $storedName;
                }
            }
        }

        foreach ($rows as $row) {
            $locationName = trim((string)($row['location_name'] ?? ''));
            $imageName = trim((string)($row['image_name'] ?? ''));

            if ($locationName === '') {
                continue;
            }

            if (!isset($uploadedBulkImages[normalize_name($imageName)])) {
                throw new Exception("Bulk image not found for row {$row['__row']}: {$imageName}");
            }

            $newPlaces[] = [
                'name' => $locationName,
                'image' => $uploadedBulkImages[normalize_name($imageName)],
                'image_source' => 'admin',
                'lat' => (float)($row['lat'] ?? 0),
                'lng' => (float)($row['lng'] ?? 0),
                'description' => trim((string)($row['description'] ?? '')),
                'why_go' => split_pipe_values($row['why_go'] ?? ''),
                'know_before_you_go' => split_pipe_values($row['know_before_you_go'] ?? '')
            ];
        }
    } else {
        if (!isset($_POST['locations']) || !is_array($_POST['locations'])) {
            respond(false, 'Please add at least one location.');
        }

        foreach ($_POST['locations'] as $index => $location) {
            $name = trim((string)($location['name'] ?? ''));
            $lat = (float)($location['lat'] ?? 0);
            $lng = (float)($location['lng'] ?? 0);
            $description = trim((string)($location['description'] ?? ''));
            $whyGo = $location['why_go'] ?? [];
            $knowBefore = $location['know_before_you_go'] ?? [];

            if ($name === '' || $lat == 0 || $lng == 0 || $description === '') {
                continue;
            }

            $file = null;
            if (isset($_FILES['locations']['name'][$index]['image'])) {
                $file = [
                    'name' => $_FILES['locations']['name'][$index]['image'],
                    'tmp_name' => $_FILES['locations']['tmp_name'][$index]['image'],
                    'error' => $_FILES['locations']['error'][$index]['image']
                ];
            }

            $hasUploadedFile = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
            $imageUrl = trim((string)($location['image_url'] ?? ''));

            if ($hasUploadedFile) {
                $storedName = upload_single_image($file, $locationImagesDir, $name);
                if (!$storedName) {
                    throw new Exception("Failed to upload image for {$name}.");
                }
            } elseif ($imageUrl !== '') {
                $storedName = download_remote_image($imageUrl, $locationImagesDir, $name);
                if (!$storedName) {
                    throw new Exception("Failed to fetch the found image for {$name}. Please upload an image manually.");
                }
            } else {
                throw new Exception("Location image is required for {$name}. Upload one or use Find Location to fetch one automatically.");
            }

            $newPlaces[] = [
                'name' => $name,
                'image' => $storedName,
                'image_source' => 'admin',
                'lat' => $lat,
                'lng' => $lng,
                'description' => $description,
                'why_go' => array_values(array_filter(array_map('trim', (array)$whyGo))),
                'know_before_you_go' => array_values(array_filter(array_map('trim', (array)$knowBefore)))
            ];
        }
    }

    if (!$newPlaces) {
        respond(false, 'No valid locations found to save.');
    }

    $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/trip_destinations/{$citySlug}";

    $existingPlaces = [];
    $existingCenter = [$centerLat, $centerLng];
    $existingDescription = $cityDescription;

    [$existingStatus, $existingResponse] = firestore_request('GET', $docUrl, $accessToken);

    if ($existingStatus === 200 && $existingResponse) {
        $existingDoc = json_decode($existingResponse, true);
        $fields = $existingDoc['fields'] ?? [];

        if (isset($fields['description']['stringValue']) && $fields['description']['stringValue'] !== '') {
            $existingDescription = $fields['description']['stringValue'];
        }

        if (isset($fields['center']['mapValue']['fields']['lat'])) {
            $existingCenter[0] = isset($fields['center']['mapValue']['fields']['lat']['doubleValue'])
                ? (float)$fields['center']['mapValue']['fields']['lat']['doubleValue']
                : (float)($fields['center']['mapValue']['fields']['lat']['integerValue'] ?? $centerLat);
        }

        if (isset($fields['center']['mapValue']['fields']['lng'])) {
            $existingCenter[1] = isset($fields['center']['mapValue']['fields']['lng']['doubleValue'])
                ? (float)$fields['center']['mapValue']['fields']['lng']['doubleValue']
                : (float)($fields['center']['mapValue']['fields']['lng']['integerValue'] ?? $centerLng);
        }

        foreach (($fields['places']['arrayValue']['values'] ?? []) as $placeValue) {
            $map = $placeValue['mapValue']['fields'] ?? [];

            $whyGo = [];
            foreach (($map['why_go']['arrayValue']['values'] ?? []) as $item) {
                $whyGo[] = $item['stringValue'] ?? '';
            }

            $knowBefore = [];
            foreach (($map['know_before_you_go']['arrayValue']['values'] ?? []) as $item) {
                $knowBefore[] = $item['stringValue'] ?? '';
            }

            $existingPlaces[] = [
                'name' => $map['name']['stringValue'] ?? '',
                'image' => $map['image']['stringValue'] ?? '',
                'image_source' => $map['image_source']['stringValue'] ?? 'admin',
                'lat' => isset($map['lat']['doubleValue']) ? (float)$map['lat']['doubleValue'] : (float)($map['lat']['integerValue'] ?? 0),
                'lng' => isset($map['lng']['doubleValue']) ? (float)$map['lng']['doubleValue'] : (float)($map['lng']['integerValue'] ?? 0),
                'description' => $map['description']['stringValue'] ?? '',
                'why_go' => $whyGo,
                'know_before_you_go' => $knowBefore
            ];
        }
    }

    $existingNames = [];
    foreach ($existingPlaces as $place) {
        $existingNames[normalize_name($place['name'] ?? '')] = true;
    }

    $addedCount = 0;
    foreach ($newPlaces as $place) {
        $key = normalize_name($place['name']);
        if ($key === '' || isset($existingNames[$key])) {
            continue;
        }

        $existingPlaces[] = $place;
        $existingNames[$key] = true;
        $addedCount++;
    }

    if ($addedCount === 0) {
        respond(true, 'All submitted locations already exist for this city. No duplicates were added.', [
            'addedCount' => 0
        ]);
    }

    $placeValues = [];
    foreach ($existingPlaces as $place) {
        $whyValues = [];
        foreach ((array)$place['why_go'] as $item) {
            $whyValues[] = ['stringValue' => (string)$item];
        }

        $knowValues = [];
        foreach ((array)$place['know_before_you_go'] as $item) {
            $knowValues[] = ['stringValue' => (string)$item];
        }

        $placeValues[] = [
            'mapValue' => [
                'fields' => [
                    'name' => ['stringValue' => (string)$place['name']],
                    'image' => ['stringValue' => (string)$place['image']],
                    'image_source' => ['stringValue' => (string)($place['image_source'] ?? 'admin')],
                    'lat' => ['doubleValue' => (float)$place['lat']],
                    'lng' => ['doubleValue' => (float)$place['lng']],
                    'description' => ['stringValue' => (string)$place['description']],
                    'why_go' => ['arrayValue' => ['values' => $whyValues]],
                    'know_before_you_go' => ['arrayValue' => ['values' => $knowValues]]
                ]
            ]
        ];
    }

    $payload = [
        'fields' => [
            'city' => ['stringValue' => $cityName],
            'slug' => ['stringValue' => $citySlug],
            'description' => ['stringValue' => $existingDescription],
            'center' => [
                'mapValue' => [
                    'fields' => [
                        'lat' => ['doubleValue' => (float)$existingCenter[0]],
                        'lng' => ['doubleValue' => (float)$existingCenter[1]]
                    ]
                ]
            ],
            'places' => [
                'arrayValue' => [
                    'values' => $placeValues
                ]
            ],
            'source' => ['stringValue' => 'admin_panel'],
            'updatedAt' => ['timestampValue' => gmdate('c')]
        ]
    ];

    [$saveStatus, $saveResponse] = firestore_request('PATCH', $docUrl, $accessToken, $payload);

    if ($saveStatus < 200 || $saveStatus >= 300) {
        respond(false, 'Failed to save city to Firebase.', [
            'firebase_status' => $saveStatus,
            'firebase_response' => $saveResponse
        ]);
    }

    respond(true, "City saved successfully. {$addedCount} new location(s) added.", [
        'addedCount' => $addedCount
    ]);

} catch (Throwable $e) {
    respond(false, $e->getMessage());
}