<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/user_dashboard.php');
    exit;
}

$firstName = (string)($currentUser['first_name'] ?? 'Admin');
$lastName = (string)($currentUser['last_name'] ?? '');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
$fullName = trim($firstName . ' ' . $lastName);

$configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
if (!file_exists($configPath)) {
    die('firebase_config.php file not found.');
}
require_once $configPath;

if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
    die('FIREBASE_PROJECT_ID is missing in firebase_config.php.');
}

$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    die('firebase-service-account.json file not found.');
}

$projectId = FIREBASE_PROJECT_ID;
$pageTitle = 'Manage Cities | Travelix Admin';
$flash = null;

/* -------------------------------------------------------
   Helpers
------------------------------------------------------- */

function base64url_encode_custom($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token_manage($serviceAccountPath)
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || !$response) {
        throw new Exception('Failed to get Google access token. ' . $error);
    }

    $json = json_decode($response, true);
    $accessToken = $json['access_token'] ?? '';

    if ($accessToken === '') {
        throw new Exception('Google access token missing in token response.');
    }

    return $accessToken;
}

function firestore_request_manage($method, $url, $accessToken, $body = null)
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
    $error = curl_error($ch);
    curl_close($ch);

    return [$status, $response, $error];
}

function firestore_value_string($field, $default = '')
{
    if (!is_array($field)) {
        return $default;
    }

    if (isset($field['stringValue'])) {
        return (string)$field['stringValue'];
    }

    if (isset($field['timestampValue'])) {
        return (string)$field['timestampValue'];
    }

    return $default;
}

function firestore_value_number($field, $default = 0)
{
    if (!is_array($field)) {
        return $default;
    }

    if (isset($field['doubleValue'])) {
        return (float)$field['doubleValue'];
    }

    if (isset($field['integerValue'])) {
        return (float)$field['integerValue'];
    }

    return $default;
}

function firestore_array_strings($field)
{
    $result = [];

    if (!isset($field['arrayValue']['values']) || !is_array($field['arrayValue']['values'])) {
        return $result;
    }

    foreach ($field['arrayValue']['values'] as $item) {
        $result[] = (string)($item['stringValue'] ?? '');
    }

    return array_values(array_filter(array_map('trim', $result)));
}

function parse_trip_document($doc)
{
    $fields = $doc['fields'] ?? [];
    $namePath = $doc['name'] ?? '';
    $docId = '';
    if ($namePath !== '') {
        $parts = explode('/', $namePath);
        $docId = end($parts);
    }

    $centerFields = $fields['center']['mapValue']['fields'] ?? [];

    $places = [];
    foreach (($fields['places']['arrayValue']['values'] ?? []) as $placeValue) {
        $map = $placeValue['mapValue']['fields'] ?? [];

        $places[] = [
            'name' => firestore_value_string($map['name'] ?? null),
            'image' => firestore_value_string($map['image'] ?? null),
            'image_source' => firestore_value_string($map['image_source'] ?? null, 'admin'),
            'lat' => firestore_value_number($map['lat'] ?? null),
            'lng' => firestore_value_number($map['lng'] ?? null),
            'description' => firestore_value_string($map['description'] ?? null),
            'why_go' => firestore_array_strings($map['why_go'] ?? null),
            'know_before_you_go' => firestore_array_strings($map['know_before_you_go'] ?? null)
        ];
    }

    return [
        'doc_id' => $docId,
        'city' => firestore_value_string($fields['city'] ?? null),
        'slug' => firestore_value_string($fields['slug'] ?? null, $docId),
        'description' => firestore_value_string($fields['description'] ?? null),
        'source' => firestore_value_string($fields['source'] ?? null),
        'updatedAt' => firestore_value_string($fields['updatedAt'] ?? null),
        'center' => [
            'lat' => firestore_value_number($centerFields['lat'] ?? null),
            'lng' => firestore_value_number($centerFields['lng'] ?? null)
        ],
        'places' => $places
    ];
}

function build_places_firestore_values($places)
{
    $placeValues = [];

    foreach ($places as $place) {
        $whyValues = [];
        foreach ((array)($place['why_go'] ?? []) as $item) {
            $whyValues[] = ['stringValue' => (string)$item];
        }

        $knowValues = [];
        foreach ((array)($place['know_before_you_go'] ?? []) as $item) {
            $knowValues[] = ['stringValue' => (string)$item];
        }

        $placeValues[] = [
            'mapValue' => [
                'fields' => [
                    'name' => ['stringValue' => (string)($place['name'] ?? '')],
                    'image' => ['stringValue' => (string)($place['image'] ?? '')],
                    'image_source' => ['stringValue' => (string)($place['image_source'] ?? 'admin')],
                    'lat' => ['doubleValue' => (float)($place['lat'] ?? 0)],
                    'lng' => ['doubleValue' => (float)($place['lng'] ?? 0)],
                    'description' => ['stringValue' => (string)($place['description'] ?? '')],
                    'why_go' => ['arrayValue' => ['values' => $whyValues]],
                    'know_before_you_go' => ['arrayValue' => ['values' => $knowValues]]
                ]
            ]
        ];
    }

    return $placeValues;
}

function fetch_all_trip_documents($projectId, $accessToken)
{
    $documents = [];
    $pageToken = '';

    do {
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/trip_destinations?pageSize=100";
        if ($pageToken !== '') {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        [$status, $response, $error] = firestore_request_manage('GET', $url, $accessToken);

        if ($status < 200 || $status >= 300) {
            throw new Exception('Failed to fetch trip destinations from Firebase. ' . $error);
        }

        $json = json_decode($response, true);
        if (!$json) {
            throw new Exception('Invalid Firebase response while loading trip destinations.');
        }

        foreach (($json['documents'] ?? []) as $doc) {
            $documents[] = $doc;
        }

        $pageToken = (string)($json['nextPageToken'] ?? '');
    } while ($pageToken !== '');

    return $documents;
}

/* -------------------------------------------------------
   Handle Actions
------------------------------------------------------- */

try {
    $accessToken = get_google_access_token_manage($serviceAccountPath);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'delete_city') {
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($slug === '') {
                throw new Exception('City slug is required.');
            }

            $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/trip_destinations/{$slug}";
            [$status, $response, $error] = firestore_request_manage('DELETE', $docUrl, $accessToken);

            if ($status < 200 || $status >= 300) {
                throw new Exception('Failed to delete city from Firebase. ' . $error);
            }

            $flash = [
                'type' => 'success',
                'title' => 'Deleted',
                'message' => 'Trip city deleted successfully.'
            ];
        }

        if ($action === 'delete_location') {
            $slug = trim((string)($_POST['slug'] ?? ''));
            $locationIndex = isset($_POST['location_index']) ? (int)$_POST['location_index'] : -1;

            if ($slug === '') {
                throw new Exception('City slug is required.');
            }

            if ($locationIndex < 0) {
                throw new Exception('Invalid location index.');
            }

            $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/trip_destinations/{$slug}";
            [$status, $response, $error] = firestore_request_manage('GET', $docUrl, $accessToken);

            if ($status < 200 || $status >= 300 || !$response) {
                throw new Exception('Unable to load selected city from Firebase. ' . $error);
            }

            $doc = json_decode($response, true);
            if (!$doc) {
                throw new Exception('Invalid Firebase response while reading city.');
            }

            $trip = parse_trip_document($doc);
            $places = $trip['places'];

            if (!isset($places[$locationIndex])) {
                throw new Exception('Selected location was not found.');
            }

            $deletedLocationName = $places[$locationIndex]['name'] ?? 'Location';
            array_splice($places, $locationIndex, 1);

            if (count($places) === 0) {
                [$deleteStatus, $deleteResponse, $deleteError] = firestore_request_manage('DELETE', $docUrl, $accessToken);

                if ($deleteStatus < 200 || $deleteStatus >= 300) {
                    throw new Exception('Failed to delete city after removing last location. ' . $deleteError);
                }

                $flash = [
                    'type' => 'success',
                    'title' => 'Deleted',
                    'message' => "Last location removed. City \"{$trip['city']}\" was also deleted."
                ];
            } else {
                $payload = [
                    'fields' => [
                        'city' => ['stringValue' => (string)$trip['city']],
                        'slug' => ['stringValue' => (string)$trip['slug']],
                        'description' => ['stringValue' => (string)$trip['description']],
                        'center' => [
                            'mapValue' => [
                                'fields' => [
                                    'lat' => ['doubleValue' => (float)($trip['center']['lat'] ?? 0)],
                                    'lng' => ['doubleValue' => (float)($trip['center']['lng'] ?? 0)]
                                ]
                            ]
                        ],
                        'places' => [
                            'arrayValue' => [
                                'values' => build_places_firestore_values($places)
                            ]
                        ],
                        'source' => ['stringValue' => (string)($trip['source'] ?: 'admin_panel')],
                        'updatedAt' => ['timestampValue' => gmdate('c')]
                    ]
                ];

                [$saveStatus, $saveResponse, $saveError] = firestore_request_manage('PATCH', $docUrl, $accessToken, $payload);

                if ($saveStatus < 200 || $saveStatus >= 300) {
                    throw new Exception('Failed to update city after deleting location. ' . $saveError);
                }

                $flash = [
                    'type' => 'success',
                    'title' => 'Deleted',
                    'message' => "\"{$deletedLocationName}\" location deleted successfully."
                ];
            }
        }
    }

    $documents = fetch_all_trip_documents($projectId, $accessToken);
    $trips = [];

    foreach ($documents as $doc) {
        $trips[] = parse_trip_document($doc);
    }

    usort($trips, function ($a, $b) {
        return strcasecmp($a['city'], $b['city']);
    });

} catch (Throwable $e) {
    $flash = [
        'type' => 'error',
        'title' => 'Error',
        'message' => $e->getMessage()
    ];
    $trips = [];
}

$search = trim((string)($_GET['search'] ?? ''));
$filteredTrips = [];

if ($search !== '') {
    $needle = mb_strtolower($search);

    foreach ($trips as $trip) {
        $matched = false;

        if (mb_stripos($trip['city'], $needle) !== false || mb_stripos($trip['slug'], $needle) !== false) {
            $matched = true;
        } else {
            foreach ($trip['places'] as $place) {
                if (mb_stripos((string)$place['name'], $needle) !== false) {
                    $matched = true;
                    break;
                }
            }
        }

        if ($matched) {
            $filteredTrips[] = $trip;
        }
    }
} else {
    $filteredTrips = $trips;
}

$totalCities = count($trips);
$totalLocations = 0;
foreach ($trips as $trip) {
    $totalLocations += count($trip['places']);
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_datetime_pretty($value)
{
    if (!$value) {
        return '-';
    }

    $time = strtotime($value);
    if (!$time) {
        return $value;
    }

    return date('d M Y, h:i A', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
            --bg: #f4f8ff;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 18px 45px rgba(29, 78, 216, 0.10);
            --danger-soft: #fee2e2;
            --danger-text: #b91c1c;
            --warning-soft: #fff7ed;
            --warning-text: #c2410c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            font-family: Arial, sans-serif;
            color: var(--text);
        }

        .admin-page {
            display: flex;
            gap: 24px;
            padding: 16px;
            min-height: 100vh;
        }

        .admin-content {
            flex: 1;
            min-width: 0;
        }

        .page-hero,
        .admin-card,
        .trip-card {
            background: rgba(255,255,255,0.95);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
        }

        .page-hero {
            padding: 24px 28px;
            margin-bottom: 22px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-hero h1 {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 34px;
        }

        .page-hero p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
            max-width: 860px;
        }

        .hero-badge {
            padding: 10px 15px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 800;
            border: 1px solid rgba(29, 78, 216, 0.10);
        }

        .admin-card {
            padding: 24px;
            margin-bottom: 22px;
        }

        .top-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .admin-info-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #f4fbfd;
            border: 1px solid rgba(20, 132, 180, 0.16);
        }

        .admin-info-bar img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 2px solid rgba(15, 23, 42, 0.08);
        }

        .admin-info-bar h6 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .admin-info-bar p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .btn-main,
        .btn-soft,
        .btn-danger-soft,
        .btn-warning-soft {
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 800;
            transition: 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-main:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-soft {
            background: #eef4ff;
            color: var(--primary-dark);
        }

        .btn-soft:hover {
            transform: translateY(-1px);
        }

        .btn-danger-soft {
            background: var(--danger-soft);
            color: var(--danger-text);
        }

        .btn-danger-soft:hover {
            transform: translateY(-1px);
        }

        .btn-warning-soft {
            background: var(--warning-soft);
            color: var(--warning-text);
        }

        .btn-warning-soft:hover {
            transform: translateY(-1px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 20px;
            padding: 18px;
        }

        .stat-card h6 {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .stat-card p {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }

        .search-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            margin-top: 18px;
        }

        .form-control {
            width: 100%;
            border: 1px solid rgba(15,23,42,0.10);
            border-radius: 16px;
            min-height: 52px;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            box-shadow: none;
            background: #fff;
        }

        .trips-grid {
            display: grid;
            gap: 18px;
        }

        .trip-card {
            padding: 22px;
        }

        .trip-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .trip-city-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .trip-card h3 {
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }

        .trip-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .meta-pill {
            padding: 8px 12px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.08);
            font-size: 13px;
            color: #334155;
            font-weight: 700;
        }

        .trip-description {
            margin: 14px 0 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 14px;
        }

        .trip-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .places-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .place-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .place-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #e2e8f0;
            display: block;
        }

        .place-body {
            padding: 16px;
        }

        .place-body h5 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 20px;
            font-weight: 800;
        }

        .place-coords {
            margin: 0 0 10px;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 700;
        }

        .place-desc {
            margin: 0 0 14px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .mini-section {
            margin-top: 14px;
        }

        .mini-section h6 {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag-item {
            padding: 8px 10px;
            border-radius: 12px;
            background: #eef4ff;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 700;
        }

        .empty-state {
            padding: 40px 24px;
            border: 1px dashed rgba(15, 23, 42, 0.16);
            border-radius: 20px;
            text-align: center;
            color: var(--muted);
            background: #f8fbfc;
        }

        .empty-state h4 {
            margin: 0 0 10px;
            color: #0f172a;
            font-weight: 800;
        }

        .hidden-form {
            display: none;
        }

        @media (max-width: 1199px) {
            .admin-page {
                display: block;
                padding: 0;
            }

            .admin-content {
                padding: 0 16px 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .places-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .page-hero,
            .admin-card,
            .trip-card {
                padding: 18px;
            }

            .page-hero h1 {
                font-size: 28px;
            }

            .stats-grid,
            .search-row {
                grid-template-columns: 1fr;
            }

            .trip-card-top {
                flex-direction: column;
            }

            .trip-actions {
                width: 100%;
            }

            .trip-actions > * {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="page-hero">
            <div class="hero-top">
                <div>
                    <h1>Manage Cities</h1>
                    <p>
                        View all cities saved in Firebase, search destinations, review locations,
                        and delete unwanted cities or locations from the user side data.
                    </p>
                </div>
                <div class="hero-badge">Firebase City Control</div>
            </div>
        </section>

        <section class="admin-card">
            <div class="top-info">
                <div class="admin-info-bar">
                    <img src="<?php echo h($profileImage); ?>" alt="Admin">
                    <div>
                        <h6><?php echo h($fullName ?: 'Admin'); ?></h6>
                        <p>Manage all destination cities and locations shown on the user side.</p>
                    </div>
                </div>

                <a href="<?php echo h($baseUrl . '/admin_manage/add_trips.php'); ?>" class="btn-main" id="addTripPageBtn">
                    + Add New City
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h6>Total Cities</h6>
                    <p><?php echo (int)$totalCities; ?></p>
                </div>
                <div class="stat-card">
                    <h6>Total Locations</h6>
                    <p><?php echo (int)$totalLocations; ?></p>
                </div>
                <div class="stat-card">
                    <h6>Showing Results</h6>
                    <p><?php echo (int)count($filteredTrips); ?></p>
                </div>
                <div class="stat-card">
                    <h6>Data Source</h6>
                    <p style="font-size:18px;">Firebase</p>
                </div>
            </div>

            <form method="GET" class="search-row" id="searchTripsForm">
                <input
                    type="text"
                    class="form-control"
                    name="search"
                    value="<?php echo h($search); ?>"
                    placeholder="Search by city, slug, or location name..."
                >
                <button type="submit" class="btn btn-main">Search</button>
                <a href="<?php echo h($baseUrl . '/admin_manage/manage_trips.php'); ?>" class="btn btn-soft">Reset</a>
            </form>
        </section>

        <section class="trips-grid">
            <?php if (empty($filteredTrips)): ?>
                <div class="empty-state">
                    <h4>No trips found</h4>
                    <p>No city or location matched your search, or no trip destination has been added yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($filteredTrips as $trip): ?>
                    <article class="trip-card">
                        <div class="trip-card-top">
                            <div>
                                <div class="trip-city-badge">City</div>
                                <h3><?php echo h($trip['city']); ?></h3>

                                <div class="trip-meta">
                                    <span class="meta-pill">Slug: <?php echo h($trip['slug']); ?></span>
                                    <span class="meta-pill">Locations: <?php echo count($trip['places']); ?></span>
                                    <span class="meta-pill">
                                        Center: <?php echo h($trip['center']['lat']); ?>, <?php echo h($trip['center']['lng']); ?>
                                    </span>
                                    <span class="meta-pill">Updated: <?php echo h(format_datetime_pretty($trip['updatedAt'])); ?></span>
                                </div>

                                <p class="trip-description">
                                    <?php echo nl2br(h($trip['description'] ?: 'No city description available.')); ?>
                                </p>
                            </div>

                            <div class="trip-actions">
                                <a href="<?php echo h($baseUrl . '/admin_manage/add_trips.php?mode=add_locations&city=' . urlencode($trip['slug'])); ?>" class="btn btn-soft add-more-btn">
                                    Add More Locations
                                </a>

                                <button
                                    type="button"
                                    class="btn btn-danger-soft delete-city-btn"
                                    data-slug="<?php echo h($trip['slug']); ?>"
                                    data-city="<?php echo h($trip['city']); ?>"
                                >
                                    Delete City
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($trip['places'])): ?>
                            <div class="places-grid">
                                <?php foreach ($trip['places'] as $placeIndex => $place): ?>
                                    <?php
                                        $imagePath = $baseUrl . '/location_images/' . ltrim((string)$place['image'], '/');
                                    ?>
                                    <div class="place-card">
                                        <img
                                            src="<?php echo h($imagePath); ?>"
                                            alt="<?php echo h($place['name']); ?>"
                                            class="place-image"
                                            onerror="this.src='<?php echo h($baseUrl . '/images/default_profile.png'); ?>'"
                                        >

                                        <div class="place-body">
                                            <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                                                <div>
                                                    <h5><?php echo h($place['name']); ?></h5>
                                                    <p class="place-coords">
                                                        Lat: <?php echo h($place['lat']); ?> | Lng: <?php echo h($place['lng']); ?>
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="btn btn-warning-soft delete-location-btn"
                                                    data-slug="<?php echo h($trip['slug']); ?>"
                                                    data-city="<?php echo h($trip['city']); ?>"
                                                    data-location="<?php echo h($place['name']); ?>"
                                                    data-index="<?php echo (int)$placeIndex; ?>"
                                                >
                                                    Delete Location
                                                </button>
                                            </div>

                                            <p class="place-desc">
                                                <?php echo nl2br(h($place['description'] ?: 'No location description available.')); ?>
                                            </p>

                                            <?php if (!empty($place['why_go'])): ?>
                                                <div class="mini-section">
                                                    <h6>Why Go</h6>
                                                    <div class="tag-list">
                                                        <?php foreach ($place['why_go'] as $item): ?>
                                                            <span class="tag-item"><?php echo h($item); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($place['know_before_you_go'])): ?>
                                                <div class="mini-section">
                                                    <h6>Know Before You Go</h6>
                                                    <div class="tag-list">
                                                        <?php foreach ($place['know_before_you_go'] as $item): ?>
                                                            <span class="tag-item"><?php echo h($item); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="margin-top:18px;">
                                <h4>No locations available</h4>
                                <p>This city has no locations right now.</p>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<form method="POST" id="deleteCityForm" class="hidden-form">
    <input type="hidden" name="action" value="delete_city">
    <input type="hidden" name="slug" id="deleteCitySlug">
</form>

<form method="POST" id="deleteLocationForm" class="hidden-form">
    <input type="hidden" name="action" value="delete_location">
    <input type="hidden" name="slug" id="deleteLocationSlug">
    <input type="hidden" name="location_index" id="deleteLocationIndex">
</form>

<script>
    function showLoader(title = 'Please wait...', text = 'Processing request...') {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    document.querySelectorAll('.delete-city-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const slug = this.dataset.slug || '';
            const city = this.dataset.city || 'this city';

            Swal.fire({
                icon: 'warning',
                title: 'Delete city?',
                text: `This will permanently delete "${city}" and all its locations.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete city',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteCitySlug').value = slug;
                    showLoader('Deleting City...', 'Please wait while the city is removed from Firebase.');
                    document.getElementById('deleteCityForm').submit();
                }
            });
        });
    });

    document.querySelectorAll('.delete-location-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const slug = this.dataset.slug || '';
            const city = this.dataset.city || 'this city';
            const location = this.dataset.location || 'this location';
            const index = this.dataset.index || '';

            Swal.fire({
                icon: 'warning',
                title: 'Delete location?',
                text: `Delete "${location}" from "${city}"?`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete location',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d97706'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteLocationSlug').value = slug;
                    document.getElementById('deleteLocationIndex').value = index;
                    showLoader('Deleting Location...', 'Please wait while the location is removed from Firebase.');
                    document.getElementById('deleteLocationForm').submit();
                }
            });
        });
    });

    document.getElementById('addTripPageBtn')?.addEventListener('click', function () {
        showLoader('Opening Add City...', 'Please wait...');
    });

    document.querySelectorAll('.add-more-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            showLoader('Opening Add Locations...', 'Please wait...');
        });
    });

    <?php if ($flash): ?>
    Swal.fire({
        icon: <?php echo json_encode($flash['type']); ?>,
        title: <?php echo json_encode($flash['title']); ?>,
        text: <?php echo json_encode($flash['message']); ?>,
        confirmButtonColor: '#1484B4'
    });
    <?php endif; ?>
</script>
</body>
</html>