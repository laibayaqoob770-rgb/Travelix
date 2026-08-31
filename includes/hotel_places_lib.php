<?php
/**
 * Merges the static hotel_places.php list with admin-added hotel
 * destinations (Firestore `hotel_destinations`) and hotel-portal-onboarded
 * hotels (Firestore `hotels`, status=active).
 *
 * Shared by hotel/data/get_merged_hotel_places.php (the HTTP JSON endpoint
 * client-side JS calls) and hotel/searched_hotel.php (server-side render) —
 * calling this function directly instead of curl-ing the endpoint avoids a
 * self-HTTP round trip that only ever worked because it was hardcoded to
 * http://localhost/... and broke the moment the site went live on a real
 * domain.
 */

function hp_field_string(array $field = null, string $default = ''): string
{
    if (!$field) return $default;
    return isset($field['stringValue']) ? (string)$field['stringValue'] : $default;
}

function hp_field_number(array $field = null, float $default = 0): float
{
    if (!$field) return $default;

    if (isset($field['doubleValue'])) {
        return (float)$field['doubleValue'];
    }

    if (isset($field['integerValue'])) {
        return (float)$field['integerValue'];
    }

    return $default;
}

function hp_field_bool(array $field = null, bool $default = false): bool
{
    if (!$field) return $default;
    return isset($field['booleanValue']) ? (bool)$field['booleanValue'] : $default;
}

function hp_normalize_place_name(string $value): string
{
    return strtolower(trim($value));
}

function hp_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function hp_get_google_access_token_for_merge(string $serviceAccountPath): ?string
{
    if (!file_exists($serviceAccountPath)) {
        return null;
    }

    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
    if (!$serviceAccount) {
        return null;
    }

    $clientEmail = $serviceAccount['client_email'] ?? '';
    $privateKey = $serviceAccount['private_key'] ?? '';
    $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

    if ($clientEmail === '' || $privateKey === '') {
        return null;
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

    $jwtHeader = hp_base64url_encode(json_encode($header));
    $jwtClaimSet = hp_base64url_encode(json_encode($claimSet));
    $signatureInput = $jwtHeader . '.' . $jwtClaimSet;

    $signature = '';
    $ok = openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
    if (!$ok) {
        return null;
    }

    $jwt = $signatureInput . '.' . hp_base64url_encode($signature);

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
        return null;
    }

    $json = json_decode($response, true);
    $accessToken = $json['access_token'] ?? '';

    return $accessToken !== '' ? $accessToken : null;
}

function hp_get_merged_hotel_places(): array
{
    $baseUrl = '/travelix';

    // 1) Load static hotel places from hotel_places.php
    ob_start();
    include __DIR__ . '/../hotel/data/hotel_places.php';
    $staticJson = ob_get_clean();

    $staticCities = json_decode($staticJson, true);
    if (!is_array($staticCities)) {
        $staticCities = [];
    }

    // 2) Load Firebase project config
    $configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
    if (!file_exists($configPath)) {
        return $staticCities;
    }

    require_once $configPath;

    if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
        return $staticCities;
    }

    $projectId = FIREBASE_PROJECT_ID;
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations";
    $serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

    // 3) Read Firestore hotel destinations
    $accessToken = hp_get_google_access_token_for_merge($serviceAccountPath);
    if (!$accessToken) {
        return $staticCities;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$response) {
        return $staticCities;
    }

    $data = json_decode($response, true);
    $documents = $data['documents'] ?? [];

    // 4) Merge Firestore hotel cities into static hotel cities
    foreach ($documents as $doc) {
        $fields = $doc['fields'] ?? [];

        $city = hp_field_string($fields['city'] ?? null, '');
        if ($city === '') {
            continue;
        }

        $centerLat = hp_field_number($fields['center']['mapValue']['fields']['lat'] ?? null, 0);
        $centerLng = hp_field_number($fields['center']['mapValue']['fields']['lng'] ?? null, 0);

        $firebaseHotels = [];

        foreach (($fields['hotels']['arrayValue']['values'] ?? []) as $hotelValue) {
            $map = $hotelValue['mapValue']['fields'] ?? [];

            $features = [];
            foreach (($map['features']['arrayValue']['values'] ?? []) as $item) {
                $value = hp_field_string($item, '');
                if ($value !== '') {
                    $features[] = $value;
                }
            }

            $hotelName = hp_field_string($map['name'] ?? null, '');
            $hotelId = hp_field_string($map['id'] ?? null, '');

            if ($hotelName === '' && $hotelId === '') {
                continue;
            }

            $firebaseHotels[] = [
                'id' => $hotelId,
                'name' => $hotelName,
                'image' => hp_field_string($map['image'] ?? null, ''),
                'image_source' => hp_field_string($map['image_source'] ?? null, 'admin'),
                'price_per_night' => hp_field_number($map['price_per_night'] ?? null, 0),
                'rating' => hp_field_number($map['rating'] ?? null, 0),
                'reviews' => (int)hp_field_number($map['reviews'] ?? null, 0),
                'stars' => (int)hp_field_number($map['stars'] ?? null, 0),
                'address' => hp_field_string($map['address'] ?? null, ''),
                'lat' => hp_field_number($map['lat'] ?? null, 0),
                'lng' => hp_field_number($map['lng'] ?? null, 0),
                'features' => $features
            ];
        }

        // If city does not exist in static list, add it directly
        if (!isset($staticCities[$city])) {
            $staticCities[$city] = [
                'center' => [
                    'lat' => $centerLat,
                    'lng' => $centerLng
                ],
                'hotels' => $firebaseHotels
            ];
            continue;
        }

        // If city exists, append only new hotels by id or name
        $existingHotels = $staticCities[$city]['hotels'] ?? [];
        $existingIds = [];
        $existingNames = [];

        foreach ($existingHotels as $hotel) {
            $existingIds[hp_normalize_place_name((string)($hotel['id'] ?? ''))] = true;
            $existingNames[hp_normalize_place_name((string)($hotel['name'] ?? ''))] = true;
        }

        foreach ($firebaseHotels as $hotel) {
            $idKey = hp_normalize_place_name((string)($hotel['id'] ?? ''));
            $nameKey = hp_normalize_place_name((string)($hotel['name'] ?? ''));

            $isDuplicateId = ($idKey !== '' && isset($existingIds[$idKey]));
            $isDuplicateName = ($nameKey !== '' && isset($existingNames[$nameKey]));

            if (!$isDuplicateId && !$isDuplicateName) {
                $existingHotels[] = $hotel;

                if ($idKey !== '') {
                    $existingIds[$idKey] = true;
                }

                if ($nameKey !== '') {
                    $existingNames[$nameKey] = true;
                }
            }
        }

        $staticCities[$city]['hotels'] = $existingHotels;

        if (
            (!isset($staticCities[$city]['center']) || !is_array($staticCities[$city]['center'])) &&
            $centerLat != 0 &&
            $centerLng != 0
        ) {
            $staticCities[$city]['center'] = [
                'lat' => $centerLat,
                'lng' => $centerLng
            ];
        }

        if (
            isset($staticCities[$city]['center']) &&
            is_array($staticCities[$city]['center']) &&
            (
                !isset($staticCities[$city]['center']['lat']) ||
                !isset($staticCities[$city]['center']['lng'])
            ) &&
            $centerLat != 0 &&
            $centerLng != 0
        ) {
            $staticCities[$city]['center'] = [
                'lat' => $centerLat,
                'lng' => $centerLng
            ];
        }
    }

    // 5) Merge portal-onboarded hotels (flat `hotels` collection) in too
    $portalUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotels?pageSize=200";
    $portalDocuments = [];

    do {
        $ch = curl_init($portalUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);

        $portalResponse = curl_exec($ch);
        $portalStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($portalStatus !== 200 || !$portalResponse) {
            break;
        }

        $portalData = json_decode($portalResponse, true);
        foreach (($portalData['documents'] ?? []) as $doc) {
            $portalDocuments[] = $doc;
        }

        $nextPageToken = (string)($portalData['nextPageToken'] ?? '');
        $portalUrl = $nextPageToken !== ''
            ? "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotels?pageSize=200&pageToken=" . urlencode($nextPageToken)
            : '';
    } while ($portalUrl !== '');

    foreach ($portalDocuments as $doc) {
        $fields = $doc['fields'] ?? [];

        $status = hp_field_string($fields['status'] ?? null, 'active');
        if ($status !== 'active') {
            continue;
        }

        // Disabled for missing a guest refund's SLA — hidden from search
        // until admin reviews and re-enables the account.
        if (hp_field_bool($fields['disabled'] ?? null, false)) {
            continue;
        }

        $city = hp_field_string($fields['city'] ?? null, '');
        if ($city === '') {
            continue;
        }

        $docId = basename((string)($doc['name'] ?? ''));

        $features = [];
        foreach (($fields['features']['arrayValue']['values'] ?? []) as $item) {
            $value = hp_field_string($item, '');
            if ($value !== '') {
                $features[] = $value;
            }
        }

        $hotelLat = hp_field_number($fields['lat'] ?? null, 0);
        $hotelLng = hp_field_number($fields['lng'] ?? null, 0);

        $portalHotel = [
            'id' => hp_field_string($fields['id'] ?? null, $docId),
            'name' => hp_field_string($fields['name'] ?? null, ''),
            'image' => hp_field_string($fields['image'] ?? null, ''),
            'image_source' => hp_field_string($fields['image_source'] ?? null, 'hotel_portal'),
            'price_per_night' => hp_field_number($fields['price_per_night'] ?? null, 0),
            'rating' => hp_field_number($fields['rating'] ?? null, 0),
            'reviews' => (int)hp_field_number($fields['reviews'] ?? null, 0),
            'stars' => (int)hp_field_number($fields['stars'] ?? null, 0),
            'address' => hp_field_string($fields['address'] ?? null, ''),
            'lat' => $hotelLat,
            'lng' => $hotelLng,
            'features' => $features
        ];

        if ($portalHotel['name'] === '') {
            continue;
        }

        if (!isset($staticCities[$city])) {
            $staticCities[$city] = [
                'center' => [
                    'lat' => $hotelLat,
                    'lng' => $hotelLng
                ],
                'hotels' => [$portalHotel]
            ];
            continue;
        }

        $existingHotels = $staticCities[$city]['hotels'] ?? [];
        $existingIds = [];
        $existingNames = [];

        foreach ($existingHotels as $hotel) {
            $existingIds[hp_normalize_place_name((string)($hotel['id'] ?? ''))] = true;
            $existingNames[hp_normalize_place_name((string)($hotel['name'] ?? ''))] = true;
        }

        $idKey = hp_normalize_place_name($portalHotel['id']);
        $nameKey = hp_normalize_place_name($portalHotel['name']);
        $isDuplicate = ($idKey !== '' && isset($existingIds[$idKey])) || ($nameKey !== '' && isset($existingNames[$nameKey]));

        if (!$isDuplicate) {
            $existingHotels[] = $portalHotel;
            $staticCities[$city]['hotels'] = $existingHotels;
        }

        if (
            (!isset($staticCities[$city]['center']) || !is_array($staticCities[$city]['center'])) &&
            $hotelLat != 0 &&
            $hotelLng != 0
        ) {
            $staticCities[$city]['center'] = [
                'lat' => $hotelLat,
                'lng' => $hotelLng
            ];
        }
    }

    return $staticCities;
}
