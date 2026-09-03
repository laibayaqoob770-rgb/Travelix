<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$baseUrl = '/travelix';
if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php'); exit;
}
$currentUser = $_SESSION['user'] ?? [];
if (strtolower($currentUser['role'] ?? '') !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/admin_dashboard.php'); exit;
}
$editId = trim($_GET['edit'] ?? '');
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $editId ? 'Edit Hotel' : 'Add Hotel' ?> — Travelix Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/leaflet/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<script src="/travelix/assets/js/travelix_autocomplete.js"></script>
<script src="/travelix/assets/vendor/leaflet/leaflet.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh;}
.admin-layout{display:flex;gap:24px;padding:16px;min-height:100vh;}
.admin-main{flex:1;min-width:0;}

@media (max-width:991.98px){
.admin-layout{display:block;padding:0;}
.admin-main{padding:0 16px 16px;}
}

.page-header{background:linear-gradient(135deg,#133c96,#1e50c0,#2d6af0);border-radius:22px;padding:26px 32px;color:#fff;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header h1{font-size:20px;font-weight:800;margin:0 0 3px;}
.page-header p{font-size:13px;opacity:.8;margin:0;}
.back-btn{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);color:#fff;border:none;padding:10px 18px;border-radius:12px;font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;transition:.2s;white-space:nowrap;}
.back-btn:hover{background:rgba(255,255,255,.25);color:#fff;}

.form-card{background:#fff;border-radius:22px;box-shadow:0 2px 14px rgba(0,0,0,.07);padding:32px;}

.section-divider{margin:28px 0 22px;padding-top:22px;border-top:1px solid #f1f4f9;}
.section-title{font-size:15px;font-weight:800;color:#133c96;margin-bottom:4px;display:flex;align-items:center;gap:8px;}
.section-desc{font-size:12.5px;color:#6b7280;margin-bottom:18px;}

/* Fields */
.field-group{margin-bottom:22px;}
.field-label{font-size:13px;font-weight:700;color:#374151;margin-bottom:7px;display:block;}
.req{color:#ef4444;margin-left:2px;}
.opt{font-size:11px;font-weight:500;color:#9ca3af;margin-left:4px;}
.field-input{width:100%;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:14px;color:#1e293b;background:#fafbfc;transition:.2s;outline:none;}
.field-input:focus{border-color:#133c96;background:#fff;box-shadow:0 0 0 3px rgba(19,60,150,.10);}
.field-input.error{border-color:#ef4444;background:#fff5f5;}

.staff-box{background:#f8faff;border:1.5px solid #dce8ff;border-radius:16px;padding:20px 20px 6px;}
.staff-existing{background:#eefaf3;border:1.5px solid #bbf0d4;border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;}
.staff-existing-icon{width:38px;height:38px;min-width:38px;border-radius:10px;background:#22c55e;display:flex;align-items:center;justify-content:center;font-size:17px;}
.staff-existing-info strong{display:block;font-size:14px;font-weight:700;color:#166534;}
.staff-existing-info span{font-size:12.5px;color:#4ade80;}

.row-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:600px){.row-2{grid-template-columns:1fr;}}

.city-search-row{display:flex;gap:10px;}
.city-search-row .field-input{flex:1;}
.find-hotels-btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#133c96,#2d6af0);color:#fff;border:none;padding:0 20px;border-radius:12px;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap;transition:.2s;}
.find-hotels-btn:hover:not(:disabled){opacity:.9;}
.find-hotels-btn:disabled{opacity:.55;cursor:not-allowed;}
.search-status{font-size:12.5px;margin-top:8px;color:#6b7280;display:none;}
.search-status.show{display:block;}
.search-status.ok{color:#166534;}
.search-status.warn{color:#b45309;}

.hotel-pick-wrap{display:none;margin-top:8px;}
.hotel-pick-wrap.show{display:block;}

#hotelMap{height:260px;border-radius:16px;margin-top:12px;display:none;}
#hotelMap.show{display:block;}

/* Save row */
.save-row{display:flex;align-items:center;gap:12px;margin-top:28px;padding-top:22px;border-top:1px solid #f1f4f9;}
.save-btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#133c96,#2d6af0);color:#fff;border:none;padding:13px 32px;border-radius:14px;font-weight:800;font-size:15px;cursor:pointer;transition:.2s;}
.save-btn:hover{opacity:.9;}
.save-btn:disabled{opacity:.5;cursor:not-allowed;}
.cancel-btn{display:inline-flex;align-items:center;gap:8px;background:#f1f5f9;color:#374151;border:none;padding:13px 22px;border-radius:14px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;transition:.2s;}
.cancel-btn:hover{background:#e2e8f0;color:#374151;}
</style>
</head>
<body>
<div class="admin-layout">
    <?php include $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1><?= $editId ? '✏️ Edit Hotel' : '➕ Add New Hotel' ?></h1>
                <p><?= $editId ? 'Update the hotel details below.' : 'Add a new hotel listing to Travelix.' ?></p>
            </div>
            <a href="manage_hotels_portal.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Hotels
            </a>
        </div>

        <!-- Form -->
        <div class="form-card">

            <!-- City + Auto Hotel Search -->
            <div class="field-group">
                <label class="field-label">City <span class="req">*</span></label>
                <div class="city-search-row">
                    <input type="text" class="field-input" id="fCity" placeholder="e.g. Lahore, Multan, Gwadar...">
                    <button type="button" class="find-hotels-btn" id="findHotelsBtn">
                        <i class="fas fa-search"></i> Find Hotels
                    </button>
                </div>
                <p class="search-status" id="searchStatus"></p>

                <div class="hotel-pick-wrap" id="hotelPickWrap">
                    <label class="field-label" style="margin-top:14px;">Select Hotel</label>
                    <input type="text" class="field-input" id="fHotelPick" placeholder="Type to search the results below...">
                    <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
                        <i class="fas fa-info-circle" style="color:#133c96;"></i>
                        Picking a hotel fills in the name, address and map location below — all fields stay editable.
                    </p>
                </div>

                <div id="hotelMap"></div>
            </div>

            <!-- Hotel Details -->
            <div class="row-2">
                <div class="field-group">
                    <label class="field-label">Hotel Name <span class="req">*</span></label>
                    <input type="text" class="field-input" id="fName" placeholder="e.g. Pearl Continental Lahore">
                </div>
                <div class="field-group">
                    <label class="field-label">Address <span class="req">*</span></label>
                    <input type="text" class="field-input" id="fAddress" placeholder="e.g. Mall Road, Lahore">
                </div>
            </div>

            <input type="hidden" id="fLat" value="">
            <input type="hidden" id="fLng" value="">

            <div class="field-group">
                <label class="field-label">Status <span class="req">*</span></label>
                <select class="field-input" id="fStatus">
                    <option value="active">Active — Visible to users</option>
                    <option value="draft">Draft — Hidden from users</option>
                    <option value="inactive">Inactive — Temporarily disabled</option>
                </select>
            </div>

            <!-- Payment Account -->
            <div class="section-divider">
                <div class="section-title">💳 Hotel Payment Account</div>
                <div class="section-desc">
                    Guests will send payment to this account and attach a proof screenshot when booking. Your staff will verify the proof before confirming each booking.
                </div>

                <div class="row-2">
                    <div class="field-group">
                        <label class="field-label">Payment Method <span class="req">*</span></label>
                        <select class="field-input" id="fPaymentMethod">
                            <option value="">— Select Method —</option>
                            <option value="easypaisa">EasyPaisa</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="raast">Raast</option>
                            <option value="bank">Bank Account</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label" id="fPaymentAccountNumberLabel">Account Number <span class="req">*</span></label>
                        <input type="text" class="field-input" id="fPaymentAccountNumber" placeholder="e.g. 03XXXXXXXXX">
                    </div>
                </div>

                <div class="row-2" id="fBankNameWrap" style="display:none;">
                    <div class="field-group">
                        <label class="field-label">Bank Name <span class="req">*</span></label>
                        <select class="field-input" id="fBankName">
                            <option value="">— Select Bank —</option>
                            <option value="Allied Bank">Allied Bank</option>
                            <option value="Al Baraka Bank">Al Baraka Bank</option>
                            <option value="Askari Bank">Askari Bank</option>
                            <option value="Bank Al Habib">Bank Al Habib</option>
                            <option value="Bank Alfalah">Bank Alfalah</option>
                            <option value="Bank of Khyber">Bank of Khyber</option>
                            <option value="Bank of Punjab">Bank of Punjab</option>
                            <option value="BankIslami Pakistan">BankIslami Pakistan</option>
                            <option value="Dubai Islamic Bank Pakistan">Dubai Islamic Bank Pakistan</option>
                            <option value="Faysal Bank">Faysal Bank</option>
                            <option value="Habib Bank Limited (HBL)">Habib Bank Limited (HBL)</option>
                            <option value="Habib Metropolitan Bank">Habib Metropolitan Bank</option>
                            <option value="JS Bank">JS Bank</option>
                            <option value="MCB Bank">MCB Bank</option>
                            <option value="MCB Islamic Bank">MCB Islamic Bank</option>
                            <option value="Meezan Bank">Meezan Bank</option>
                            <option value="National Bank of Pakistan">National Bank of Pakistan</option>
                            <option value="Samba Bank">Samba Bank</option>
                            <option value="Silk Bank">Silk Bank</option>
                            <option value="Soneri Bank">Soneri Bank</option>
                            <option value="Standard Chartered Bank">Standard Chartered Bank</option>
                            <option value="Summit Bank">Summit Bank</option>
                            <option value="United Bank Limited (UBL)">United Bank Limited (UBL)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Staff Assignment -->
            <div class="section-divider">
                <div class="section-title">👤 Assign Hotel Staff</div>
                <div class="section-desc">
                    <?= $editId
                        ? 'Assign or replace the staff account linked to this hotel. Leave blank to keep the existing staff.'
                        : 'Optionally assign a staff member to manage this hotel. A login password will be auto-generated.' ?>
                </div>

                <!-- Existing staff badge — shown when editing a hotel that already has staff -->
                <div class="staff-existing" id="existingStaffBadge" style="display:none;">
                    <div class="staff-existing-icon">✅</div>
                    <div class="staff-existing-info">
                        <strong id="existingStaffEmail">—</strong>
                        <span>Staff already assigned — enter a new email below to replace</span>
                    </div>
                </div>

                <div class="staff-box">
                    <div class="field-group">
                        <label class="field-label">
                            Staff Email <?php if (!$editId): ?><span class="req">*</span><?php else: ?><span class="opt">(leave blank to keep existing)</span><?php endif; ?>
                        </label>
                        <input type="email" class="field-input" id="fStaffEmail"
                               placeholder="e.g. staff@hotelname.com">
                        <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
                            <i class="fas fa-info-circle" style="color:#133c96;"></i>
                            <?php if (!$editId): ?>
                                Required. A 12-character password will be auto-generated and shown once after saving.
                            <?php else: ?>
                                Enter a new email only if you want to replace the current staff account.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="save-row">
                <button type="button" class="save-btn" id="saveBtn" onclick="saveHotel()">
                    <i class="fas fa-save"></i>
                    <?= $editId ? 'Update Hotel' : 'Save Hotel' ?>
                </button>
                <a href="manage_hotels_portal.php" class="cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </div>
    </main>
</div>

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

const EDIT_ID = <?= json_encode($editId ?: '') ?>;

function getSelectedCity() {
    return document.getElementById('fCity').value.trim();
}

// ── Hotel map (Leaflet, self-hosted, no API key) ──
const hotelMapEl = document.getElementById('hotelMap');
let hotelMap = null;
let hotelMarker = null;

function showHotelOnMap(lat, lng) {
    const numLat = Number(lat);
    const numLng = Number(lng);
    if (Number.isNaN(numLat) || Number.isNaN(numLng)) return;

    hotelMapEl.classList.add('show');

    if (!hotelMap) {
        hotelMap = L.map(hotelMapEl, { scrollWheelZoom: false }).setView([numLat, numLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(hotelMap);
        hotelMarker = L.marker([numLat, numLng], { draggable: true }).addTo(hotelMap);
        hotelMarker.on('dragend', function () {
            const pos = hotelMarker.getLatLng();
            document.getElementById('fLat').value = pos.lat.toFixed(6);
            document.getElementById('fLng').value = pos.lng.toFixed(6);
        });
    } else {
        hotelMarker.setLatLng([numLat, numLng]);
        hotelMap.setView([numLat, numLng], 15);
    }

    setTimeout(() => hotelMap.invalidateSize(), 150);
}

// ── Payment method: toggle bank name field ──
const fPaymentMethod = document.getElementById('fPaymentMethod');
const fBankNameWrap = document.getElementById('fBankNameWrap');
const fBankName = document.getElementById('fBankName');
const fPaymentAccountNumberLabel = document.getElementById('fPaymentAccountNumberLabel');

function syncBankFieldVisibility() {
    const isBank = fPaymentMethod.value === 'bank';
    fBankNameWrap.style.display = isBank ? 'block' : 'none';
    fPaymentAccountNumberLabel.innerHTML = (isBank ? 'Account Number / IBAN' : 'Account Number') + ' <span class="req">*</span>';
}
fPaymentMethod.addEventListener('change', syncBankFieldVisibility);
syncBankFieldVisibility();

// ── Find Hotels (OpenStreetMap Overpass search) ──
let fetchedHotels = [];
const findHotelsBtn = document.getElementById('findHotelsBtn');
const searchStatus = document.getElementById('searchStatus');
const hotelPickWrap = document.getElementById('hotelPickWrap');
const fHotelPick = document.getElementById('fHotelPick');

function hotelLabel(h) {
    return `${h.name} (${h.type})${h.address ? ' — ' + h.address : ''}`;
}

const fHotelPickAutocomplete = travelixAutocomplete(fHotelPick, {
    getItems: () => fetchedHotels.map((h, i) => ({ value: String(i), label: hotelLabel(h) })),
    strict: false,
    emptyText: 'No hotels in the results match that.',
    onSelect: (item) => {
        const hotel = fetchedHotels[Number(item.value)];
        if (!hotel) return;

        document.getElementById('fName').value = hotel.name || '';
        if (hotel.address) {
            document.getElementById('fAddress').value = hotel.address;
        }
        document.getElementById('fLat').value = hotel.lat ?? '';
        document.getElementById('fLng').value = hotel.lng ?? '';

        showHotelOnMap(hotel.lat, hotel.lng);
    }
});

function setSearchStatus(text, cls) {
    searchStatus.textContent = text;
    searchStatus.className = 'search-status show' + (cls ? ' ' + cls : '');
}

findHotelsBtn.addEventListener('click', async function () {
    const city = getSelectedCity();
    if (!city) {
        err('fCity');
        setSearchStatus('Enter a city name first.', 'warn');
        return;
    }

    const originalHtml = findHotelsBtn.innerHTML;
    findHotelsBtn.disabled = true;
    findHotelsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    setSearchStatus('Searching hotels in ' + city + '...', '');

    try {
        const resp = await fetch(`/travelix/admin_manage/ajax/search_city_hotels.php?city=${encodeURIComponent(city)}`, {
            credentials: 'same-origin'
        });
        const result = await resp.json();

        if (!result.success) {
            setSearchStatus(result.message || 'Search failed.', 'warn');
            return;
        }

        fetchedHotels = Array.isArray(result.hotels) ? result.hotels : [];
        setSearchStatus(result.message || '', fetchedHotels.length ? 'ok' : 'warn');

        fHotelPickAutocomplete.clear();
        fHotelPickAutocomplete.refresh();
        hotelPickWrap.classList.toggle('show', fetchedHotels.length > 0);

        if (result.cityLat && result.cityLng && !fetchedHotels.length) {
            showHotelOnMap(result.cityLat, result.cityLng);
        }
    } catch (error) {
        setSearchStatus('Search failed: ' + error.message, 'warn');
    } finally {
        findHotelsBtn.disabled = false;
        findHotelsBtn.innerHTML = originalHtml;
    }
});

if (EDIT_ID) {
    db.collection('hotels').doc(EDIT_ID).get().then(doc => {
        if (!doc.exists) return;
        const h = doc.data();
        document.getElementById('fName').value    = h.name    || '';
        document.getElementById('fCity').value    = h.city    || '';
        document.getElementById('fAddress').value = h.address || '';
        document.getElementById('fStatus').value  = h.status  || 'active';
        document.getElementById('fPaymentMethod').value = h.paymentMethod || '';
        document.getElementById('fPaymentAccountNumber').value = h.paymentAccountNumber || '';
        document.getElementById('fBankName').value = h.bankName || '';
        syncBankFieldVisibility();

        if (h.lat != null && h.lng != null) {
            document.getElementById('fLat').value = h.lat;
            document.getElementById('fLng').value = h.lng;
            showHotelOnMap(h.lat, h.lng);
        }

        // Show existing staff badge if staff is already assigned
        if (h.staff_email) {
            document.getElementById('existingStaffEmail').textContent = h.staff_email;
            document.getElementById('existingStaffBadge').style.display = 'flex';
        }
    });
}

function err(id) {
    const el = document.getElementById(id);
    el.classList.add('error');
    el.focus();
    setTimeout(() => el.classList.remove('error'), 3000);
}

async function saveHotel() {
    const name = document.getElementById('fName').value.trim();
    const city = getSelectedCity();
    const addr = document.getElementById('fAddress').value.trim();

    if (!name) { err('fName'); Swal.fire({icon:'warning',title:'Required',text:'Enter the hotel name.',confirmButtonColor:'#133c96'}); return; }
    if (!city) {
        err('fCity');
        Swal.fire({icon:'warning',title:'Required',text:'Enter a city.',confirmButtonColor:'#133c96'});
        return;
    }
    if (!addr) { err('fAddress'); Swal.fire({icon:'warning',title:'Required',text:'Enter the hotel address.',confirmButtonColor:'#133c96'}); return; }

    const paymentMethod = document.getElementById('fPaymentMethod').value;
    const paymentAccountNumber = document.getElementById('fPaymentAccountNumber').value.trim();
    const bankName = document.getElementById('fBankName').value;

    if (!paymentMethod) { err('fPaymentMethod'); Swal.fire({icon:'warning',title:'Required',text:'Select a payment method.',confirmButtonColor:'#133c96'}); return; }
    if (paymentMethod === 'bank' && !bankName) { err('fBankName'); Swal.fire({icon:'warning',title:'Required',text:'Select the bank.',confirmButtonColor:'#133c96'}); return; }
    if (!paymentAccountNumber) { err('fPaymentAccountNumber'); Swal.fire({icon:'warning',title:'Required',text:'Enter the payment account number.',confirmButtonColor:'#133c96'}); return; }

    const staffEmail = document.getElementById('fStaffEmail').value.trim();

    // Staff email is required when adding a new hotel
    if (!EDIT_ID && !staffEmail) {
        err('fStaffEmail');
        Swal.fire({icon:'warning',title:'Staff Email Required',text:'Enter a staff email address. The staff member will use it to log in and manage this hotel.',confirmButtonColor:'#133c96'});
        return;
    }
    if (staffEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(staffEmail)) {
        err('fStaffEmail');
        Swal.fire({icon:'warning',title:'Invalid Email',text:'Please enter a valid staff email address.',confirmButtonColor:'#133c96'});
        return;
    }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    // A hotel with the same name shouldn't exist twice — catches accidental
    // double-submits (e.g. a double-click) as well as someone re-adding a
    // hotel that's already in the system. Case-insensitive so "Capital
    // Grande" and "capital grande" still count as the same hotel.
    try {
        const nameKey = name.toLowerCase();
        const existingSnap = await db.collection('hotels').get();
        const dupe = existingSnap.docs.find(d => {
            if (EDIT_ID && d.id === EDIT_ID) return false;
            return String(d.data().name || '').trim().toLowerCase() === nameKey;
        });
        if (dupe) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> <?= $editId ? "Update Hotel" : "Save Hotel" ?>';
            err('fName');
            Swal.fire({icon:'warning',title:'Hotel Already Exists',text:`A hotel named "${name}" is already in the system. Use a different name, or edit the existing hotel instead.`,confirmButtonColor:'#133c96'});
            return;
        }
    } catch (dupeErr) {
        // If the duplicate check itself fails (e.g. offline), don't block
        // saving entirely — just proceed without the safety net this time.
    }

    const latVal = document.getElementById('fLat').value;
    const lngVal = document.getElementById('fLng').value;

    const data = {
        name,
        city,
        citySlug:  city.toLowerCase().replace(/\s+/g, '-'),
        address:   addr,
        lat:       latVal !== '' ? Number(latVal) : null,
        lng:       lngVal !== '' ? Number(lngVal) : null,
        status:    document.getElementById('fStatus').value,
        paymentMethod,
        paymentAccountNumber,
        bankName:  paymentMethod === 'bank' ? bankName : '',
        added_by:  'admin',
        updatedAt: firebase.firestore.FieldValue.serverTimestamp(),
    };

    try {
        let docId;
        if (EDIT_ID) {
            await db.collection('hotels').doc(EDIT_ID).update(data);
            docId = EDIT_ID;
        } else {
            data.createdAt = firebase.firestore.FieldValue.serverTimestamp();
            const ref = await db.collection('hotels').add(data);
            docId = ref.id;
        }

        // ── Assign staff if email provided ──
        let staffInfo = null;
        if (staffEmail) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating staff account...';
            try {
                const staffRes = await fetch('/travelix/admin_manage/create_hotel_staff.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: staffEmail, hotelId: docId, hotelName: name, city })
                });
                staffInfo = await staffRes.json();
            } catch (staffErr) {
                staffInfo = { success: false, error: staffErr.message };
            }
        }

        // ── Success modal ──
        let successHtml = `<b>${name}</b> has been ${EDIT_ID ? 'updated' : 'added'} successfully.`;

        if (staffInfo) {
            if (staffInfo.success) {
                successHtml += `
                    <div style="margin-top:16px;padding:18px;background:#f0fdf4;border:2px solid #bbf7d0;border-radius:14px;text-align:center;">
                        <div style="font-size:30px;margin-bottom:8px;">📧</div>
                        <p style="font-size:14px;font-weight:700;color:#166534;margin:0 0 6px;">Login credentials sent to staff!</p>
                        <code style="background:#dcfce7;padding:5px 14px;border-radius:8px;font-size:13px;color:#166534;font-weight:700;">${staffInfo.email}</code>
                        <p style="margin:10px 0 0;font-size:12px;color:#6b7280;">Staff will receive their email and password in their inbox.</p>
                    </div>`;
            } else {
                successHtml += `
                    <div style="margin-top:14px;padding:12px 16px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:12px;text-align:left;font-size:13px;color:#b91c1c;">
                        ⚠️ Hotel saved, but staff creation failed: ${staffInfo.error || 'Unknown error'}
                    </div>`;
            }
        }

        await Swal.fire({
            icon: 'success',
            title: EDIT_ID ? '✅ Updated!' : '🎉 Hotel Added!',
            html: successHtml,
            confirmButtonText: 'Back to Hotels',
            showCancelButton: !EDIT_ID && !staffInfo,
            cancelButtonText: 'Add Another',
            confirmButtonColor: '#133c96',
            width: staffInfo?.success ? 520 : 440,
        }).then(r => {
            if (EDIT_ID || r.isConfirmed) window.location.href = 'manage_hotels_portal.php';
            else window.location.reload();
        });

    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> <?= $editId ? "Update Hotel" : "Save Hotel" ?>';
        Swal.fire('Error', e.message, 'error');
    }
}
</script>
</body>
</html>
