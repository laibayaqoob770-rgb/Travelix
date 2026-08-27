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

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    respond(false, 'Session expired. Please log in again.');
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    respond(false, 'Only admin can auto-populate hotels.');
}

$baseUrl = '/travelix';
$configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
if (!file_exists($configPath)) {
    respond(false, 'firebase_config.php not found.');
}
require_once $configPath;

if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
    respond(false, 'FIREBASE_PROJECT_ID missing.');
}

$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    respond(false, 'firebase-service-account.json not found.');
}

$projectId = FIREBASE_PROJECT_ID;

/*
|--------------------------------------------------------------------------
| REAL HOTEL DATABASE - Curated Pakistani Hotels
|--------------------------------------------------------------------------
*/

$hotelDatabase = [

    'Islamabad' => [
        'slug' => 'islamabad',
        'center' => ['lat' => 33.6844, 'lng' => 73.0479],
        'hotels' => [
            [
                'id' => 'isb_r1', 'name' => 'Islamabad Serena Hotel',
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 32000, 'rating' => 9.4, 'reviews' => 1250, 'stars' => 5,
                'address' => 'Faisal Avenue, Islamabad',
                'lat' => 33.7126, 'lng' => 73.0872,
                'features' => ['Free WiFi', 'Swimming Pool', 'Spa', 'Fine Dining', 'Valet Parking']
            ],
            [
                'id' => 'isb_r2', 'name' => 'Islamabad Marriott Hotel',
                'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 28500, 'rating' => 9.1, 'reviews' => 980, 'stars' => 5,
                'address' => 'Aga Khan Road, F-5/1, Islamabad',
                'lat' => 33.7215, 'lng' => 73.0850,
                'features' => ['Free WiFi', 'Gym', 'Business Center', 'Restaurant', 'Room Service']
            ],
            [
                'id' => 'isb_r3', 'name' => 'Pearl Continental Rawalpindi',
                'image' => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 18500, 'rating' => 8.6, 'reviews' => 720, 'stars' => 4,
                'address' => 'The Mall, Rawalpindi',
                'lat' => 33.5977, 'lng' => 73.0479,
                'features' => ['Free WiFi', 'Free Breakfast', 'Pool', 'Parking', 'Banquet Hall']
            ],
            [
                'id' => 'isb_r4', 'name' => 'Hotel One Downtown Islamabad',
                'image' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 8900, 'rating' => 8.0, 'reviews' => 340, 'stars' => 3,
                'address' => 'Jinnah Avenue, Blue Area, Islamabad',
                'lat' => 33.7080, 'lng' => 73.0551,
                'features' => ['Free WiFi', 'Air Conditioning', 'Room Service', 'Laundry']
            ],
            [
                'id' => 'isb_r5', 'name' => 'Envoy Continental Hotel',
                'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 12500, 'rating' => 8.3, 'reviews' => 510, 'stars' => 4,
                'address' => 'Constitution Avenue, G-5, Islamabad',
                'lat' => 33.7100, 'lng' => 73.0700,
                'features' => ['Free WiFi', 'Restaurant', 'Conference Room', 'Airport Shuttle', 'Parking']
            ]
        ]
    ],

    'Lahore' => [
        'slug' => 'lahore',
        'center' => ['lat' => 31.5204, 'lng' => 74.3587],
        'hotels' => [
            [
                'id' => 'lhr_r1', 'name' => 'Pearl Continental Lahore',
                'image' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 25000, 'rating' => 9.2, 'reviews' => 1450, 'stars' => 5,
                'address' => 'Shahrah-e-Quaid-e-Azam, Lahore',
                'lat' => 31.5546, 'lng' => 74.3432,
                'features' => ['Free WiFi', 'Swimming Pool', 'Spa', 'Multiple Restaurants', 'Valet Parking']
            ],
            [
                'id' => 'lhr_r2', 'name' => 'Avari Hotel Lahore',
                'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 19000, 'rating' => 8.8, 'reviews' => 870, 'stars' => 5,
                'address' => '87 Shahrah-e-Quaid-e-Azam, Lahore',
                'lat' => 31.5580, 'lng' => 74.3460,
                'features' => ['Free WiFi', 'Rooftop Pool', 'Gym', 'Business Center', 'Fine Dining']
            ],
            [
                'id' => 'lhr_r3', 'name' => 'Luxus Grand Hotel Lahore',
                'image' => 'https://images.unsplash.com/photo-1455587734955-081b22074882?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 14000, 'rating' => 8.5, 'reviews' => 620, 'stars' => 4,
                'address' => '4-Egerton Road, Lahore',
                'lat' => 31.5500, 'lng' => 74.3400,
                'features' => ['Free WiFi', 'Restaurant', 'Room Service', 'Parking', 'Laundry']
            ],
            [
                'id' => 'lhr_r4', 'name' => 'Falettis Hotel Lahore',
                'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 16500, 'rating' => 8.7, 'reviews' => 530, 'stars' => 4,
                'address' => 'Egerton Road, Lahore',
                'lat' => 31.5520, 'lng' => 74.3410,
                'features' => ['Free WiFi', 'Heritage Building', 'Garden', 'Restaurant', 'Free Parking']
            ]
        ]
    ],

    'Karachi' => [
        'slug' => 'karachi',
        'center' => ['lat' => 24.8607, 'lng' => 67.0011],
        'hotels' => [
            [
                'id' => 'khi_r1', 'name' => 'Pearl Continental Karachi',
                'image' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 27000, 'rating' => 9.0, 'reviews' => 1680, 'stars' => 5,
                'address' => 'Club Road, Karachi',
                'lat' => 24.8484, 'lng' => 67.0260,
                'features' => ['Free WiFi', 'Pool', 'Spa', 'Multiple Restaurants', 'Sea View']
            ],
            [
                'id' => 'khi_r2', 'name' => 'Movenpick Hotel Karachi',
                'image' => 'https://images.unsplash.com/photo-1596436889106-be35e843f974?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 22000, 'rating' => 8.9, 'reviews' => 940, 'stars' => 5,
                'address' => 'Club Road, Karachi',
                'lat' => 24.8480, 'lng' => 67.0250,
                'features' => ['Free WiFi', 'Chocolate Hour', 'Pool', 'Fitness Center', 'Business Center']
            ],
            [
                'id' => 'khi_r3', 'name' => 'Avari Towers Karachi',
                'image' => 'https://images.unsplash.com/photo-1529290130-4ca3753253ae?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 18000, 'rating' => 8.4, 'reviews' => 760, 'stars' => 4,
                'address' => 'Fatima Jinnah Road, Karachi',
                'lat' => 24.8520, 'lng' => 67.0290,
                'features' => ['Free WiFi', 'Restaurant', 'Room Service', 'Gym', 'Parking']
            ]
        ]
    ],

    'Murree' => [
        'slug' => 'murree',
        'center' => ['lat' => 33.9070, 'lng' => 73.3943],
        'hotels' => [
            [
                'id' => 'mur_r1', 'name' => 'Pearl Continental Bhurban',
                'image' => 'https://images.unsplash.com/photo-1610641818989-c2051b5e2cfd?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 24000, 'rating' => 9.1, 'reviews' => 890, 'stars' => 5,
                'address' => 'Bhurban, Murree Hills',
                'lat' => 33.9430, 'lng' => 73.4590,
                'features' => ['Mountain View', 'Golf Course', 'Spa', 'Indoor Pool', 'Free WiFi']
            ],
            [
                'id' => 'mur_r2', 'name' => 'Shangrila Resort Murree',
                'image' => 'https://images.unsplash.com/photo-1470075801209-17f9ec0each6?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 15000, 'rating' => 8.6, 'reviews' => 540, 'stars' => 4,
                'address' => 'GPO Road, Murree',
                'lat' => 33.9080, 'lng' => 73.3950,
                'features' => ['Hill View', 'Free WiFi', 'Restaurant', 'Bonfire', 'Room Heater']
            ],
            [
                'id' => 'mur_r3', 'name' => 'Lockwood Hotel Murree',
                'image' => 'https://images.unsplash.com/photo-1501117716987-c8c394bb29df?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 9500, 'rating' => 8.2, 'reviews' => 320, 'stars' => 3,
                'address' => 'Cart Road, Murree',
                'lat' => 33.9060, 'lng' => 73.3930,
                'features' => ['Free WiFi', 'Valley View', 'Hot Water', 'Parking', 'Room Service']
            ]
        ]
    ],

    'Hunza' => [
        'slug' => 'hunza',
        'center' => ['lat' => 36.3167, 'lng' => 74.6500],
        'hotels' => [
            [
                'id' => 'hnz_r1', 'name' => 'Serena Inn Hunza',
                'image' => 'https://images.unsplash.com/photo-1540541338287-41700207dee6?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 19000, 'rating' => 9.3, 'reviews' => 680, 'stars' => 4,
                'address' => 'Karimabad, Hunza Valley',
                'lat' => 36.3120, 'lng' => 74.6600,
                'features' => ['Mountain View', 'Free WiFi', 'Traditional Dining', 'Garden', 'Guided Tours']
            ],
            [
                'id' => 'hnz_r2', 'name' => 'Eagle Nest Hotel Hunza',
                'image' => 'https://images.unsplash.com/photo-1586375300773-8384e3e4916f?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 12000, 'rating' => 9.0, 'reviews' => 470, 'stars' => 3,
                'address' => 'Duikar, Altit, Hunza',
                'lat' => 36.3200, 'lng' => 74.6700,
                'features' => ['Panoramic View', 'Free WiFi', 'Breakfast Included', 'Terrace', 'Hiking Trails']
            ],
            [
                'id' => 'hnz_r3', 'name' => 'Darbar Hotel Hunza',
                'image' => 'https://images.unsplash.com/photo-1510798831971-661eb04b3739?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 7500, 'rating' => 8.1, 'reviews' => 290, 'stars' => 3,
                'address' => 'Main Bazaar, Karimabad, Hunza',
                'lat' => 36.3150, 'lng' => 74.6580,
                'features' => ['Free WiFi', 'Mountain View', 'Local Cuisine', 'Hot Water', 'Parking']
            ]
        ]
    ],

    'Swat' => [
        'slug' => 'swat',
        'center' => ['lat' => 35.2227, 'lng' => 72.3526],
        'hotels' => [
            [
                'id' => 'swt_r1', 'name' => 'Swat Serena Hotel',
                'image' => 'https://images.unsplash.com/photo-1568084680786-a84f928999c0?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 18000, 'rating' => 9.0, 'reviews' => 620, 'stars' => 4,
                'address' => 'Saidu Sharif, Swat',
                'lat' => 35.2230, 'lng' => 72.3530,
                'features' => ['Free WiFi', 'Garden', 'Restaurant', 'Room Service', 'Valley View']
            ],
            [
                'id' => 'swt_r2', 'name' => 'PTDC Motel Malam Jabba',
                'image' => 'https://images.unsplash.com/photo-1587061949409-02df41d5e562?q=80&w=900&auto=format&fit=crop',
                'price_per_night' => 8500, 'rating' => 7.8, 'reviews' => 280, 'stars' => 3,
                'address' => 'Malam Jabba, Swat',
                'lat' => 35.2910, 'lng' => 72.5730,
                'features' => ['Mountain View', 'Skiing Nearby', 'Free Parking', 'Hot Water', 'Bonfire']
            ]
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| FIRESTORE HELPERS
|--------------------------------------------------------------------------
*/

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token($serviceAccountPath)
{
    $sa = json_decode(file_get_contents($serviceAccountPath), true);
    if (!$sa) throw new Exception('Cannot read service account.');

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];

    $input = base64url_encode(json_encode($header)) . '.' . base64url_encode(json_encode($claims));
    $sig = '';
    if (!openssl_sign($input, $sig, $sa['private_key'], 'SHA256')) throw new Exception('JWT sign failed.');

    $jwt = $input . '.' . base64url_encode($sig);
    $tokenUri = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) throw new Exception('Token request failed.');
    $token = json_decode($resp, true)['access_token'] ?? '';
    if (!$token) throw new Exception('No access token.');
    return $token;
}

function firestore_request($method, $url, $token, $body = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 25
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $resp];
}

function normalize_name($v) { return strtolower(trim((string)$v)); }

function build_firestore_hotel_values($hotels)
{
    $values = [];
    foreach ($hotels as $h) {
        $features = [];
        foreach ((array)($h['features'] ?? []) as $f) $features[] = ['stringValue' => (string)$f];

        $values[] = ['mapValue' => ['fields' => [
            'id' => ['stringValue' => (string)$h['id']],
            'name' => ['stringValue' => (string)$h['name']],
            'image' => ['stringValue' => (string)($h['image'] ?? '')],
            'image_source' => ['stringValue' => 'static'],
            'price_per_night' => ['doubleValue' => (float)$h['price_per_night']],
            'rating' => ['doubleValue' => (float)$h['rating']],
            'reviews' => ['integerValue' => (string)((int)$h['reviews'])],
            'stars' => ['integerValue' => (string)((int)$h['stars'])],
            'address' => ['stringValue' => (string)$h['address']],
            'lat' => ['doubleValue' => (float)$h['lat']],
            'lng' => ['doubleValue' => (float)$h['lng']],
            'features' => ['arrayValue' => ['values' => $features]]
        ]]];
    }
    return $values;
}

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/

$action = trim((string)($_POST['action'] ?? ''));

// Action: get_cities - return list of available cities
if ($action === 'get_cities') {
    $cities = [];
    foreach ($hotelDatabase as $cityName => $data) {
        $cities[] = [
            'name' => $cityName,
            'slug' => $data['slug'],
            'hotelCount' => count($data['hotels']),
            'center' => $data['center']
        ];
    }
    respond(true, 'Cities loaded.', ['cities' => $cities]);
}

// Action: get_hotels - return hotels for a specific city
if ($action === 'get_hotels') {
    $cityName = trim((string)($_POST['city'] ?? ''));
    if (!isset($hotelDatabase[$cityName])) {
        respond(false, 'City not found in database.');
    }
    respond(true, 'Hotels loaded.', [
        'city' => $cityName,
        'data' => $hotelDatabase[$cityName]
    ]);
}

// Action: save_hotel - save ONE hotel to Firestore (for real-time one-by-one effect)
if ($action === 'save_hotel') {
    $cityName = trim((string)($_POST['city'] ?? ''));
    $hotelIndex = (int)($_POST['hotelIndex'] ?? -1);

    if (!isset($hotelDatabase[$cityName])) {
        respond(false, 'City not found.');
    }

    $cityData = $hotelDatabase[$cityName];
    if (!isset($cityData['hotels'][$hotelIndex])) {
        respond(false, 'Hotel index out of range.');
    }

    $newHotel = $cityData['hotels'][$hotelIndex];

    try {
        $accessToken = get_google_access_token($serviceAccountPath);
        $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations/{$cityData['slug']}";

        // Get existing
        [$status, $resp] = firestore_request('GET', $docUrl, $accessToken);
        $existingHotels = [];
        $existingCenter = [$cityData['center']['lat'], $cityData['center']['lng']];

        if ($status === 200 && $resp) {
            $doc = json_decode($resp, true);
            $fields = $doc['fields'] ?? [];

            if (isset($fields['center']['mapValue']['fields']['lat'])) {
                $existingCenter[0] = (float)($fields['center']['mapValue']['fields']['lat']['doubleValue'] ?? $fields['center']['mapValue']['fields']['lat']['integerValue'] ?? $existingCenter[0]);
            }
            if (isset($fields['center']['mapValue']['fields']['lng'])) {
                $existingCenter[1] = (float)($fields['center']['mapValue']['fields']['lng']['doubleValue'] ?? $fields['center']['mapValue']['fields']['lng']['integerValue'] ?? $existingCenter[1]);
            }

            foreach (($fields['hotels']['arrayValue']['values'] ?? []) as $hv) {
                $m = $hv['mapValue']['fields'] ?? [];
                $feats = [];
                foreach (($m['features']['arrayValue']['values'] ?? []) as $fv) $feats[] = $fv['stringValue'] ?? '';

                $existingHotels[] = [
                    'id' => $m['id']['stringValue'] ?? '',
                    'name' => $m['name']['stringValue'] ?? '',
                    'image' => $m['image']['stringValue'] ?? '',
                    'image_source' => $m['image_source']['stringValue'] ?? 'admin',
                    'price_per_night' => (float)($m['price_per_night']['doubleValue'] ?? $m['price_per_night']['integerValue'] ?? 0),
                    'rating' => (float)($m['rating']['doubleValue'] ?? $m['rating']['integerValue'] ?? 0),
                    'reviews' => (int)($m['reviews']['integerValue'] ?? 0),
                    'stars' => (int)($m['stars']['integerValue'] ?? 0),
                    'address' => $m['address']['stringValue'] ?? '',
                    'lat' => (float)($m['lat']['doubleValue'] ?? $m['lat']['integerValue'] ?? 0),
                    'lng' => (float)($m['lng']['doubleValue'] ?? $m['lng']['integerValue'] ?? 0),
                    'features' => array_values(array_filter(array_map('trim', $feats)))
                ];
            }
        }

        // Check duplicate
        $existingIds = [];
        $existingNames = [];
        foreach ($existingHotels as $h) {
            $existingIds[normalize_name($h['id'])] = true;
            $existingNames[normalize_name($h['name'])] = true;
        }

        $idKey = normalize_name($newHotel['id']);
        $nameKey = normalize_name($newHotel['name']);

        if (isset($existingIds[$idKey]) || isset($existingNames[$nameKey])) {
            respond(true, "Hotel \"{$newHotel['name']}\" already exists. Skipped.", [
                'skipped' => true,
                'hotel' => $newHotel
            ]);
        }

        $existingHotels[] = $newHotel;

        // Save
        $payload = [
            'fields' => [
                'city' => ['stringValue' => $cityName],
                'slug' => ['stringValue' => $cityData['slug']],
                'center' => ['mapValue' => ['fields' => [
                    'lat' => ['doubleValue' => (float)$existingCenter[0]],
                    'lng' => ['doubleValue' => (float)$existingCenter[1]]
                ]]],
                'hotels' => ['arrayValue' => ['values' => build_firestore_hotel_values($existingHotels)]],
                'source' => ['stringValue' => 'auto_system'],
                'updatedAt' => ['timestampValue' => gmdate('c')]
            ]
        ];

        [$saveStatus, $saveResp] = firestore_request('PATCH', $docUrl, $accessToken, $payload);

        if ($saveStatus < 200 || $saveStatus >= 300) {
            respond(false, 'Failed to save to Firestore.', ['firestore_status' => $saveStatus]);
        }

        respond(true, "Hotel \"{$newHotel['name']}\" saved to {$cityName}!", [
            'skipped' => false,
            'hotel' => $newHotel,
            'totalHotelsInCity' => count($existingHotels)
        ]);

    } catch (Throwable $e) {
        respond(false, $e->getMessage());
    }
}

respond(false, 'Invalid action.');
