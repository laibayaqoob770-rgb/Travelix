<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$firebaseConfigPath = $_SERVER['DOCUMENT_ROOT'] . '/travelix/config/firebase_config.php';
if (file_exists($firebaseConfigPath)) {
    require_once $firebaseConfigPath;
}

// ── Fetch hotels from Firestore `hotels` collection ──────────────────────────
function b64url_sh($d){ return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }

function getFirestoreToken_sh($saPath){
    if (!file_exists($saPath)) return null;
    $sa  = json_decode(file_get_contents($saPath), true);
    $now = time();
    $h   = b64url_sh(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $c   = b64url_sh(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $sig = '';
    openssl_sign("$h.$c", $sig, $sa['private_key'], 'SHA256');
    $jwt = "$h.$c." . b64url_sh($sig);
    $ch  = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT=>10]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode($r, true)['access_token'] ?? null;
}

function fetchFirestoreHotelsByCity($city, $projectId, $saPath) {
    $token = getFirestoreToken_sh($saPath);
    if (!$token) return [];

    // Firestore REST: structured query filtered by city + status=active
    $url  = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents:runQuery";
    $body = json_encode([
        'structuredQuery' => [
            'from'  => [['collectionId' => 'hotels']],
            'where' => [
                'compositeFilter' => [
                    'op'      => 'AND',
                    'filters' => [
                        ['fieldFilter' => ['field'=>['fieldPath'=>'city'],'op'=>'EQUAL','value'=>['stringValue'=>$city]]],
                        ['fieldFilter' => ['field'=>['fieldPath'=>'status'],'op'=>'EQUAL','value'=>['stringValue'=>'active']]],
                    ]
                ]
            ],
            'limit' => 50
        ]
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
        CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch); curl_close($ch);
    $rows = json_decode($resp, true);
    if (!is_array($rows)) return [];

    $hotels = [];
    foreach ($rows as $row) {
        if (empty($row['document']['fields'])) continue;
        $f = $row['document']['fields'];
        $get = function($key) use ($f) {
            if (!isset($f[$key])) return null;
            $v = $f[$key];
            return $v['stringValue'] ?? $v['integerValue'] ?? $v['doubleValue'] ?? $v['booleanValue'] ?? null;
        };
        $getArr = function($key) use ($f) {
            if (!isset($f[$key]['arrayValue']['values'])) return [];
            return array_map(function($v){ return $v['stringValue'] ?? ''; }, $f[$key]['arrayValue']['values']);
        };

        $docId = basename($row['document']['name']);
        $hotels[] = [
            'id'             => $get('id') ?: $docId,
            'firestore_id'   => $docId,
            'name'           => $get('name') ?: 'Unknown Hotel',
            'city'           => $get('city') ?: $city,
            'address'        => $get('address') ?: '',
            'description'    => $get('description') ?: '',
            'image'          => $get('image') ?: '',
            'stars'          => (int)($get('stars') ?: 0),
            'rating'         => (float)($get('rating') ?: 0),
            'reviews'        => (int)($get('reviews') ?: 0),
            'price_per_night'=> (float)($get('price_per_night') ?: 0),
            'total_rooms'    => (int)($get('total_rooms') ?: 0),
            'features'       => $getArr('features'),
            'lat'            => (float)($get('lat') ?: 0),
            'lng'            => (float)($get('lng') ?: 0),
            'source'         => 'firestore',
        ];
    }
    return $hotels;
}

$_saPath    = $_SERVER['DOCUMENT_ROOT'] . '/travelix/config/firebase-service-account.json';
$_projectId = defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : 'travelix-330b6';
$_firestoreHotels = [];
if (!empty($_GET['toCity'])) {
    $_firestoreHotels = fetchFirestoreHotelsByCity(trim($_GET['toCity']), $_projectId, $_saPath);
}
// ─────────────────────────────────────────────────────────────────────────────

$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['user']) || isset($_SESSION['email']);

$city = trim($_GET['toCity'] ?? '');
$arrivalDate = trim($_GET['arrivalDate'] ?? '');
$departureDate = trim($_GET['departureDate'] ?? '');
$travelers = max(1, (int)($_GET['travelers'] ?? 2));
$rooms = max(1, (int)($_GET['rooms'] ?? 1));
$adults = max(1, (int)($_GET['adults'] ?? max(1, $travelers)));
$children = max(0, (int)($_GET['children'] ?? 0));
$source = trim($_GET['source'] ?? '');
$returnStep = trim($_GET['returnStep'] ?? '3');

$hotelPlacesRaw = [];
$hotelPlacesJson = '{}';

require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/hotel_places_lib.php';

// Calls the shared merge logic directly instead of curl-ing this same
// server over HTTP — the old approach was hardcoded to
// http://localhost/... and silently broke (falling back to an empty
// hotel_places.php) the moment this site ran on a real domain.
function fetchMergedHotelPlaces(): string
{
    return json_encode(hp_get_merged_hotel_places(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$hotelPlacesJson = fetchMergedHotelPlaces();

$decodedHotelPlaces = json_decode($hotelPlacesJson, true);
if (is_array($decodedHotelPlaces)) {
    $hotelPlacesRaw = $decodedHotelPlaces;
}

$selectedCenterRaw = $hotelPlacesRaw[$city]['center'] ?? ['lat' => 33.6844, 'lng' => 73.0479];
$hotels = $hotelPlacesRaw[$city]['hotels'] ?? [];

// Merge Firestore hotels — deduplicate by name to avoid showing duplicates
if (!empty($_firestoreHotels)) {
    $existingNames = array_map('strtolower', array_column($hotels, 'name'));
    foreach ($_firestoreHotels as $fh) {
        if (!in_array(strtolower($fh['name']), $existingNames, true)) {
            $hotels[]       = $fh;
            $existingNames[] = strtolower($fh['name']);
        }
    }
}

if (is_array($selectedCenterRaw) && isset($selectedCenterRaw[0], $selectedCenterRaw[1])) {
    $selectedCenter = [
        'lat' => (float)$selectedCenterRaw[0],
        'lng' => (float)$selectedCenterRaw[1]
    ];
} else {
    $selectedCenter = [
        'lat' => (float)($selectedCenterRaw['lat'] ?? 33.6844),
        'lng' => (float)($selectedCenterRaw['lng'] ?? 73.0479)
    ];
}

function calculateNights(string $arrivalDate, string $departureDate): int
{
    if (!$arrivalDate || !$departureDate) {
        return 1;
    }

    try {
        $start = new DateTime($arrivalDate);
        $end = new DateTime($departureDate);
        $diff = $start->diff($end)->days;
        return max(1, (int)$diff);
    } catch (Throwable $e) {
        return 1;
    }
}

function formatShortDate(string $date): string
{
    if (!$date) return '';
    try {
        return (new DateTime($date))->format('n/j');
    } catch (Throwable $e) {
        return $date;
    }
}

function getHotelImagePath(array $hotel): string
{
    $image = trim((string)($hotel['image'] ?? ''));
    $imageSource = strtolower(trim((string)($hotel['image_source'] ?? '')));

    if ($image === '') {
        return '/travelix/images/default_hotel.png';
    }

    if ($imageSource === 'admin' || $imageSource === 'google_places') {
        return '/travelix/hotel_images/' . $image;
    }

    if (
        str_starts_with($image, 'http://') ||
        str_starts_with($image, 'https://') ||
        str_starts_with($image, '/')
    ) {
        return $image;
    }

    return $image;
}

$nights = calculateNights($arrivalDate, $departureDate);

foreach ($hotels as &$hotel) {
    $hotel['total_price'] = ((float)($hotel['price_per_night'] ?? 0)) * $nights;
    $hotel['resolved_image'] = getHotelImagePath($hotel);
}
unset($hotel);

$loginUrl = '/travelix/auth/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Hotels | Travelix</title>

    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <link rel="stylesheet" href="/travelix/assets/css/travelix_3d_effects.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="assets/searched_hotel.css">
</head>
<body>

<?php include '../includes/user_top_navbar.php'; ?>

<div class="hotel-results-shell">

    <aside class="hotel-results-left">

        <div class="hotel-search-summary-bar">
            <div class="summary-box">
                <div class="summary-label">Where</div>
                <div class="summary-value"><?php echo $city ? htmlspecialchars($city) : 'Select City'; ?></div>
            </div>

            <div class="summary-box">
                <div class="summary-label">When</div>
                <div class="summary-value">
                    <?php if ($arrivalDate && $departureDate): ?>
                        <i class="fa-regular fa-calendar"></i>
                        <?php echo htmlspecialchars(formatShortDate($arrivalDate)); ?>
                        &nbsp;–&nbsp;
                        <?php echo htmlspecialchars(formatShortDate($departureDate)); ?>
                    <?php else: ?>
                        Select dates
                    <?php endif; ?>
                </div>
            </div>

            <div class="summary-box small">
                <div class="summary-label">Travelers</div>
                <div class="summary-value">
                    <i class="fa-solid fa-bed"></i> <?php echo (int)$rooms; ?>
                    <i class="fa-solid fa-user-group travelers-icon-gap"></i> <?php echo (int)$adults + (int)$children; ?>
                </div>
            </div>
        </div>

        <?php
            $minPrice = !empty($hotels) ? (int)floor(min(array_column($hotels, 'price_per_night'))) : 0;
            $maxPrice = !empty($hotels) ? (int)ceil(max(array_column($hotels, 'price_per_night'))) : 0;
            if ($maxPrice <= $minPrice) $maxPrice = $minPrice + 1000;
        ?>
        <div class="hotel-filter-bar">
            <div class="range-filter-chip">
                <div class="price-range-label">
                    Price: <span id="priceRangeLabel">PKR <?php echo number_format($minPrice); ?> - PKR <?php echo number_format($maxPrice); ?></span>
                </div>
                <div class="dual-range" id="priceRangeSlider">
                    <input type="range" id="priceRangeMin" min="<?php echo $minPrice; ?>" max="<?php echo $maxPrice; ?>" value="<?php echo $minPrice; ?>" step="500">
                    <input type="range" id="priceRangeMax" min="<?php echo $minPrice; ?>" max="<?php echo $maxPrice; ?>" value="<?php echo $maxPrice; ?>" step="500">
                </div>
            </div>

            <div class="range-filter-chip">
                <div class="price-range-label">
                    Min rating: <span id="ratingRangeLabel">Any</span>
                </div>
                <div class="dual-range single">
                    <input type="range" id="ratingRangeMin" min="0" max="10" value="0" step="0.5">
                </div>
            </div>

            <button type="button" class="filter-pill" id="resetHotelFiltersBtn">
                <i class="fa-solid fa-sliders"></i>
                Reset
            </button>
        </div>

        <div class="results-head-row">
            <div class="results-head-left">
                <strong><?php echo count($hotels); ?> results</strong>
                <span class="results-separator">|</span>
                <span>Sort by: <strong>User ratings</strong> <i class="fa-solid fa-caret-down"></i></span>
            </div>
            <div class="results-head-right">Currency – PKR <i class="fa-solid fa-caret-down"></i></div>
        </div>

        <div class="hotel-results-list" id="hotelResultsList">
            <?php if (!$city): ?>
                <div class="empty-results-box">
                    <h4>No city selected</h4>
                    <p>Please go back and choose a destination city first.</p>
                </div>
            <?php elseif (empty($hotels)): ?>
                <div class="empty-results-box">
                    <h4>No hotels found</h4>
                    <p>No hotel data is available for this city yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($hotels as $index => $hotel): ?>
                    <div class="hotel-result-card tilt-card" data-hotel-index="<?php echo $index; ?>"
                         data-hotel-id="<?php echo htmlspecialchars($hotel['id'] ?? ''); ?>"
                         data-total-rooms="<?php echo (int)($hotel['total_rooms'] ?? 0); ?>"
                         data-price="<?php echo (float)($hotel['price_per_night'] ?? 0); ?>"
                         data-rating="<?php echo (float)($hotel['rating'] ?? 0); ?>">
                        <div class="hotel-card-image">
                            <img src="<?php echo htmlspecialchars($hotel['resolved_image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($hotel['name'] ?? 'Hotel'); ?>" width="220" height="138"<?php echo $index > 0 ? ' loading="lazy"' : ''; ?>>
                        </div>

                        <div class="hotel-card-main">
                            <h3><?php echo htmlspecialchars($hotel['name'] ?? 'Hotel'); ?></h3>
                            <div class="avail-badge" id="avail-<?php echo htmlspecialchars($hotel['id'] ?? ''); ?>"></div>
                            <div class="hotel-card-location"><?php echo htmlspecialchars($city); ?></div>

                            <div class="hotel-card-meta">
                                <?php echo htmlspecialchars(implode(' · ', $hotel['features'] ?? [])); ?>
                            </div>

                            <?php if (!empty($hotel['features'])): ?>
                                <div class="hotel-free-cancel">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <?php echo htmlspecialchars($hotel['features'][0]); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hotel-card-rating">
                            <span class="rating-badge"><?php echo number_format((float)($hotel['rating'] ?? 0), 1); ?></span>
                            <div class="rating-text-wrap">
                                <div class="rating-title">Exceptional</div>
                                <div class="rating-count">(<?php echo (int)($hotel['reviews'] ?? 0); ?>)</div>
                            </div>
                        </div>

                        <div class="hotel-card-price">
                            <div class="price-big">PKR <?php echo number_format((float)($hotel['price_per_night'] ?? 0)); ?></div>
                            <div class="price-small">Total PKR <?php echo number_format((float)($hotel['total_price'] ?? 0)); ?></div>

                            <button
                                type="button"
                                class="view-deal-btn"
                                data-hotel-id="<?php echo htmlspecialchars($hotel['id'] ?? ''); ?>"
                                data-hotel-name="<?php echo htmlspecialchars($hotel['name'] ?? ''); ?>"
                                data-hotel-price="<?php echo (float)($hotel['total_price'] ?? 0); ?>"
                                data-hotel-price-night="<?php echo (float)($hotel['price_per_night'] ?? 0); ?>"
                                data-hotel-image="<?php echo htmlspecialchars($hotel['resolved_image'] ?? ''); ?>"
                                data-hotel-address="<?php echo htmlspecialchars($hotel['address'] ?? ''); ?>"
                                data-hotel-rating="<?php echo (float)($hotel['rating'] ?? 0); ?>"
                                data-hotel-reviews="<?php echo (int)($hotel['reviews'] ?? 0); ?>"
                                data-hotel-stars="<?php echo (int)($hotel['stars'] ?? 0); ?>"
                            >
                                Book Now
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>

                            <div class="provider-text">Travelix</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </aside>

    <section class="hotel-results-right">
        <div class="map-top-actions">
            <button type="button" class="top-map-btn center-btn" id="fitHotelsBtn">Search this area</button>
            <button type="button" class="top-map-btn filter-btn"><i class="fa-solid fa-sliders"></i> Filters</button>
        </div>

        <div id="searchedHotelMap"></div>
    </section>
</div>

<script>
    window.hotelPlaces = <?php echo $hotelPlacesJson ?: '{}'; ?>;

    window.searchedHotelsData = <?php echo json_encode([
        'city' => $city,
        'center' => [
            'lat' => (float)($selectedCenter['lat'] ?? 33.6844),
            'lng' => (float)($selectedCenter['lng'] ?? 73.0479),
        ],
        'hotels' => $hotels,
        'source' => $source,
        'returnStep' => $returnStep,
        'arrivalDate' => $arrivalDate,
        'departureDate' => $departureDate,
        'rooms' => $rooms,
        'adults' => $adults,
        'children' => $children,
        'nights' => $nights,
        'isLoggedIn' => $isLoggedIn,
        'loginUrl' => $loginUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<script src="/travelix/assets/vendor/leaflet/leaflet.js"></script>
<script src="assets/searched_hotel.js"></script>
<script src="/travelix/assets/js/travelix_3d_effects.js"></script>

<!-- Firebase SDK — Real-Time Hotel Listener -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>

<style>
/* New hotel card pop-in animation */
@keyframes hotelSlideIn {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
.rt-hotel-new { animation: hotelSlideIn 0.5s ease forwards; }

/* Live badge on new cards */
.rt-live-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:linear-gradient(135deg,#059669,#10b981);
    color:#fff; border-radius:999px; padding:3px 10px;
    font-size:11px; font-weight:700; margin-left:8px;
}
.rt-live-badge .dot { width:6px; height:6px; background:#fff; border-radius:50%; animation:blink2 1s infinite; }
@keyframes blink2 { 0%,100%{opacity:1} 50%{opacity:0.2} }

/* Live room availability badge */
.avail-badge {
    display:inline-flex; align-items:center; gap:6px;
    font-size:12px; font-weight:700; margin:4px 0 2px;
    padding:3px 0;
}
.avail-badge:empty { display:none; }
.avail-badge .dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.avail-badge.avail-ok { color:#16a34a; }
.avail-badge.avail-ok .dot { background:#16a34a; }
.avail-badge.avail-low { color:#d97706; }
.avail-badge.avail-low .dot { background:#d97706; }
.avail-badge.avail-none { color:#dc2626; }
.avail-badge.avail-none .dot { background:#dc2626; }

/* Top toast */
.rt-toast {
    position:fixed; top:16px; left:50%; transform:translateX(-50%);
    z-index:9999; background:linear-gradient(135deg,#133c96,#2d6af0);
    color:#fff; border-radius:999px; padding:12px 26px;
    font-weight:700; font-size:14px; box-shadow:0 8px 30px rgba(45,106,240,0.4);
    display:flex; align-items:center; gap:10px;
    animation:toastIn 0.4s ease;
    white-space:nowrap;
}
@keyframes toastIn { from{opacity:0;transform:translate(-50%,-20px)} to{opacity:1;transform:translate(-50%,0)} }
</style>

<script>
(function () {
    const CITY     = <?php echo json_encode($city); ?>;
    const NIGHTS   = <?php echo json_encode($nights); ?>;
    const ARRIVAL  = <?php echo json_encode($arrivalDate); ?>;
    const DEPART   = <?php echo json_encode($departureDate); ?>;
    const ROOMS    = <?php echo json_encode($rooms); ?>;
    const ADULTS   = <?php echo json_encode($adults); ?>;
    const CHILDREN = <?php echo json_encode($children); ?>;
    const SOURCE   = <?php echo json_encode($source); ?>;
    const R_STEP   = <?php echo json_encode($returnStep); ?>;
    const IS_LOGGED= <?php echo json_encode($isLoggedIn); ?>;

    if (!CITY) return;

    const firebaseConfig = {
        apiKey: "AIzaSyBWCOX2TjmnbxQrRmKkj7DTqOjoJJfjJR8",
        authDomain: "travelix-330b6.firebaseapp.com",
        projectId: "travelix-330b6",
        storageBucket: "travelix-330b6.firebasestorage.app",
        messagingSenderId: "948223226994",
        appId: "1:948223226994:web:a21f26a843b987ec0d3eeb"
    };

    try {
        if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
        const db = firebase.firestore();

        // ── Live room availability — checked against the searched date range ──
        function checkAvailability(hotelId, totalRooms, badgeEl) {
            if (!badgeEl || !hotelId || !totalRooms) return;
            const from = ARRIVAL || new Date().toISOString().slice(0, 10);
            const to   = DEPART  || from;

            db.collection('hotel_bookings').where('hotelId', '==', hotelId).get()
                .then(snap => {
                    let booked = 0;
                    snap.forEach(doc => {
                        const b = doc.data();
                        const status = String(b.bookingStatus || 'confirmed').toLowerCase();
                        if (['cancelled','rejected','payment_rejected'].includes(status)) return;
                        // Overlap check: existing booking overlaps the searched date range
                        if (b.arrivalDate && b.departureDate && b.arrivalDate < to && from < b.departureDate) {
                            booked += Number(b.rooms || 1);
                        }
                    });
                    const available = Math.max(0, totalRooms - booked);
                    let cls = 'avail-ok', icon = 'fa-circle-check', text = `${available} rooms available`;
                    if (available === 0) { cls = 'avail-none'; icon = 'fa-circle-xmark'; text = 'Fully booked for these dates'; }
                    else if (available <= 3) { cls = 'avail-low'; icon = 'fa-triangle-exclamation'; text = `Only ${available} room${available===1?'':'s'} left`; }
                    badgeEl.className = 'avail-badge ' + cls;
                    badgeEl.innerHTML = `<span class="dot"></span> ${text}`;
                })
                .catch(() => { badgeEl.innerHTML = ''; });
        }

        function loadAllAvailability() {
            document.querySelectorAll('.hotel-result-card').forEach(card => {
                const hotelId = card.dataset.hotelId;
                const total   = parseInt(card.dataset.totalRooms) || 0;
                const badge   = document.getElementById('avail-' + hotelId);
                checkAvailability(hotelId, total, badge);
            });
        }
        loadAllAvailability();

        // Track IDs already shown (server-rendered + real-time added)
        const shownIds = new Set();
        document.querySelectorAll('.hotel-result-card').forEach(card => {
            const btn = card.querySelector('.view-deal-btn');
            if (btn && btn.dataset.hotelId) shownIds.add(btn.dataset.hotelId);
            // Also track by name to avoid duplicate on name
            if (btn && btn.dataset.hotelName) shownIds.add('name:' + btn.dataset.hotelName.toLowerCase().trim());
        });

        let isFirstSnapshot = true;

        // Listen to `hotels` collection filtered by city
        db.collection('hotels')
            .where('city', '==', CITY)
            .where('status', '==', 'active')
            .onSnapshot(function (snapshot) {

                snapshot.docChanges().forEach(function (change) {
                    if (change.type !== 'added') return;

                    const h   = change.doc.data();
                    const hId = h.id || change.doc.id;
                    const nameKey = 'name:' + (h.name || '').toLowerCase().trim();

                    // Skip if already shown (either by ID or name)
                    if (shownIds.has(hId) || shownIds.has(nameKey)) return;

                    // Skip silently on first snapshot (hotels already rendered server-side may overlap)
                    if (isFirstSnapshot) {
                        shownIds.add(hId);
                        shownIds.add(nameKey);
                        return;
                    }

                    shownIds.add(hId);
                    shownIds.add(nameKey);

                    injectHotelCard(h, hId);
                    showToast('🏨 ' + h.name + ' just listed in ' + CITY + '!');
                });

                isFirstSnapshot = false;

            }, function (err) {
                console.log('hotels collection listener error:', err.message);
            });

        // ── Build and inject a hotel card ──
        function injectHotelCard(h, docId) {
            const totalPrice = (parseFloat(h.price_per_night) || 0) * (NIGHTS || 1);
            const features   = Array.isArray(h.features) ? h.features : [];
            const image      = h.image || '/travelix/images/default_hotel.jpg';
            const id         = h.id || docId;

            const card = document.createElement('div');
            card.className = 'hotel-result-card rt-hotel-new tilt-card';
            card.setAttribute('data-hotel-index', 'rt_' + docId);
            card.setAttribute('data-hotel-id', id);
            card.setAttribute('data-total-rooms', h.total_rooms || 0);

            card.innerHTML = `
                <div class="hotel-card-image">
                    <img src="${escHtml(image)}" alt="${escHtml(h.name || 'Hotel')}" width="220" height="138" loading="lazy"
                         onerror="this.src='/travelix/images/default_hotel.jpg'">
                </div>

                <div class="hotel-card-main">
                    <h3>
                        ${escHtml(h.name || 'Hotel')}
                        <span class="rt-live-badge"><span class="dot"></span> Just Listed</span>
                    </h3>
                    <div class="avail-badge" id="avail-${escHtml(id)}"></div>
                    <div class="hotel-card-location">${escHtml(CITY)}</div>
                    <div class="hotel-card-meta">${escHtml(features.slice(0,4).join(' · '))}</div>
                    ${features.length ? `<div class="hotel-free-cancel"><i class="fa-solid fa-circle-check"></i> ${escHtml(features[0])}</div>` : ''}
                </div>

                <div class="hotel-card-rating">
                    <span class="rating-badge">${parseFloat(h.rating || 0).toFixed(1)}</span>
                    <div class="rating-text-wrap">
                        <div class="rating-title">Exceptional</div>
                        <div class="rating-count">(${parseInt(h.reviews || 0).toLocaleString()})</div>
                    </div>
                </div>

                <div class="hotel-card-price">
                    <div class="price-big">PKR ${Number(h.price_per_night || 0).toLocaleString()}</div>
                    <div class="price-small">Total PKR ${Number(totalPrice).toLocaleString()}</div>

                    <button type="button" class="view-deal-btn"
                        data-hotel-id="${escHtml(id)}"
                        data-hotel-name="${escHtml(h.name || '')}"
                        data-hotel-price="${totalPrice}"
                        data-hotel-price-night="${parseFloat(h.price_per_night || 0)}"
                        data-hotel-image="${escHtml(image)}"
                        data-hotel-address="${escHtml(h.address || '')}"
                        data-hotel-rating="${parseFloat(h.rating || 0)}"
                        data-hotel-reviews="${parseInt(h.reviews || 0)}"
                        data-hotel-stars="${parseInt(h.stars || 0)}">
                        Book Now <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <div class="provider-text">Travelix</div>
                </div>
            `;

            // Add to list
            const list = document.getElementById('hotelResultsList');
            if (list) {
                // Remove "no hotels" empty state if present
                const empty = list.querySelector('.empty-results-box');
                if (empty) empty.remove();

                list.prepend(card);
                window.travelixInit3D?.(card);

                // Rebind the Book Now button on this new card
                const btn = card.querySelector('.view-deal-btn');
                if (btn) bindSingleBookBtn(btn);

                checkAvailability(id, h.total_rooms || 0, document.getElementById('avail-' + id));
            }
        }

        // ── Bind a single Book Now button (mirrors searched_hotel.js logic) ──
        function bindSingleBookBtn(button) {
            button.addEventListener('click', async function (e) {
                e.stopPropagation();

                if (!IS_LOGGED) {
                    const res = await Swal.fire({
                        icon:'warning', title:'Login Required',
                        text:'Please log in first to book a hotel.',
                        confirmButtonText:'Go to Login', showCancelButton:true,
                        cancelButtonText:'Cancel', confirmButtonColor:'#1484B4'
                    });
                    if (res.isConfirmed) window.location.href = '/travelix/auth/login.php';
                    return;
                }

                Swal.fire({ title:'Opening booking page...', text:'Please wait...', allowOutsideClick:false, allowEscapeKey:false, didOpen:()=>Swal.showLoading() });

                const params = new URLSearchParams({
                    hotelId: button.dataset.hotelId || '',
                    hotelName: button.dataset.hotelName || '',
                    hotelPrice: button.dataset.hotelPrice || '',
                    hotelPriceNight: button.dataset.hotelPriceNight || '',
                    hotelImage: button.dataset.hotelImage || '',
                    hotelAddress: button.dataset.hotelAddress || '',
                    hotelRating: button.dataset.hotelRating || '',
                    hotelReviews: button.dataset.hotelReviews || '',
                    hotelStars: button.dataset.hotelStars || '',
                    toCity: CITY || '', arrivalDate: ARRIVAL || '',
                    departureDate: DEPART || '', rooms: ROOMS || 1,
                    adults: ADULTS || 1, children: CHILDREN || 0,
                    nights: NIGHTS || 1, source: SOURCE || '',
                    returnStep: R_STEP || 3
                });

                window.location.href = '/travelix/hotel/book_hotel.php?' + params.toString();
            });
        }

        // ── Toast notification ──
        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'rt-toast';
            t.innerHTML = `<span style="width:10px;height:10px;border-radius:50%;background:#4ade80;animation:blink2 1s infinite;flex-shrink:0;"></span>${msg}`;
            document.body.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.5s'; setTimeout(()=>t.remove(),500); }, 4000);
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.textContent = String(s ?? '');
            return d.innerHTML;
        }

    } catch(e) {
        console.log('Firebase init error:', e.message);
    }
})();
</script>

<?php include '../includes/user_bottom_footer.php'; ?>

</body>
</html>
