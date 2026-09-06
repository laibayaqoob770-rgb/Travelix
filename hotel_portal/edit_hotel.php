<?php
/**
 * Hotel Management Portal — Edit Hotel
 * Multi-step form. Saves to Firestore `hotels` collection.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

require_once __DIR__ . '/includes/resolve_hotel.php';
ensure_staff_hotel_linked();

$staff    = $_SESSION['hotel_staff'];
$staffUid = $staff['uid'];
$staffName= $staff['first_name'] ?? explode(' ', $staff['name'])[0];

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';

$editId      = trim($_GET['edit'] ?? $staff['hotel_id'] ?? '');
$initialStep = max(1, min(6, (int)($_GET['step'] ?? 1)));
$hasHotel    = $editId !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Edit Hotel — Travelix Hotel Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/css/travelix_notifications.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4fa;min-height:100vh;}

/* Topbar */
.portal-topbar{background:linear-gradient(135deg,#0f3460,#1a5276,#2471a3);color:#fff;padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 20px rgba(15,52,96,.3);position:sticky;top:0;z-index:100;}
.topbar-brand{display:flex;align-items:center;gap:12px;font-size:18px;font-weight:800;}
.topbar-back{background:rgba(255,255,255,.15);border:none;color:#fff;padding:8px 18px;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;transition:.2s;text-decoration:none;}
.topbar-back:hover{background:rgba(255,255,255,.25);color:#fff;}

/* Portal Sub-Nav */
.portal-subnav{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;display:flex;gap:4px;overflow-x:auto;position:sticky;top:64px;z-index:99;}
.portal-subnav a{display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13.5px;font-weight:700;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:.2s;}
.portal-subnav a:hover{color:#0f3460;background:#f8fafc;}
.portal-subnav a.active{color:#0f3460;border-bottom-color:#0f3460;}

/* Layout */
.page-wrap{max-width:1100px;margin:0 auto;padding:28px 16px;display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;}
@media(max-width:900px){.page-wrap{grid-template-columns:1fr;}}

/* Steps Nav */
.steps-nav{display:flex;gap:0;margin-bottom:24px;background:#fff;border-radius:16px;padding:8px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:auto;}
.step-tab{flex:1;padding:10px 6px;border:none;background:none;border-radius:12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;transition:.2s;min-width:80px;}
.step-tab.active{background:#0f3460;color:#fff;}
.step-tab:not(.active){color:#64748b;}
.step-tab:not(.active):hover{background:#f1f5f9;}
.step-tab-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;border:2px solid currentColor;}
.step-tab.active .step-tab-num{background:#fff;color:#0f3460;border-color:#fff;}
.step-tab.done .step-tab-num{background:#dcfce7;color:#16a34a;border-color:#16a34a;}
.step-tab-label{font-size:11px;font-weight:600;white-space:nowrap;}

/* Card */
.form-card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);padding:28px;}
.card-heading{font-size:18px;font-weight:800;color:#1e293b;margin-bottom:6px;}
.card-sub{font-size:13px;color:#64748b;margin-bottom:22px;}

/* Form fields */
.field-group{margin-bottom:18px;}
.field-label{font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;display:block;}
.field-label span{color:#ef4444;margin-left:2px;}
.field-input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:14px;color:#1e293b;background:#fafbfc;transition:.2s;outline:none;}
.field-input:focus{border-color:#2471a3;background:#fff;box-shadow:0 0 0 3px rgba(36,113,163,.12);}
.field-input.error{border-color:#ef4444;}
textarea.field-input{resize:vertical;min-height:90px;}
.field-hint{font-size:11px;color:#94a3b8;margin-top:4px;}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* Room type inventory */
.room-type-grid{display:flex;flex-direction:column;gap:10px;}
.room-type-row{display:grid;grid-template-columns:86px 1fr 1fr;gap:12px;align-items:end;background:#fafbfc;border:1.5px solid #e2e8f0;border-radius:12px;padding:12px 14px;}
.rt-name{font-weight:800;color:#0f3460;font-size:14px;padding-bottom:11px;}
.rt-field label{display:block;font-size:11px;color:#64748b;margin-bottom:4px;font-weight:700;}
.room-type-summary{margin-top:10px;font-size:12.5px;font-weight:700;color:#0f3460;background:#eff6ff;border-radius:10px;padding:9px 14px;display:inline-block;}
@media(max-width:600px){.room-type-row{grid-template-columns:1fr;}.rt-name{padding-bottom:0;}}

/* Amenities */
.amenity-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.amenity-chip{padding:8px 14px;border-radius:999px;border:2px solid #e2e8f0;background:#fafbfc;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;color:#374151;}
.amenity-chip.selected{background:#eff6ff;border-color:#2471a3;color:#1d4ed8;}

/* Image upload card */
.upload-card{display:block;border:2px dashed #cbd5e1;border-radius:16px;padding:32px 20px;text-align:center;cursor:pointer;background:#fafbfc;transition:.2s;}
.upload-card:hover{border-color:#2471a3;background:#f0f6ff;}
.upload-card i{font-size:34px;color:#2471a3;margin-bottom:10px;display:block;}
.image-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-top:16px;}
.image-card{position:relative;border-radius:12px;overflow:hidden;height:110px;background:#e8edf5;}
.image-card img{width:100%;height:100%;object-fit:cover;display:block;}
.image-card .remove-img-btn{position:absolute;top:5px;right:5px;width:24px;height:24px;border-radius:50%;background:rgba(220,38,38,.9);color:#fff;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;}
.image-card .main-badge{position:absolute;bottom:0;left:0;right:0;background:rgba(15,52,96,.85);color:#fff;font-size:10px;font-weight:700;text-align:center;padding:3px 0;}

/* Nav buttons */
.step-nav-row{display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding-top:18px;border-top:1px solid #f1f4f9;}
.nav-btn{padding:12px 28px;border-radius:14px;font-weight:700;font-size:14px;cursor:pointer;border:none;transition:.2s;}
.nav-btn.back{background:#f1f5f9;color:#374151;}
.nav-btn.back:hover{background:#e2e8f0;}
.nav-btn.next{background:#0f3460;color:#fff;}
.nav-btn.next:hover{background:#1a5276;}
.nav-btn.save{background:linear-gradient(135deg,#059669,#10b981);color:#fff;}
.nav-btn.save:hover{background:linear-gradient(135deg,#047857,#059669);}
.nav-btn:disabled{opacity:.5;cursor:not-allowed;}

/* ── Live Preview Panel ── */
.preview-panel{position:sticky;top:150px;}
.preview-card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;}
.preview-header{background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;padding:14px 18px;font-weight:800;font-size:14px;display:flex;align-items:center;gap:8px;}
.preview-img{width:100%;height:180px;object-fit:cover;background:#e8edf5;display:block;}
.preview-body{padding:16px;}
.preview-hotel-name{font-size:18px;font-weight:800;color:#1e293b;margin-bottom:4px;}
.preview-city{font-size:13px;color:#64748b;margin-bottom:8px;display:flex;align-items:center;gap:4px;}
.preview-stars{color:#f59e0b;font-size:16px;margin-bottom:8px;}
.preview-desc{font-size:13px;color:#475569;line-height:1.6;margin-bottom:12px;max-height:60px;overflow:hidden;}
.preview-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
.preview-chip{background:#eff6ff;color:#1d4ed8;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;}
.preview-price-row{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid #f1f4f9;}
.preview-price{font-size:20px;font-weight:800;color:#0f3460;}
.preview-price span{font-size:12px;font-weight:500;color:#94a3b8;}
.preview-rating{background:#f0fdf4;color:#16a34a;padding:6px 12px;border-radius:10px;font-weight:700;font-size:13px;}

/* Sync indicator */
.sync-badge{display:inline-flex;align-items:center;gap:6px;background:#dcfce7;color:#166534;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;margin-top:12px;opacity:0;transition:opacity .4s;}
.sync-badge.show{opacity:1;}

/* Save result */
.save-result{border-radius:16px;padding:16px;margin-top:18px;display:none;}
.save-result.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.save-result.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}

/* First-time / no hotel assigned yet */
.page-body{max-width:1100px;margin:0 auto;padding:28px 16px;}
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
</style>
</head>
<body>

<div class="portal-topbar">
    <div class="topbar-brand">🏨 Hotel Management Portal</div>
    <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:13px;opacity:.8;">👤 <?= htmlspecialchars($staffName) ?></span>
        <div class="travelix-notification-wrapper" id="travelixNotificationWrapper">
            <button type="button" class="travelix-notification-btn" id="travelixNotificationToggle"><i class="fa-solid fa-bell"></i><span class="travelix-notification-badge" id="travelixNotificationBadge">0</span></button>
            <div class="travelix-notification-panel" id="travelixNotificationPanel"><div class="travelix-notification-header"><div><h6>Notifications</h6><span id="travelixNotificationCountText">0 new</span></div><div class="travelix-notification-header-actions"><button type="button" id="travelixRefreshNotificationsBtn" class="travelix-refresh-notification-btn"><i class="fa-solid fa-arrow-rotate-right"></i></button><button type="button" id="travelixReadAllBtn" class="travelix-read-all-btn">Read all</button></div></div><div class="travelix-notification-list" id="travelixNotificationList"></div></div>
        </div>
        <a href="/travelix/hotel_portal/logout.php" class="topbar-back" style="background:rgba(239,68,68,.25);" onclick="return confirm('Sign out?')">Logout</a>
    </div>
</div>

<div class="portal-subnav">
    <a href="/travelix/hotel_portal/index.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="/travelix/hotel_portal/hotel_bookings.php<?= $hasHotel ? '?hotel_id=' . urlencode($editId) : '' ?>"><i class="fas fa-calendar-alt"></i> Bookings</a>
    <a href="/travelix/hotel_portal/commission.php"><i class="fas fa-money-bill-wave"></i> Payouts</a>
    <a href="/travelix/hotel_portal/refunds.php"><i class="fas fa-rotate-left"></i> Refunds</a>
    <a href="/travelix/hotel_portal/discounts.php"><i class="fas fa-tag"></i> Discounts</a>
    <a href="/travelix/hotel_portal/edit_hotel.php<?= $hasHotel ? '?edit=' . urlencode($editId) : '' ?>" class="active"><i class="fas fa-pen"></i> Edit Hotel</a>
</div>

<?php if (!$hasHotel): ?>

<div class="page-body">
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
                    <i class="fas fa-user-circle"></i> You are signed in as:
                    <span style="font-family:monospace;"><?= htmlspecialchars($staff['email'] ?? '(unknown)') ?></span>
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
    </div>
</div>

<?php else: ?>

<div style="max-width:1100px;margin:0 auto;padding:20px 16px 8px;">
    <!-- Steps Nav -->
    <div class="steps-nav" id="stepsNav">
        <button class="step-tab active" data-step="1" onclick="goStep(1)">
            <div class="step-tab-num">1</div>
            <div class="step-tab-label">Basic Info</div>
        </button>
        <button class="step-tab" data-step="2" onclick="goStep(2)">
            <div class="step-tab-num">2</div>
            <div class="step-tab-label">Location</div>
        </button>
        <button class="step-tab" data-step="3" onclick="goStep(3)">
            <div class="step-tab-num">3</div>
            <div class="step-tab-label">Pricing</div>
        </button>
        <button class="step-tab" data-step="4" onclick="goStep(4)">
            <div class="step-tab-num">4</div>
            <div class="step-tab-label">Amenities</div>
        </button>
        <button class="step-tab" data-step="5" onclick="goStep(5)">
            <div class="step-tab-num">5</div>
            <div class="step-tab-label">Images</div>
        </button>
        <button class="step-tab" data-step="6" onclick="goStep(6)">
            <div class="step-tab-num">6</div>
            <div class="step-tab-label">Review & Save</div>
        </button>
    </div>
</div>

<div class="page-wrap">
    <!-- LEFT: Form -->
    <div>

        <!-- Step 1: Basic Info -->
        <div class="form-card" id="step1">
            <div class="card-heading">🏨 Basic Hotel Information</div>
            <div class="card-sub">Enter the core details about your hotel property.</div>

            <div class="field-group">
                <label class="field-label">Hotel Name <span>*</span></label>
                <input type="text" class="field-input" id="fName" placeholder="e.g. Pearl Continental Lahore" oninput="syncPreview()">
            </div>
            <div class="row-2">
                <div class="field-group">
                    <label class="field-label">City <span>*</span></label>
                    <select class="field-input" id="fCity" onchange="toggleCustomCity(); syncPreview();">
                        <option value="">— Select City —</option>
                        <option>Lahore</option><option>Karachi</option><option>Islamabad</option>
                        <option>Peshawar</option><option>Quetta</option><option>Murree</option>
                        <option>Faisalabad</option><option>Multan</option><option>Rawalpindi</option>
                        <option>Hyderabad</option><option>Sialkot</option>
                        <option value="__other__">+ Add another city</option>
                    </select>
                    <input type="text" class="field-input" id="fCityOther"
                           placeholder="Enter city name" style="margin-top:10px;display:none;" oninput="syncPreview()">
                </div>
                <div class="field-group">
                    <label class="field-label">Guest Rating <span style="color:#94a3b8;font-weight:500;">(auto — from guest reviews)</span></label>
                    <div id="ratingDisplay" style="padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:12px;background:#f8faff;font-size:14px;color:#374151;font-weight:700;">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                    <div class="field-hint">Calculated automatically from guest feedback — cannot be edited manually.</div>
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Full Address <span>*</span></label>
                <input type="text" class="field-input" id="fAddress" placeholder="e.g. Shahrah-e-Quaid-e-Azam, Mall Road" oninput="syncPreview()">
            </div>
            <div class="field-group">
                <label class="field-label">Description <span>*</span></label>
                <textarea class="field-input" id="fDescription" placeholder="Describe the hotel — location, atmosphere, highlights..." oninput="syncPreview()"></textarea>
            </div>
            <div class="row-2">
                <div class="field-group">
                    <label class="field-label">Contact Phone</label>
                    <input type="tel" class="field-input" id="fPhone" placeholder="+92 300 0000000" oninput="syncPreview()">
                </div>
                <div class="field-group">
                    <label class="field-label">Contact Email</label>
                    <input type="email" class="field-input" id="fEmail" placeholder="info@hotel.com" oninput="syncPreview()">
                </div>
            </div>
            <div class="step-nav-row">
                <div></div>
                <button class="nav-btn next" onclick="nextStep(1)">Next: Location →</button>
            </div>
        </div>

        <!-- Step 2: Location -->
        <div class="form-card" id="step2" style="display:none;">
            <div class="card-heading">📍 Location Details</div>
            <div class="card-sub">Provide exact coordinates for map display on Travelix.</div>

            <div class="row-2">
                <div class="field-group">
                    <label class="field-label">Latitude</label>
                    <input type="number" class="field-input" id="fLat" step="0.0001" placeholder="e.g. 31.5204" oninput="syncPreview()">
                    <div class="field-hint">Find on Google Maps → right click → copy coordinates</div>
                </div>
                <div class="field-group">
                    <label class="field-label">Longitude</label>
                    <input type="number" class="field-input" id="fLng" step="0.0001" placeholder="e.g. 74.3587" oninput="syncPreview()">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Nearby Landmark</label>
                <input type="text" class="field-input" id="fLandmark" placeholder="e.g. Near Lahore Airport, 2 km from Mall Road" oninput="syncPreview()">
            </div>
            <div class="field-group">
                <label class="field-label">Distance from City Center</label>
                <input type="text" class="field-input" id="fDistance" placeholder="e.g. 1.5 km from city center" oninput="syncPreview()">
            </div>
            <div class="step-nav-row">
                <button class="nav-btn back" onclick="goStep(1)">← Back</button>
                <button class="nav-btn next" onclick="nextStep(2)">Next: Pricing →</button>
            </div>
        </div>

        <!-- Step 3: Pricing & Rooms -->
        <div class="form-card" id="step3" style="display:none;">
            <div class="card-heading">💰 Pricing & Room Details</div>
            <div class="card-sub">Set your pricing and room availability information.</div>

            <div class="field-group">
                <label class="field-label">Room Types &amp; Pricing <span>*</span></label>
                <div class="field-hint" style="margin:0 0 10px;">Enter how many rooms of each type you have and the price per night for that type. At least one type is required.</div>
                <div class="room-type-grid" id="roomTypeGrid">
                    <?php
                    $roomTypes = ['single' => 'Single', 'double' => 'Double', 'triple' => 'Triple', 'quad' => 'Quad'];
                    foreach ($roomTypes as $rtKey => $rtLabel):
                    ?>
                    <div class="room-type-row" data-type="<?= $rtKey ?>">
                        <div class="rt-name"><?= $rtLabel ?></div>
                        <div class="rt-field">
                            <label>Rooms</label>
                            <input type="number" class="field-input rt-count" min="0" placeholder="0" oninput="onRoomTypeChange()">
                        </div>
                        <div class="rt-field">
                            <label>Price / Night (PKR)</label>
                            <input type="number" class="field-input rt-price" min="0" placeholder="e.g. 8000" oninput="onRoomTypeChange()">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="room-type-summary" id="roomTypeSummary">Total rooms: 0 &nbsp;|&nbsp; Starting from PKR —&nbsp;/ night</div>
            </div>
            <div class="row-2">
                <div class="field-group">
                    <label class="field-label">Check-in Time</label>
                    <input type="time" class="field-input" id="fCheckin" value="14:00" oninput="syncPreview()">
                </div>
                <div class="field-group">
                    <label class="field-label">Check-out Time</label>
                    <input type="time" class="field-input" id="fCheckout" value="12:00" oninput="syncPreview()">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Cancellation Policy</label>
                <select class="field-input" id="fCancellationType" onchange="toggleCancellationFields(); syncPreview();">
                    <option value="free">Free cancellation</option>
                    <option value="partial">Partial refund</option>
                    <option value="non_refundable">Non-refundable</option>
                </select>
            </div>
            <div class="row-2" id="cancellationWindowRow">
                <div class="field-group">
                    <label class="field-label">Hours Before Check-in</label>
                    <input type="number" class="field-input" id="fCancellationWindowHours" value="24" min="0" max="720" oninput="syncPreview()">
                    <p class="help-text">Cancelling before this many hours qualifies for the refund below. Cancelling after gets no refund.</p>
                </div>
                <div class="field-group" id="cancellationPercentGroup">
                    <label class="field-label">Refund Percentage</label>
                    <input type="number" class="field-input" id="fCancellationRefundPercent" value="100" min="0" max="100" oninput="syncPreview()">
                </div>
            </div>
            <div class="step-nav-row">
                <button class="nav-btn back" onclick="goStep(2)">← Back</button>
                <button class="nav-btn next" onclick="nextStep(3)">Next: Amenities →</button>
            </div>
        </div>

        <!-- Step 4: Amenities -->
        <div class="form-card" id="step4" style="display:none;">
            <div class="card-heading">✅ Amenities & Features</div>
            <div class="card-sub">Select all amenities your hotel offers.</div>

            <div class="amenity-grid" id="amenityGrid">
                <?php
                $amenities = ['WiFi','Pool','Gym','Restaurant','Spa','Parking','AC','Room Service',
                              'Bar','Conference Hall','Business Center','Laundry','Airport Shuttle',
                              'Pet Friendly','Kids Play Area','Rooftop Lounge','24hr Reception',
                              'Security','Garden','Sea View','Mountain View','Heater','Bonfire',
                              'Steam Room','Doctor on Call'];
                foreach($amenities as $a):
                ?>
                <div class="amenity-chip" data-amenity="<?= htmlspecialchars($a) ?>" onclick="toggleAmenity(this)"><?= htmlspecialchars($a) ?></div>
                <?php endforeach; ?>
            </div>

            <div class="field-group" style="margin-top:20px;">
                <label class="field-label">Additional Amenities (comma-separated)</label>
                <input type="text" class="field-input" id="fExtraAmenities" placeholder="e.g. Helicopter Pad, Private Beach" oninput="syncPreview()">
            </div>
            <div class="step-nav-row">
                <button class="nav-btn back" onclick="goStep(3)">← Back</button>
                <button class="nav-btn next" onclick="nextStep(4)">Next: Images →</button>
            </div>
        </div>

        <!-- Step 5: Images -->
        <div class="form-card" id="step5" style="display:none;">
            <div class="card-heading">🖼️ Hotel Images</div>
            <div class="card-sub">Upload photos of your hotel. The first image becomes the main listing photo.</div>

            <div class="field-group">
                <label class="field-label" for="fImageUpload">Upload Images</label>
                <label class="upload-card" for="fImageUpload">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div style="font-weight:700;color:#374151;">Click to upload images</div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px;">JPG, PNG or WEBP — select multiple at once</div>
                </label>
                <input type="file" id="fImageUpload" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;" onchange="handleImageUpload(this.files)">
            </div>

            <div id="uploadStatus" style="font-size:12.5px;color:#2471a3;margin:-8px 0 14px;display:none;">
                <i class="fas fa-spinner fa-spin"></i> Uploading...
            </div>

            <div class="image-card-grid" id="imageCardGrid"></div>
            <div id="imageCardEmpty" style="text-align:center;color:#94a3b8;font-size:13px;padding:20px;">No images uploaded yet.</div>

            <div class="step-nav-row">
                <button class="nav-btn back" onclick="goStep(4)">← Back</button>
                <button class="nav-btn next" onclick="nextStep(5)">Review & Save →</button>
            </div>
        </div>

        <!-- Step 6: Review & Save -->
        <div class="form-card" id="step6" style="display:none;">
            <div class="card-heading">✅ Review & Save to Firebase</div>
            <div class="card-sub">Check the live preview on the right, then save your hotel to Travelix.</div>

            <div id="reviewSummary" style="font-size:14px;color:#334155;line-height:2;"></div>

            <div class="field-group" style="margin-top:18px;">
                <label class="field-label">Listing Status</label>
                <select class="field-input" id="fStatus">
                    <option value="active">Active — Visible to users immediately</option>
                    <option value="draft">Draft — Save but hide from users</option>
                </select>
            </div>

            <div id="saveResult" class="save-result"></div>
            <div id="syncBadge" class="sync-badge"><i class="fas fa-check-circle"></i> Saved to Firebase!</div>

            <div class="step-nav-row">
                <button class="nav-btn back" onclick="goStep(5)">← Back</button>
                <button class="nav-btn save" id="saveBtn" onclick="saveHotel()">
                    <i class="fas fa-save"></i> Save Hotel to Firebase
                </button>
            </div>
        </div>

    </div><!-- /left -->

    <!-- RIGHT: Live Preview -->
    <div class="preview-panel">
        <div class="preview-card">
            <div class="preview-header">
                <i class="fas fa-eye"></i> Live Preview — as seen on Travelix
            </div>
            <img id="pvImg" class="preview-img" src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400" onerror="this.src='https://via.placeholder.com/400x180?text=Hotel+Image'">
            <div class="preview-body">
                <div class="preview-hotel-name" id="pvName">Your Hotel Name</div>
                <div class="preview-city" id="pvCity"><i class="fas fa-map-marker-alt" style="color:#2471a3"></i> City</div>
                <div class="preview-stars" id="pvStars">☆☆☆☆☆</div>
                <div class="preview-desc" id="pvDesc">Hotel description will appear here as you type...</div>
                <div class="preview-chips" id="pvChips"></div>
                <div class="preview-price-row">
                    <div class="preview-price" id="pvPrice">PKR —<span> / night</span></div>
                    <div class="preview-rating" id="pvRating">⭐ —</div>
                </div>
            </div>
        </div>

        <!-- Firebase Sync Status -->
        <div style="margin-top:14px;background:#fff;border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.06);">
            <div style="font-weight:700;font-size:13px;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <span style="width:10px;height:10px;background:#10b981;border-radius:50%;display:inline-block;"></span>
                Firebase Connection
            </div>
            <div id="fbStatus" style="font-size:12px;color:#6b7280;">Connecting to Firestore...</div>
            <div style="margin-top:10px;font-size:12px;color:#94a3b8;">
                Collection: <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">hotels</code>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Firebase -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
<script>
// ── Firebase Init ──
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

// ── Test Firebase connection ──
db.collection('hotels').limit(1).get()
    .then(() => { const el = document.getElementById('fbStatus'); if (el) el.innerHTML = '<span style="color:#16a34a;font-weight:600;">✅ Connected to Firestore</span>'; })
    .catch(e  => { const el = document.getElementById('fbStatus'); if (el) el.innerHTML = '<span style="color:#ef4444;font-weight:600;">❌ ' + e.message + '</span>'; });

// ── Edit mode ──
const EDIT_ID      = <?= json_encode($editId ?: '') ?>;
const STAFF_UID    = <?= json_encode($staffUid) ?>;
const INITIAL_STEP = <?= json_encode($initialStep) ?>;
let selectedAmenities = [];
let uploadedImages = [];
let currentStep = 1;
let computedRating  = 0;
let computedReviews = 0;
let computedStars   = 0;

function toggleCustomCity() {
    const sel   = document.getElementById('fCity');
    const other = document.getElementById('fCityOther');
    if (sel.value === '__other__') {
        other.style.display = 'block';
        other.focus();
    } else {
        other.style.display = 'none';
        other.value = '';
    }
}

function toggleCancellationFields() {
    const type = document.getElementById('fCancellationType').value;
    const windowRow = document.getElementById('cancellationWindowRow');
    const percentGroup = document.getElementById('cancellationPercentGroup');
    const percentInput = document.getElementById('fCancellationRefundPercent');
    const windowInput = document.getElementById('fCancellationWindowHours');

    if (type === 'non_refundable') {
        windowRow.style.display = 'none';
        percentInput.value = 0;
    } else if (type === 'free') {
        windowRow.style.display = '';
        percentGroup.style.display = 'none';
        percentInput.value = 100;
        if (!windowInput.value) windowInput.value = 24;
    } else {
        windowRow.style.display = '';
        percentGroup.style.display = '';
        if (!windowInput.value) windowInput.value = 24;
    }
}

function getSelectedCity() {
    const sel = document.getElementById('fCity');
    return sel.value === '__other__' ? document.getElementById('fCityOther').value.trim() : sel.value;
}

if (EDIT_ID) {
    db.collection('hotels').doc(EDIT_ID).get().then(doc => {
        if (!doc.exists) return;
        const h = doc.data();
        document.getElementById('fName').value        = h.name || '';

        // City — select it if it's a known option, otherwise fall back to "Add another city"
        const citySel = document.getElementById('fCity');
        const knownCity = [...citySel.options].some(o => o.value === h.city);
        if (h.city && knownCity) {
            citySel.value = h.city;
        } else if (h.city) {
            citySel.value = '__other__';
            document.getElementById('fCityOther').value = h.city;
            toggleCustomCity();
        }

        document.getElementById('fAddress').value     = h.address || '';
        document.getElementById('fDescription').value = h.description || '';
        document.getElementById('fPhone').value       = h.phone || '';
        document.getElementById('fEmail').value       = h.email || '';
        document.getElementById('fLat').value         = h.lat || '';
        document.getElementById('fLng').value         = h.lng || '';
        document.getElementById('fLandmark').value    = h.landmark || '';
        document.getElementById('fDistance').value    = h.distance || '';
        document.getElementById('fCheckin').value     = h.checkin_time || '14:00';
        document.getElementById('fCheckout').value    = h.checkout_time || '12:00';
        // New structured cancellationPolicy; fall back to migrating the old bare-string
        // 'cancellation' field (free/partial/non-refundable) with sensible defaults.
        (function prefillCancellationPolicy() {
            const legacy = h.cancellation || '';
            const policy = (h.cancellationPolicy && typeof h.cancellationPolicy === 'object')
                ? h.cancellationPolicy
                : {
                    type: legacy === 'non-refundable' ? 'non_refundable' : (legacy === 'partial' ? 'partial' : 'free'),
                    windowHours: legacy === 'partial' ? 48 : 24,
                    refundPercent: legacy === 'non-refundable' ? 0 : (legacy === 'partial' ? 70 : 100)
                };

            document.getElementById('fCancellationType').value = policy.type || 'free';
            document.getElementById('fCancellationWindowHours').value = policy.windowHours ?? 24;
            document.getElementById('fCancellationRefundPercent').value = policy.refundPercent ?? 100;
            toggleCancellationFields();
        })();
        document.getElementById('fStatus').value      = h.status || 'active';
        if (Array.isArray(h.features)) {
            h.features.forEach(f => {
                const chip = document.querySelector(`.amenity-chip[data-amenity="${f}"]`);
                if (chip) { chip.classList.add('selected'); selectedAmenities.push(f); }
            });
        }

        // Room types — prefill from room_types map; migrate legacy total_rooms/price_per_night if missing
        if (h.room_types && typeof h.room_types === 'object') {
            Object.keys(h.room_types).forEach(type => {
                const row = document.querySelector(`.room-type-row[data-type="${type}"]`);
                if (!row) return;
                row.querySelector('.rt-count').value = h.room_types[type].count || '';
                row.querySelector('.rt-price').value = h.room_types[type].price || '';
            });
        } else if (h.total_rooms || h.price_per_night) {
            // Legacy hotel without room-type breakdown — migrate into "Double" as a starting point
            const row = document.querySelector('.room-type-row[data-type="double"]');
            if (row) {
                row.querySelector('.rt-count').value = h.total_rooms || '';
                row.querySelector('.rt-price').value = h.price_per_night || '';
            }
        }
        onRoomTypeChange();

        uploadedImages = [h.image, ...(h.gallery||[])].filter(Boolean);
        renderImageCards();
        syncPreview();
        computeGuestRating(EDIT_ID);
        if (INITIAL_STEP > 1) goStep(INITIAL_STEP);
    });
}

// ── Steps ──
function goStep(n) {
    document.querySelectorAll('.form-card[id^="step"]').forEach(el => el.style.display='none');
    document.getElementById('step'+n).style.display = 'block';
    currentStep = n;
    document.querySelectorAll('.step-tab').forEach(t => {
        const s = parseInt(t.dataset.step);
        t.classList.remove('active','done');
        if (s === n) t.classList.add('active');
        else if (s < n) t.classList.add('done');
    });
    if (n === 6) buildReviewSummary();
    window.scrollTo({top:0,behavior:'smooth'});
}

function nextStep(from) {
    if (from === 1 && !validateStep1()) return;
    if (from === 3 && !validateStep3()) return;
    goStep(from + 1);
}

function validateStep1() {
    const name = document.getElementById('fName').value.trim();
    const city = getSelectedCity();
    const addr = document.getElementById('fAddress').value.trim();
    const desc = document.getElementById('fDescription').value.trim();
    if (!name) { highlight('fName','Hotel name is required'); return false; }
    if (!city) {
        highlight(document.getElementById('fCity').value === '__other__' ? 'fCityOther' : 'fCity', 'Please select or enter a city');
        return false;
    }
    if (!addr) { highlight('fAddress','Address is required'); return false; }
    if (!desc) { highlight('fDescription','Description is required'); return false; }
    return true;
}

function validateStep3() {
    // A room type with a count entered but no price would otherwise be
    // silently dropped by getRoomTypes() — catch that explicitly instead of
    // just failing the generic "at least one room type" check below, so the
    // hotel knows exactly which row is missing a price.
    let missingPriceType = null;
    document.querySelectorAll('.room-type-row').forEach(row => {
        const count = parseInt(row.querySelector('.rt-count').value) || 0;
        const price = parseFloat(row.querySelector('.rt-price').value) || 0;
        if (count > 0 && price <= 0 && !missingPriceType) {
            missingPriceType = row.querySelector('.rt-name')?.textContent?.trim() || 'this room type';
        }
    });
    if (missingPriceType) {
        Swal.fire({icon:'warning',title:'Price Required',text:`Enter a price above 0 per night for ${missingPriceType}, or set its room count to 0.`,confirmButtonColor:'#0f3460'});
        return false;
    }

    const { totalRooms } = derivedTotals();
    if (!totalRooms) {
        Swal.fire({icon:'warning',title:'Room Types Required',text:'Enter at least one room type with a room count and price per night.',confirmButtonColor:'#0f3460'});
        return false;
    }
    return true;
}

function highlight(id, msg) {
    const el = document.getElementById(id);
    el.classList.add('error');
    el.focus();
    setTimeout(() => el.classList.remove('error'), 2000);
    Swal.fire({icon:'warning',title:'Required Field',text:msg,timer:2000,showConfirmButton:false});
}

// ── Guest Rating — auto-computed from real guest feedback, never entered manually ──
async function computeGuestRating(hotelId) {
    const display = document.getElementById('ratingDisplay');
    try {
        // 1. Find this hotel's bookings → their booking IDs
        const bookingsSnap = await db.collection('hotel_bookings').where('hotelId', '==', hotelId).get();
        const bookingIds = new Set(bookingsSnap.docs.map(d => d.id));

        // 2. Fetch feedback and keep only entries tied to one of this hotel's bookings
        const feedbackSnap = await db.collection('hotel_feedback').get();
        let sum = 0, count = 0;
        feedbackSnap.forEach(doc => {
            const f = doc.data();
            if (bookingIds.has(f.bookingId) && typeof f.rating === 'number') {
                sum += f.rating;
                count++;
            }
        });

        computedReviews = count;
        computedRating  = count > 0 ? Math.round((sum / count) * 10) / 10 : 0;
        computedStars   = count > 0 ? Math.round(computedRating) : 0;

        display.innerHTML = count > 0
            ? `⭐ ${computedRating.toFixed(1)} <span style="font-weight:500;color:#94a3b8;">(${count} review${count===1?'':'s'})</span>`
            : `<span style="color:#94a3b8;font-weight:500;">No guest reviews yet</span>`;
    } catch (e) {
        display.innerHTML = `<span style="color:#94a3b8;font-weight:500;">No guest reviews yet</span>`;
    }
    syncPreview();
}

// ── Room Types & Pricing ──
function getRoomTypes() {
    const types = {};
    document.querySelectorAll('.room-type-row').forEach(row => {
        const type  = row.dataset.type;
        const count = parseInt(row.querySelector('.rt-count').value) || 0;
        const price = parseFloat(row.querySelector('.rt-price').value) || 0;
        if (count > 0 && price > 0) types[type] = { count, price };
    });
    return types;
}

function derivedTotals() {
    const types = getRoomTypes();
    const entries = Object.values(types);
    const totalRooms = entries.reduce((sum, t) => sum + t.count, 0);
    const startingPrice = entries.length ? Math.min(...entries.map(t => t.price)) : 0;
    return { totalRooms, startingPrice, types };
}

function onRoomTypeChange() {
    const { totalRooms, startingPrice } = derivedTotals();
    document.getElementById('roomTypeSummary').textContent =
        `Total rooms: ${totalRooms} | Starting from ${startingPrice ? 'PKR ' + startingPrice.toLocaleString() : '—'} / night`;
    syncPreview();
}

// ── Amenities ──
function toggleAmenity(chip) {
    const val = chip.dataset.amenity;
    chip.classList.toggle('selected');
    if (chip.classList.contains('selected')) {
        if (!selectedAmenities.includes(val)) selectedAmenities.push(val);
    } else {
        selectedAmenities = selectedAmenities.filter(a => a !== val);
    }
    syncPreview();
}

// ── Preview ──
function syncPreview() {
    const name  = document.getElementById('fName').value || 'Your Hotel Name';
    const city  = getSelectedCity() || 'City';
    const { startingPrice } = derivedTotals();
    const desc  = document.getElementById('fDescription').value || 'Hotel description...';

    document.getElementById('pvName').textContent  = name;
    document.getElementById('pvCity').innerHTML    = `<i class="fas fa-map-marker-alt" style="color:#2471a3"></i> ${city}`;
    document.getElementById('pvStars').textContent = '⭐'.repeat(computedStars) + '☆'.repeat(Math.max(0,5-computedStars));
    document.getElementById('pvDesc').textContent  = desc;
    document.getElementById('pvPrice').innerHTML   = startingPrice ? `PKR ${Number(startingPrice).toLocaleString()}<span> / night</span>` : `PKR —<span> / night</span>`;
    document.getElementById('pvRating').textContent= computedReviews > 0 ? `⭐ ${computedRating.toFixed(1)}` : '⭐ —';
    document.getElementById('pvImg').src = uploadedImages[0] || 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';

    const chips = [...selectedAmenities].slice(0,5);
    document.getElementById('pvChips').innerHTML   = chips.map(c => `<span class="preview-chip">${c}</span>`).join('');
}

// ── Images ──
async function handleImageUpload(fileList) {
    if (!fileList || !fileList.length) return;
    const status = document.getElementById('uploadStatus');
    status.style.display = 'block';

    const formData = new FormData();
    for (const file of fileList) formData.append('images[]', file);

    try {
        const res  = await fetch('/travelix/hotel_portal/ajax/upload_hotel_images.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            uploadedImages.push(...data.paths);
            renderImageCards();
            syncPreview();
        } else {
            Swal.fire({icon:'error', title:'Upload Failed', text: data.message || 'Could not upload images.', confirmButtonColor:'#0f3460'});
        }
    } catch (e) {
        Swal.fire({icon:'error', title:'Upload Failed', text: e.message, confirmButtonColor:'#0f3460'});
    } finally {
        status.style.display = 'none';
        document.getElementById('fImageUpload').value = '';
    }
}

function removeImage(idx) {
    uploadedImages.splice(idx, 1);
    renderImageCards();
    syncPreview();
}

function renderImageCards() {
    const grid  = document.getElementById('imageCardGrid');
    const empty = document.getElementById('imageCardEmpty');
    empty.style.display = uploadedImages.length ? 'none' : 'block';
    grid.innerHTML = uploadedImages.map((url, i) => `
        <div class="image-card">
            <img src="${url}" onerror="this.src='https://via.placeholder.com/200x150?text=Image'">
            <button type="button" class="remove-img-btn" onclick="removeImage(${i})"><i class="fas fa-times"></i></button>
            ${i === 0 ? '<div class="main-badge">Main</div>' : ''}
        </div>`).join('');
}

// ── Review Summary ──
function buildReviewSummary() {
    const { totalRooms, startingPrice, types } = derivedTotals();
    const typeLabels = { single:'Single', double:'Double', triple:'Triple', quad:'Quad' };
    const roomTypeLines = Object.keys(types).map(t =>
        `${typeLabels[t]}: ${types[t].count} rooms @ PKR ${types[t].price.toLocaleString()}/night`
    ).join('<br>') || '—';

    const rows = [
        ['Hotel Name',       document.getElementById('fName').value || '—'],
        ['City',             getSelectedCity() || '—'],
        ['Address',          document.getElementById('fAddress').value || '—'],
        ['Room Types',       roomTypeLines],
        ['Total Rooms',      String(totalRooms)],
        ['Starting Price',   startingPrice ? `PKR ${Number(startingPrice).toLocaleString()} / night` : '—'],
        ['Check-in',         document.getElementById('fCheckin').value || '—'],
        ['Check-out',        document.getElementById('fCheckout').value || '—'],
        ['Amenities',        selectedAmenities.slice(0,6).join(', ') || '—'],
        ['Total Images',     String(uploadedImages.length)],
        ['Status',           document.getElementById('fStatus').value],
    ];
    document.getElementById('reviewSummary').innerHTML = rows.map(([k,v]) =>
        `<div style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid #f1f4f9;">
            <span style="min-width:140px;font-weight:700;color:#374151;">${k}</span>
            <span style="color:#475569;">${v}</span>
        </div>`).join('') + `
        <div style="margin-top:14px;padding:14px 16px;background:#f8faff;border-radius:12px;text-align:center;">
            <div style="font-size:12.5px;color:#374151;margin-bottom:4px;">⭐ Guest Rating (auto — from guest reviews)</div>
            <div style="font-size:20px;font-weight:800;color:#0f3460;">${computedReviews > 0 ? computedRating.toFixed(1)+' / 5' : 'No reviews yet'}</div>
            ${computedReviews > 0 ? `<div style="font-size:11.5px;color:#6b7280;margin-top:2px;">based on ${computedReviews} guest review${computedReviews===1?'':'s'}</div>` : ''}
        </div>`;
}

// ── Save to Firebase ──
async function saveHotel() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving to Firebase...';

    const extra = document.getElementById('fExtraAmenities').value
        .split(',').map(s=>s.trim()).filter(Boolean);
    const allFeatures = [...new Set([...selectedAmenities, ...extra])];

    const name = document.getElementById('fName').value.trim();
    const city = getSelectedCity();
    const { totalRooms, startingPrice, types } = derivedTotals();

    const hotelData = {
        name,
        city,
        citySlug:        city.toLowerCase().replace(/\s+/g,'-'),
        address:         document.getElementById('fAddress').value.trim(),
        description:     document.getElementById('fDescription').value.trim(),
        phone:           document.getElementById('fPhone').value.trim(),
        email:           document.getElementById('fEmail').value.trim(),
        lat:             parseFloat(document.getElementById('fLat').value) || 0,
        lng:             parseFloat(document.getElementById('fLng').value) || 0,
        landmark:        document.getElementById('fLandmark').value.trim(),
        distance:        document.getElementById('fDistance').value.trim(),
        stars:           computedStars,
        room_types:      types,
        price_per_night: startingPrice,
        total_rooms:     totalRooms,
        checkin_time:    document.getElementById('fCheckin').value,
        checkout_time:   document.getElementById('fCheckout').value,
        rating:          computedRating,
        reviews:         computedReviews,
        cancellationPolicy: {
            type: document.getElementById('fCancellationType').value,
            windowHours: Number(document.getElementById('fCancellationWindowHours').value) || 0,
            refundPercent: Number(document.getElementById('fCancellationRefundPercent').value) || 0
        },
        features:        allFeatures,
        image:           uploadedImages[0] || '',
        gallery:         uploadedImages.slice(1),
        status:          document.getElementById('fStatus').value,
        image_source:    'hotel_portal',
        staff_uid:       STAFF_UID,
        updatedAt:       firebase.firestore.FieldValue.serverTimestamp(),
    };

    try {
        let docId;
        if (EDIT_ID) {
            await db.collection('hotels').doc(EDIT_ID).update(hotelData);
            docId = EDIT_ID;
        } else {
            hotelData.createdAt = firebase.firestore.FieldValue.serverTimestamp();
            const ref = await db.collection('hotels').add(hotelData);
            docId = ref.id;
        }

        // Show sync badge
        const badge = document.getElementById('syncBadge');
        badge.style.display = 'inline-flex';
        badge.classList.add('show');

        await Swal.fire({
            icon: 'success',
            title: EDIT_ID ? 'Hotel Updated!' : 'Hotel Saved to Firebase! 🎉',
            html: `<b>${name}</b> has been ${EDIT_ID?'updated':'registered'} successfully.<br>
                   <small style="color:#64748b">Document ID: <code>${docId}</code></small>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#0f3460',
        }).then(() => {
            window.location.href = '/travelix/hotel_portal/edit_hotel.php?edit=' + docId;
        });

    } catch(err) {
        const div = document.getElementById('saveResult');
        div.style.display = 'block';
        div.className = 'save-result error';
        div.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${err.message}`;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Retry Save';
    }
}

toggleCancellationFields();
</script>
<script src="/travelix/assets/js/travelix_portal_notifications.js"></script>
<script>
if (typeof firebase !== 'undefined' && <?= json_encode($editId) ?>) {
    window.travelixInitPortalNotifications({db:firebase.firestore(),filters:[['audience','==','hotel'],['hotelId','==',<?= json_encode($editId) ?>]],manageUrl:'/travelix/hotel_portal/notifications.php'});
}
</script>
</body>
</html>
