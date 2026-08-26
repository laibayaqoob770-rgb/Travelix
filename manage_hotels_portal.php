<?php
/* ================================================================
   Unified Hotel Management  — Admin
   Merges: hotel_destinations (city-based) + hotels (portal/staff)
================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php'); exit;
}
$currentUser = $_SESSION['user'] ?? [];
$userRole    = strtolower((string)($currentUser['role'] ?? 'user'));
if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/admin_dashboard.php'); exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';

$projectId          = FIREBASE_PROJECT_ID;
$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
$flash              = null;

/* ── PHP Helpers (service account / city-based hotels) ── */
function b64url($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }

function getAdminToken($path) {
    $sa  = json_decode(file_get_contents($path), true);
    $now = time();
    $hdr = b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $cls = b64url(json_encode([
        'iss'=>$sa['client_email'], 'scope'=>'https://www.googleapis.com/auth/datastore',
        'aud'=>$sa['token_uri'],    'exp'=>$now+3600, 'iat'=>$now
    ]));
    $sig = ''; openssl_sign("$hdr.$cls", $sig, $sa['private_key'], 'SHA256');
    $jwt = "$hdr.$cls." . b64url($sig);
    $ch  = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT=>20]);
    $res = json_decode(curl_exec($ch), true); curl_close($ch);
    return $res['access_token'] ?? '';
}

function fsReq($method, $url, $token, $body=null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],
        CURLOPT_TIMEOUT=>25]);
    if ($body!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch); $st = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$st, $res];
}

function fsStr($f,$d='') { return isset($f['stringValue'])?(string)$f['stringValue']:$d; }
function fsNum($f,$d=0)  { return isset($f['doubleValue'])?(float)$f['doubleValue']:(isset($f['integerValue'])?(float)$f['integerValue']:$d); }
function fsInt($f,$d=0)  { return isset($f['integerValue'])?(int)$f['integerValue']:(isset($f['doubleValue'])?(int)$f['doubleValue']:$d); }
function fsArr($f) {
    $out=[];
    foreach(($f['arrayValue']['values']??[]) as $v) $out[]=(string)($v['stringValue']??'');
    return array_values(array_filter(array_map('trim',$out)));
}

function parseCityDoc($doc) {
    $f = $doc['fields']??[]; $np=$doc['name']??''; $parts=explode('/',$np); $docId=end($parts);
    $cf = $f['center']['mapValue']['fields']??[];
    $hotels=[];
    foreach(($f['hotels']['arrayValue']['values']??[]) as $hv) {
        $m=$hv['mapValue']['fields']??[];
        $hotels[]=['id'=>fsStr($m['id']??null),'name'=>fsStr($m['name']??null),
            'image'=>fsStr($m['image']??null),'price_per_night'=>fsNum($m['price_per_night']??null),
            'rating'=>fsNum($m['rating']??null),'reviews'=>fsInt($m['reviews']??null),
            'stars'=>fsInt($m['stars']??null),'address'=>fsStr($m['address']??null),
            'features'=>fsArr($m['features']??null),'lat'=>fsNum($m['lat']??null),'lng'=>fsNum($m['lng']??null),
            'status'=>fsStr($m['status']??null,'active')];
    }
    return ['doc_id'=>$docId,'city'=>fsStr($f['city']??null),'slug'=>fsStr($f['slug']??null,$docId),
        'updatedAt'=>fsStr($f['updatedAt']??null),'source'=>fsStr($f['source']??null),
        'center'=>['lat'=>fsNum($cf['lat']??null),'lng'=>fsNum($cf['lng']??null)],'hotels'=>$hotels];
}

function buildHotelValues($hotels) {
    $out=[];
    foreach($hotels as $h) {
        $fv=[]; foreach((array)($h['features']??[]) as $ft) $fv[]=['stringValue'=>(string)$ft];
        $out[]=['mapValue'=>['fields'=>['id'=>['stringValue'=>(string)($h['id']??'')],
            'name'=>['stringValue'=>(string)($h['name']??'')],'image'=>['stringValue'=>(string)($h['image']??'')],
            'image_source'=>['stringValue'=>'admin'],'price_per_night'=>['doubleValue'=>(float)($h['price_per_night']??0)],
            'rating'=>['doubleValue'=>(float)($h['rating']??0)],'reviews'=>['integerValue'=>(string)((int)($h['reviews']??0))],
            'stars'=>['integerValue'=>(string)((int)($h['stars']??0))],'address'=>['stringValue'=>(string)($h['address']??'')],
            'lat'=>['doubleValue'=>(float)($h['lat']??0)],'lng'=>['doubleValue'=>(float)($h['lng']??0)],
            'features'=>['arrayValue'=>['values'=>$fv]],
            'status'=>['stringValue'=>(string)($h['status']??'active')]]]];
    }
    return $out;
}

/* ── Handle city-hotel status actions ── */
try {
    $token = file_exists($serviceAccountPath) ? getAdminToken($serviceAccountPath) : '';

    if ($_SERVER['REQUEST_METHOD']==='POST' && $token) {
        $action = trim($_POST['action']??'');

        if ($action==='toggle_city_status') {
            $slug=trim($_POST['slug']??''); $idx=(int)($_POST['hotel_index']??-1);
            $newStatus=trim($_POST['new_status']??'active');
            $url ="https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations/{$slug}";
            [$st,$res]=fsReq('GET',$url,$token); $doc=json_decode($res,true);
            $cityDoc=parseCityDoc($doc); $hotels=$cityDoc['hotels'];
            if (isset($hotels[$idx])) {
                $hotels[$idx]['status']=$newStatus;
                $hotelName=$hotels[$idx]['name']??'Hotel';
                $payload=['fields'=>['city'=>['stringValue'=>$cityDoc['city']],'slug'=>['stringValue'=>$cityDoc['slug']],
                    'center'=>['mapValue'=>['fields'=>['lat'=>['doubleValue'=>(float)$cityDoc['center']['lat']],'lng'=>['doubleValue'=>(float)$cityDoc['center']['lng']]]]],
                    'hotels'=>['arrayValue'=>['values'=>buildHotelValues($hotels)]],'source'=>['stringValue'=>'admin_panel'],
                    'updatedAt'=>['timestampValue'=>gmdate('c')]]];
                fsReq('PATCH',$url,$token,$payload);
                $flash=['type'=>'success','msg'=>"\"{$hotelName}\" is now ".($newStatus==='active'?'active.':'inactive.')];
            }
        }
    }

    /* ── Load city-based hotels from hotel_destinations ── */
    $cityDocs=[]; $pageToken='';
    if ($token) {
        do {
            $url="https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/hotel_destinations?pageSize=100";
            if($pageToken) $url.='&pageToken='.urlencode($pageToken);
            [$st,$res]=fsReq('GET',$url,$token); $json=json_decode($res,true);
            foreach(($json['documents']??[]) as $d) $cityDocs[]=parseCityDoc($d);
            $pageToken=(string)($json['nextPageToken']??'');
        } while($pageToken);
    }

    /* ── Flatten city-based hotels into normalised rows ── */
    $cityHotels=[];
    foreach($cityDocs as $cd) {
        foreach($cd['hotels'] as $idx=>$h) {
            $cityHotels[]=['_source'=>'city','_city_slug'=>$cd['slug'],'_hotel_index'=>$idx,
                'id'=>$h['id'],'name'=>$h['name'],'city'=>$cd['city'],'address'=>$h['address'],
                'stars'=>$h['stars'],'price_per_night'=>$h['price_per_night'],
                'rating'=>$h['rating'],'reviews'=>$h['reviews'],'status'=>$h['status'] ?: 'active',
                'image'=>$h['image'],'features'=>$h['features']];
        }
    }

} catch(Throwable $e) {
    $flash=['type'=>'error','msg'=>$e->getMessage()];
    $cityHotels=[];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Hotels — Travelix Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh;}
.admin-layout{display:flex;gap:24px;padding:16px;min-height:100vh;}
.admin-main{flex:1;min-width:0;}

@media (max-width:991.98px){
.admin-layout{display:block;padding:0;}
.admin-main{padding:0 16px 16px;}
}

/* Header */
.page-header{background:linear-gradient(135deg,#133c96,#1e50c0,#2d6af0);border-radius:24px;padding:28px 32px;color:#fff;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;position:relative;overflow:hidden;}
.page-header::before{content:'';position:absolute;top:-40%;right:-8%;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.07),transparent 70%);border-radius:50%;}
.page-header h1{font-size:22px;font-weight:800;margin:0 0 4px;position:relative;}
.page-header p{font-size:13px;opacity:.82;margin:0;position:relative;}
.add-btn{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#133c96;border:none;padding:11px 20px;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;text-decoration:none;transition:.2s;position:relative;white-space:nowrap;}
.add-btn:hover{background:#f0f6ff;color:#133c96;}
.commission-btn{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;box-shadow:0 4px 14px rgba(124,58,237,.35);}
.commission-btn:hover{background:linear-gradient(135deg,#6d28d9,#9333ea);color:#fff;box-shadow:0 6px 18px rgba(124,58,237,.45);}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:22px;}
.stat-box{background:#fff;border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;gap:12px;}
.stat-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.si-b{background:#eff6ff;} .si-g{background:#f0fdf4;} .si-o{background:#fff7ed;} .si-p{background:#faf5ff;} .si-r{background:#fef2f2;}
.stat-lbl{font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
.stat-val{font-size:20px;font-weight:800;color:#1e293b;margin-top:1px;}

/* Toolbar */
.toolbar{background:#fff;border-radius:16px;padding:14px 18px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.search-wrap{display:flex;align-items:center;gap:8px;background:#f8faff;border:1.5px solid #e2e8f0;border-radius:11px;padding:8px 13px;flex:1;min-width:200px;}
.search-wrap input{border:none;background:none;outline:none;font-size:13px;color:#1e293b;width:100%;}
.filter-sel{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:11px;font-size:13px;color:#374151;background:#fff;outline:none;cursor:pointer;}

/* Table */
.table-card{background:#fff;border-radius:18px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow-x:auto;overflow-y:hidden;}
table{min-width:600px;}
table{width:100%;border-collapse:collapse;}
thead th{background:#f8faff;padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e8edf5;}
tbody td{padding:13px 16px;border-bottom:1px solid #f1f4f9;font-size:13px;color:#334155;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#fafbff;}
.hotel-thumb{width:46px;height:46px;border-radius:10px;object-fit:cover;background:#e8edf5;}
.hname{font-weight:700;color:#1e293b;font-size:14px;}
.haddr{font-size:11px;color:#94a3b8;margin-top:1px;}
.stars-txt{color:#f59e0b;font-size:12px;letter-spacing:1px;}
.badge-status{display:inline-flex;align-items:center;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;}
.bs-active{background:#dcfce7;color:#166534;} .bs-draft{background:#fef9c3;color:#854d0e;} .bs-inactive{background:#fee2e2;color:#991b1b;}
.src-badge{padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700;}
.src-city{background:#eff6ff;color:#1d4ed8;} .src-portal{background:#f0fdf4;color:#166534;}

/* Action buttons */
.actions{display:flex;gap:5px;flex-wrap:wrap;}
.abtn{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer;border:none;transition:.2s;}
.abtn-view{background:#f0fdf4;color:#16a34a;} .abtn-view:hover{background:#dcfce7;}
.abtn-disable{background:#fff7ed;color:#c2410c;} .abtn-disable:hover{background:#ffedd5;}
.abtn-enable{background:#f0fdf4;color:#16a34a;}  .abtn-enable:hover{background:#dcfce7;}

/* Staff cell */
.staff-assigned{display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;color:#166534;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;}
.staff-assigned i{color:#22c55e;font-size:11px;}
.staff-none{display:inline-flex;align-items:center;gap:6px;background:#fff7ed;color:#c2410c;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;}
.staff-none i{color:#f59e0b;font-size:11px;}

/* Loading / empty */
.loader-row td,.empty-row td{text-align:center;padding:48px;}
.spinner{width:30px;height:30px;border:3px solid #e2e8f0;border-top-color:#133c96;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 10px;}
@keyframes spin{to{transform:rotate(360deg);}}

/* Source tabs */
.src-tabs{display:flex;gap:8px;flex-wrap:wrap;}
.src-tab{padding:7px 14px;border-radius:10px;font-size:12px;font-weight:700;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;cursor:pointer;transition:.2s;}
.src-tab.active{background:#133c96;color:#fff;border-color:#133c96;}
</style>
</head>
<body>
<div class="admin-layout">
    <?php include $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1>🏨 Hotel Management</h1>
                <p>All hotels — city-based destinations &amp; hotel portal listings in one place.</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/travelix/admin_manage/booking_payments.php" class="add-btn commission-btn">
                    <i class="fas fa-file-invoice-dollar"></i> Booking Payments
                </a>
                <a href="/travelix/admin_manage/refunds.php" class="add-btn commission-btn">
                    <i class="fas fa-rotate-left"></i> Escalated Refunds
                </a>
                <a href="admin_hotel_form.php" class="add-btn">
                    <i class="fas fa-plus"></i> Add New Hotel
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box"><div class="stat-ico si-b">🏨</div><div><div class="stat-lbl">Total Hotels</div><div class="stat-val" id="sTotal">—</div></div></div>
            <div class="stat-box"><div class="stat-ico si-g">✅</div><div><div class="stat-lbl">Active</div><div class="stat-val" id="sActive">—</div></div></div>
            <div class="stat-box"><div class="stat-ico si-o">📝</div><div><div class="stat-lbl">Draft</div><div class="stat-val" id="sDraft">—</div></div></div>
            <div class="stat-box"><div class="stat-ico si-p">🏙️</div><div><div class="stat-lbl">City-Based</div><div class="stat-val" id="sCityCount">—</div></div></div>
            <div class="stat-box"><div class="stat-ico si-r">🏢</div><div><div class="stat-lbl">Portal Hotels</div><div class="stat-val" id="sPortalCount">—</div></div></div>
            <div class="stat-box"><div class="stat-ico si-p">💰</div><div><div class="stat-lbl">Total Payout Owed to Hotels</div><div class="stat-val" id="sTotalCommission">—</div></div></div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-search" style="color:#94a3b8;font-size:13px;"></i>
                <input type="text" id="searchInput" placeholder="Search hotel name or city..." oninput="applyFilters()">
            </div>
            <select class="filter-sel" id="filterCity" onchange="applyFilters()">
                <option value="">All Cities</option>
            </select>
            <select class="filter-sel" id="filterStatus" onchange="applyFilters()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="inactive">Inactive</option>
            </select>
            <div class="src-tabs">
                <button class="src-tab active" data-src="" onclick="setSrcFilter(this,'')">All</button>
                <button class="src-tab" data-src="city" onclick="setSrcFilter(this,'city')">🏙️ City-Based</button>
                <button class="src-tab" data-src="portal" onclick="setSrcFilter(this,'portal')">🏢 Portal</button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>City</th>
                        <th>Hotel Name</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Staff</th>
                        <th>Revenue</th>
                        <th>Payout Owed (100%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr class="loader-row"><td colspan="8"><div class="spinner"></div>Loading hotels...</td></tr>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- Hidden PHP form for city-hotel status toggle -->
<form method="POST" id="toggleCityStatusForm" style="display:none;">
    <input type="hidden" name="action" value="toggle_city_status">
    <input type="hidden" name="slug" id="ts_slug">
    <input type="hidden" name="hotel_index" id="ts_index">
    <input type="hidden" name="new_status" id="ts_status">
</form>

<!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
<script>
const firebaseConfig = {
    apiKey:            "<?= FIREBASE_API_KEY ?>",
    authDomain:        "<?= FIREBASE_PROJECT_ID ?>.firebaseapp.com",
    projectId:         "<?= FIREBASE_PROJECT_ID ?>",
    storageBucket:     "<?= FIREBASE_PROJECT_ID ?>.firebasestorage.app",
    messagingSenderId: "<?= FIREBASE_MESSAGING_SENDER_ID ?>",
    appId:             "<?= FIREBASE_APP_ID ?>"
};
if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
const db = firebase.firestore();
const BASE_URL = "<?= $baseUrl ?>";

// City-based hotels loaded from PHP
const CITY_HOTELS = <?= json_encode(array_values($cityHotels)) ?>;

let portalHotels     = [];
let staffMap         = {};   // uid → name
let staffByHotelId   = {};   // hotel_id → email
let revenueByHotelId = {};   // hotelId → total confirmed revenue (PKR)
let payoutOwedByHotelId = {}; // hotelId → sent/unconfirmed or not-yet-confirmed hotel share
let srcFilter        = '';
let allCombined      = [];

// Load staff — build both maps then re-render
db.collection('hotel_staff').get().then(snap => {
    snap.forEach(d => {
        const s = d.data();
        staffMap[d.id] = s.full_name || s.first_name || s.email || 'Staff';
        if (s.hotel_id) staffByHotelId[s.hotel_id] = s.email || '';
    });
    // Re-render so city-based rows show correct staff status
    if (allCombined.length) applyFilters();
});

// Load revenue per hotel — confirmed (verified-payment) bookings only
db.collection('hotel_bookings').onSnapshot(snap => {
    const revenue = {};
    const owed = {};
    snap.forEach(d => {
        const b = d.data();
        const status = String(b.bookingStatus || 'confirmed').toLowerCase();
        const hotelId = b.hotelId || '';
        if (!hotelId) return;
        const amount = Number(b.hotelPrice || b.total_amount || 0);
        if (status === 'confirmed') revenue[hotelId] = (revenue[hotelId] || 0) + amount;
        const paymentVerified = String(b.payment?.status || '').toLowerCase() === 'verified';
        const payoutStatus = String(b.hotelPayoutStatus || '').toLowerCase();
        if (paymentVerified && payoutStatus !== 'confirmed_received' && !['cancelled','rejected','payment_rejected'].includes(status)) {
            owed[hotelId] = (owed[hotelId] || 0) + amount;
        }
    });
    revenueByHotelId = revenue;
    payoutOwedByHotelId = owed;
    if (allCombined.length) applyFilters();
    renderCommissionTotal();
});

function renderCommissionTotal() {
    // Hotels are owed 100% of their price — the 12% platform fee is charged
    // to the guest on top, never deducted from what the hotel is owed.
    const totalPayoutOwed = Object.values(payoutOwedByHotelId).reduce((sum, v) => sum + v, 0);
    document.getElementById('sTotalCommission').textContent = 'PKR ' + Math.round(totalPayoutOwed).toLocaleString();
}

// Load portal hotels (live)
db.collection('hotels').onSnapshot(snap => {
    portalHotels = snap.docs.map(d => {
        const h = d.data();
        return {
            _source: 'portal', _docId: d.id,
            id: d.id, name: h.name||'', city: h.city||'', address: h.address||'',
            stars: h.stars||0, price_per_night: h.price_per_night||0,
            rating: h.rating||0, reviews: h.reviews||0,
            status: h.status||'active', image: h.image||'',
            features: h.features||[], staff_uid: h.staff_uid||'',
            staff_email: h.staff_email||'', total_rooms: h.total_rooms||0,
            disabled: h.disabled||false, disabledReason: h.disabledReason||''
        };
    });
    merge();
}, () => {
    db.collection('hotels').get().then(snap => {
        portalHotels = snap.docs.map(d => {
            const h=d.data();
            return { _source:'portal',_docId:d.id,id:d.id,name:h.name||'',city:h.city||'',
                address:h.address||'',stars:h.stars||0,price_per_night:h.price_per_night||0,
                rating:h.rating||0,reviews:h.reviews||0,status:h.status||'active',
                image:h.image||'',features:h.features||[],staff_uid:h.staff_uid||'',
                staff_email:h.staff_email||'',total_rooms:h.total_rooms||0,
                disabled:h.disabled||false,disabledReason:h.disabledReason||'' };
        });
        merge();
    });
});

function merge() {
    allCombined = [...CITY_HOTELS, ...portalHotels];
    populateCityFilter();
    applyFilters();
    renderStats();
}

function renderStats() {
    const active  = allCombined.filter(h => h.status==='active').length;
    const draft   = allCombined.filter(h => h.status==='draft').length;
    const city    = allCombined.filter(h => h._source==='city').length;
    const portal  = allCombined.filter(h => h._source==='portal').length;
    document.getElementById('sTotal').textContent       = allCombined.length;
    document.getElementById('sActive').textContent      = active;
    document.getElementById('sDraft').textContent       = draft;
    document.getElementById('sCityCount').textContent   = city;
    document.getElementById('sPortalCount').textContent = portal;
}

function populateCityFilter() {
    const cities = [...new Set(allCombined.map(h=>h.city).filter(Boolean))].sort();
    const sel = document.getElementById('filterCity');
    const cur = sel.value;
    sel.innerHTML = '<option value="">All Cities</option>' +
        cities.map(c=>`<option value="${c}"${c===cur?' selected':''}>${c}</option>`).join('');
}

function setSrcFilter(btn, val) {
    srcFilter = val;
    document.querySelectorAll('.src-tab').forEach(t=>t.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function applyFilters() {
    const q  = document.getElementById('searchInput').value.toLowerCase();
    const st = document.getElementById('filterStatus').value;
    const ct = document.getElementById('filterCity').value;
    const filtered = allCombined.filter(h =>
        (!q  || h.name.toLowerCase().includes(q) || h.city.toLowerCase().includes(q)) &&
        (!st || h.status===st) &&
        (!ct || h.city===ct) &&
        (!srcFilter || h._source===srcFilter)
    );
    renderTable(filtered);
}

function renderTable(hotels) {
    const tbody = document.getElementById('tableBody');
    if (!hotels.length) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8">
            <div style="font-size:42px;opacity:.25;margin-bottom:10px;">🏨</div>
            <div style="color:#94a3b8;font-size:14px;">No hotels found.</div>
        </td></tr>`;
        return;
    }
    tbody.innerHTML = hotels.map((h,i) => {
        const stars    = '★'.repeat(Math.min(5,h.stars||0))+'☆'.repeat(Math.max(0,5-(h.stars||0)));
        const bc       = h.status==='active'?'bs-active':h.status==='draft'?'bs-draft':'bs-inactive';
        const bt       = h.status==='active'?'Active':h.status==='draft'?'Draft':'Inactive';
        const statusDot= h.status==='active'?'#22c55e':h.status==='draft'?'#eab308':'#ef4444';
        const img      = h._source==='city'
            ? (h.image ? BASE_URL+'/hotel_images/'+h.image.replace(/^\//,'') : '')
            : (h.image||'');
        const fallback = BASE_URL+'/images/default_profile.png';
        const srcLabel = h._source==='city'
            ? '<span class="src-badge src-city">City-Based</span>'
            : '<span class="src-badge src-portal">Portal</span>';
        const reviewCount = h.reviews || 0;

        // Resolve staff email for any hotel type
        const resolvedStaffEmail = h._source === 'portal'
            ? (h.staff_email || '')
            : (staffByHotelId[h.id] || '');

        // Staff cell
        const staffCell = resolvedStaffEmail
            ? `<span class="staff-assigned"><i class="fas fa-user-check"></i> ${esc(resolvedStaffEmail)}</span>`
            : `<span class="staff-none"><i class="fas fa-exclamation-triangle"></i> No staff</span>`;

        const revenue    = revenueByHotelId[h.id] || 0;
        const commission = payoutOwedByHotelId[h.id] || 0;
        const revenueCell = revenue > 0
            ? `<div style="font-size:14px;font-weight:800;color:#166534;">PKR ${revenue.toLocaleString()}</div>`
            : `<div style="font-size:13px;color:#94a3b8;">—</div>`;

        const commissionCell = commission > 0
            ? `<div style="font-size:14px;font-weight:800;color:#7c3aed;">PKR ${Math.round(commission).toLocaleString()}</div>`
            : `<div style="font-size:13px;color:#94a3b8;">—</div>`;

        const isActive = h.status === 'active';
        const toggleBtn = h._source==='portal'
            ? `<button class="abtn ${isActive?'abtn-disable':'abtn-enable'}" title="${isActive?'Disable':'Enable'}" onclick="togglePortalStatus('${h._docId}','${esc(h.name)}','${h.status}')"><i class="fas ${isActive?'fa-ban':'fa-check'}"></i></button>`
            : `<button class="abtn ${isActive?'abtn-disable':'abtn-enable'}" title="${isActive?'Disable':'Enable'}" onclick="toggleCityStatus('${esc(h._city_slug)}',${h._hotel_index},'${esc(h.name)}','${h.status}')"><i class="fas ${isActive?'fa-ban':'fa-check'}"></i></button>`;

        return `<tr>
            <td>
                <div class="hname">${esc(h.city||'—')}</div>
                ${srcLabel}
            </td>
            <td>
                <div style="display:flex;align-items:center;gap:12px;">
                    <img src="${esc(img)}" class="hotel-thumb" onerror="this.src='${fallback}'">
                    <div>
                        <div class="hname">${esc(h.name||'—')}</div>
                        <div class="haddr">${esc(h.address||'')}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="stars-txt">${stars}</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">(${reviewCount} reviews)</div>
            </td>
            <td>
                <span class="badge-status ${bc}">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${statusDot};margin-right:5px;"></span>${bt}
                </span>
                ${h.disabled ? `
                    <div style="margin-top:5px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;color:#991b1b;background:#fef2f2;padding:3px 8px;border-radius:999px;">
                            <i class="fas fa-ban"></i> Refund SLA
                        </span>
                        ${h._source==='portal' ? `
                            <button class="abtn" style="background:#dcfce7;color:#166534;font-size:10.5px;padding:4px 8px;margin-top:4px;white-space:nowrap;" onclick="reEnableHotel('${h._docId}','${esc(h.name)}')">
                                <i class="fas fa-unlock"></i> Re-enable
                            </button>
                        ` : ''}
                    </div>
                ` : ''}
            </td>
            <td>${staffCell}</td>
            <td>${revenueCell}</td>
            <td>${commissionCell}</td>
            <td>
                <div class="actions">
                    <button class="abtn abtn-view" title="View" onclick="viewHotel(${i})"><i class="fas fa-eye"></i></button>
                    ${toggleBtn}
                </div>
            </td>
        </tr>`;
    }).join('');
    // Store filtered list for view modal
    window._filteredHotels = hotels;
}

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function viewHotel(idx) {
    const h = window._filteredHotels[idx]; if (!h) return;
    const img = h._source==='city'
        ? (h.image ? BASE_URL+'/hotel_images/'+h.image.replace(/^\//,'') : '')
        : (h.image||'');
    const staff = h._source==='portal' && h.staff_uid ? (staffMap[h.staff_uid]||'Hotel Staff') : 'N/A (City-Based)';

    Swal.fire({
        title: h.name,
        html: `<div style="text-align:center;padding:24px 0;"><div class="spinner" style="margin:0 auto;"></div><p style="margin-top:12px;color:#64748b;font-size:13px;">Checking live room availability...</p></div>`,
        showConfirmButton: false,
        allowOutsideClick: false,
        width: 480
    });

    // Live-fetch today's confirmed bookings for this hotel to compute room availability
    const today = new Date().toISOString().slice(0, 10);
    let bookedToday = 0;
    try {
        const snap = await db.collection('hotel_bookings').where('hotelId', '==', h.id || '').get();
        snap.forEach(doc => {
            const b = doc.data();
            const status = String(b.bookingStatus || 'confirmed').toLowerCase();
            if (status === 'cancelled') return;
            if (b.arrivalDate && b.departureDate && b.arrivalDate <= today && today < b.departureDate) {
                bookedToday += Number(b.rooms || 1);
            }
        });
    } catch (e) { /* leave bookedToday at 0 on error */ }

    const totalRooms = Number(h.total_rooms || 0);
    let availabilityHtml;
    if (totalRooms > 0) {
        const available  = Math.max(0, totalRooms - bookedToday);
        const availColor = available > 0 ? '#166534' : '#991b1b';
        const availBg    = available > 0 ? '#f0fdf4' : '#fef2f2';
        availabilityHtml = `
            <div style="margin-top:14px;padding:14px 16px;background:${availBg};border-radius:12px;text-align:center;">
                <div style="font-size:12.5px;color:#374151;margin-bottom:4px;">🛏️ Live Room Availability (today)</div>
                <div style="font-size:22px;font-weight:800;color:${availColor};">${available} / ${totalRooms} rooms available</div>
                <div style="font-size:11.5px;color:#6b7280;margin-top:4px;">${bookedToday} room(s) currently booked</div>
            </div>`;
    } else {
        availabilityHtml = `
            <div style="margin-top:14px;padding:14px 16px;background:#fff7ed;border-radius:12px;text-align:center;">
                <div style="font-size:12.5px;color:#9a3412;">🛏️ Room capacity not set for this hotel — ${bookedToday} room(s) currently booked, total unknown.</div>
            </div>`;
    }

    Swal.fire({
        title: h.name,
        html: `<div style="text-align:left;font-size:14px;line-height:2;">
            ${img?`<img src="${img}" style="width:100%;height:150px;object-fit:cover;border-radius:10px;margin-bottom:12px;" onerror="this.style.display='none'">`:''}
            <p><b>City:</b> ${h.city||'—'}</p>
            <p><b>Address:</b> ${h.address||'—'}</p>
            <p><b>Stars:</b> ${'★'.repeat(h.stars||0)} (${h.stars||0}-star)</p>
            <p><b>Rating:</b> ${h.rating||'—'} / 5 &nbsp;|&nbsp; <b>Reviews:</b> ${h.reviews||0}</p>
            <p><b>Price/Night:</b> PKR ${Number(h.price_per_night||0).toLocaleString()}</p>
            ${h._source==='portal'?`<p><b>Check-in/out:</b> ${h.checkin_time||'—'} / ${h.checkout_time||'—'}</p>`:''}
            <p><b>Amenities:</b> ${(h.features||[]).slice(0,8).join(', ')||'—'}</p>
            <p><b>Source:</b> ${h._source==='city'?'🏙️ City-Based Destination':'🏢 Hotel Portal'}</p>
            ${h._source==='portal'?`<p><b>Managed By:</b> <span style="color:#133c96;font-weight:700;">${staff}</span></p>`:''}
            <p><b>Status:</b> ${h.status||'active'}</p>
            ${availabilityHtml}
        </div>`,
        confirmButtonText: 'Close',
        confirmButtonColor: '#133c96',
        width: 520
    });
}

function reEnableHotel(docId, name) {
    Swal.fire({
        title: 'Re-enable ' + name + '?',
        html: 'This hotel was disabled for missing a guest refund SLA. Only re-enable after you\'ve confirmed the refund situation is resolved (either the hotel paid, or you paid the guest directly).',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Yes, Re-enable',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#16a34a'
    }).then(r => {
        if (!r.isConfirmed) return;
        db.collection('hotels').doc(docId).update({ disabled: false, disabledReason: '' })
            .then(() => Swal.fire({icon:'success',title:'Re-enabled!',timer:1500,showConfirmButton:false}))
            .catch(e => Swal.fire('Error', e.message, 'error'));
    });
}

function togglePortalStatus(docId, name, currentStatus) {
    const goingActive = currentStatus !== 'active';
    const newStatus = goingActive ? 'active' : 'inactive';
    Swal.fire({
        title: goingActive ? 'Enable Hotel?' : 'Disable Hotel?',
        html: goingActive
            ? `<b>${name}</b> will become visible to users again.`
            : `<b>${name}</b> will be hidden from users. It will not be deleted.`,
        icon: 'question', showCancelButton: true,
        confirmButtonText: goingActive ? 'Yes, Enable' : 'Yes, Disable',
        cancelButtonText: 'Cancel',
        confirmButtonColor: goingActive ? '#16a34a' : '#c2410c'
    }).then(r => {
        if (!r.isConfirmed) return;
        db.collection('hotels').doc(docId).update({ status: newStatus })
            .then(() => Swal.fire({icon:'success',title: goingActive?'Enabled!':'Disabled!',timer:1500,showConfirmButton:false}))
            .catch(e  => Swal.fire('Error', e.message, 'error'));
    });
}

function toggleCityStatus(slug, index, name, currentStatus) {
    const goingActive = currentStatus !== 'active';
    const newStatus = goingActive ? 'active' : 'inactive';
    Swal.fire({
        title: goingActive ? 'Enable Hotel?' : 'Disable Hotel?',
        html: goingActive
            ? `<b>${name}</b> will become visible to users again.`
            : `<b>${name}</b> will be hidden from users. It will not be deleted.`,
        icon: 'question', showCancelButton: true,
        confirmButtonText: goingActive ? 'Yes, Enable' : 'Yes, Disable',
        cancelButtonText: 'Cancel',
        confirmButtonColor: goingActive ? '#16a34a' : '#c2410c'
    }).then(r => {
        if (!r.isConfirmed) return;
        document.getElementById('ts_slug').value   = slug;
        document.getElementById('ts_index').value  = index;
        document.getElementById('ts_status').value = newStatus;
        Swal.fire({title:'Updating...',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()});
        document.getElementById('toggleCityStatusForm').submit();
    });
}


<?php if ($flash): ?>
Swal.fire({
    icon:  <?= json_encode($flash['type']) ?>,
    title: <?= json_encode($flash['type']==='success'?'Done!':'Error') ?>,
    text:  <?= json_encode($flash['msg']) ?>,
    confirmButtonColor:'#133c96'
});
<?php endif; ?>
</script>
</body>
</html>
