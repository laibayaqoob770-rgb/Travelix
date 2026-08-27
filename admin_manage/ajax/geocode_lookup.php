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

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    respond(false, 'Session expired. Please log in again.');
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    respond(false, 'Only admin can use location lookup.');
}

$query = trim((string)($_GET['q'] ?? ''));
$kind = trim((string)($_GET['kind'] ?? 'city'));

if ($query === '') {
    respond(false, 'A name is required to look up a location.');
}

function geocode_curl_get($url, array $headers)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $response];
}

function lookup_nominatim($query)
{
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);

    [$status, $response] = geocode_curl_get($url, [
        'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)'
    ]);

    if ($status !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json[0])) {
        return null;
    }

    $result = $json[0];

    if (!isset($result['lat']) || !isset($result['lon'])) {
        return null;
    }

    return [
        'lat' => (float)$result['lat'],
        'lng' => (float)$result['lon']
    ];
}

function lookup_nearby_places($lat, $lng)
{
    $url = 'https://en.wikipedia.org/w/api.php?action=query&list=geosearch'
        . '&gscoord=' . urlencode($lat . '|' . $lng)
        . '&gsradius=10000&gslimit=20&format=json';

    [$status, $response] = geocode_curl_get($url, [
        'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)',
        'Accept: application/json'
    ]);

    if ($status !== 200 || !$response) {
        return [];
    }

    $json = json_decode($response, true);
    $rows = $json['query']['geosearch'] ?? [];

    if (!is_array($rows)) {
        return [];
    }

    $places = [];
    foreach ($rows as $row) {
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '' || !isset($row['lat']) || !isset($row['lon'])) {
            continue;
        }
        $places[] = [
            'name' => $title,
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lon']
        ];
    }

    return $places;
}

function lookup_wikipedia($query)
{
    $url = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $query));

    [$status, $response] = geocode_curl_get($url, [
        'User-Agent: Travelix-Admin/1.0 (travel booking app admin tool)',
        'Accept: application/json'
    ]);

    if ($status !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return null;
    }

    return [
        'description' => trim((string)($json['extract'] ?? '')),
        'imageUrl' => trim((string)($json['thumbnail']['source'] ?? ''))
    ];
}

try {
    if ($kind === 'city_places') {
        $geo = lookup_nominatim($query);
        if (!$geo) {
            respond(true, 'City location not found. Please find the city location first.', ['places' => []]);
        }

        $places = lookup_nearby_places($geo['lat'], $geo['lng']);

        respond(true, count($places) ? 'Famous places found.' : 'No famous places found nearby.', [
            'cityLat' => $geo['lat'],
            'cityLng' => $geo['lng'],
            'places' => $places
        ]);
    }

    $result = [
        'success' => true,
        'message' => 'Lookup complete.',
        'lat' => null,
        'lng' => null,
        'description' => '',
        'imageUrl' => ''
    ];

    $geo = lookup_nominatim($query);
    if ($geo) {
        $result['lat'] = $geo['lat'];
        $result['lng'] = $geo['lng'];
    }

    if ($kind === 'place') {
        $wiki = lookup_wikipedia($query);
        if ($wiki) {
            $result['description'] = $wiki['description'];
            $result['imageUrl'] = $wiki['imageUrl'];
        }
    }

    if ($result['lat'] === null && $result['description'] === '' && $result['imageUrl'] === '') {
        respond(true, 'No results found. Please fill in the details manually.', [
            'lat' => null,
            'lng' => null,
            'description' => '',
            'imageUrl' => ''
        ]);
    }

    echo json_encode($result);
    exit;

} catch (Throwable $e) {
    respond(false, 'Lookup failed: ' . $e->getMessage());
}
