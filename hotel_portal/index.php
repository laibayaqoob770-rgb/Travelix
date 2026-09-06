<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once __DIR__ . '/includes/resolve_hotel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/commission_lib.php';

// Hotel + bookings are loaded server-side with the admin credential, so the
// dashboard renders correctly regardless of what the browser is allowed to
// read from Firestore directly.
$hotel = hp_load_portal_hotel();
hp_enforce_hotel_active($hotel);

$staff    = $_SESSION['hotel_staff'];
$staffUid = $staff['uid'];
$staffName= $staff['first_name'] ?? explode(' ', $staff['name'])[0];
$hotelName= $hotel['name'] ?? '';
$hotelId  = $hotel['id'] ?? '';

$bookings = $hotelId ? hp_get_hotel_bookings(
    $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json',
    FIREBASE_PROJECT_ID,
    $hotelId
) : [];

// Derived booking stats
$today          = date('Y-m-d');
$totalBookings  = count($bookings);
$confirmedCount = 0;
$bookedToday    = 0;
$revenue        = 0.0;

foreach ($bookings as $b) {
    $status = strtolower((string)($b['bookingStatus'] ?? $b['status'] ?? 'confirmed'));

    if ($status === 'confirmed') {
        $confirmedCount++;
        $revenue += (float)($b['hotelPrice'] ?? $b['total_amount'] ?? 0);
    }

    if ($status !== 'cancelled') {
        $arrival   = (string)($b['arrivalDate'] ?? '');
        $departure = (string)($b['departureDate'] ?? '');
        if ($arrival !== '' && $departure !== '' && $arrival <= $today && $today < $departure) {
            $bookedToday += (int)($b['rooms'] ?? 1);
        }
    }
}

// Payout ledger — what Travelix owes this hotel (100% of price), tracked
// per booking so paid / due are distinct.
$saPath   = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
$payouts  = $hotelId ? hp_get_payout_payments($saPath, FIREBASE_PROJECT_ID, $hotelId) : [];
$ledger   = hp_build_payout_ledger($bookings, $payouts);
$commission = $ledger['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Hotel Dashboard — Travelix Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/css/travelix_notifications.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4fa;min-height:100vh;}

/* ── Topbar ── */
.topbar{background:linear-gradient(135deg,#0f3460,#1a5276,#2471a3);color:#fff;padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 20px rgba(15,52,96,.3);position:sticky;top:0;z-index:100;}
.topbar-brand{display:flex;align-items:center;gap:12px;}
.topbar-brand-icon{width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.topbar-brand-text{font-size:17px;font-weight:800;}
.topbar-brand-sub{font-size:11px;opacity:.72;margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.staff-pill{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.12);border-radius:12px;padding:7px 14px;}
.staff-avatar{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;}
.staff-name{font-size:13px;font-weight:700;}
.staff-role{font-size:10px;opacity:.7;}
.logout-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,.25);border:1px solid rgba(239,68,68,.35);color:#fff;padding:8px 16px;border-radius:10px;font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;transition:.2s;}
.logout-btn:hover{background:rgba(239,68,68,.45);color:#fff;}

/* ── Portal Sub-Nav ── */
.portal-subnav{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;display:flex;gap:4px;overflow-x:auto;position:sticky;top:64px;z-index:99;}
.portal-subnav a{display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13.5px;font-weight:700;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:.2s;}
.portal-subnav a:hover{color:#0f3460;background:#f8fafc;}
.portal-subnav a.active{color:#0f3460;border-bottom-color:#0f3460;}

/* ── Page ── */
.page-body{max-width:1200px;margin:0 auto;padding:28px 16px;}

/* ── Hero Banner ── */
.hotel-hero{border-radius:22px;overflow:hidden;position:relative;min-height:340px;display:flex;align-items:flex-end;margin-bottom:20px;box-shadow:0 8px 30px rgba(0,0,0,.15);}
.hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(to right, rgba(0,0,0,.72) 0%, rgba(0,0,0,.3) 60%, rgba(0,0,0,.05) 100%);}
.hero-content{position:relative;z-index:2;padding:36px 40px;flex:1;}
.hero-badge{display:inline-flex;align-items:center;gap:6px;background:#dcfce7;color:#166534;padding:5px 14px;border-radius:999px;font-size:12px;font-weight:700;margin-bottom:14px;}
.hero-badge.draft{background:#fef9c3;color:#854d0e;}
.hero-badge.inactive{background:#fee2e2;color:#991b1b;}
.hero-badge-dot{width:8px;height:8px;border-radius:50%;background:#16a34a;}
.hero-badge.draft .hero-badge-dot{background:#d97706;}
.hero-badge.inactive .hero-badge-dot{background:#dc2626;}
.hero-name{font-size:38px;font-weight:800;color:#fff;margin-bottom:8px;line-height:1.15;text-shadow:0 2px 8px rgba(0,0,0,.3);}
.hero-address{font-size:14px;color:rgba(255,255,255,.85);display:flex;align-items:flex-start;gap:6px;margin-bottom:24px;max-width:420px;line-height:1.5;}
.hero-address i{margin-top:2px;color:#60a5fa;flex-shrink:0;}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap;}
.hero-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;border:none;transition:.2s;text-decoration:none;}
.hero-btn-primary{background:#0f3460;color:#fff;}
.hero-btn-primary:hover{background:#1a5276;color:#fff;}
.hero-btn-outline{background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.5);backdrop-filter:blur(4px);}
.hero-btn-outline:hover{background:rgba(255,255,255,.25);color:#fff;}

/* ── Stats Bar ── */
.stats-bar{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
@media(max-width:900px){.stats-bar{grid-template-columns:repeat(3,1fr);}}
@media(max-width:560px){.stats-bar{grid-template-columns:repeat(2,1fr);}}
.stat-card{background:#fff;border-radius:16px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;}
.stat-icon-wrap{width:46px;height:46px;border-radius:14px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;color:#2471a3;}
.stat-lbl{font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
.stat-val{font-size:17px;font-weight:800;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── Two-col layout ── */
.dashboard-grid{display:grid;grid-template-columns:1fr 360px;gap:20px;}
@media(max-width:900px){.dashboard-grid{grid-template-columns:1fr;}}

/* ── Cards ── */
.dash-card{background:#fff;border-radius:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:24px;margin-bottom:20px;}
.dash-card:last-child{margin-bottom:0;}
.card-head{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
.card-head-icon{width:38px;height:38px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:18px;color:#2471a3;}
.card-head-text h3{font-size:16px;font-weight:800;color:#1e293b;margin:0;}
.card-head-text p{font-size:12px;color:#94a3b8;margin:0;}

/* ── Overview metrics ── */
.overview-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.overview-metrics.cols-4{grid-template-columns:repeat(4,1fr);}
.metric-box{background:#f8faff;border-radius:14px;padding:18px 14px;text-align:center;border:1px solid #e8edf5;}
.metric-icon{font-size:28px;margin-bottom:8px;}
.metric-val{font-size:26px;font-weight:800;color:#1e293b;margin-bottom:2px;}
.metric-lbl{font-size:12px;color:#64748b;font-weight:600;}
.metric-sub{font-size:11px;color:#94a3b8;margin-top:2px;}

/* ── Room Availability ── */
.room-avail-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.room-avail-box{border-radius:14px;padding:18px 14px;text-align:center;border:1px solid #e8edf5;}
.room-avail-box.total{background:#f8faff;}
.room-avail-box.booked{background:#fff7ed;}
.room-avail-box.remaining{background:#f0fdf4;}
.room-avail-val{font-size:26px;font-weight:800;color:#1e293b;margin-bottom:2px;}
.room-avail-lbl{font-size:12px;color:#64748b;font-weight:600;}

/* ── Reviews ── */
.review-head-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.view-all-link{font-size:13px;color:#2471a3;font-weight:600;text-decoration:none;}
.view-all-link:hover{text-decoration:underline;}
.review-item{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid #f1f4f9;}
.review-item:last-child{border-bottom:none;padding-bottom:0;}
.rev-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;color:#fff;flex-shrink:0;}
.rev-body{flex:1;min-width:0;}
.rev-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.rev-name{font-size:14px;font-weight:700;color:#1e293b;}
.rev-time{font-size:11px;color:#94a3b8;}
.rev-stars{color:#f59e0b;font-size:13px;margin-bottom:4px;}
.rev-text{font-size:13px;color:#475569;line-height:1.5;}

/* ── Quick Actions ── */
.action-item{display:flex;align-items:center;gap:14px;padding:16px;border-radius:14px;border:1px solid #e8edf5;cursor:pointer;transition:.2s;text-decoration:none;margin-bottom:10px;}
.action-item:last-child{margin-bottom:0;}
.action-item:hover{background:#f8faff;border-color:#c7d9f8;transform:translateX(3px);}
.action-icon{width:44px;height:44px;border-radius:14px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:20px;color:#2471a3;flex-shrink:0;}
.action-text{flex:1;}
.action-text h4{font-size:14px;font-weight:700;color:#1e293b;margin:0 0 2px;}
.action-text p{font-size:12px;color:#94a3b8;margin:0;}
.action-arrow{color:#94a3b8;font-size:14px;}

/* ── Spinner / Loading ── */
.loading-area{text-align:center;padding:80px 20px;}
.spinner{width:38px;height:38px;border:3px solid #e2e8f0;border-top-color:#2471a3;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 14px;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── First-time empty ── */
.first-time-wrap{background:#fff;border-radius:24px;box-shadow:0 4px 20px rgba(0,0,0,.07);overflow:hidden;}
.ft-hero{background:linear-gradient(135deg,#0f3460,#1a5276,#2471a3);padding:52px 40px;text-align:center;color:#fff;position:relative;overflow:hidden;}
.ft-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:320px;height:320px;background:radial-gradient(circle,rgba(255,255,255,.07),transparent 70%);border-radius:50%;}
.ft-icon{font-size:64px;margin-bottom:18px;position:relative;}
.ft-hero h2{font-size:26px;font-weight:800;margin-bottom:8px;position:relative;}
.ft-hero p{font-size:15px;opacity:.82;max-width:460px;margin:0 auto;position:relative;}
.ft-body{padding:36px 40px;}
.ft-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:32px;}
@media(max-width:640px){.ft-steps{grid-template-columns:1fr;}}
.ft-step{text-align:center;padding:24px 16px;background:#f8faff;border-radius:18px;border:1px solid #e8edf5;}
.ft-step-num{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;font-size:16px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.ft-step-icon{font-size:28px;margin-bottom:10px;}
.ft-step h4{font-size:14px;font-weight:800;color:#1e293b;margin-bottom:6px;}
.ft-step p{font-size:12px;color:#64748b;line-height:1.6;margin:0;}
.ft-contact-box{background:linear-gradient(135deg,#f0f7ff,#e8f0fe);border:1px solid #c7d9f8;border-radius:18px;padding:24px 28px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.ft-contact-icon{font-size:40px;flex-shrink:0;}
.ft-contact-text h4{font-size:16px;font-weight:800;color:#1e293b;margin-bottom:4px;}
.ft-contact-text p{font-size:13px;color:#64748b;margin:0;}
.ft-contact-btn{margin-left:auto;background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:.2s;}
.ft-contact-btn:hover{opacity:.9;color:#fff;}

/* Notice */
.notice-bar{background:#fef9c3;border:1px solid #fde047;border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:#854d0e;font-weight:600;}

/* ══════════════════════════════════════════════════════════
   Mobile — every section below is a stacked card, nothing
   scrolls the page horizontally.
   ══════════════════════════════════════════════════════════ */
@media(max-width:640px){
    html,body{overflow-x:hidden;}

    /* Topbar: icon-only actions, name/role dropped so the row never
       has to fit more content than a phone screen can hold. */
    .topbar{padding:0 12px;height:58px;}
    .topbar-brand{gap:8px;min-width:0;}
    .topbar-brand-icon{width:34px;height:34px;font-size:16px;flex-shrink:0;}
    .topbar-brand-text{font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
    .topbar-brand-sub{display:none;}
    .topbar-right{gap:6px;}
    .staff-pill{padding:0;background:none;}
    .staff-pill .staff-avatar{width:30px;height:30px;font-size:12.5px;}
    .staff-pill>div:last-child{display:none;}
    .logout-btn{padding:8px 10px;font-size:0;gap:0;}
    .logout-btn i{font-size:14px;}
    .logout-btn .btn-label{display:none;}

    .portal-subnav{padding:0 10px;top:58px;}
    .portal-subnav a{padding:12px 12px;font-size:12.5px;}

    .page-body{padding:16px 12px;}

    /* Hero */
    .hotel-hero{border-radius:16px;min-height:auto;margin-bottom:14px;}
    .hero-content{padding:20px 18px;width:100%;}
    .hero-name{font-size:24px;margin-bottom:6px;}
    .hero-address{font-size:12.5px;max-width:100%;margin-bottom:16px;}
    .hero-actions{flex-direction:column;gap:8px;}
    .hero-btn{width:100%;justify-content:center;padding:12px 18px;}

    /* Stats bar — one clean card per row */
    .stats-bar{grid-template-columns:1fr !important;gap:10px;margin-bottom:14px;}
    .stat-card{padding:14px 16px;}

    .dashboard-grid{gap:14px;}
    .dash-card{border-radius:16px;padding:16px;margin-bottom:14px;}
    .card-head-text h3{font-size:14.5px;}

    /* Metric grids — stack to one column so numbers/labels never squeeze */
    .overview-metrics,
    .room-avail-metrics{grid-template-columns:1fr;gap:10px;}
    .metric-box,
    .room-avail-box{padding:14px;display:flex;align-items:center;gap:12px;text-align:left;}
    .metric-icon{margin-bottom:0;}
    .metric-val,
    .room-avail-val{margin-bottom:0;}

    .review-item{gap:10px;}
    .action-item{padding:14px;}

    .ft-hero{padding:36px 22px;}
    .ft-hero h2{font-size:21px;}
    .ft-body{padding:24px 18px;}
    .ft-contact-box{padding:18px;flex-direction:column;text-align:center;}
    .ft-contact-btn{margin-left:0;width:100%;justify-content:center;}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-brand-icon">🏨</div>
        <div>
            <div class="topbar-brand-text">Hotel Management Portal</div>
            <div class="topbar-brand-sub"><?= htmlspecialchars($hotelName ?: 'Travelix Partner') ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="travelix-notification-wrapper" id="travelixNotificationWrapper">
            <button type="button" class="travelix-notification-btn" id="travelixNotificationToggle" title="Notifications" aria-label="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="travelix-notification-badge" id="travelixNotificationBadge">0</span>
            </button>

            <div class="travelix-notification-panel" id="travelixNotificationPanel">
                <div class="travelix-notification-header">
                    <div>
                        <h6>Notifications</h6>
                        <span class="travelix-notification-count-text" id="travelixNotificationCountText">0 new</span>
                    </div>
                    <div class="travelix-notification-header-actions">
                        <button type="button" class="travelix-refresh-notification-btn" id="travelixRefreshNotificationsBtn" title="Refresh notifications" aria-label="Refresh notifications">
                            <i class="fa-solid fa-arrow-rotate-right"></i>
                        </button>
                        <button type="button" class="travelix-read-all-btn" id="travelixReadAllBtn">Read all</button>
                    </div>
                </div>
                <div class="travelix-notification-list" id="travelixNotificationList">
                    <div class="travelix-notification-empty">Loading notifications...</div>
                </div>
            </div>
        </div>

        <div class="staff-pill">
            <div class="staff-avatar"><?= strtoupper(substr($staffName,0,1)) ?></div>
            <div>
                <div class="staff-name"><?= htmlspecialchars($staffName) ?></div>
                <div class="staff-role">Hotel Staff</div>
            </div>
        </div>
        <button type="button" class="logout-btn" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.28);" onclick="openChangePassword()">
            <i class="fas fa-key"></i> <span class="btn-label">Change Password</span>
        </button>
        <a href="/travelix/hotel_portal/logout.php" class="logout-btn" onclick="return confirmLogout()">
            <i class="fas fa-sign-out-alt"></i> <span class="btn-label">Logout</span>
        </a>
    </div>
</div>

<div class="portal-subnav">
    <a href="/travelix/hotel_portal/index.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="/travelix/hotel_portal/hotel_bookings.php<?= $hotelId ? '?hotel_id=' . urlencode($hotelId) : '' ?>"><i class="fas fa-calendar-alt"></i> Bookings</a>
    <a href="/travelix/hotel_portal/commission.php"><i class="fas fa-money-bill-wave"></i> Payouts</a>
    <a href="/travelix/hotel_portal/refunds.php"><i class="fas fa-rotate-left"></i> Refunds</a>
    <a href="/travelix/hotel_portal/discounts.php"><i class="fas fa-tag"></i> Discounts</a>
    <a href="/travelix/hotel_portal/edit_hotel.php<?= $hotelId ? '?edit=' . urlencode($hotelId) : '' ?>"><i class="fas fa-pen"></i> Edit Hotel</a>
</div>

<div class="page-body">

    <?php if (($_GET['notice'] ?? '') === 'no_add'): ?>
    <div class="notice-bar">
        <span style="font-size:17px;">ℹ️</span>
        Hotel staff cannot register new hotels. Please contact the Travelix Admin.
    </div>
    <?php endif; ?>

    <div id="mainContent">
        <div class="loading-area">
            <div class="spinner"></div>
            <div style="color:#94a3b8;font-size:14px;">Loading your hotel...</div>
        </div>
    </div>

</div>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
<script src="/travelix/assets/js/travelix_portal_notifications.js"></script>
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
const db        = firebase.firestore();
const STAFF_UID   = "<?= htmlspecialchars($staffUid) ?>";
const STAFF_EMAIL = "<?= htmlspecialchars($staff['email'] ?? '') ?>";
const HOTEL_ID  = "<?= htmlspecialchars($hotelId) ?>";
const BASE_URL  = "<?= $baseUrl ?>";

if (HOTEL_ID) {
    window.travelixInitPortalNotifications({
        db: db,
        filters: [['audience', '==', 'hotel'], ['hotelId', '==', HOTEL_ID]],
        manageUrl: '/travelix/hotel_portal/notifications.php'
    });
}

// Sample reviews — replace with real Firestore reviews if available
const SAMPLE_REVIEWS = [
    { name:'Ayesha Khan',  stars:5, text:'Excellent stay! Great service and beautiful property.', time:'2 days ago',  color:'#2471a3' },
    { name:'Usman Ahmed',  stars:4, text:'Very comfortable rooms and friendly staff. Will visit again.', time:'1 week ago',  color:'#059669' },
    { name:'Sara Malik',   stars:5, text:'Amazing experience, the amenities were top-notch!', time:'2 weeks ago', color:'#7c3aed' },
];

// Hotel + booking stats come straight from the server (fetched with the admin
// credential), so rendering never depends on what this browser is allowed to
// read from Firestore directly.
const HOTEL = <?= json_encode($hotel ?: null, JSON_UNESCAPED_SLASHES) ?>;
const STATS = <?= json_encode([
    'totalBookings'  => $totalBookings,
    'confirmedCount' => $confirmedCount,
    'bookedToday'    => $bookedToday,
    'revenue'        => $revenue,
    'commission'     => $commission,
], JSON_UNESCAPED_SLASHES) ?>;
const PAYOUT = <?= json_encode([
    'total'   => round($ledger['total'], 2),
    'paid'    => round($ledger['paid'], 2),
    'pending' => round($ledger['pending'], 2),
    'due'     => round($ledger['due'], 2),
    'counts'  => $ledger['counts'],
], JSON_UNESCAPED_SLASHES) ?>;

function money(n) { return 'PKR ' + Math.round(Number(n) || 0).toLocaleString(); }

const area = document.getElementById('mainContent');
if (HOTEL) {
    renderDashboard(area, HOTEL);
} else {
    renderEmpty(area);
}

function renderEmpty(area) {
    area.innerHTML = `
    <div class="first-time-wrap">
        <div class="ft-hero">
            <div class="ft-icon">🏨</div>
            <h2>Welcome to Travelix Hotel Portal</h2>
            <p>Your account is ready. Once the Travelix Admin assigns a hotel to you, it will appear here and you can start managing it.</p>
        </div>
        <div class="ft-body">
            <div class="ft-steps">
                <div class="ft-step">
                    <div class="ft-step-num">1</div>
                    <div class="ft-step-icon">📋</div>
                    <h4>Admin Assigns Hotel</h4>
                    <p>The Travelix Admin registers your hotel and links it to your account.</p>
                </div>
                <div class="ft-step">
                    <div class="ft-step-num">2</div>
                    <div class="ft-step-icon">✏️</div>
                    <h4>You Fill the Details</h4>
                    <p>Edit hotel info, pricing, amenities and images from this dashboard.</p>
                </div>
                <div class="ft-step">
                    <div class="ft-step-num">3</div>
                    <div class="ft-step-icon">🌍</div>
                    <h4>Goes Live on Travelix</h4>
                    <p>Your hotel appears instantly on the Travelix booking platform.</p>
                </div>
            </div>
            <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:14px;padding:16px 20px;margin-bottom:18px;">
                <div style="font-size:13.5px;color:#92400e;font-weight:700;margin-bottom:4px;">
                    <i class="fas fa-user-circle"></i> You are signed in as: <span style="font-family:monospace;">${STAFF_EMAIL || '(unknown)'}</span>
                </div>
                <div style="font-size:12.5px;color:#a16207;">
                    No hotel is linked to this account. If you manage a hotel, make sure you are
                    signing in with the <b>hotel staff email</b> the admin registered — not your
                    personal or admin account.
                </div>
            </div>

            <div class="ft-contact-box">
                <div class="ft-contact-icon">📞</div>
                <div class="ft-contact-text">
                    <h4>No hotel assigned yet?</h4>
                    <p>Contact the Travelix Admin to get your hotel registered and linked to this account.</p>
                </div>
                <a href="mailto:admin@travelix.com" class="ft-contact-btn">
                    <i class="fas fa-envelope"></i> Contact Admin
                </a>
            </div>
        </div>
    </div>`;
}

function renderDashboard(area, h) {
    let img = h.image || 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200';
    // City-based hotels store relative image paths — prepend base URL
    if (img && !img.startsWith('http') && !img.startsWith('//')) {
        img = BASE_URL + '/hotel_images/' + img.replace(/^\//, '');
    }
    const sc        = h.status==='active' ? '' : h.status==='draft' ? 'draft' : 'inactive';
    const st        = h.status==='active' ? 'Active' : h.status==='draft' ? 'Draft' : 'Inactive';
    const price     = h.price_per_night ? 'PKR '+Number(h.price_per_night).toLocaleString() : '—';
    const checkin   = h.checkin_time  || '14:00';
    const checkout  = h.checkout_time || '12:00';
    const rooms     = h.total_rooms ? h.total_rooms+' Rooms' : '—';
    const firstAmen = (h.features||[])[0] || '—';
    const rating    = h.rating ? parseFloat(h.rating).toFixed(1) : '0.0';
    const reviews   = h.reviews || 0;
    const satisfy   = h.rating ? Math.round((parseFloat(h.rating)/5)*100) : 0;
    const stars     = '★'.repeat(Math.min(5,parseInt(h.stars)||0))+'☆'.repeat(Math.max(0,5-(parseInt(h.stars)||0)));

    const reviewsHTML = SAMPLE_REVIEWS.map(r => `
        <div class="review-item">
            <div class="rev-avatar" style="background:${r.color}">${r.name.charAt(0)}</div>
            <div class="rev-body">
                <div class="rev-top">
                    <span class="rev-name">${r.name}</span>
                    <span class="rev-time">${r.time}</span>
                </div>
                <div class="rev-stars">${'★'.repeat(r.stars)}${'☆'.repeat(5-r.stars)}</div>
                <div class="rev-text">${r.text}</div>
            </div>
        </div>`).join('');

    area.innerHTML = `

        <!-- Hero -->
        <div class="hotel-hero">
            <img class="hero-bg" src="${esc(img)}" onerror="this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200'">
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <div class="hero-badge ${sc}">
                    <span class="hero-badge-dot"></span> ${st}
                </div>
                <div class="hero-name">${esc(h.name||'Your Hotel')}</div>
                <div class="hero-address">
                    <i class="fas fa-map-marker-alt"></i>
                    ${esc(h.city||'')}${h.address ? ' · '+esc(h.address) : ''}
                </div>
                <div class="hero-actions">
                    <button class="hero-btn hero-btn-primary" onclick="viewHotel('${h.id}')">
                        <i class="fas fa-eye"></i> View Hotel Details
                    </button>
                    <a href="/travelix/hotel_portal/edit_hotel.php?edit=${h.id}" class="hero-btn hero-btn-outline">
                        <i class="fas fa-pen"></i> Edit Hotel
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon-wrap"><i class="fas fa-tag"></i></div>
                <div><div class="stat-lbl">Price / Night</div><div class="stat-val">${price}</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
                <div><div class="stat-lbl">Check-in</div><div class="stat-val">${checkin}</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap"><i class="fas fa-calendar-times"></i></div>
                <div><div class="stat-lbl">Check-out</div><div class="stat-val">${checkout}</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap"><i class="fas fa-bed"></i></div>
                <div><div class="stat-lbl">Total Rooms</div><div class="stat-val">${rooms}</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap"><i class="fas fa-wifi"></i></div>
                <div><div class="stat-lbl">Amenities</div><div class="stat-val">${esc(firstAmen)}</div></div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">

            <!-- LEFT -->
            <div>
                <!-- Hotel Overview -->
                <div class="dash-card">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-hotel"></i></div>
                        <div class="card-head-text">
                            <h3>Hotel Overview</h3>
                            <p>Quick overview of your hotel performance</p>
                        </div>
                    </div>
                    <div class="overview-metrics">
                        <div class="metric-box">
                            <div class="metric-icon">⭐</div>
                            <div class="metric-val">${rating}</div>
                            <div class="metric-lbl">Hotel Rating</div>
                            <div class="metric-sub">${reviews} reviews</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#059669;">👥</div>
                            <div class="metric-val">${satisfy}%</div>
                            <div class="metric-lbl">Guest Satisfaction</div>
                            <div class="metric-sub">${satisfy >= 80 ? 'Excellent' : satisfy >= 60 ? 'Good' : 'Needs Work'}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#2471a3;">📈</div>
                            <div class="metric-val">1</div>
                            <div class="metric-lbl">Active Hotel</div>
                            <div class="metric-sub">All systems operational</div>
                        </div>
                    </div>
                </div>

                <!-- Room Availability -->
                <div class="dash-card">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-bed"></i></div>
                        <div class="card-head-text">
                            <h3>Room Availability</h3>
                            <p>Live count, updated from current bookings</p>
                        </div>
                    </div>
                    <div class="room-avail-metrics" id="roomAvailMetrics-${h.id}">
                        <div class="room-avail-box total">
                            <div class="room-avail-val">${h.total_rooms || 0}</div>
                            <div class="room-avail-lbl">Total Rooms</div>
                        </div>
                        <div class="room-avail-box booked">
                            <div class="room-avail-val" id="roomBooked-${h.id}">—</div>
                            <div class="room-avail-lbl">Booked Today</div>
                        </div>
                        <div class="room-avail-box remaining">
                            <div class="room-avail-val" id="roomRemaining-${h.id}">—</div>
                            <div class="room-avail-lbl">Available Now</div>
                        </div>
                    </div>
                </div>

                <!-- Payouts from Travelix -->
                <div class="dash-card">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="card-head-text">
                            <h3>Payouts from Travelix</h3>
                            <p>Your full price for every confirmed booking (100%)</p>
                        </div>
                    </div>
                    <div class="overview-metrics cols-4">
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#2471a3;">💰</div>
                            <div class="metric-val" style="font-size:20px;">${money(PAYOUT.total)}</div>
                            <div class="metric-lbl">Total Owed to You</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#16a34a;">✅</div>
                            <div class="metric-val" style="font-size:20px;color:#166534;">${money(PAYOUT.paid)}</div>
                            <div class="metric-lbl">Paid Out</div>
                            <div class="metric-sub">${PAYOUT.counts.paid} booking(s)</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#f59e0b;">⏱️</div>
                            <div class="metric-val" style="font-size:20px;color:#92400e;">${money(PAYOUT.pending)}</div>
                            <div class="metric-lbl">Awaiting Your Confirmation</div>
                            <div class="metric-sub">${PAYOUT.counts.pending} booking(s)</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-icon" style="font-size:26px;color:#dc2626;">⏳</div>
                            <div class="metric-val" style="font-size:20px;color:#991b1b;">${money(PAYOUT.due)}</div>
                            <div class="metric-lbl">Not Yet Sent</div>
                            <div class="metric-sub">${PAYOUT.counts.due} booking(s)</div>
                        </div>
                    </div>

                    <a href="/travelix/hotel_portal/commission.php"
                       style="display:block;margin-top:16px;text-align:center;background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;padding:12px;border-radius:12px;font-weight:800;font-size:14px;text-decoration:none;">
                        <i class="fas fa-list-ul"></i> View Payouts &amp; Proof
                    </a>
                </div>

                <!-- Recent Reviews -->
                <div class="dash-card">
                    <div class="review-head-row">
                        <div class="card-head" style="margin-bottom:0;">
                            <div class="card-head-icon"><i class="fas fa-comment-dots"></i></div>
                            <div class="card-head-text">
                                <h3>Recent Reviews</h3>
                            </div>
                        </div>
                        <a href="#" class="view-all-link">View All Reviews</a>
                    </div>
                    ${reviewsHTML}
                </div>
            </div>

            <!-- RIGHT -->
            <div>
                <div class="dash-card">
                    <div class="card-head">
                        <div class="card-head-icon"><i class="fas fa-bolt"></i></div>
                        <div class="card-head-text">
                            <h3>Quick Actions</h3>
                            <p>Manage your hotel operations</p>
                        </div>
                    </div>

                    <a href="/travelix/hotel_portal/edit_hotel.php?edit=${h.id}&step=3" class="action-item">
                        <div class="action-icon"><i class="fas fa-bed"></i></div>
                        <div class="action-text">
                            <h4>Manage Rooms &amp; Pricing</h4>
                            <p>Update rooms, price per night &amp; policies</p>
                        </div>
                        <i class="fas fa-chevron-right action-arrow"></i>
                    </a>

                    <a href="/travelix/hotel_portal/hotel_bookings.php?hotel_id=${h.id}" class="action-item">
                        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="action-text">
                            <h4>View Bookings</h4>
                            <p id="bookings-sub-${h.id}">Loading bookings...</p>
                        </div>
                        <i class="fas fa-chevron-right action-arrow"></i>
                    </a>

                    <a href="/travelix/hotel_portal/edit_hotel.php?edit=${h.id}&step=5" class="action-item">
                        <div class="action-icon"><i class="fas fa-images"></i></div>
                        <div class="action-text">
                            <h4>Photos &amp; Gallery</h4>
                            <p>Update hotel images and photo gallery</p>
                        </div>
                        <i class="fas fa-chevron-right action-arrow"></i>
                    </a>
                </div>
            </div>

        </div>`;

    // Fill in the server-computed booking stats
    applyStats(h);
}

function applyStats(h) {
    const hotelId = h.id;

    const subEl = document.getElementById('bookings-sub-' + hotelId);
    if (subEl) {
        subEl.innerHTML = STATS.totalBookings === 0
            ? 'No bookings yet'
            : `<b style="color:#0f3460">${STATS.totalBookings}</b> total &nbsp;·&nbsp; <b style="color:#059669">${STATS.confirmedCount}</b> confirmed`;
    }

    const bookedEl    = document.getElementById('roomBooked-' + hotelId);
    const remainingEl = document.getElementById('roomRemaining-' + hotelId);
    if (bookedEl && remainingEl) {
        const totalRooms = Number(h.total_rooms || 0);
        bookedEl.textContent    = STATS.bookedToday;
        remainingEl.textContent = Math.max(0, totalRooms - STATS.bookedToday);
    }

    // Commission figures are rendered straight from COMMISSION in the card markup.
}

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function viewHotel(id) {
    db.collection('hotels').doc(id).get().then(doc => {
        if (!doc.exists) return;
        const h = doc.data();
        Swal.fire({
            title: h.name,
            html: `<div style="text-align:left;font-size:14px;line-height:2;">
                <img src="${h.image||''}" style="width:100%;height:160px;object-fit:cover;border-radius:12px;margin-bottom:12px;" onerror="this.style.display='none'">
                <p><b>City:</b> ${h.city||'—'}</p>
                <p><b>Address:</b> ${h.address||'—'}</p>
                <p><b>Stars:</b> ${'★'.repeat(h.stars||0)} (${h.stars||0}-star)</p>
                <p><b>Rating:</b> ${h.rating||'—'} / 5 | <b>Reviews:</b> ${h.reviews||0}</p>
                <p><b>Price / Night:</b> PKR ${Number(h.price_per_night||0).toLocaleString()}</p>
                <p><b>Check-in:</b> ${h.checkin_time||'—'} &nbsp; <b>Check-out:</b> ${h.checkout_time||'—'}</p>
                <p><b>Rooms:</b> ${h.total_rooms||'—'}</p>
                <p><b>Amenities:</b> ${(h.features||[]).join(', ')||'—'}</p>
                <p><b>Description:</b> ${h.description||'—'}</p>
            </div>`,
            confirmButtonText: 'Close',
            confirmButtonColor: '#0f3460',
            width: 520
        });
    });
}

async function openChangePassword() {
    const { value: form } = await Swal.fire({
        title: 'Change Password',
        html: `
            <div style="text-align:left;">
                <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:5px;">Current Password</label>
                <input type="password" id="cpCurrent" class="swal2-input" style="margin:0 0 14px;width:100%;" placeholder="Enter current password">

                <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:5px;">New Password</label>
                <input type="password" id="cpNew" class="swal2-input" style="margin:0 0 14px;width:100%;" placeholder="At least 6 characters">

                <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:5px;">Confirm New Password</label>
                <input type="password" id="cpConfirm" class="swal2-input" style="margin:0;width:100%;" placeholder="Re-enter new password">
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Update Password',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0f3460',
        focusConfirm: false,
        preConfirm: () => {
            const current = document.getElementById('cpCurrent').value;
            const next    = document.getElementById('cpNew').value;
            const confirm = document.getElementById('cpConfirm').value;

            if (!current)            { Swal.showValidationMessage('Enter your current password.'); return false; }
            if (next.length < 6)     { Swal.showValidationMessage('New password must be at least 6 characters.'); return false; }
            if (next !== confirm)    { Swal.showValidationMessage('New passwords do not match.'); return false; }
            if (next === current)    { Swal.showValidationMessage('New password must be different.'); return false; }

            return { current, next };
        }
    });

    if (!form) return;

    Swal.fire({ title: 'Updating password...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/travelix/hotel_portal/ajax/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ current_password: form.current, new_password: form.next })
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Password Updated', text: data.message, confirmButtonColor: '#0f3460' });
        } else {
            Swal.fire({ icon: 'error', title: 'Could Not Update', text: data.message, confirmButtonColor: '#0f3460' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Please try again.', confirmButtonColor: '#0f3460' });
    }
}

function confirmLogout() {
    Swal.fire({
        title: 'Sign Out?',
        text: 'You will be logged out of the Hotel Portal.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
    }).then(r => { if (r.isConfirmed) window.location.href = '/travelix/hotel_portal/logout.php'; });
    return false;
}
</script>
</body>
</html>
