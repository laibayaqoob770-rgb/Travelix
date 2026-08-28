<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$hotelId         = trim((string)($_GET['hotelId'] ?? ''));
$hotelName       = trim((string)($_GET['hotelName'] ?? ''));
$hotelPrice      = (float)($_GET['hotelPrice'] ?? 0);
$hotelPriceNight = (float)($_GET['hotelPriceNight'] ?? 0);
$hotelImage      = trim((string)($_GET['hotelImage'] ?? ''));
$hotelAddress    = trim((string)($_GET['hotelAddress'] ?? ''));
$hotelRating     = (float)($_GET['hotelRating'] ?? 0);
$hotelReviews    = (int)($_GET['hotelReviews'] ?? 0);
$hotelStars      = (int)($_GET['hotelStars'] ?? 0);

$city            = trim((string)($_GET['toCity'] ?? ''));
$arrivalDate     = trim((string)($_GET['arrivalDate'] ?? ''));
$departureDate   = trim((string)($_GET['departureDate'] ?? ''));
$rooms           = max(1, (int)($_GET['rooms'] ?? 1));
$adults          = max(1, (int)($_GET['adults'] ?? 1));
$children        = max(0, (int)($_GET['children'] ?? 0));
$nights          = max(1, (int)($_GET['nights'] ?? 1));
$source          = trim((string)($_GET['source'] ?? ''));
$returnStep      = trim((string)($_GET['returnStep'] ?? '3'));
$sessionId       = trim((string)($_GET['sessionId'] ?? ''));

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateForView(string $date): string {
    if ($date === '') return '-';
    try { return (new DateTime($date))->format('M j, Y'); }
    catch (Throwable $ex) { return $date; }
}

$hasHotel = ($hotelId !== '' || $hotelName !== '');

// Server-fetched so the guest sees the real cancellation policy before
// confirming — this page otherwise only has whatever was passed in the URL.
$cancellationPolicy = null;
if ($hotelId !== '') {
    require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/firestore_admin.php';
    $saPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
    $hotelDoc = hp_firestore_get($saPath, FIREBASE_PROJECT_ID, 'hotels/' . $hotelId);
    $cancellationPolicy = is_array($hotelDoc['cancellationPolicy'] ?? null) ? $hotelDoc['cancellationPolicy'] : null;
}

function refundPolicySummary(?array $policy): string {
    if (!$policy) {
        return 'This hotel has not set a cancellation policy yet — contact support if you need to cancel.';
    }
    $type = (string)($policy['type'] ?? 'free');
    $windowHours = (float)($policy['windowHours'] ?? 24);
    $refundPercent = (float)($policy['refundPercent'] ?? 100);

    if ($type === 'non_refundable') {
        return 'This booking is <b>non-refundable</b> — no refund applies if you cancel.';
    }
    if ($type === 'free') {
        return "<b>Free cancellation</b> — cancel more than {$windowHours} hours before check-in for a full refund.";
    }
    return "Cancel more than {$windowHours} hours before check-in for a <b>{$refundPercent}% refund</b>. Cancelling later gets no refund.";
}

$backQuery = http_build_query([
    'toCity' => $city, 'arrivalDate' => $arrivalDate,
    'departureDate' => $departureDate, 'rooms' => $rooms,
    'adults' => $adults, 'children' => $children,
    'source' => $source, 'returnStep' => $returnStep
]);

$backToResultsUrl = '/travelix/hotel/searched_hotel.php?' . $backQuery;
$backToTripUrl    = '/travelix/trips/create_trip.php';

$mgmtUrlBase = '/travelix/hotel/hotel_management_form.php?' . http_build_query([
    'sessionId'       => $sessionId,
    'hotelId'         => $hotelId,
    'hotelName'       => $hotelName,
    'hotelPriceNight' => $hotelPriceNight,
    'hotelImage'      => $hotelImage,
    'hotelAddress'    => $hotelAddress,
    'hotelRating'     => $hotelRating,
    'hotelReviews'    => $hotelReviews,
    'hotelStars'      => $hotelStars,
    'toCity'          => $city,
    'arrivalDate'     => $arrivalDate,
    'departureDate'   => $departureDate,
    'rooms'           => $rooms,
    'adults'          => $adults,
    'children'        => $children,
    'nights'          => $nights,
]);

$bookingPayload = [
    'hotelId' => $hotelId, 'hotelName' => $hotelName,
    'hotelPrice' => $hotelPrice, 'hotelPriceNight' => $hotelPriceNight,
    'hotelImage' => $hotelImage, 'hotelAddress' => $hotelAddress,
    'hotelRating' => $hotelRating, 'hotelReviews' => $hotelReviews,
    'hotelStars' => $hotelStars, 'toCity' => $city,
    'arrivalDate' => $arrivalDate, 'departureDate' => $departureDate,
    'rooms' => $rooms, 'adults' => $adults, 'children' => $children,
    'nights' => $nights, 'source' => $source, 'returnStep' => $returnStep
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Hotel | Travelix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
    <style>
        :root {
            --theme-color: #1484B4; --theme-dark: #0f6d95;
            --text-dark: #1f2937; --text-muted: #6b7280;
            --border-soft: rgba(15,23,42,0.08); --danger: #dc2626;
            --page-top-offset: 118px; --shadow-soft: 0 14px 40px rgba(15,23,42,0.08);
        }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; font-family:'Poppins',sans-serif; background:#eef2f5; color:var(--text-dark); overflow-x:hidden; }

        .booking-shell { max-width:1460px; margin:var(--page-top-offset) auto 28px; padding:0 18px; }
        .booking-grid { display:grid; grid-template-columns:1.18fr 0.82fr; gap:22px; align-items:start; }
        .booking-panel, .booking-summary { background:#fff; border-radius:26px; border:1px solid var(--border-soft); box-shadow:var(--shadow-soft); overflow:hidden; }
        .panel-section { padding:24px; border-bottom:1px solid rgba(15,23,42,0.08); }
        .panel-section:last-child { border-bottom:none; }

        .top-actions { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--theme-color); font-weight:700; }
        .booking-badge { display:inline-flex; align-items:center; gap:8px; background:#eaf6fb; color:var(--theme-color); border-radius:999px; padding:8px 14px; font-weight:700; font-size:13px; }

        .hero-title { font-size:34px; font-weight:800; margin:0 0 8px; line-height:1.15; }
        .hero-subtitle { margin:0; color:var(--text-muted); line-height:1.75; font-size:15px; }

        /* Live banner */
        .live-banner {
            background: linear-gradient(135deg, #133c96, #2d6af0);
            color:#fff; border-radius:18px; padding:16px 20px;
            display:flex; align-items:center; gap:14px; margin-bottom:18px;
        }
        .live-dot { width:12px; height:12px; background:#ef4444; border-radius:50%; flex-shrink:0; animation:livePulse 1.2s ease-in-out infinite; }
        @keyframes livePulse {
            0%,100% { box-shadow:0 0 0 0 rgba(239,68,68,0.6); }
            50% { box-shadow:0 0 0 8px rgba(239,68,68,0); }
        }
        .live-banner-text { font-weight:700; font-size:14px; }
        .live-banner-sub { font-size:12px; opacity:0.8; margin-top:2px; }

        /* Open mgmt button */
        .open-mgmt-btn {
            display:inline-flex; align-items:center; gap:8px;
            background: rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.3);
            color:#fff; border-radius:12px; padding:8px 14px; font-size:13px; font-weight:700;
            cursor:pointer; text-decoration:none; transition:0.2s; flex-shrink:0;
        }
        .open-mgmt-btn:hover { background:rgba(255,255,255,0.28); color:#fff; }

        /* Hotel card */
        .hotel-card { display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:start; }
        .hotel-card-image img { width:100%; height:210px; object-fit:cover; border-radius:20px; display:block; background:#e5e7eb; }
        .hotel-name { margin:0 0 8px; font-size:28px; font-weight:800; line-height:1.2; }
        .hotel-meta, .hotel-price-line { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .meta-chip { display:inline-flex; align-items:center; gap:8px; padding:9px 12px; border-radius:999px; background:#f8fbfd; border:1px solid rgba(15,23,42,0.06); font-size:13px; font-weight:700; color:#475569; }
        .hotel-address { color:var(--text-muted); line-height:1.7; margin-bottom:14px; }
        .price-box, .trip-detail-card { background:#f8fbfd; border:1px solid rgba(15,23,42,0.06); border-radius:18px; padding:14px 16px; }
        .price-label, .trip-detail-label { font-size:12px; color:var(--text-muted); margin-bottom:4px; font-weight:700; }
        .price-value { font-size:24px; font-weight:800; color:var(--text-dark); line-height:1.1; }
        .section-title { font-size:20px; font-weight:800; margin:0 0 16px; }
        .trip-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .trip-detail-value { font-size:16px; font-weight:700; color:var(--text-dark); line-height:1.4; }

        /* ===== REAL-TIME SUMMARY FIELDS ===== */
        .summary-list { display:flex; flex-direction:column; gap:14px; }
        .summary-row { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; font-size:15px; line-height:1.6; }
        .summary-row .label { color:var(--text-muted); font-weight:600; }
        .summary-row .value { color:var(--text-dark); font-weight:700; text-align:right; min-width:80px; }
        .summary-divider { height:1px; background:rgba(15,23,42,0.08); margin:2px 0; }
        .summary-row.total .label, .summary-row.total .value { font-size:18px; font-weight:800; color:var(--text-dark); }
        .summary-row.discount .label, .summary-row.discount .value { color:#16a34a; }
        .summary-row.platform-fee .label { color:#7c3aed; }

        .discount-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; background:#dcfce7; color:#166534; font-size:12.5px; font-weight:800; margin-bottom:10px; }

        /* Empty placeholder shimmer — only on summary values */
        .rt-empty {
            display:inline-block; background:#e8ecf4; border-radius:8px;
            animation:shimmer 1.5s ease-in-out infinite;
        }
        @keyframes shimmer { 0%,100%{opacity:0.35} 50%{opacity:0.9} }

        /* Typing cursor */
        .rt-typing { border-right:2px solid #2d6af0; animation:blink 0.6s step-end infinite; padding-right:2px; }
        @keyframes blink { 50%{border-color:transparent} }

        /* Pop-in animation when a value arrives */
        @keyframes popIn { 0%{transform:scale(0.8);opacity:0} 60%{transform:scale(1.07)} 100%{transform:scale(1);opacity:1} }
        .rt-pop { animation:popIn 0.35s ease forwards; color:#10b981 !important; }
        .rt-done { color:var(--text-dark) !important; }

        /* Complete banner */
        .fill-complete-banner {
            background:linear-gradient(135deg,#059669,#10b981);
            color:#fff; border-radius:18px; padding:16px 20px;
            display:none; align-items:center; gap:14px; margin-bottom:18px;
        }
        .fill-complete-banner.show { display:flex; }
        .fill-complete-banner i { font-size:22px; }
        .fill-complete-text { font-weight:700; font-size:14px; }

        /* Payment */
        .payment-wrap { background:#f8fbfd; border:1px solid rgba(15,23,42,0.06); border-radius:20px; padding:20px; }
        .payment-heading { font-size:17px; font-weight:800; margin:0 0 18px; display:flex; align-items:center; gap:8px; }
        .payment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .payment-field.full { grid-column:1/-1; }
        .payment-field label { display:block; margin-bottom:8px; font-size:13px; font-weight:800; color:#334155; }
        .payment-field input, .payment-field select { width:100%; border:1px solid rgba(15,23,42,0.10); border-radius:16px; padding:14px 16px; font-size:15px; color:#334155; background:#fff; outline:none; }
        .payment-field input:focus, .payment-field select:focus { border-color:var(--theme-color); box-shadow:0 0 0 4px rgba(20,132,180,0.12); }
        .payment-row-3 { display:grid; grid-template-columns:140px 140px 1fr; gap:16px; }
        .helper-text { margin-top:7px; font-size:12px; color:var(--text-muted); line-height:1.5; }
        .error-text { margin-top:7px; font-size:12px; color:var(--danger); display:none; }
        .success-box { margin-top:14px; padding:14px 16px; border-radius:16px; background:#ecfdf5; border:1px solid rgba(22,163,74,0.16); color:#166534; font-size:13px; line-height:1.7; display:none; }

        /* Manual payment (EasyPaisa/JazzCash/Raast) */
        .pay-account-box { display:flex; align-items:center; gap:14px; padding:16px 18px; border-radius:18px; background:#fff; border:1px solid rgba(15,23,42,0.08); }
        .pay-method-badge { flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; padding:9px 16px; border-radius:999px; font-weight:800; font-size:13px; color:#fff; white-space:nowrap; }
        .pay-account-info .pay-account-label { font-size:12px; color:var(--text-muted); font-weight:700; margin-bottom:3px; }
        .pay-account-info .pay-account-number { font-size:19px; font-weight:800; color:var(--text-dark); letter-spacing:0.4px; }
        .pay-instructions { margin-top:12px; font-size:13px; color:var(--text-muted); line-height:1.7; }
        .proof-upload-box { margin-top:18px; }
        .proof-upload-box label { display:block; margin-bottom:8px; font-size:13px; font-weight:800; color:#334155; }
        .proof-upload-box input[type="file"] { width:100%; border:1.5px dashed rgba(15,23,42,0.18); border-radius:16px; padding:16px; font-size:14px; color:#334155; background:#fff; cursor:pointer; }
        .proof-preview { display:none; margin-top:12px; align-items:center; gap:12px; padding:10px 14px; border-radius:14px; background:#ecfdf5; border:1px solid rgba(22,163,74,0.16); }
        .proof-preview.show { display:flex; }
        .proof-preview img { width:52px; height:52px; object-fit:cover; border-radius:10px; }
        .proof-preview-name { font-size:13px; font-weight:700; color:#166534; word-break:break-all; }

        /* Confirm button */
        .action-stack { display:flex; flex-direction:column; gap:12px; }
        .primary-btn, .secondary-btn { border:none; border-radius:999px; padding:15px 22px; font-weight:800; font-size:15px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:10px; cursor:pointer; transition:0.2s ease; width:100%; }
        .primary-btn { background:linear-gradient(135deg,#1484B4,#0f6d95); color:#fff; box-shadow:0 14px 30px rgba(20,132,180,0.22); }
        .secondary-btn { background:#eef7fb; color:var(--theme-color); border:1px solid rgba(20,132,180,0.16); }
        .primary-btn[disabled] { opacity:0.55; cursor:not-allowed; }

        .summary-note { margin-top:16px; background:#fff7ed; border:1px solid rgba(245,158,11,0.18); color:#92400e; border-radius:18px; padding:14px 16px; font-size:13px; line-height:1.7; }
        .refund-policy-box { margin-top:12px; background:#eff6ff; border:1px solid rgba(37,99,235,0.18); border-radius:18px; padding:14px 16px; }
        .refund-policy-box-title { font-weight:800; font-size:13px; color:#1e40af; display:flex; align-items:center; gap:8px; margin-bottom:6px; }
        .refund-policy-box-text { font-size:12.5px; color:#334155; line-height:1.7; }

        .rt-select { width:100%; padding:13px 16px; border:1.5px solid rgba(15,23,42,0.10); border-radius:16px; font-size:15px; color:#334155; background:#fff; outline:none; }
        .rt-select:focus { border-color:var(--theme-color); box-shadow:0 0 0 4px rgba(20,132,180,0.12); }
        .rt-avail-note { margin-top:10px; font-size:13px; font-weight:600; padding:10px 14px; border-radius:12px; display:none; }
        .rt-avail-note.ok { display:block; background:#ecfdf5; color:#166534; }
        .rt-avail-note.warn { display:block; background:#fffbeb; color:#92400e; }
        .rt-avail-note.error { display:block; background:#fef2f2; color:#991b1b; }

        .empty-state { padding:30px 24px; text-align:center; }
        .empty-state-icon { font-size:48px; margin-bottom:10px; }
        .empty-state h3 { margin:0 0 8px; font-size:28px; font-weight:800; }
        .empty-state p { margin:0 0 18px; color:var(--text-muted); line-height:1.8; }

        @media (max-width:1200px) { :root{--page-top-offset:104px} .booking-grid{grid-template-columns:1fr} }
        @media (max-width:768px) { :root{--page-top-offset:96px} .booking-shell{padding:0 12px} .hotel-card,.trip-detail-grid,.payment-grid,.payment-row-3{grid-template-columns:1fr} .hero-title{font-size:28px} }
    </style>
</head>
<body>

<?php include '../includes/user_top_navbar.php'; ?>

<div class="booking-shell">
<?php if (!$hasHotel): ?>
    <div class="booking-panel empty-state">
        <div class="empty-state-icon">🏨</div>
        <h3>No hotel selected</h3>
        <p>Please go back and choose a hotel first.</p>
        <div class="action-stack" style="max-width:320px;margin:0 auto;">
            <a href="<?php echo e($backToResultsUrl); ?>" class="secondary-btn"><i class="fa-solid fa-arrow-left"></i> Back to Results</a>
        </div>
    </div>
<?php else: ?>
<div class="booking-grid">

    <!-- ===== LEFT PANEL ===== -->
    <div class="booking-panel">
        <div class="panel-section">
            <div class="top-actions">
                <a href="<?php echo e($backToResultsUrl); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Hotel Results</a>
                <div class="booking-badge"><i class="fa-solid fa-hotel"></i> Travelix Hotel Booking</div>
            </div>

            <h1 class="hero-title">Book Your Hotel</h1>
            <p class="hero-subtitle">Review your selected hotel and complete payment to confirm your booking.</p>
        </div>

        <!-- Hotel info — shown immediately from URL params -->
        <div class="panel-section">
            <div class="hotel-card">
                <div class="hotel-card-image">
                    <img src="<?php echo e($hotelImage ?: $baseUrl.'/images/default_hotel.jpg'); ?>"
                         alt="<?php echo e($hotelName); ?>"
                         onerror="this.onerror=null;this.src='<?php echo $baseUrl; ?>/images/default_hotel.jpg';">
                </div>
                <div class="hotel-card-content">
                    <h2 class="hotel-name"><?php echo e($hotelName); ?></h2>
                    <div class="hotel-meta">
                        <span class="meta-chip"><i class="fa-solid fa-location-dot"></i> <?php echo e($city); ?></span>
                        <span class="meta-chip"><i class="fa-solid fa-star"></i> <?php echo e($hotelStars); ?> Star</span>
                        <span class="meta-chip"><i class="fa-solid fa-thumbs-up"></i> <?php echo number_format($hotelRating,1); ?></span>
                        <span class="meta-chip"><i class="fa-solid fa-message"></i> <?php echo (int)$hotelReviews; ?> Reviews</span>
                    </div>
                    <div class="hotel-address"><?php echo e($hotelAddress); ?></div>
                    <div id="hotelDiscountBadge" class="discount-badge" style="display:none;">
                        <i class="fa-solid fa-tag"></i> <span id="hotelDiscountBadgeText"></span>
                    </div>
                    <div class="hotel-price-line">
                        <div class="price-box">
                            <div class="price-label">Per Night</div>
                            <div class="price-value" id="pricePerNightDisplay">PKR <?php echo number_format($hotelPriceNight); ?></div>
                        </div>
                        <div class="price-box">
                            <div class="price-label">Total to Pay (incl. 12% platform fee)</div>
                            <div class="price-value" id="priceTotalDisplay">PKR <?php echo number_format($hotelPrice * 1.12); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Type Selection -->
        <div class="panel-section">
            <h3 class="section-title">Select Room Type</h3>
            <label for="roomTypeSelect" style="display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:8px;">Room Type <span style="color:red">*</span></label>
            <select id="roomTypeSelect" class="rt-select">
                <option value="">Loading room types...</option>
            </select>
            <div id="roomTypeAvailNote" class="rt-avail-note"></div>
        </div>

        <!-- Trip details — shown immediately -->
        <div class="panel-section">
            <h3 class="section-title">Trip Details</h3>
            <div class="trip-detail-grid">
                <div class="trip-detail-card"><div class="trip-detail-label">Destination City</div><div class="trip-detail-value"><?php echo e($city); ?></div></div>
                <div class="trip-detail-card"><div class="trip-detail-label">Stay Dates</div><div class="trip-detail-value"><?php echo e(formatDateForView($arrivalDate)); ?> – <?php echo e(formatDateForView($departureDate)); ?></div></div>
                <div class="trip-detail-card"><div class="trip-detail-label">Rooms</div><div class="trip-detail-value"><?php echo (int)$rooms; ?></div></div>
                <div class="trip-detail-card"><div class="trip-detail-label">Guests</div><div class="trip-detail-value"><?php echo (int)$adults; ?> Adults, <?php echo (int)$children; ?> Children</div></div>
                <div class="trip-detail-card"><div class="trip-detail-label">Nights</div><div class="trip-detail-value"><?php echo (int)$nights; ?></div></div>
                <div class="trip-detail-card"><div class="trip-detail-label">Hotel ID</div><div class="trip-detail-value"><?php echo e($hotelId); ?></div></div>
            </div>
        </div>

        <!-- Payment -->
        <div class="panel-section">
            <div class="payment-wrap">
                <h3 class="payment-heading">Payment <i class="fa-solid fa-lock"></i></h3>

                <div class="pay-account-box" id="payAccountBox">
                    <div class="pay-method-badge" id="payMethodBadge" style="background:#94a3b8;">Loading...</div>
                    <div class="pay-account-info">
                        <div class="pay-account-label">Send payment to this Travelix account</div>
                        <div class="pay-account-number" id="payAccountNumber">—</div>
                        <div class="pay-account-number" id="payTillId" style="display:none;margin-top:4px;font-size:13px;"></div>
                    </div>
                </div>

                <div id="payQrBox" style="display:none;text-align:center;margin-top:16px;padding:16px;border:1.5px solid #e2e8f0;border-radius:16px;background:#fafbfc;">
                    <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:10px;">Or scan to pay</div>
                    <img id="payQrImage" src="" alt="Scan to pay QR code" style="width:200px;height:200px;object-fit:contain;cursor:zoom-in;border-radius:10px;transition:.15s;" title="Click to enlarge">
                    <div style="font-size:11.5px;color:#94a3b8;margin-top:8px;"><i class="fa-solid fa-magnifying-glass-plus"></i> Tap the QR code to enlarge</div>
                </div>

                <div id="qrZoomOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.82);z-index:9999;align-items:center;justify-content:center;padding:24px;">
                    <div style="position:relative;background:#fff;border-radius:20px;padding:24px;max-width:min(92vw,420px);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);">
                        <button type="button" id="qrZoomCloseBtn" aria-label="Close" style="position:absolute;top:-14px;right:-14px;width:36px;height:36px;border-radius:50%;background:#133c96;color:#fff;border:3px solid #fff;font-size:16px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.25);">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <div style="font-size:14px;font-weight:800;color:#133c96;margin-bottom:14px;">Scan to Pay</div>
                        <img id="qrZoomImage" src="" alt="Scan to pay QR code (enlarged)" style="width:100%;max-width:340px;height:auto;object-fit:contain;border-radius:12px;">
                        <div style="font-size:12.5px;color:#64748b;margin-top:14px;">Open your payment app and scan this code</div>
                    </div>
                </div>

                <div class="pay-instructions">
                    Send the total amount to the Travelix account above (or scan the QR code), then attach a screenshot as proof. Travelix will verify it, pay the hotel with proof, and the hotel will confirm after receiving its share.
                </div>

                <div class="proof-upload-box">
                    <label for="paymentProofInput">Attach Payment Proof <span style="color:red">*</span></label>
                    <input type="file" id="paymentProofInput" accept="image/*,.pdf">
                    <div class="proof-preview" id="proofPreview">
                        <img id="proofPreviewImg" alt="">
                        <span class="proof-preview-name" id="proofPreviewName"></span>
                    </div>
                </div>

                <div class="success-box" id="successBox">Booking saved successfully in Firebase.</div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT PANEL — BOOKING SUMMARY ===== -->
    <div class="booking-summary">
        <div class="panel-section">
            <h3 class="section-title">Booking Summary</h3>

            <div class="summary-list">
                <div class="summary-row">
                    <div class="label">Hotel</div>
                    <div class="value"><?php echo e($hotelName); ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">City</div>
                    <div class="value"><?php echo e($city); ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Check-in</div>
                    <div class="value"><?php echo e(formatDateForView($arrivalDate)); ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Check-out</div>
                    <div class="value"><?php echo e(formatDateForView($departureDate)); ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Rooms</div>
                    <div class="value"><?php echo (int)$rooms; ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Guests</div>
                    <div class="value"><?php echo (int)$adults + (int)$children; ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Price per night</div>
                    <div class="value" id="summaryPricePerNight">PKR <?php echo number_format($hotelPriceNight); ?></div>
                </div>
                <div class="summary-row">
                    <div class="label">Nights</div>
                    <div class="value"><?php echo (int)$nights; ?></div>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-row">
                    <div class="label">Hotel Charges</div>
                    <div class="value" id="summaryHotelCharges">PKR <?php echo number_format($hotelPrice); ?></div>
                </div>
                <div class="summary-row discount" id="summaryDiscountRow" style="display:none;">
                    <div class="label">Hotel Discount</div>
                    <div class="value" id="summaryDiscountValue">-PKR 0</div>
                </div>
                <div class="summary-row platform-fee">
                    <div class="label">Platform Charges (12%)</div>
                    <div class="value" id="summaryPlatformFee">PKR <?php echo number_format($hotelPrice * 0.12); ?></div>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-row total">
                    <div class="label">Total to Pay</div>
                    <div class="value" id="summaryTotal">PKR <?php echo number_format($hotelPrice * 1.12); ?></div>
                </div>
            </div>

            <div class="summary-note">Your booking stays pending until Travelix verifies your payment, pays the hotel, and the hotel confirms receipt and reserves your room.</div>

            <div class="refund-policy-box">
                <div class="refund-policy-box-title"><i class="fa-solid fa-rotate-left"></i> Cancellation &amp; Refund Policy</div>
                <div class="refund-policy-box-text"><?php echo refundPolicySummary($cancellationPolicy); ?></div>
            </div>
        </div>

        <div class="panel-section">
            <div class="action-stack">
                <button type="button" class="primary-btn" id="confirmBookingBtn" disabled>
                    <i class="fa-solid fa-check"></i> Confirm Booking
                </button>
                <a href="<?php echo e($backToResultsUrl); ?>" class="secondary-btn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Results
                </a>
                <?php if ($source === 'create_trip'): ?>
                <a href="<?php echo e($backToTripUrl); ?>" class="secondary-btn">
                    <i class="fa-solid fa-route"></i> Back to Create Trip
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>
</div>

<?php include '../includes/user_bottom_footer.php'; ?>

<input type="hidden" id="hotelPayload" value='<?php echo json_encode($bookingPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>'>

<!-- Payment + booking confirm (module) -->
<script type="module">
import { firebaseConfig } from "../config/firebase-config.js";
import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";
import { getFirestore, collection, addDoc, serverTimestamp, doc, getDoc, query, where, getDocs } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

const confirmBookingBtn = document.getElementById('confirmBookingBtn');
const successBox       = document.getElementById('successBox');
const hotelPayload     = JSON.parse(document.getElementById('hotelPayload')?.value || '{}');
const roomTypeSelect   = document.getElementById('roomTypeSelect');
const roomTypeAvailNote= document.getElementById('roomTypeAvailNote');
const payMethodBadge   = document.getElementById('payMethodBadge');
const payAccountNumber = document.getElementById('payAccountNumber');
const payTillId        = document.getElementById('payTillId');
const payQrBox         = document.getElementById('payQrBox');
const payQrImage       = document.getElementById('payQrImage');
const qrZoomOverlay    = document.getElementById('qrZoomOverlay');
const qrZoomImage      = document.getElementById('qrZoomImage');
const qrZoomCloseBtn   = document.getElementById('qrZoomCloseBtn');
const pricePerNightDisplay  = document.getElementById('pricePerNightDisplay');
const priceTotalDisplay     = document.getElementById('priceTotalDisplay');
const hotelDiscountBadge    = document.getElementById('hotelDiscountBadge');
const hotelDiscountBadgeText= document.getElementById('hotelDiscountBadgeText');
const summaryPricePerNight  = document.getElementById('summaryPricePerNight');
const summaryHotelCharges   = document.getElementById('summaryHotelCharges');
const summaryDiscountRow    = document.getElementById('summaryDiscountRow');
const summaryDiscountValue  = document.getElementById('summaryDiscountValue');
const summaryPlatformFee    = document.getElementById('summaryPlatformFee');
const summaryTotal          = document.getElementById('summaryTotal');

function openQrZoom() {
    if (!payQrImage.src) return;
    qrZoomImage.src = payQrImage.src;
    qrZoomOverlay.style.display = 'flex';
}
function closeQrZoom() {
    qrZoomOverlay.style.display = 'none';
}
payQrImage.addEventListener('click', openQrZoom);
qrZoomCloseBtn.addEventListener('click', closeQrZoom);
qrZoomOverlay.addEventListener('click', (e) => { if (e.target === qrZoomOverlay) closeQrZoom(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeQrZoom(); });
const paymentProofInput= document.getElementById('paymentProofInput');
const proofPreview     = document.getElementById('proofPreview');
const proofPreviewImg  = document.getElementById('proofPreviewImg');
const proofPreviewName = document.getElementById('proofPreviewName');

const TYPE_LABELS = { single:'Single', double:'Double', triple:'Triple', quad:'Quad' };
const PAYMENT_METHODS = {
    easypaisa: { label: 'EasyPaisa', color: '#16a34a' },
    jazzcash:  { label: 'JazzCash',  color: '#dc2626' },
    raast:     { label: 'Raast',     color: '#0d9488' },
    bank:      { label: 'Bank Transfer', color: '#133c96' }
};

// Live-selected pricing/type — snapshotted at Confirm time, never re-read afterwards
const PLATFORM_FEE_RATE = 0.12; // charged to the guest ON TOP of the hotel's price, never deducted from the hotel's share
let selectedRoomType = '';
let selectedPricePerNight = Number(hotelPayload.hotelPriceNight || 0);
let hotelDiscounts = []; // this hotel's active discount periods, loaded in initRoomTypes()
let selectedDiscountPercent = 0;
let selectedDiscountAmount = 0;
let selectedHotelCharges = Number(hotelPayload.hotelPrice || 0); // hotel's own price, after any hotel discount — this is what the hotel is owed
let selectedPlatformFee = 0;
let selectedTotalPrice = Number(hotelPayload.hotelPrice || 0); // what the guest actually pays = hotel charges + platform fee
let isRoomAvailable = false;
let paymentMethod = '';
let paymentAccountNumber = '';
let paymentTillId = '';
let paymentQrPath = '';

// Returns the largest-percent hotel discount whose date range covers the
// booking's arrival date, or 0 if none applies — hotel discounts don't stack.
function getActiveDiscountPercent() {
    const arrival = hotelPayload.arrivalDate || '';
    if (!arrival || !Array.isArray(hotelDiscounts) || !hotelDiscounts.length) return 0;

    let best = 0;
    hotelDiscounts.forEach(d => {
        const start = d.startDate || '';
        const end = d.endDate || '';
        const percent = Number(d.percent || 0);
        if (start && end && arrival >= start && arrival <= end && percent > best) {
            best = percent;
        }
    });
    return best;
}

function updateConfirmButtonState() {
    confirmBookingBtn.disabled = !(isRoomAvailable && paymentProofInput.files.length > 0);
}

function renderPaymentAccount() {
    const info = PAYMENT_METHODS[paymentMethod];
    if (info && paymentAccountNumber) {
        payMethodBadge.textContent = info.label;
        payMethodBadge.style.background = info.color;
        payAccountNumber.textContent = paymentAccountNumber;

        if (paymentTillId) {
            payTillId.textContent = 'Till ID: ' + paymentTillId;
            payTillId.style.display = 'block';
        } else {
            payTillId.style.display = 'none';
        }

        if (paymentQrPath) {
            payQrImage.src = paymentQrPath;
            payQrBox.style.display = 'block';
        } else {
            payQrBox.style.display = 'none';
        }
    } else {
        payMethodBadge.textContent = 'Unavailable';
        payMethodBadge.style.background = '#94a3b8';
        payAccountNumber.textContent = 'Payments are temporarily unavailable. Please contact support.';
        payTillId.style.display = 'none';
        payQrBox.style.display = 'none';
    }
}

paymentProofInput?.addEventListener('change', function () {
    const file = this.files?.[0];
    if (!file) {
        proofPreview.classList.remove('show');
        updateConfirmButtonState();
        return;
    }

    proofPreviewName.textContent = file.name;
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { proofPreviewImg.src = e.target?.result || ''; proofPreviewImg.style.display = 'block'; };
        reader.readAsDataURL(file);
    } else {
        proofPreviewImg.style.display = 'none';
    }
    proofPreview.classList.add('show');
    updateConfirmButtonState();
});

async function initRoomTypes() {
    if (!hotelPayload.hotelId) return;
    let roomTypes = {};
    let hotelData = {};
    try {
        const hotelDoc = await getDoc(doc(db, 'hotels', hotelPayload.hotelId));
        if (hotelDoc.exists()) {
            hotelData = hotelDoc.data();
            roomTypes = hotelData.room_types || {};
            hotelDiscounts = Array.isArray(hotelData.discounts) ? hotelData.discounts : [];
        }
    } catch (e) { /* fall through to legacy option below */ }

    // Guests pay into Travelix's own central account only — never the hotel's
    // own account. If admin hasn't configured it yet, the payment box below
    // correctly shows "Unavailable" rather than silently falling back to a
    // hotel's individual account.
    try {
        const configDoc = await getDoc(doc(db, 'app_config', 'payment_account'));
        if (configDoc.exists()) {
            const configData = configDoc.data();
            paymentMethod = configData.method || '';
            paymentAccountNumber = configData.accountNumber || '';
            paymentTillId = configData.tillId || '';
            paymentQrPath = configData.qrImagePath || '';
        }
    } catch (e) { /* leave empty — payment box shows Unavailable */ }

    renderPaymentAccount();
    roomTypeSelect.innerHTML = '';
    const typeKeys = Object.keys(roomTypes);

    if (typeKeys.length) {
        typeKeys.forEach(key => {
            const t = roomTypes[key];
            const opt = document.createElement('option');
            opt.value = key;
            opt.dataset.price = t.price || 0;
            opt.dataset.count = t.count || 0;
            opt.textContent = `${TYPE_LABELS[key] || key} — PKR ${Number(t.price || 0).toLocaleString()} / night`;
            roomTypeSelect.appendChild(opt);
        });
    } else {
        // Legacy hotel without a room-type breakdown yet
        const opt = document.createElement('option');
        opt.value = '';
        opt.dataset.price = hotelPayload.hotelPriceNight || 0;
        opt.dataset.count = 9999;
        opt.textContent = `Standard Room — PKR ${Number(hotelPayload.hotelPriceNight || 0).toLocaleString()} / night`;
        roomTypeSelect.appendChild(opt);
    }

    roomTypeSelect.addEventListener('change', onRoomTypeSelected);
    onRoomTypeSelected();
}

async function onRoomTypeSelected() {
    const opt = roomTypeSelect.selectedOptions[0];
    if (!opt) return;

    selectedRoomType = opt.value;
    const price     = Number(opt.dataset.price || 0);
    const typeCount = Number(opt.dataset.count || 0);
    const roomsWanted = Number(hotelPayload.rooms || 1);

    // Live availability for this type, for the searched date range
    let booked = 0;
    try {
        const from = hotelPayload.arrivalDate || '';
        const to   = hotelPayload.departureDate || '';
        const q = query(collection(db, 'hotel_bookings'), where('hotelId', '==', hotelPayload.hotelId));
        const snap = await getDocs(q);
        snap.forEach(d => {
            const b = d.data();
            const status = String(b.bookingStatus || 'confirmed').toLowerCase();
            if (['cancelled','rejected','payment_rejected'].includes(status)) return;
            if (selectedRoomType && b.roomType && b.roomType !== selectedRoomType) return;
            if (from && to && b.arrivalDate && b.departureDate && b.arrivalDate < to && from < b.departureDate) {
                booked += Number(b.rooms || 1);
            }
        });
    } catch (e) { /* leave booked at 0 on error */ }

    const available = Math.max(0, typeCount - booked);

    selectedPricePerNight = price;
    const baseHotelCharges = price * (Number(hotelPayload.nights || 1)) * roomsWanted;

    // Hotel discount (if any) comes off the hotel's own price first — the
    // hotel absorbs its own promotion. The 12% platform fee is then charged
    // on top of that (discounted) total, never on the pre-discount amount.
    selectedDiscountPercent = getActiveDiscountPercent();
    selectedDiscountAmount = Math.round(baseHotelCharges * selectedDiscountPercent / 100);
    selectedHotelCharges = baseHotelCharges - selectedDiscountAmount;
    selectedPlatformFee = Math.round(selectedHotelCharges * PLATFORM_FEE_RATE);
    selectedTotalPrice = selectedHotelCharges + selectedPlatformFee;

    // Update displayed prices live
    pricePerNightDisplay.textContent = 'PKR ' + selectedPricePerNight.toLocaleString();
    priceTotalDisplay.textContent = 'PKR ' + selectedTotalPrice.toLocaleString();
    summaryPricePerNight.textContent = 'PKR ' + selectedPricePerNight.toLocaleString();
    summaryHotelCharges.textContent = 'PKR ' + baseHotelCharges.toLocaleString();
    summaryPlatformFee.textContent = 'PKR ' + selectedPlatformFee.toLocaleString();
    summaryTotal.textContent = 'PKR ' + selectedTotalPrice.toLocaleString();

    if (selectedDiscountPercent > 0) {
        summaryDiscountRow.style.display = 'flex';
        summaryDiscountValue.textContent = '-PKR ' + selectedDiscountAmount.toLocaleString();
        hotelDiscountBadge.style.display = 'inline-flex';
        hotelDiscountBadgeText.textContent = `${selectedDiscountPercent}% OFF — Hotel Discount`;
    } else {
        summaryDiscountRow.style.display = 'none';
        hotelDiscountBadge.style.display = 'none';
    }

    if (available <= 0) {
        roomTypeAvailNote.className = 'rt-avail-note error';
        roomTypeAvailNote.textContent = `Fully booked for these dates. Please choose another room type or dates.`;
        isRoomAvailable = false;
    } else if (roomsWanted > available) {
        roomTypeAvailNote.className = 'rt-avail-note error';
        roomTypeAvailNote.textContent = `Only ${available} room${available===1?'':'s'} of this type available for your dates — you requested ${roomsWanted}.`;
        isRoomAvailable = false;
    } else if (available <= 3) {
        roomTypeAvailNote.className = 'rt-avail-note warn';
        roomTypeAvailNote.textContent = `Only ${available} room${available===1?'':'s'} left of this type for your dates.`;
        isRoomAvailable = true;
    } else {
        roomTypeAvailNote.className = 'rt-avail-note ok';
        roomTypeAvailNote.textContent = `${available} rooms available for your dates.`;
        isRoomAvailable = true;
    }

    updateConfirmButtonState();
}

initRoomTypes();

function waitForAuth() { return new Promise(r => onAuthStateChanged(auth, u => r(u||null))); }

let phpSessionUid = <?= json_encode((string)($_SESSION['user']['uid'] ?? '')) ?>;

async function resyncPhpUserSession(authUser, userProfile) {
    if (phpSessionUid === authUser.uid) return;
    const formData = new FormData();
    formData.append('action', 'set_login_session');
    formData.append('uid', authUser.uid || '');
    formData.append('first_name', userProfile.firstName || 'User');
    formData.append('last_name', userProfile.lastName || '');
    formData.append('email', userProfile.email || authUser.email || '');
    formData.append('profile_image', userProfile.profileImage || '/travelix/images/default_profile.png');
    formData.append('role', userProfile.role || 'user');

    const response = await fetch('/travelix/auth/login.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error('Could not synchronize your login session. Please log in again.');
    phpSessionUid = authUser.uid;
}

async function uploadPaymentProof(file) {
    const formData = new FormData();
    formData.append('proof', file);
    const res = await fetch('/travelix/hotel/ajax/upload_payment_proof.php', { method: 'POST', body: formData });
    const result = await res.json();
    if (!res.ok || !result.success) {
        throw new Error(result.message || 'Failed to upload payment proof.');
    }
    return result.path;
}

confirmBookingBtn?.addEventListener('click', async function () {
    if (!hotelPayload.hotelName||!hotelPayload.toCity) { await Swal.fire({icon:'error',title:'Hotel Data Missing',text:'Please go back and select the hotel again.',confirmButtonText:'OK'}); return; }
    if (!roomTypeSelect.options.length) { await Swal.fire({icon:'warning',title:'Room Type Required',text:'Please wait for room types to load, then select one.',confirmButtonText:'OK'}); return; }
    const proofFile = paymentProofInput.files?.[0];
    if (!proofFile) { await Swal.fire({icon:'warning',title:'Payment Proof Required',text:'Please attach a screenshot of your payment before confirming.',confirmButtonText:'OK'}); return; }
    if (!paymentMethod || !paymentAccountNumber) { await Swal.fire({icon:'error',title:'Payment Account Unavailable',text:'Payments are temporarily unavailable. Please contact support.',confirmButtonText:'OK'}); return; }
    const authUser = await waitForAuth();
    if (!authUser) { await Swal.fire({icon:'warning',title:'Not Logged In',text:'Please log out and log in again.',confirmButtonText:'OK'}); return; }
    const userProfileSnap = await getDoc(doc(db, 'users', authUser.uid));
    const userProfile = userProfileSnap.exists() ? userProfileSnap.data() : {};
    if (!userProfile.paymentMethod || !userProfile.paymentAccountNumber || (userProfile.paymentMethod === 'bank' && !userProfile.bankName)) {
        const missing = await Swal.fire({
            icon:'warning', title:'Refund Account Required',
            text:'Save your bank or wallet account before booking. This account is required for any cancellation refund.',
            showCancelButton:true, confirmButtonText:'Open Profile', confirmButtonColor:'#1484B4'
        });
        if (missing.isConfirmed) window.location.href='/travelix/profile/manage_user_profile.php';
        return;
    }

    try {
        confirmBookingBtn.disabled=true;
        // Firebase Auth is authoritative in the browser. Refresh the PHP
        // session before uploading so the server validates the exact same
        // user profile (important for old duplicate/stale session records).
        await resyncPhpUserSession(authUser, userProfile);
        Swal.fire({title:'Uploading payment proof...',text:'Please wait...',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()});

        const proofImagePath = await uploadPaymentProof(proofFile);

        const bookingData = {
            uid:authUser.uid, userId:authUser.uid, userEmail:authUser.email||'',
            hotelId:hotelPayload.hotelId||'', hotelName:hotelPayload.hotelName||'',
            roomType:selectedRoomType||'',
            // hotelPrice is what the hotel is owed (100%, after any hotel discount) — payout
            // and revenue math both read this field. platformFee is Travelix's 12% on top,
            // and totalCharged (hotelPrice + platformFee) is what the guest actually paid —
            // that's the amount the proof upload covers and refunds are based on.
            hotelPrice:Number(selectedHotelCharges||0), hotelPriceNight:Number(selectedPricePerNight||0),
            discountPercent:Number(selectedDiscountPercent||0), discountAmount:Number(selectedDiscountAmount||0),
            platformFee:Number(selectedPlatformFee||0), totalCharged:Number(selectedTotalPrice||0),
            hotelImage:hotelPayload.hotelImage||'', hotelAddress:hotelPayload.hotelAddress||'',
            hotelRating:Number(hotelPayload.hotelRating||0), hotelReviews:Number(hotelPayload.hotelReviews||0),
            hotelStars:Number(hotelPayload.hotelStars||0), toCity:hotelPayload.toCity||'',
            arrivalDate:hotelPayload.arrivalDate||'', departureDate:hotelPayload.departureDate||'',
            rooms:Number(hotelPayload.rooms||1), adults:Number(hotelPayload.adults||1),
            children:Number(hotelPayload.children||0), nights:Number(hotelPayload.nights||1),
            totalGuests:Number(hotelPayload.adults||1)+Number(hotelPayload.children||0),
            source:hotelPayload.source||'', returnStep:hotelPayload.returnStep||'3',
            payment:{ method:paymentMethod, accountNumber:paymentAccountNumber, proofImagePath, status:'awaiting_verification' },
            refundAccountSnapshot:{ method:userProfile.paymentMethod, bankName:userProfile.bankName||'', accountNumber:userProfile.paymentAccountNumber },
            roomTypeCapacity:Number(roomTypeSelect.selectedOptions[0]?.dataset.count || 0),
            hotelPayoutStatus:'not_sent', bookingStatus:'pending', createdAt:serverTimestamp()
        };

        Swal.update({title:'Confirming booking...'});
        // The server serializes the final availability check + reservation,
        // preventing two simultaneous guests from taking the same last room.
        bookingData.createdAt = new Date().toISOString();
        const createResp = await fetch('/travelix/hotel/ajax/create_booking.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({booking:bookingData})});
        const createResult = await createResp.json();
        if(!createResult.success) throw new Error(createResult.message || 'Could not reserve the selected rooms.');
        const ref = { id:createResult.bookingId };
        // Booking + all three notifications are committed atomically by the
        // server in one request, so the success popup no longer waits on
        // three additional Firestore round trips.

        try {
            localStorage.setItem('travelix_hotel_booking', JSON.stringify({
                id: ref.id,
                hotelName: hotelPayload.hotelName || '',
                hotelImage: hotelPayload.hotelImage || '',
                hotelAddress: hotelPayload.hotelAddress || '',
                roomType: selectedRoomType || '',
                hotelPrice: Number(selectedHotelCharges||0),
                totalCharged: Number(selectedTotalPrice||0),
                hotelPriceNight: Number(selectedPricePerNight||0),
                arrivalDate: hotelPayload.arrivalDate || '',
                departureDate: hotelPayload.departureDate || ''
            }));
        } catch(e){}

        successBox.style.display='block';
        await Swal.fire({icon:'success',title:'Booking Submitted',text:`Booking ID: ${ref.id}. Travelix will verify your payment, pay the hotel, then the hotel will confirm your room.`,confirmButtonText:'OK'});

        if (hotelPayload.source === 'create_trip') {
            window.location.href = '/travelix/trips/create_trip.php';
        } else {
            window.location.href='/travelix/hotel/manage_bookings.php';
        }
    } catch(err) {
        await Swal.fire({icon:'error',title:'Booking Failed',text:err?.message||'Could not save booking.',confirmButtonText:'OK'});
    } finally { confirmBookingBtn.disabled=false; updateConfirmButtonState(); }
});
</script>
</body>
</html>
