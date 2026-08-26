<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'] ?? [];
$userRole    = strtolower((string)($currentUser['role'] ?? 'user'));

// Allow both admin and hotel staff
$firstName    = (string)($currentUser['first_name'] ?? 'User');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hotel Registration — Travelix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; min-height:100vh; }

        .admin-layout { display:flex; gap:24px; padding:16px; min-height:100vh; }
        .admin-main  { flex:1; min-width:0; }

        @media (max-width:991px) {
            .admin-layout { display:block; padding:0; }
            .admin-main { padding:0 16px 16px; }
        }

        /* Page header */
        .page-header {
            background: linear-gradient(135deg,#133c96,#1e50c0,#2d6af0);
            border-radius:24px; padding:36px 32px; color:#fff;
            margin-bottom:24px; position:relative; overflow:hidden;
        }
        .page-header::before {
            content:''; position:absolute; top:-40%; right:-15%;
            width:380px; height:380px;
            background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%);
            border-radius:50%;
        }
        .page-header h1 { font-size:26px; font-weight:800; margin-bottom:6px; position:relative; }
        .page-header p  { font-size:14px; opacity:0.82; position:relative; }

        /* Live indicator */
        .live-pill {
            display:inline-flex; align-items:center; gap:8px;
            background:rgba(239,68,68,0.18); border:1px solid rgba(239,68,68,0.3);
            border-radius:999px; padding:6px 14px; font-size:12px; font-weight:700;
            color:#fca5a5; margin-top:12px; position:relative;
        }
        .live-pill .dot { width:8px; height:8px; background:#ef4444; border-radius:50%; animation:pulse 1.2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.5)} 50%{box-shadow:0 0 0 6px rgba(239,68,68,0)} }

        /* Two-column layout */
        .form-layout { display:grid; grid-template-columns:1fr 420px; gap:22px; align-items:start; }

        .card-box {
            background:#fff; border-radius:20px; padding:28px;
            box-shadow:0 2px 12px rgba(0,0,0,0.06);
            margin-bottom:20px;
        }
        .card-box h3 { font-weight:700; font-size:17px; margin-bottom:18px; color:#1a1a2e; display:flex; align-items:center; gap:10px; }

        /* Form fields */
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:7px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; border:1.5px solid #e2e8f0; border-radius:12px;
            padding:13px 15px; font-size:14px; color:#1e293b; background:#fff;
            outline:none; transition:0.2s; font-family:inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color:#2d6af0; box-shadow:0 0 0 3px rgba(45,106,240,0.12);
        }
        .form-group textarea { resize:vertical; min-height:80px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

        /* Feature tags */
        .tags-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .feature-tag {
            display:inline-flex; align-items:center; gap:6px;
            background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca;
            border-radius:999px; padding:6px 14px; font-size:13px; font-weight:600;
            cursor:pointer; transition:0.2s;
        }
        .feature-tag.selected { background:#4338ca; border-color:#4338ca; color:#fff; }
        .feature-tag:hover { opacity:0.8; }

        /* Image preview */
        .img-preview {
            width:100%; height:180px; border-radius:14px; object-fit:cover;
            background:#f8f9ff; border:2px dashed #e2e8f0;
            display:flex; align-items:center; justify-content:center;
            font-size:13px; color:#94a3b8; overflow:hidden;
        }
        .img-preview img { width:100%; height:100%; object-fit:cover; border-radius:12px; }

        /* Star selector */
        .star-selector { display:flex; gap:8px; }
        .star-btn {
            width:44px; height:44px; border-radius:12px; border:2px solid #e2e8f0;
            background:#fff; font-size:18px; cursor:pointer; transition:0.2s;
            display:flex; align-items:center; justify-content:center;
        }
        .star-btn.selected { border-color:#f59e0b; background:#fffbeb; }
        .star-btn:hover { border-color:#f59e0b; }

        /* Submit button */
        .btn-submit {
            width:100%; padding:16px; border:none; border-radius:14px;
            background:linear-gradient(135deg,#2d6af0,#1e50c0); color:#fff;
            font-size:16px; font-weight:800; cursor:pointer; transition:0.25s;
            display:flex; align-items:center; justify-content:center; gap:10px;
        }
        .btn-submit:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 24px rgba(45,106,240,0.35); }
        .btn-submit:disabled { opacity:0.55; cursor:not-allowed; }

        /* Right side: Live preview */
        .preview-panel { position:sticky; top:20px; }

        .preview-hotel-card {
            background:#fff; border-radius:20px; overflow:hidden;
            border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,0.07);
        }
        .preview-img { width:100%; height:200px; object-fit:cover; background:#f0f2f5; display:block; }
        .preview-body { padding:18px; }
        .preview-name { font-size:20px; font-weight:800; color:#1e293b; margin-bottom:8px; min-height:28px; }
        .preview-city { font-size:13px; color:#64748b; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
        .preview-meta { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .preview-chip { background:#f1f5f9; border-radius:999px; padding:5px 12px; font-size:12px; font-weight:600; color:#475569; }
        .preview-price { font-size:22px; font-weight:800; color:#1e293b; }
        .preview-price-label { font-size:12px; color:#94a3b8; }
        .preview-features { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .preview-feature { background:#eef2ff; color:#4338ca; border-radius:8px; padding:4px 10px; font-size:11px; font-weight:600; }

        /* Saved hotels list */
        .saved-hotel-item {
            display:flex; align-items:center; gap:12px;
            padding:12px; background:#f8f9ff; border-radius:14px;
            border:1px solid #e8ecf4; margin-bottom:10px;
            animation:slideIn 0.4s ease;
        }
        @keyframes slideIn { from{opacity:0;transform:translateX(-15px)} to{opacity:1;transform:translateX(0)} }
        .saved-hotel-img { width:52px; height:52px; border-radius:10px; object-fit:cover; background:#e2e8f0; flex-shrink:0; }
        .saved-hotel-info h5 { font-size:14px; font-weight:700; margin:0 0 3px; }
        .saved-hotel-info p  { font-size:12px; color:#64748b; margin:0; }
        .saved-badge { margin-left:auto; background:#ecfdf5; color:#059669; border-radius:8px; padding:4px 10px; font-size:11px; font-weight:700; flex-shrink:0; }

        @media (max-width:991px) {
            .form-layout { grid-template-columns:1fr; }
            .preview-panel { position:static; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/admin_sidebar.php'; ?>

    <div class="admin-main">

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-hotel"></i> &nbsp;Hotel Registration System</h1>
            <p>Hotels fill in their details here. Data saves to Firebase instantly — users see it on the hotel search page in real-time.</p>
            <div class="live-pill"><span class="dot"></span> Connected to Firebase — changes appear live for users</div>
        </div>

        <div class="form-layout">

            <!-- ===== LEFT: FORM ===== -->
            <div>

                <!-- Basic Info -->
                <div class="card-box">
                    <h3><i class="fas fa-info-circle" style="color:#2d6af0"></i> Basic Hotel Information</h3>

                    <div class="form-group">
                        <label>Hotel Name *</label>
                        <input type="text" id="hotelName" placeholder="e.g. Grand Serena Hotel" maxlength="100">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>City *</label>
                            <select id="hotelCity">
                                <option value="">Select City</option>
                                <option>Islamabad</option>
                                <option>Lahore</option>
                                <option>Karachi</option>
                                <option>Murree</option>
                                <option>Hunza</option>
                                <option>Swat</option>
                                <option>Peshawar</option>
                                <option>Quetta</option>
                                <option>Faisalabad</option>
                                <option>Multan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hotel Stars</label>
                            <div class="star-selector" id="starSelector">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <button type="button" class="star-btn" data-stars="<?php echo $s; ?>">
                                    <?php echo str_repeat('★', $s); ?>
                                </button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="hotelStars" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Full Address *</label>
                        <input type="text" id="hotelAddress" placeholder="e.g. Faisal Avenue, F-8, Islamabad">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="hotelDesc" placeholder="Brief description of the hotel, surroundings, special features..."></textarea>
                    </div>
                </div>

                <!-- Image -->
                <div class="card-box">
                    <h3><i class="fas fa-image" style="color:#10b981"></i> Hotel Image</h3>

                    <div class="form-group">
                        <label>Image URL *</label>
                        <input type="url" id="hotelImage" placeholder="https://images.unsplash.com/...">
                    </div>

                    <div class="img-preview" id="imgPreviewBox">
                        <img id="imgPreviewEl" src="" alt="" style="display:none;">
                        <span id="imgPreviewPlaceholder"><i class="fas fa-image" style="font-size:28px;margin-bottom:8px;display:block;"></i>Paste image URL above to preview</span>
                    </div>
                </div>

                <!-- Pricing & Rating -->
                <div class="card-box">
                    <h3><i class="fas fa-tag" style="color:#f59e0b"></i> Pricing & Rating</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Price per Night (PKR) *</label>
                            <input type="number" id="hotelPrice" placeholder="e.g. 15000" min="0">
                        </div>
                        <div class="form-group">
                            <label>Rating (0–10)</label>
                            <input type="number" id="hotelRating" placeholder="e.g. 8.5" min="0" max="10" step="0.1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Reviews</label>
                            <input type="number" id="hotelReviews" placeholder="e.g. 320" min="0">
                        </div>
                        <div class="form-group">
                            <label>Coordinates (optional)</label>
                            <input type="text" id="hotelLatLng" placeholder="lat, lng — e.g. 33.6844, 73.0479">
                        </div>
                    </div>
                </div>

                <!-- Payment Account -->
                <div class="card-box">
                    <h3><i class="fas fa-money-bill-wave" style="color:#16a34a"></i> Payment Account</h3>
                    <p style="font-size:13px;color:#64748b;margin-bottom:14px;">
                        Guests will send payment here and attach a proof screenshot when booking. You'll verify the proof before confirming each booking.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select id="hotelPaymentMethod">
                                <option value="">— Select Method —</option>
                                <option value="easypaisa">EasyPaisa</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="raast">Raast</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="hotelPaymentAccountNumberLabel">Account Number *</label>
                            <input type="text" id="hotelPaymentAccountNumber" placeholder="e.g. 03XXXXXXXXX">
                        </div>
                    </div>

                    <div class="form-row" id="hotelBankNameWrap" style="display:none;">
                        <div class="form-group">
                            <label>Bank Name *</label>
                            <select id="hotelBankName">
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

                <!-- Amenities -->
                <div class="card-box">
                    <h3><i class="fas fa-concierge-bell" style="color:#8b5cf6"></i> Amenities & Features</h3>
                    <p style="font-size:13px;color:#64748b;margin-bottom:14px;">Click to select what your hotel offers:</p>

                    <div class="tags-wrap" id="featureTags">
                        <?php
                        $allFeatures = [
                            'Free WiFi','Swimming Pool','Spa','Restaurant','Room Service',
                            'Gym','Parking','Airport Shuttle','Business Center','Fine Dining',
                            'Bar / Lounge','Laundry','Balcony','Sea View','Mountain View',
                            'Garden','Conference Hall','Kids Club','Pet Friendly','Breakfast Included',
                        ];
                        foreach ($allFeatures as $f):
                        ?>
                        <span class="feature-tag" data-feature="<?php echo htmlspecialchars($f); ?>">
                            <?php echo htmlspecialchars($f); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="hotelFeatures" value="[]">
                </div>

                <!-- Submit -->
                <div class="card-box">
                    <button type="button" class="btn-submit" id="btnSaveHotel">
                        <i class="fas fa-cloud-upload-alt"></i> Save Hotel to Firebase — Goes Live Instantly
                    </button>
                    <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:12px;">
                        Once saved, this hotel appears on the user-facing search page in real-time.
                    </p>
                </div>

                <!-- Recently saved hotels -->
                <div class="card-box" id="savedListSection" style="display:none;">
                    <h3><i class="fas fa-check-circle" style="color:#10b981"></i> Recently Saved Hotels</h3>
                    <div id="savedHotelList"></div>
                </div>

            </div>

            <!-- ===== RIGHT: LIVE PREVIEW ===== -->
            <div class="preview-panel">
                <div class="card-box" style="margin-bottom:0;">
                    <h3><i class="fas fa-eye" style="color:#f59e0b"></i> Live Preview</h3>
                    <p style="font-size:12px;color:#64748b;margin-bottom:16px;">This is how users will see your hotel on the search page.</p>

                    <div class="preview-hotel-card">
                        <img id="previewImg" class="preview-img"
                             src="https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=600&auto=format&fit=crop"
                             alt="Hotel Preview"
                             onerror="this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=600&auto=format&fit=crop'">
                        <div class="preview-body">
                            <div class="preview-name" id="previewName">Your Hotel Name</div>
                            <div class="preview-city">
                                <i class="fas fa-map-marker-alt" style="color:#2d6af0;"></i>
                                <span id="previewCity">City</span>
                            </div>
                            <div class="preview-meta">
                                <span class="preview-chip" id="previewStars">⭐ Stars</span>
                                <span class="preview-chip" id="previewRating">👍 Rating</span>
                                <span class="preview-chip" id="previewReviews">💬 Reviews</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-end;">
                                <div>
                                    <div class="preview-price-label">Per Night</div>
                                    <div class="preview-price" id="previewPrice">PKR —</div>
                                </div>
                                <button style="background:linear-gradient(135deg,#2d6af0,#1e50c0);color:#fff;border:none;border-radius:12px;padding:10px 18px;font-weight:700;font-size:13px;">Book Now →</button>
                            </div>
                            <div class="preview-features" id="previewFeatures"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /form-layout -->
    </div><!-- /admin-main -->
</div><!-- /admin-layout -->

<!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>

<script>
(function () {
    const firebaseConfig = {
        apiKey: "AIzaSyBWCOX2TjmnbxQrRmKkj7DTqOjoJJfjJR8",
        authDomain: "travelix-330b6.firebaseapp.com",
        projectId: "travelix-330b6",
        storageBucket: "travelix-330b6.firebasestorage.app",
        messagingSenderId: "948223226994",
        appId: "1:948223226994:web:a21f26a843b987ec0d3eeb"
    };

    if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
    const db = firebase.firestore();

    // ── Selected state ──
    let selectedStars    = 0;
    let selectedFeatures = [];

    // ── DOM refs ──
    const nameEl     = document.getElementById('hotelName');
    const cityEl     = document.getElementById('hotelCity');
    const addressEl  = document.getElementById('hotelAddress');
    const descEl     = document.getElementById('hotelDesc');
    const imageEl    = document.getElementById('hotelImage');
    const priceEl    = document.getElementById('hotelPrice');
    const ratingEl   = document.getElementById('hotelRating');
    const reviewsEl  = document.getElementById('hotelReviews');
    const latlngEl   = document.getElementById('hotelLatLng');
    const starsHidEl = document.getElementById('hotelStars');
    const featHidEl  = document.getElementById('hotelFeatures');

    // ── Payment method: toggle bank name field ──
    const hotelPaymentMethodEl = document.getElementById('hotelPaymentMethod');
    const hotelBankNameWrap = document.getElementById('hotelBankNameWrap');
    const hotelPaymentAccountNumberLabel = document.getElementById('hotelPaymentAccountNumberLabel');
    function syncHotelBankFieldVisibility() {
        const isBank = hotelPaymentMethodEl.value === 'bank';
        hotelBankNameWrap.style.display = isBank ? 'block' : 'none';
        hotelPaymentAccountNumberLabel.textContent = isBank ? 'Account Number / IBAN *' : 'Account Number *';
    }
    hotelPaymentMethodEl.addEventListener('change', syncHotelBankFieldVisibility);
    syncHotelBankFieldVisibility();

    // ── Live preview updates ──
    function updatePreview() {
        document.getElementById('previewName').textContent   = nameEl.value  || 'Your Hotel Name';
        document.getElementById('previewCity').textContent   = cityEl.value  || 'City';
        document.getElementById('previewStars').textContent  = selectedStars  ? '⭐ ' + selectedStars + ' Star' : '⭐ Stars';
        document.getElementById('previewRating').textContent = ratingEl.value ? '👍 ' + ratingEl.value : '👍 Rating';
        document.getElementById('previewReviews').textContent= reviewsEl.value? '💬 ' + Number(reviewsEl.value).toLocaleString() + ' reviews' : '💬 Reviews';
        document.getElementById('previewPrice').textContent  = priceEl.value  ? 'PKR ' + Number(priceEl.value).toLocaleString() : 'PKR —';

        const featsEl = document.getElementById('previewFeatures');
        featsEl.innerHTML = '';
        selectedFeatures.slice(0, 5).forEach(f => {
            const chip = document.createElement('span');
            chip.className = 'preview-feature';
            chip.textContent = f;
            featsEl.appendChild(chip);
        });

        const imgUrl = imageEl.value.trim();
        if (imgUrl) {
            const previewImg = document.getElementById('previewImg');
            previewImg.src = imgUrl;
        }
    }

    // Attach preview listeners
    [nameEl, cityEl, addressEl, priceEl, ratingEl, reviewsEl, imageEl].forEach(el => {
        el?.addEventListener('input', updatePreview);
        el?.addEventListener('change', updatePreview);
    });

    // ── Image URL preview ──
    imageEl.addEventListener('input', function () {
        const url = this.value.trim();
        const img = document.getElementById('imgPreviewEl');
        const placeholder = document.getElementById('imgPreviewPlaceholder');
        if (url) {
            img.src = url;
            img.style.display = 'block';
            placeholder.style.display = 'none';
            img.onerror = function () {
                img.style.display = 'none';
                placeholder.style.display = 'block';
            };
        } else {
            img.style.display = 'none';
            placeholder.style.display = 'block';
        }
    });

    // ── Stars selector ──
    document.querySelectorAll('.star-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            selectedStars = parseInt(this.dataset.stars);
            starsHidEl.value = selectedStars;
            document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            updatePreview();
        });
    });

    // ── Feature tags ──
    document.querySelectorAll('.feature-tag').forEach(tag => {
        tag.addEventListener('click', function () {
            const feat = this.dataset.feature;
            if (selectedFeatures.includes(feat)) {
                selectedFeatures = selectedFeatures.filter(f => f !== feat);
                this.classList.remove('selected');
            } else {
                selectedFeatures.push(feat);
                this.classList.add('selected');
            }
            featHidEl.value = JSON.stringify(selectedFeatures);
            updatePreview();
        });
    });

    // ── Generate hotel ID ──
    function generateId(name, city) {
        const base = (name + '_' + city).toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').slice(0, 30);
        return base + '_' + Date.now().toString(36);
    }

    // ── Parse lat/lng ──
    function parseLatLng(str) {
        if (!str.trim()) return { lat: 0, lng: 0 };
        const parts = str.split(',').map(p => parseFloat(p.trim()));
        return { lat: parts[0] || 0, lng: parts[1] || 0 };
    }

    // ── Save Hotel to Firebase ──
    document.getElementById('btnSaveHotel').addEventListener('click', async function () {
        const name    = nameEl.value.trim();
        const city    = cityEl.value.trim();
        const address = addressEl.value.trim();
        const image   = imageEl.value.trim();
        const price   = parseFloat(priceEl.value) || 0;
        const paymentMethod = document.getElementById('hotelPaymentMethod').value;
        const paymentAccountNumber = document.getElementById('hotelPaymentAccountNumber').value.trim();
        const bankName = document.getElementById('hotelBankName').value;

        if (!name)    { Swal.fire({ icon:'warning', title:'Hotel name required',   text:'Please enter the hotel name.',    confirmButtonColor:'#2d6af0' }); return; }
        if (!city)    { Swal.fire({ icon:'warning', title:'City required',          text:'Please select a city.',           confirmButtonColor:'#2d6af0' }); return; }
        if (!address) { Swal.fire({ icon:'warning', title:'Address required',       text:'Please enter the hotel address.', confirmButtonColor:'#2d6af0' }); return; }
        if (!image)   { Swal.fire({ icon:'warning', title:'Image URL required',     text:'Please provide an image URL.',    confirmButtonColor:'#2d6af0' }); return; }
        if (!price)   { Swal.fire({ icon:'warning', title:'Price required',         text:'Please enter the price per night.',confirmButtonColor:'#2d6af0' }); return; }
        if (!paymentMethod) { Swal.fire({ icon:'warning', title:'Payment method required', text:'Please select a payment method.', confirmButtonColor:'#2d6af0' }); return; }
        if (paymentMethod === 'bank' && !bankName) { Swal.fire({ icon:'warning', title:'Bank required', text:'Please select the bank.', confirmButtonColor:'#2d6af0' }); return; }
        if (!paymentAccountNumber) { Swal.fire({ icon:'warning', title:'Account number required', text:'Please enter the payment account number.', confirmButtonColor:'#2d6af0' }); return; }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving to Firebase...';

        const { lat, lng } = parseLatLng(latlngEl.value);
        const citySlug = city.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-');

        const hotelData = {
            id:            generateId(name, city),
            name:          name,
            city:          city,
            citySlug:      citySlug,
            address:       address,
            description:   descEl.value.trim(),
            image:         image,
            image_source:  'hotel_portal',
            stars:         selectedStars,
            rating:        parseFloat(ratingEl.value) || 0,
            reviews:       parseInt(reviewsEl.value)  || 0,
            price_per_night: price,
            features:      selectedFeatures,
            lat:           lat,
            lng:           lng,
            paymentMethod: paymentMethod,
            paymentAccountNumber: paymentAccountNumber,
            bankName:      paymentMethod === 'bank' ? bankName : '',
            status:        'active',
            createdAt:     firebase.firestore.FieldValue.serverTimestamp(),
            updatedAt:     firebase.firestore.FieldValue.serverTimestamp()
        };

        try {
            const docRef = await db.collection('hotels').add(hotelData);

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save Hotel to Firebase — Goes Live Instantly';

            // Show in saved list
            addToSavedList(hotelData, docRef.id);

            Swal.fire({
                icon: 'success',
                title: 'Hotel Saved!',
                html: `<b>${name}</b> is now live in <b>${city}</b>.<br><small style="color:#6b7280">Users on the hotel search page will see it instantly.</small>`,
                confirmButtonColor: '#2d6af0',
                confirmButtonText: 'Add Another Hotel'
            }).then(() => {
                resetForm();
            });

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save Hotel to Firebase — Goes Live Instantly';
            Swal.fire({ icon:'error', title:'Save Failed', text: err.message, confirmButtonColor:'#2d6af0' });
        }
    });

    function addToSavedList(hotel, docId) {
        document.getElementById('savedListSection').style.display = 'block';
        const list = document.getElementById('savedHotelList');
        const item = document.createElement('div');
        item.className = 'saved-hotel-item';
        item.innerHTML = `
            <img class="saved-hotel-img" src="${hotel.image}" onerror="this.src=''" alt="">
            <div class="saved-hotel-info">
                <h5>${hotel.name}</h5>
                <p>${hotel.city} · PKR ${Number(hotel.price_per_night).toLocaleString()}/night</p>
                <p style="font-size:11px;color:#94a3b8;">ID: ${docId}</p>
            </div>
            <span class="saved-badge">✓ Live</span>
        `;
        list.prepend(item);
    }

    function resetForm() {
        nameEl.value = ''; cityEl.value = ''; addressEl.value = '';
        descEl.value = ''; imageEl.value = ''; priceEl.value = '';
        ratingEl.value = ''; reviewsEl.value = ''; latlngEl.value = '';
        selectedStars = 0; selectedFeatures = [];
        starsHidEl.value = '0'; featHidEl.value = '[]';
        document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('selected'));
        document.querySelectorAll('.feature-tag').forEach(t => t.classList.remove('selected'));
        document.getElementById('imgPreviewEl').style.display = 'none';
        document.getElementById('imgPreviewPlaceholder').style.display = 'block';
        updatePreview();
    }

})();
</script>
</body>
</html>
