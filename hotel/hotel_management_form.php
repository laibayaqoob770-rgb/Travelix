<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

$sessionId       = trim((string)($_GET['sessionId'] ?? ''));
$hotelId         = trim((string)($_GET['hotelId'] ?? ''));
$hotelName       = trim((string)($_GET['hotelName'] ?? ''));
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
$totalPrice      = $hotelPriceNight * $nights;

function e2($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function formatDate2(string $d): string {
    if (!$d) return '';
    try { return (new DateTime($d))->format('M j, Y'); } catch (Throwable $e) { return $d; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hotel Management — Booking Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        /* Header */
        .mgmt-header {
            background: linear-gradient(135deg, #133c96, #1e50c0);
            padding: 22px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .mgmt-header-left h1 {
            font-size: 20px; font-weight: 800; color: #fff;
            display: flex; align-items: center; gap: 10px;
        }
        .mgmt-header-left p { color: rgba(255,255,255,0.65); font-size: 13px; margin-top: 4px; }

        .live-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3);
            border-radius: 999px; padding: 8px 16px; font-size: 12px; font-weight: 700;
            color: #fca5a5;
        }
        .live-badge .dot {
            width: 8px; height: 8px; background: #ef4444; border-radius: 50%;
            animation: pulse 1.2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
            50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        }

        /* Body */
        .mgmt-body { padding: 24px; max-width: 760px; margin: 0 auto; }

        .info-bar {
            background: rgba(45,106,240,0.1);
            border: 1px solid rgba(45,106,240,0.2);
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 13px;
            color: #93c5fd;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.5;
        }

        /* Form sections */
        .form-section {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 18px;
        }
        .form-section h3 {
            font-size: 15px; font-weight: 700; color: #94a3b8;
            margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-field { display: flex; flex-direction: column; gap: 6px; }
        .form-field.full { grid-column: 1 / -1; }

        .form-field label {
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.8px;
        }

        .form-field input,
        .form-field select {
            background: rgba(0,0,0,0.4);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 13px 15px;
            font-size: 14px;
            color: #e2e8f0;
            font-weight: 600;
            outline: none;
            transition: all 0.25s ease;
            font-family: inherit;
        }
        .form-field input:focus,
        .form-field select:focus {
            border-color: #2d6af0;
            box-shadow: 0 0 0 3px rgba(45,106,240,0.25);
        }

        .form-field input.synced {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.15) !important;
        }

        .form-field input::placeholder { color: #475569; }

        /* Sync indicator */
        .sync-indicator {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; color: #10b981; font-weight: 600;
            margin-top: 4px; opacity: 0; transition: opacity 0.3s;
        }
        .sync-indicator.show { opacity: 1; }
        .sync-indicator i { font-size: 10px; }

        /* Session info */
        .session-info {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 12px;
            color: #475569;
            margin-top: 16px;
        }
        .session-info code { color: #94a3b8; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .mgmt-body { padding: 14px; }
            .mgmt-header { padding: 16px; }
        }
    </style>
</head>
<body>

<div class="mgmt-header">
    <div class="mgmt-header-left">
        <h1><i class="fas fa-concierge-bell"></i> Hotel Management</h1>
        <p>Fill in the booking form — customer sees every field in real-time</p>
    </div>
    <div class="live-badge">
        <span class="dot"></span>
        LIVE — Connected to customer
    </div>
</div>

<div class="mgmt-body">

    <div class="info-bar">
        <i class="fas fa-info-circle" style="font-size:18px; flex-shrink:0;"></i>
        <div>Type into any field below. As you type, the <strong>customer's booking summary</strong> updates instantly in real-time on their screen.</div>
    </div>

    <!-- Hotel Information -->
    <div class="form-section">
        <h3><i class="fas fa-hotel"></i> Hotel Information</h3>
        <div class="form-grid">
            <div class="form-field full">
                <label>Hotel Name</label>
                <input type="text" id="fHotelName" placeholder="Enter hotel name..." value="<?php echo e2($hotelName); ?>">
                <div class="sync-indicator" id="syncHotelName"><i class="fas fa-check-circle"></i> Synced to customer</div>
            </div>
            <div class="form-field">
                <label>City</label>
                <input type="text" id="fCity" placeholder="Enter city..." value="<?php echo e2($city); ?>">
                <div class="sync-indicator" id="syncCity"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Address</label>
                <input type="text" id="fAddress" placeholder="Enter address..." value="<?php echo e2($hotelAddress); ?>">
                <div class="sync-indicator" id="syncAddress"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Stars</label>
                <input type="number" id="fStars" placeholder="e.g. 5" min="1" max="5" value="<?php echo (int)$hotelStars; ?>">
                <div class="sync-indicator" id="syncStars"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Rating (out of 10)</label>
                <input type="number" id="fRating" placeholder="e.g. 9.2" step="0.1" min="0" max="10" value="<?php echo number_format($hotelRating, 1); ?>">
                <div class="sync-indicator" id="syncRating"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Reviews Count</label>
                <input type="number" id="fReviews" placeholder="e.g. 450" value="<?php echo (int)$hotelReviews; ?>">
                <div class="sync-indicator" id="syncReviews"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field full">
                <label>Hotel Image URL</label>
                <input type="text" id="fImage" placeholder="Paste image URL..." value="<?php echo e2($hotelImage); ?>">
                <div class="sync-indicator" id="syncImage"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
        </div>
    </div>

    <!-- Booking Details -->
    <div class="form-section">
        <h3><i class="fas fa-calendar-alt"></i> Booking Details</h3>
        <div class="form-grid">
            <div class="form-field">
                <label>Check-in Date</label>
                <input type="text" id="fCheckin" placeholder="e.g. May 2, 2026" value="<?php echo e2(formatDate2($arrivalDate)); ?>">
                <div class="sync-indicator" id="syncCheckin"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Check-out Date</label>
                <input type="text" id="fCheckout" placeholder="e.g. May 13, 2026" value="<?php echo e2(formatDate2($departureDate)); ?>">
                <div class="sync-indicator" id="syncCheckout"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Rooms</label>
                <input type="number" id="fRooms" placeholder="1" min="1" value="<?php echo (int)$rooms; ?>">
                <div class="sync-indicator" id="syncRooms"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Adults</label>
                <input type="number" id="fAdults" placeholder="1" min="1" value="<?php echo (int)$adults; ?>">
                <div class="sync-indicator" id="syncAdults"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Children</label>
                <input type="number" id="fChildren" placeholder="0" min="0" value="<?php echo (int)$children; ?>">
                <div class="sync-indicator" id="syncChildren"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Nights</label>
                <input type="number" id="fNights" placeholder="1" min="1" value="<?php echo (int)$nights; ?>">
                <div class="sync-indicator" id="syncNights"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
        </div>
    </div>

    <!-- Pricing -->
    <div class="form-section">
        <h3><i class="fas fa-receipt"></i> Pricing</h3>
        <div class="form-grid">
            <div class="form-field">
                <label>Price per Night (PKR)</label>
                <input type="number" id="fPriceNight" placeholder="e.g. 9105" value="<?php echo (int)$hotelPriceNight; ?>">
                <div class="sync-indicator" id="syncPriceNight"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
            <div class="form-field">
                <label>Total Price (PKR)</label>
                <input type="number" id="fTotalPrice" placeholder="Auto-calculated" value="<?php echo (int)$totalPrice; ?>">
                <div class="sync-indicator" id="syncTotalPrice"><i class="fas fa-check-circle"></i> Synced</div>
            </div>
        </div>
    </div>

    <div class="session-info">
        Session ID: <code><?php echo e2($sessionId); ?></code>
    </div>
</div>

<!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>

<script>
(function () {
    const SESSION_ID = <?php echo json_encode($sessionId); ?>;
    if (!SESSION_ID) {
        alert('No session ID. Cannot connect to customer.');
        return;
    }

    // Firebase
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
    const docRef = db.collection('live_booking_sessions').doc(SESSION_ID);

    // Create the initial document
    docRef.set({ status: 'connected', createdAt: firebase.firestore.FieldValue.serverTimestamp() }, { merge: true });

    // Debounce helper
    let syncTimers = {};
    function debouncedSync(fieldKey, data, delay = 300) {
        clearTimeout(syncTimers[fieldKey]);
        syncTimers[fieldKey] = setTimeout(() => {
            docRef.update(data).then(() => {
                showSyncBadge(fieldKey);
                flashField(fieldKey);
            }).catch(err => console.error('Sync error:', err));
        }, delay);
    }

    function showSyncBadge(fieldKey) {
        const badge = document.getElementById('sync' + capitalize(fieldKey));
        if (!badge) return;
        badge.classList.add('show');
        setTimeout(() => badge.classList.remove('show'), 2000);
    }

    function flashField(fieldKey) {
        const input = document.getElementById('f' + capitalize(fieldKey));
        if (!input) return;
        input.classList.add('synced');
        setTimeout(() => input.classList.remove('synced'), 1500);
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Map field IDs to Firestore keys
    const fieldMap = {
        'fHotelName':  { key: 'HotelName',  getData: (v) => ({ hotelName: v, hotelId: <?php echo json_encode($hotelId); ?> }) },
        'fCity':       { key: 'City',        getData: (v) => ({ city: v }) },
        'fAddress':    { key: 'Address',     getData: (v) => ({ address: v }) },
        'fStars':      { key: 'Stars',       getData: (v) => ({ stars: parseInt(v) || 0 }) },
        'fRating':     { key: 'Rating',      getData: (v) => ({ rating: parseFloat(v) || 0 }) },
        'fReviews':    { key: 'Reviews',     getData: (v) => ({ reviews: parseInt(v) || 0 }) },
        'fImage':      { key: 'Image',       getData: (v) => ({ hotelImage: v }) },
        'fCheckin':    { key: 'Checkin',      getData: (v) => ({ checkIn: v }) },
        'fCheckout':   { key: 'Checkout',     getData: (v) => ({ checkOut: v }) },
        'fRooms':      { key: 'Rooms',        getData: (v) => ({ rooms: parseInt(v) || 1 }) },
        'fAdults':     { key: 'Adults',       getData: (v) => {
            const adults = parseInt(v) || 1;
            const children = parseInt(document.getElementById('fChildren').value) || 0;
            return { guests: adults + children, guestsDetail: adults + ' Adults, ' + children + ' Children' };
        }},
        'fChildren':   { key: 'Children',     getData: (v) => {
            const children = parseInt(v) || 0;
            const adults = parseInt(document.getElementById('fAdults').value) || 1;
            return { guests: adults + children, guestsDetail: adults + ' Adults, ' + children + ' Children' };
        }},
        'fNights':     { key: 'Nights',       getData: (v) => ({ nights: parseInt(v) || 1 }) },
        'fPriceNight': { key: 'PriceNight',   getData: (v) => ({ pricePerNight: parseFloat(v) || 0 }) },
        'fTotalPrice': { key: 'TotalPrice',   getData: (v) => ({ totalPrice: parseFloat(v) || 0 }) }
    };

    // Attach input listeners to all fields
    Object.keys(fieldMap).forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (!input) return;

        input.addEventListener('input', function () {
            const mapping = fieldMap[fieldId];
            const data = mapping.getData(this.value);
            debouncedSync(mapping.key, data, 250);

            // Auto-calculate total when price or nights change
            if (fieldId === 'fPriceNight' || fieldId === 'fNights') {
                const ppn = parseFloat(document.getElementById('fPriceNight').value) || 0;
                const nts = parseInt(document.getElementById('fNights').value) || 1;
                const total = ppn * nts;
                document.getElementById('fTotalPrice').value = total;
                debouncedSync('TotalPrice', { totalPrice: total }, 300);
            }
        });

        // Also fire on change (for number inputs with arrows)
        input.addEventListener('change', function () {
            const mapping = fieldMap[fieldId];
            const data = mapping.getData(this.value);
            debouncedSync(mapping.key, data, 100);
        });
    });

})();
</script>
</body>
</html>
