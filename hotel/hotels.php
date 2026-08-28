<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$city = $_GET['toCity'] ?? '';
$arrivalDate = $_GET['arrivalDate'] ?? '';
$departureDate = $_GET['departureDate'] ?? '';
$travelers = (int)($_GET['travelers'] ?? 2);
$rooms = (int)($_GET['rooms'] ?? 1);
$adults = (int)($_GET['adults'] ?? max(1, $travelers));
$children = (int)($_GET['children'] ?? 0);
$returnStep = $_GET['returnStep'] ?? '3';
$source = $_GET['source'] ?? '';
$returnUrl = $_GET['returnUrl'] ?? '/travelix/trips/create_trip.php';

$today = date('Y-m-d');

if ($source !== 'create_trip') {
    $city = '';
    $arrivalDate = '';
    $departureDate = '';
}

if ($rooms < 1) $rooms = 1;
if ($adults < 1) $adults = 1;
if ($children < 0) $children = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travelix Hotels</title>

    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <link rel="stylesheet" href="/travelix/assets/css/travelix_3d_effects.css">

    <style>
        :root {
            --theme-color: #1484B4;
            --theme-color-dark: #0f6d95;
            --theme-soft: #e8f5fb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --white: #ffffff;
            --border-soft: rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 14px 40px rgba(15, 23, 42, 0.08);
            --shadow-medium: 0 18px 48px rgba(15, 23, 42, 0.12);
            --orange: #ff5e57;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f6fafc;
            color: var(--text-dark);
        }

        .hotel-hero {
            position: relative;
            min-height: 640px;
            background:
                linear-gradient(rgba(7, 24, 37, 0.58), rgba(7, 24, 37, 0.48)),
                url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?q=80&w=1800&auto=format&fit=crop') center/cover no-repeat;
            display: flex;
            align-items: center;
            padding: 160px 20px 80px;
        }

        .hotel-hero-inner {
            width: 100%;
            max-width: 1350px;
            margin: 0 auto;
            color: #fff;
        }

        .hotel-hero-content {
            text-align: center;
            max-width: 980px;
            margin: 0 auto 34px;
        }

        .hotel-hero-content h1 {
            font-size: 68px;
            line-height: 1.04;
            font-weight: 800;
            margin: 0 0 18px;
            letter-spacing: -1px;
        }

        .hotel-hero-content p {
            margin: 0 auto;
            font-size: 22px;
            line-height: 1.7;
            max-width: 920px;
            color: rgba(255,255,255,0.92);
        }

        .hotel-search-wrap {
            position: relative;
            max-width: 1360px;
            margin: 0 auto;
        }

        .hotel-search-main {
            display: grid;
            grid-template-columns: 2.1fr 1.9fr 0.95fr auto;
            gap: 12px;
            align-items: end;
        }

        .search-field-card {
            background: #fff;
            border-radius: 16px;
            padding: 14px 18px;
            min-height: 74px;
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.14);
        }

        .search-field-card label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #2b2f36;
            margin-bottom: 5px;
        }

        .search-field-card select,
        .search-field-card input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 16px;
            color: #334155;
        }

        .search-field-inline {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .search-field-inline .divider {
            width: 1px;
            align-self: stretch;
            background: rgba(15, 23, 42, 0.08);
        }

        .field-part {
            flex: 1;
        }

        .field-part input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 16px;
            color: #334155;
        }

        .travelers-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
            font-weight: 700;
            font-size: 16px;
        }

        .travelers-preview i {
            color: #1f2937;
        }

        .search-btn {
            border: none;
            background: linear-gradient(135deg, var(--theme-color), var(--theme-color-dark));
            color: #fff;
            border-radius: 999px;
            padding: 0 34px;
            min-height: 74px;
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 16px 34px rgba(20, 132, 180, 0.32);
            transition: 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        .hero-links {
            margin-top: 22px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-links a {
            display: inline-flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 11px 22px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1.5px solid rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(4px);
            transition: 0.25s ease;
        }

        .hero-links a:hover {
            background: rgba(255, 255, 255, 0.26);
            border-color: #fff;
            transform: translateY(-1px);
        }

        .guest-popover {
            position: absolute;
            right: 142px;
            top: -18px;
            width: 370px;
            max-width: calc(100vw - 40px);
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.20);
            z-index: 20;
            display: block;
        }

        .guest-popover h4 {
            margin: 0 0 16px;
            font-size: 20px;
            font-weight: 800;
            color: #2b2f36;
        }

        .guest-row {
            display: grid;
            grid-template-columns: 28px 1fr auto;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .guest-row i {
            color: #2b2f36;
            font-size: 20px;
        }

        .guest-row label {
            margin: 0;
            font-size: 16px;
            color: #2b2f36;
        }

        .counter-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .counter-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: #f1f5f9;
            color: #1f2937;
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .counter-value {
            width: 76px;
            height: 48px;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.10);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            color: #2b2f36;
        }

        .guest-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
        }

        .guest-reset-btn {
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 800;
            padding: 10px 16px;
            cursor: pointer;
        }

        .guest-save-btn {
            border: none;
            background: linear-gradient(135deg, #ff6a49, #ff5e57);
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            font-weight: 800;
            cursor: pointer;
        }

        .prefill-note {
            max-width: 1200px;
            margin: 36px auto 0;
            background: linear-gradient(135deg, #f4fbff 0%, #eaf8ff 100%);
            border: 1px solid rgba(20, 132, 180, 0.14);
            border-radius: 20px;
            padding: 18px 20px;
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .prefill-note-icon {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 50%;
            background: var(--theme-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .prefill-note h5 {
            margin: 0 0 5px;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .prefill-note p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.8;
            font-size: 14px;
        }

        @media (max-width: 1200px) {
            .hotel-hero-content h1 {
                font-size: 52px;
            }

            .hotel-search-main {
                grid-template-columns: 1fr 1fr;
            }

            .guest-popover {
                position: static;
                margin-top: 16px;
                width: 100%;
                max-width: 100%;
            }

            .search-btn {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .hotel-hero {
                min-height: auto;
                padding: 108px 16px 48px;
            }

            .hotel-hero-content h1 {
                font-size: 38px;
            }

            .hotel-hero-content p {
                font-size: 18px;
            }

            .hotel-search-main {
                grid-template-columns: 1fr;
            }

            .search-field-inline {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .search-field-inline .divider {
                display: none;
            }

            .hero-links {
                gap: 16px;
            }
        }

        /* The hero section already ends with its own bottom padding, so the
           shared footer's default margin-top just doubles up as dead white
           space on this page — collapse it here only. */
        .travelix-footer {
            margin-top: 0 !important;
        }
    </style>
</head>
<body data-initial-city="<?php echo htmlspecialchars($city); ?>">

<?php include '../includes/user_top_navbar.php'; ?>

<section class="hotel-hero parallax-hero">
    <div class="hotel-hero-inner">
        <div class="hotel-hero-content parallax-layer" data-depth="10">
            <h1>Search for hotel stays in one place</h1>
            <p>
                Experience a better hotel search that helps you find the perfect lodging,
                with your preferences as the highest priority.
            </p>
        </div>

        <div class="hotel-search-wrap">
            <form method="GET" action="searched_hotel.php" class="hotel-search-main" id="hotelSearchForm">
                <input type="hidden" name="rooms" id="roomsInput" value="<?php echo (int)$rooms; ?>">
                <input type="hidden" name="adults" id="adultsInput" value="<?php echo (int)$adults; ?>">
                <input type="hidden" name="children" id="childrenInput" value="<?php echo (int)$children; ?>">
                <input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>">
                <input type="hidden" name="returnStep" value="<?php echo htmlspecialchars($returnStep); ?>">
                <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($returnUrl); ?>">

                <div class="search-field-card">
                    <label>Where</label>
                    <select name="toCity" id="toCitySelect" required>
                        <option value="">Loading destinations...</option>
                    </select>
                </div>

                <div class="search-field-card">
                    <label>When</label>
                    <div class="search-field-inline">
                        <div class="field-part">
                            <input type="date" name="arrivalDate" id="arrivalDate" value="<?php echo htmlspecialchars($arrivalDate); ?>" min="<?php echo $today; ?>" required>
                        </div>
                        <div class="divider"></div>
                        <div class="field-part">
                            <input type="date" name="departureDate" id="departureDate" value="<?php echo htmlspecialchars($departureDate); ?>" min="<?php echo $today; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="search-field-card" id="travelersToggle" style="cursor:pointer;">
                    <label>Travelers</label>
                    <div class="travelers-preview">
                        <i class="fa-solid fa-bed"></i> <span id="roomsPreview"><?php echo (int)$rooms; ?></span>
                        <i class="fa-solid fa-user-group"></i> <span id="guestsPreview"><?php echo (int)$adults + (int)$children; ?></span>
                    </div>
                </div>

                <button type="submit" class="search-btn" id="hotelSearchBtn">Search</button>
            </form>

            <div class="guest-popover" id="guestPopover">
                <h4>Rooms and guests</h4>

                <div class="guest-row">
                    <i class="fa-solid fa-bed"></i>
                    <label>Rooms</label>
                    <div class="counter-wrap">
                        <button type="button" class="counter-btn" data-target="rooms" data-action="minus">−</button>
                        <div class="counter-value" id="roomsValue"><?php echo (int)$rooms; ?></div>
                        <button type="button" class="counter-btn" data-target="rooms" data-action="plus">+</button>
                    </div>
                </div>

                <div class="guest-row">
                    <i class="fa-solid fa-user"></i>
                    <label>Adults</label>
                    <div class="counter-wrap">
                        <button type="button" class="counter-btn" data-target="adults" data-action="minus">−</button>
                        <div class="counter-value" id="adultsValue"><?php echo (int)$adults; ?></div>
                        <button type="button" class="counter-btn" data-target="adults" data-action="plus">+</button>
                    </div>
                </div>

                <div class="guest-row">
                    <i class="fa-solid fa-child-reaching"></i>
                    <label>Children</label>
                    <div class="counter-wrap">
                        <button type="button" class="counter-btn" data-target="children" data-action="minus">−</button>
                        <div class="counter-value" id="childrenValue"><?php echo (int)$children; ?></div>
                        <button type="button" class="counter-btn" data-target="children" data-action="plus">+</button>
                    </div>
                </div>

                <div class="guest-actions">
                    <button type="button" class="guest-reset-btn" id="guestResetBtn">Reset</button>
                    <button type="button" class="guest-save-btn" id="guestSaveBtn">Save</button>
                </div>
            </div>
        </div>

        <div class="hero-links">
            <?php if ($source === 'create_trip' && $returnStep === '3'): ?>
                <a href="<?php echo htmlspecialchars($returnUrl . '?returnStep=3'); ?>" class="js-loading-link">
                    Back to Create Trip
                </a>
            <?php endif; ?>

            <a href="manage_bookings.php" class="js-loading-link">Manage your bookings</a>
        </div>

        <?php if ($source === 'create_trip' && ($city || $arrivalDate || $departureDate)): ?>
            <div class="prefill-note">
                <div class="prefill-note-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div>
                    <h5>Trip details imported from Create Trip</h5>
                    <p>
                        Your destination and trip dates were brought here automatically. You can edit them before searching.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include '../includes/user_bottom_footer.php'; ?>

<script>
    const arrivalDate = document.getElementById('arrivalDate');
    const departureDate = document.getElementById('departureDate');

    const guestPopover = document.getElementById('guestPopover');
    const travelersToggle = document.getElementById('travelersToggle');

    const roomsInput = document.getElementById('roomsInput');
    const adultsInput = document.getElementById('adultsInput');
    const childrenInput = document.getElementById('childrenInput');

    const roomsValue = document.getElementById('roomsValue');
    const adultsValue = document.getElementById('adultsValue');
    const childrenValue = document.getElementById('childrenValue');

    const roomsPreview = document.getElementById('roomsPreview');
    const guestsPreview = document.getElementById('guestsPreview');

    const guestResetBtn = document.getElementById('guestResetBtn');
    const guestSaveBtn = document.getElementById('guestSaveBtn');
    const hotelSearchForm = document.getElementById('hotelSearchForm');
    const toCitySelect = document.getElementById('toCitySelect');

    const initialCity = document.body.dataset.initialCity || '';

    function showPageLoading(message = 'Please wait...') {
        Swal.fire({
            title: 'Loading...',
            text: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function syncDepartureMin() {
        if (!arrivalDate || !departureDate) return;

        if (arrivalDate.value) {
            departureDate.min = arrivalDate.value;

            if (departureDate.value && departureDate.value < arrivalDate.value) {
                departureDate.value = arrivalDate.value;
            }
        }
    }

    function getCount(id) {
        return parseInt(document.getElementById(id).textContent, 10) || 0;
    }

    function setCount(id, value) {
        document.getElementById(id).textContent = value;
    }

    function updatePreview() {
        roomsPreview.textContent = getCount('roomsValue');
        guestsPreview.textContent = getCount('adultsValue') + getCount('childrenValue');

        roomsInput.value = getCount('roomsValue');
        adultsInput.value = getCount('adultsValue');
        childrenInput.value = getCount('childrenValue');
    }

    function updateCounter(target, action) {
        let current = getCount(target + 'Value');

        if (action === 'plus') {
            current++;
        } else {
            const minMap = {
                rooms: 1,
                adults: 1,
                children: 0
            };
            current = Math.max(minMap[target], current - 1);
        }

        setCount(target + 'Value', current);
        updatePreview();
    }

    async function loadMergedHotelCities() {
        if (!toCitySelect) return;

        try {
            const response = await fetch('/travelix/hotel/data/get_merged_hotel_places.php', {
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('Failed to load hotel destinations.');
            }

            const data = await response.json();
            const cityNames = Object.keys(data || {}).sort((a, b) => a.localeCompare(b));

            toCitySelect.innerHTML = '<option value="">Search destinations</option>';

            cityNames.forEach((city) => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (initialCity && initialCity === city) {
                    option.selected = true;
                }
                toCitySelect.appendChild(option);
            });

            if (initialCity && !cityNames.includes(initialCity)) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = initialCity;
                fallbackOption.textContent = initialCity;
                fallbackOption.selected = true;
                toCitySelect.appendChild(fallbackOption);
            }
        } catch (error) {
            console.error('Hotel city load error:', error);

            toCitySelect.innerHTML = '<option value="">Unable to load destinations, please try again</option>';

            if (initialCity) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = initialCity;
                fallbackOption.textContent = initialCity;
                fallbackOption.selected = true;
                toCitySelect.appendChild(fallbackOption);
            }
        }
    }

    document.querySelectorAll('.counter-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            updateCounter(this.dataset.target, this.dataset.action);
        });
    });

    guestResetBtn.addEventListener('click', function () {
        setCount('roomsValue', 1);
        setCount('adultsValue', 2);
        setCount('childrenValue', 0);
        updatePreview();
    });

    guestSaveBtn.addEventListener('click', function () {
        showPageLoading('Saving guest selection...');

        setTimeout(() => {
            Swal.close();
            guestPopover.style.display = 'none';
        }, 1000);
    });

    travelersToggle.addEventListener('click', function () {
        if (guestPopover.style.display === 'none') {
            guestPopover.style.display = 'block';
        } else {
            guestPopover.style.display = 'none';
        }
    });

    document.addEventListener('click', function (e) {
        if (!guestPopover.contains(e.target) && !travelersToggle.contains(e.target)) {
            guestPopover.style.display = 'none';
        }
    });

    document.querySelectorAll('.js-loading-link').forEach((link) => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const targetUrl = this.getAttribute('href');
            showPageLoading('Opening page...');

            setTimeout(() => {
                window.location.href = targetUrl;
            }, 1500);
        });
    });

    if (hotelSearchForm) {
        hotelSearchForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const cityValue = this.querySelector('[name="toCity"]')?.value || '';
            const arrivalValue = arrivalDate?.value || '';
            const departureValue = departureDate?.value || '';

            if (!cityValue || !arrivalValue || !departureValue) {
                this.submit();
                return;
            }

            showPageLoading('Searching hotels for you...');

            setTimeout(() => {
                this.submit();
            }, 1500);
        });
    }

    if (arrivalDate) {
        arrivalDate.addEventListener('change', syncDepartureMin);
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await loadMergedHotelCities();
        syncDepartureMin();
        updatePreview();
    });

    (function () {
        let lastCityCount = 0;

        async function checkForNewCities() {
            try {
                const response = await fetch('/travelix/hotel/data/get_merged_hotel_places.php', { cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                const currentCount = Object.keys(data || {}).length;

                if (lastCityCount > 0 && currentCount !== lastCityCount) {
                    await loadMergedHotelCities();
                }
                lastCityCount = currentCount;
            } catch (e) {}
        }

        setTimeout(() => {
            checkForNewCities();
            setInterval(checkForNewCities, 20000);
        }, 5000);
    })();
</script>

<!-- Firebase SDK for Real-Time City Updates -->
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>

<?php
$configPathForJs = $_SERVER['DOCUMENT_ROOT'] . '/travelix/config/firebase_config.php';
if (file_exists($configPathForJs)) {
    require_once $configPathForJs;
}
?>

<script>
(function () {
    const firebaseConfig = {
        apiKey: <?php echo json_encode(defined('FIREBASE_API_KEY') ? FIREBASE_API_KEY : ''); ?>,
        authDomain: <?php echo json_encode(defined('FIREBASE_AUTH_DOMAIN') ? FIREBASE_AUTH_DOMAIN : ''); ?>,
        projectId: <?php echo json_encode(defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : ''); ?>,
        storageBucket: <?php echo json_encode(defined('FIREBASE_STORAGE_BUCKET') ? FIREBASE_STORAGE_BUCKET : ''); ?>,
        messagingSenderId: <?php echo json_encode(defined('FIREBASE_MESSAGING_SENDER_ID') ? FIREBASE_MESSAGING_SENDER_ID : ''); ?>,
        appId: <?php echo json_encode(defined('FIREBASE_APP_ID') ? FIREBASE_APP_ID : ''); ?>
    };

    if (!firebaseConfig.projectId) return;

    try {
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }

        const db = firebase.firestore();
        let knownCities = new Set();
        let isFirstSnapshot = true;
        let isFirstPortalSnapshot = true;
        let knownPortalCities = new Set();

        function checkForNewCities(currentCities, knownSet) {
            let hasNewCities = false;
            currentCities.forEach(function (city) {
                if (!knownSet.has(city)) {
                    hasNewCities = true;
                }
            });
            return hasNewCities || currentCities.size !== knownSet.size;
        }

        db.collection('hotel_destinations')
            .onSnapshot(function (snapshot) {
                const currentCities = new Set();
                snapshot.forEach(function (doc) {
                    const data = doc.data();
                    if (data.city) currentCities.add(data.city);
                });

                if (isFirstSnapshot) {
                    knownCities = currentCities;
                    isFirstSnapshot = false;
                    return;
                }

                if (checkForNewCities(currentCities, knownCities)) {
                    knownCities = currentCities;
                    loadMergedHotelCities();
                }
            }, function (error) {
                console.log('Firestore listener error (cities):', error.message);
            });

        db.collection('hotels')
            .where('status', '==', 'active')
            .onSnapshot(function (snapshot) {
                const currentCities = new Set();
                snapshot.forEach(function (doc) {
                    const data = doc.data();
                    if (data.city) currentCities.add(data.city);
                });

                if (isFirstPortalSnapshot) {
                    knownPortalCities = currentCities;
                    isFirstPortalSnapshot = false;
                    return;
                }

                if (checkForNewCities(currentCities, knownPortalCities)) {
                    knownPortalCities = currentCities;
                    loadMergedHotelCities();
                }
            }, function (error) {
                console.log('Firestore listener error (portal cities):', error.message);
            });

    } catch (e) {
        console.log('Firebase init error:', e.message);
    }
})();
</script>
<script src="/travelix/assets/js/travelix_3d_effects.js"></script>

</body>
</html>