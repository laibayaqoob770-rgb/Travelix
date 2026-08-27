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
    respond(false, 'Only admin can save hotel destinations.');
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
$hotelImagesDir = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/hotel_images/';

if (!is_dir($hotelImagesDir) && !mkdir($hotelImagesDir, 0777, true)) {
    respond(false, 'Could not create hotel_images folder.');
}

$cityName = trim((string)($_POST['cityName'] ?? ''));
$citySlug = trim((string)($_POST['citySlug'] ?? ''));
$centerLat = isset($_POST['centerLat']) && $_POST['centerLat'] !== '' ? (float)$_POST['centerLat'] : 0;
$centerLng = isset($_POST['centerLng']) && $_POST['centerLng'] !== '' ? (float)$_POST['centerLng'] : 0;
$bulkMode = (string)($_POST['bulkMode'] ?? '0') === '1';

function normalize_name($value)
{
    return strtolower(trim((string)$value));
}

function slugify_value($value)
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9\s-]/', '', $value);
    $value = preg_replace('/\s+/', '-', $value);
    $value = preg_replace('/-+/', '-', $value);
    return trim($value, '-');
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

    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
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
        'center_lat',
        'center_lng',
        'hotel_id',
        'hotel_name',
        'image_name',
        'price_per_night',
        'rating',
        'reviews',
        'stars',
        'address',
        'lat',
        'lng',
        'features'
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

function parse_firestore_hotel_doc($doc, $fallbackLat, $fallbackLng)
{
    $fields = $doc['fields'] ?? [];

    $existingCenter = [$fallbackLat, $fallbackLng];
    $existingHotels = [];

    if (isset($fields['center']['mapValue']['fields']['lat'])) {
        $existingCenter[0] = isset($fields['center']['mapValue']['fields']['lat']['doubleValue'])
            ? (float)$fields['center']['mapValue']['fields']['lat']['doubleValue']
            : (float)($fields['center']['mapValue']['fields']['lat']['integerValue'] ?? $fallbackLat);
    }

    if (isset($fields['center']['mapValue']['fields']['lng'])) {
        $existingCenter[1] = isset($fields['center']['mapValue']['fields']['lng']['doubleValue'])
            ? (float)$fields['center']['mapValue']['fields']['lng']['doubleValue']
            : (float)($fields['center']['mapValue']['fields']['lng']['integerValue'] ?? $fallbackLng);
    }

    foreach (($fields['hotels']['arrayValue']['values'] ?? []) as $hotelValue) {
        $map = $hotelValue['mapValue']['fields'] ?? [];

        $features = [];
        foreach (($map['features']['arrayValue']['values'] ?? []) as $item) {
            $features[] = $item['stringValue'] ?? '';
        }

        $existingHotels[] = [
            'id' => $map['id']['stringValue'] ?? '',
            'name' => $map['name']['stringValue'] ?? '',
            'image' => $map['image']['stringValue'] ?? '',
            'image_source' => $map['image_source']['stringValue'] ?? 'admin',
            'price_per_night' => isset($map['price_per_night']['doubleValue'])
                ? (float)$map['price_per_night']['doubleValue']
                : (float)($map['price_per_night']['integerValue'] ?? 0),
            'rating' => isset($map['rating']['doubleValue'])
                ? (float)$map['rating']['doubleValue']
                : (float)($map['rating']['integerValue'] ?? 0),
            'reviews' => (int)($map['reviews']['integerValue'] ?? 0),
            'stars' => (int)($map['stars']['integerValue'] ?? 0),
            'address' => $map['address']['stringValue'] ?? '',
            'lat' => isset($map['lat']['doubleValue'])
                ? (float)$map['lat']['doubleValue']
                : (float)($map['lat']['integerValue'] ?? 0),
            'lng' => isset($map['lng']['doubleValue'])
                ? (float)$map['lng']['doubleValue']
                : (float)($map['lng']['integerValue'] ?? 0),
            'features' => array_values(array_filter(array_map('trim', $features)))
        ];
    }

    return [$existingCenter, $existingHotels];
}

function build_firestore_hotel_values($hotels)
{
    $hotelValues = [];

    foreach ($hotels as $hotel) {
        $featureValues = [];
        foreach ((array)$hotel['features'] as $feature) {
            $featureValues[] = ['stringValue' => (string)$feature];
        }

        $hotelValues[] = [
            'mapValue' => [
                'fields' => [
                    'id' => ['stringValue' => (string)$hotel['id']],
                    'name' => ['stringValue' => (string)$hotel['name']],
                    'image' => ['stringValue' => (string)$hotel['image']],
                    'image_source' => ['stringValue' => (string)($hotel['image_source'] ?? 'admin')],
                    'price_per_night' => ['doubleValue' => (float)$hotel['price_per_night']],
                    'rating' => ['doubleValue' => (float)$hotel['rating']],
                    'reviews' => ['integerValue' => (string)((int)$hotel['reviews'])],
                    'stars' => ['integerValue' => (string)((int)$hotel['stars'])],
                    'address' => ['stringValue' => (string)$hotel['address']],
                    'lat' => ['doubleValue' => (float)$hotel['lat']],
                    'lng' => ['doubleValue' => (float)$hotel['lng']],
                    'features' => ['arrayValue' => ['values' => $featureValues]]
                ]
            ]
        ];
    }

    return $hotelValues;
}

try {
    $accessToken = get_google_access_token($serviceAccountPath);
    $results = [];
    $totalAdded = 0;

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

                $storedName = upload_single_image($file, $hotelImagesDir, pathinfo($name, PATHINFO_FILENAME));
                if ($storedName) {
                    $uploadedBulkImages[normalize_name($name)] = $storedName;
                }
            }
        }

        $groupedCities = [];

        foreach ($rows as $row) {
            $rowCity = trim((string)($row['city'] ?? ''));
            $rowSlug = trim((string)($row['city_slug'] ?? ''));
            $rowCenterLat = (float)($row['center_lat'] ?? 0);
            $rowCenterLng = (float)($row['center_lng'] ?? 0);

            if ($rowCity === '') {
                throw new Exception("Row {$row['__row']}: city is required.");
            }

            if ($rowSlug === '') {
                $rowSlug = slugify_value($rowCity);
            }

            if ($rowCenterLat == 0 || $rowCenterLng == 0) {
                throw new Exception("Row {$row['__row']}: valid center_lat and center_lng are required.");
            }

            $hotelId = trim((string)($row['hotel_id'] ?? ''));
            $hotelName = trim((string)($row['hotel_name'] ?? ''));
            $imageName = trim((string)($row['image_name'] ?? ''));

            if ($hotelId === '' || $hotelName === '') {
                throw new Exception("Row {$row['__row']}: hotel_id and hotel_name are required.");
            }

            if (!isset($uploadedBulkImages[normalize_name($imageName)])) {
                throw new Exception("Row {$row['__row']}: bulk image not found for {$imageName}.");
            }

            $cityKey = normalize_name($rowCity);

            if (!isset($groupedCities[$cityKey])) {
                $groupedCities[$cityKey] = [
                    'city' => $rowCity,
                    'slug' => $rowSlug,
                    'center' => [$rowCenterLat, $rowCenterLng],
                    'hotels' => []
                ];
            }

            $groupedCities[$cityKey]['hotels'][] = [
                'id' => $hotelId,
                'name' => $hotelName,
                'image' => $uploadedBulkImages[normalize_name($imageName)],
                'image_source' => 'admin',
                'price_per_night' => (float)($row['price_per_night'] ?? 0),
                'rating' => (float)($row['rating'] ?? 0),
                'reviews' => (int)($row['reviews'] ?? 0),
                'stars' => (int)($row['stars'] ?? 0),
                'address' => trim((string)($row['address'] ?? '')),
                'lat' => (float)($row['lat'] ?? 0),
                'lng' => (float)($row['lng'] ?? 0),
                'features' => split_pipe_values($row['features'] ?? '')
            ];
        }

        if (!$groupedCities) {
            respond(false, 'No valid bulk hotel rows found.');
        }

        foreach ($groupedCities as $cityData) {
            $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations/{$cityData['slug']}";
            [$existingStatus, $existingResponse] = firestore_request('GET', $docUrl, $accessToken);

            $existingHotels = [];
            $existingCenter = $cityData['center'];

            if ($existingStatus === 200 && $existingResponse) {
                $existingDoc = json_decode($existingResponse, true);
                [$existingCenter, $existingHotels] = parse_firestore_hotel_doc($existingDoc, $cityData['center'][0], $cityData['center'][1]);
            }

            $existingIds = [];
            $existingNames = [];

            foreach ($existingHotels as $hotel) {
                $existingIds[normalize_name($hotel['id'])] = true;
                $existingNames[normalize_name($hotel['name'])] = true;
            }

            $addedCount = 0;
            foreach ($cityData['hotels'] as $hotel) {
                $idKey = normalize_name($hotel['id']);
                $nameKey = normalize_name($hotel['name']);

                if ($idKey === '' || $nameKey === '' || isset($existingIds[$idKey]) || isset($existingNames[$nameKey])) {
                    continue;
                }

                $existingHotels[] = $hotel;
                $existingIds[$idKey] = true;
                $existingNames[$nameKey] = true;
                $addedCount++;
            }

            $payload = [
                'fields' => [
                    'city' => ['stringValue' => $cityData['city']],
                    'slug' => ['stringValue' => $cityData['slug']],
                    'center' => [
                        'mapValue' => [
                            'fields' => [
                                'lat' => ['doubleValue' => (float)$existingCenter[0]],
                                'lng' => ['doubleValue' => (float)$existingCenter[1]]
                            ]
                        ]
                    ],
                    'hotels' => [
                        'arrayValue' => [
                            'values' => build_firestore_hotel_values($existingHotels)
                        ]
                    ],
                    'source' => ['stringValue' => 'admin_panel'],
                    'updatedAt' => ['timestampValue' => gmdate('c')]
                ]
            ];

            [$saveStatus, $saveResponse] = firestore_request('PATCH', $docUrl, $accessToken, $payload);

            if ($saveStatus < 200 || $saveStatus >= 300) {
                respond(false, 'Failed to save hotel destination to Firestore.', [
                    'firestore_status' => $saveStatus,
                    'firestore_response' => $saveResponse
                ]);
            }

            $results[] = "{$cityData['city']}: {$addedCount} hotel(s) added";
            $totalAdded += $addedCount;
        }

        respond(true, 'Hotel destinations saved successfully. ' . implode(' | ', $results), [
            'addedCount' => $totalAdded
        ]);
    }

    if ($cityName === '') {
        respond(false, 'City name is required.');
    }

    if ($citySlug === '') {
        $citySlug = slugify_value($cityName);
    }

    if ($centerLat == 0 || $centerLng == 0) {
        respond(false, 'Valid city center latitude and longitude are required.');
    }

    if (!isset($_POST['hotels']) || !is_array($_POST['hotels'])) {
        respond(false, 'Please add at least one hotel.');
    }

    $newHotels = [];

    foreach ($_POST['hotels'] as $index => $hotel) {
        $hotelId = trim((string)($hotel['id'] ?? ''));
        $hotelName = trim((string)($hotel['name'] ?? ''));
        $pricePerNight = (float)($hotel['price_per_night'] ?? 0);
        $rating = (float)($hotel['rating'] ?? 0);
        $reviews = (int)($hotel['reviews'] ?? 0);
        $stars = (int)($hotel['stars'] ?? 0);
        $address = trim((string)($hotel['address'] ?? ''));
        $lat = (float)($hotel['lat'] ?? 0);
        $lng = (float)($hotel['lng'] ?? 0);
        $features = array_values(array_filter(array_map('trim', (array)($hotel['features'] ?? []))));

        if ($hotelId === '' || $hotelName === '' || $pricePerNight <= 0 || $lat == 0 || $lng == 0) {
            continue;
        }

        $file = null;
        if (isset($_FILES['hotels']['name'][$index]['image'])) {
            $file = [
                'name' => $_FILES['hotels']['name'][$index]['image'],
                'tmp_name' => $_FILES['hotels']['tmp_name'][$index]['image'],
                'error' => $_FILES['hotels']['error'][$index]['image']
            ];
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception("Hotel image is required for {$hotelName}.");
        }

        $storedName = upload_single_image($file, $hotelImagesDir, $hotelName);
        if (!$storedName) {
            throw new Exception("Failed to upload image for {$hotelName}.");
        }

        $newHotels[] = [
            'id' => $hotelId,
            'name' => $hotelName,
            'image' => $storedName,
            'image_source' => 'admin',
            'price_per_night' => $pricePerNight,
            'rating' => $rating,
            'reviews' => $reviews,
            'stars' => $stars,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'features' => $features
        ];
    }

    if (!$newHotels) {
        respond(false, 'No valid hotels found to save.');
    }

    $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations/{$citySlug}";
    [$existingStatus, $existingResponse] = firestore_request('GET', $docUrl, $accessToken);

    $existingHotels = [];
    $existingCenter = [$centerLat, $centerLng];

    if ($existingStatus === 200 && $existingResponse) {
        $existingDoc = json_decode($existingResponse, true);
        [$existingCenter, $existingHotels] = parse_firestore_hotel_doc($existingDoc, $centerLat, $centerLng);
    }

    $existingIds = [];
    $existingNames = [];

    foreach ($existingHotels as $hotel) {
        $existingIds[normalize_name($hotel['id'])] = true;
        $existingNames[normalize_name($hotel['name'])] = true;
    }

    $addedCount = 0;
    foreach ($newHotels as $hotel) {
        $idKey = normalize_name($hotel['id']);
        $nameKey = normalize_name($hotel['name']);

        if ($idKey === '' || $nameKey === '' || isset($existingIds[$idKey]) || isset($existingNames[$nameKey])) {
            continue;
        }

        $existingHotels[] = $hotel;
        $existingIds[$idKey] = true;
        $existingNames[$nameKey] = true;
        $addedCount++;
    }

    if ($addedCount === 0) {
        respond(true, 'All submitted hotels already exist for this city. No duplicates were added.', [
            'addedCount' => 0
        ]);
    }

    $payload = [
        'fields' => [
            'city' => ['stringValue' => $cityName],
            'slug' => ['stringValue' => $citySlug],
            'center' => [
                'mapValue' => [
                    'fields' => [
                        'lat' => ['doubleValue' => (float)$existingCenter[0]],
                        'lng' => ['doubleValue' => (float)$existingCenter[1]]
                    ]
                ]
            ],
            'hotels' => [
                'arrayValue' => [
                    'values' => build_firestore_hotel_values($existingHotels)
                ]
            ],
            'source' => ['stringValue' => 'admin_panel'],
            'updatedAt' => ['timestampValue' => gmdate('c')]
        ]
    ];

    [$saveStatus, $saveResponse] = firestore_request('PATCH', $docUrl, $accessToken, $payload);

    if ($saveStatus < 200 || $saveStatus >= 300) {
        respond(false, 'Failed to save hotel destination to Firestore.', [
            'firestore_status' => $saveStatus,
            'firestore_response' => $saveResponse
        ]);
    }

    respond(true, "Hotel destination saved successfully. {$addedCount} new hotel(s) added.", [
        'addedCount' => $addedCount
    ]);

} catch (Throwable $e) {
    respond(false, $e->getMessage());
}