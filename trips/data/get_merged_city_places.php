<?php
header('Content-Type: application/json; charset=utf-8');

$baseUrl = '/travelix';

/*
|--------------------------------------------------------------------------
| 1) Load static cities from existing city_places.php
|--------------------------------------------------------------------------
*/
ob_start();
include __DIR__ . '/city_places.php';
$staticJson = ob_get_clean();

$staticCities = json_decode($staticJson, true);
if (!is_array($staticCities)) {
    $staticCities = [];
}

/*
|--------------------------------------------------------------------------
| 2) Load Firebase project config
|--------------------------------------------------------------------------
*/
$configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';

if (!file_exists($configPath)) {
    echo json_encode($staticCities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;

if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
    echo json_encode($staticCities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$projectId = FIREBASE_PROJECT_ID;
$url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/trip_destinations";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200 || !$response) {
    echo json_encode($staticCities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
$documents = $data['documents'] ?? [];

/*
|--------------------------------------------------------------------------
| 3) Helper functions
|--------------------------------------------------------------------------
*/
function field_string(array $field = null, string $default = ''): string
{
    if (!$field) return $default;
    return isset($field['stringValue']) ? (string)$field['stringValue'] : $default;
}

function field_number(array $field = null, float $default = 0): float
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

function normalize_place_name(string $value): string
{
    return strtolower(trim($value));
}

/*
|--------------------------------------------------------------------------
| 4) Merge Firebase cities into static cities
|--------------------------------------------------------------------------
*/
foreach ($documents as $doc) {
    $fields = $doc['fields'] ?? [];

    $city = field_string($fields['city'] ?? null, '');
    if ($city === '') {
        continue;
    }

    $centerLat = field_number($fields['center']['mapValue']['fields']['lat'] ?? null, 0);
    $centerLng = field_number($fields['center']['mapValue']['fields']['lng'] ?? null, 0);

    $firebasePlaces = [];

    foreach (($fields['places']['arrayValue']['values'] ?? []) as $placeValue) {
        $map = $placeValue['mapValue']['fields'] ?? [];

        $whyGo = [];
        foreach (($map['why_go']['arrayValue']['values'] ?? []) as $item) {
            $value = field_string($item, '');
            if ($value !== '') {
                $whyGo[] = $value;
            }
        }

        $knowBefore = [];
        foreach (($map['know_before_you_go']['arrayValue']['values'] ?? []) as $item) {
            $value = field_string($item, '');
            if ($value !== '') {
                $knowBefore[] = $value;
            }
        }

        $placeName = field_string($map['name'] ?? null, '');
        if ($placeName === '') {
            continue;
        }

        $firebasePlaces[] = [
            'name' => $placeName,
            'image' => field_string($map['image'] ?? null, ''),
            'lat' => field_number($map['lat'] ?? null, 0),
            'lng' => field_number($map['lng'] ?? null, 0),
            'description' => field_string($map['description'] ?? null, ''),
            'why_go' => $whyGo,
            'know_before_you_go' => $knowBefore,
            'image_source' => 'admin'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | If city does not exist in static list, add it directly
    |--------------------------------------------------------------------------
    */
    if (!isset($staticCities[$city])) {
        $staticCities[$city] = [
            'center' => [$centerLat, $centerLng],
            'places' => $firebasePlaces
        ];
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | If city exists, append only new places
    |--------------------------------------------------------------------------
    */
    $existingPlaces = $staticCities[$city]['places'] ?? [];
    $existingNames = [];

    foreach ($existingPlaces as $place) {
        $existingNames[normalize_place_name((string)($place['name'] ?? ''))] = true;
    }

    foreach ($firebasePlaces as $place) {
        $normalized = normalize_place_name((string)$place['name']);
        if ($normalized !== '' && !isset($existingNames[$normalized])) {
            $existingPlaces[] = $place;
            $existingNames[$normalized] = true;
        }
    }

    $staticCities[$city]['places'] = $existingPlaces;

    if (
        (!isset($staticCities[$city]['center']) || !is_array($staticCities[$city]['center']) || count($staticCities[$city]['center']) < 2) &&
        $centerLat != 0 &&
        $centerLng != 0
    ) {
        $staticCities[$city]['center'] = [$centerLat, $centerLng];
    }
}

echo json_encode($staticCities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;