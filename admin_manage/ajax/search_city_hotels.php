<?php
/**
 * Given a Pakistani city name, geocodes it (Nominatim) then searches
 * OpenStreetMap (Overpass API) for real hotels/guest houses/motels near
 * that city center. Free, no API key — same data sources already used by
 * geocode_lookup.php elsewhere in this admin area.
 */
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

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    respond(false, 'Session expired. Please log in again.');
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    respond(false, 'Only admin can search hotels.');
}

$city = trim((string)($_GET['city'] ?? ''));
if ($city === '') {
    respond(false, 'Please enter a city name.');
}

function osm_curl_get($url, array $headers)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $response];
}

function geocode_city($city)
{
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=pk&q='
        . urlencode($city . ', Pakistan');

    [$status, $response] = osm_curl_get($url, [
        'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)'
    ]);

    if ($status !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json[0]['lat']) || empty($json[0]['lon'])) {
        return null;
    }

    return [
        'lat' => (float)$json[0]['lat'],
        'lng' => (float)$json[0]['lon']
    ];
}

function search_hotels_overpass($lat, $lng)
{
    $radius = 15000; // 15km around the city center
    $query = "[out:json][timeout:25];("
        . "node[\"tourism\"~\"^(hotel|guest_house|motel)$\"](around:{$radius},{$lat},{$lng});"
        . "way[\"tourism\"~\"^(hotel|guest_house|motel)$\"](around:{$radius},{$lat},{$lng});"
        . ");out center tags;";

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);

    [$status, $response] = osm_curl_get($url, [
        'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)'
    ]);

    if ($status !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    $elements = $json['elements'] ?? [];
    if (!is_array($elements)) {
        return [];
    }

    $typeLabels = [
        'hotel' => 'Hotel',
        'guest_house' => 'Guest House',
        'motel' => 'Motel'
    ];

    $hotels = [];
    $seenNames = [];

    foreach ($elements as $el) {
        $tags = $el['tags'] ?? [];
        $name = trim((string)($tags['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $nameKey = strtolower($name);
        if (isset($seenNames[$nameKey])) {
            continue;
        }

        $hotelLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
        $hotelLng = $el['lon'] ?? ($el['center']['lon'] ?? null);
        if ($hotelLat === null || $hotelLng === null) {
            continue;
        }

        $addressParts = array_filter([
            $tags['addr:housenumber'] ?? '',
            $tags['addr:street'] ?? '',
            $tags['addr:city'] ?? ''
        ]);
        $address = trim((string)($tags['addr:full'] ?? implode(', ', $addressParts)));

        $tourismType = (string)($tags['tourism'] ?? 'hotel');

        $seenNames[$nameKey] = true;
        $hotels[] = [
            'name' => $name,
            'address' => $address,
            'lat' => (float)$hotelLat,
            'lng' => (float)$hotelLng,
            'type' => $typeLabels[$tourismType] ?? 'Hotel'
        ];
    }

    usort($hotels, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return array_slice($hotels, 0, 80);
}

try {
    $geo = geocode_city($city);
    if (!$geo) {
        respond(true, 'Could not find that city. Please check the spelling or enter hotel details manually.', [
            'cityLat' => null,
            'cityLng' => null,
            'hotels' => []
        ]);
    }

    $hotels = search_hotels_overpass($geo['lat'], $geo['lng']);
    if ($hotels === null) {
        respond(false, 'Hotel search service is temporarily unavailable. Please try again or enter details manually.');
    }

    respond(true, count($hotels) ? (count($hotels) . ' hotels found.') : 'No hotels found for this city. You can still enter details manually.', [
        'cityLat' => $geo['lat'],
        'cityLng' => $geo['lng'],
        'hotels' => $hotels
    ]);
} catch (Throwable $e) {
    respond(false, 'Search failed: ' . $e->getMessage());
}
