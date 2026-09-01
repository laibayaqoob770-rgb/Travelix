<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['user']) || isset($_SESSION['email']);
$selectedCity = trim((string)($_GET['city'] ?? ''));

// City list comes entirely from admin's Firestore `trip_destinations` now —
// no hardcoded static cities, so this page never shows more cities than
// admin has actually added.
$popularCities = ['Islamabad', 'Lahore', 'Karachi', 'Murree', 'Hunza', 'Skardu'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Guides | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1484B4;
            --primary-dark: #0f6d95;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --card-border: rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #ffffff;
            color: var(--text-dark);
        }

        .page-wrap {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(20,132,180,0.08), transparent 28%),
                radial-gradient(circle at top right, rgba(20,132,180,0.06), transparent 22%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .hero-navbar-wrap {
            position: absolute;
            top: 18px;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .guides-hero {
            padding: 150px 20px 90px;
            text-align: center;
        }

        .guides-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(20,132,180,0.12);
            color: var(--primary);
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .guides-hero h1 {
            font-size: 58px;
            line-height: 1.08;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .guides-hero p {
            max-width: 840px;
            margin: 0 auto 30px;
            font-size: 18px;
            color: var(--text-muted);
            line-height: 1.8;
        }

        .search-shell {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 24px;
            box-shadow: var(--shadow-soft);
        }

        .search-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
        }

        .guide-select {
            height: 62px;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            padding: 0 18px;
            font-size: 17px;
            font-weight: 600;
            color: #0f172a;
            outline: none;
        }

        .guide-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20,132,180,0.12);
        }

        .search-btn {
            height: 62px;
            border: none;
            border-radius: 18px;
            padding: 0 28px;
            background: var(--primary);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        .chips-wrap {
            margin-top: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .city-chip {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            background: #eef7fb;
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .city-chip:hover {
            background: var(--primary);
            color: #fff;
        }

        .guides-section {
            padding: 10px 20px 90px;
        }

        .section-head {
            text-align: center;
            margin-bottom: 34px;
        }

        .section-head h2 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .section-head p {
            max-width: 760px;
            margin: 0 auto;
            color: var(--text-muted);
            font-size: 17px;
            line-height: 1.8;
        }

        .city-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }

        .city-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: 0.35s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .city-card:hover {
            transform: translateY(-8px);
        }

        .city-card-image-wrap {
            width: 100%;
            height: 240px;
            overflow: hidden;
        }

        .city-card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: 0.4s ease;
        }

        .city-card:hover .city-card-image {
            transform: scale(1.05);
        }

        .city-card-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .city-card h4 {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .city-card p {
            color: var(--text-muted);
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 18px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .city-card a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 999px;
            padding: 12px 20px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            margin-top: auto;
        }

        .city-card a:hover {
            background: var(--primary-dark);
            color: #fff;
        }

        @media (max-width: 1199px) {
            .city-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .guides-hero h1 {
                font-size: 46px;
            }
        }

        @media (max-width: 767px) {
            .guides-hero {
                padding: 130px 16px 70px;
            }

            .guides-hero h1 {
                font-size: 34px;
            }

            .guides-hero p {
                font-size: 16px;
            }

            .search-shell {
                padding: 18px;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .city-grid {
                grid-template-columns: 1fr;
            }

            .section-head h2 {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="hero-navbar-wrap">
        <?php include '../includes/user_top_navbar.php'; ?>
    </div>

    <section class="guides-hero">
        <div class="container">
            <span class="guides-badge">Travelix Pakistan Guides</span>
            <h1>Explore cities across Pakistan, one guide at a time</h1>
            <p>
                Pick a city below to discover its top attractions, hidden gems, and everything you need to plan your visit.
            </p>

            <div class="search-shell">
                <div class="search-row">
                    <select id="citySearchSelect" class="guide-select">
                        <option value="">Loading cities...</option>
                    </select>

                    <button type="button" id="openGuideBtn" class="search-btn">
                        Explore Guide
                    </button>
                </div>

                <div class="chips-wrap" id="popularCitiesWrap">
                    <?php foreach ($popularCities as $city): ?>
                        <button type="button" class="city-chip quick-city-btn" data-city="<?= htmlspecialchars($city); ?>">
                            <?= htmlspecialchars($city); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="guides-section">
        <div class="container">
            <div class="section-head">
                <h2>Popular Pakistan city guides</h2>
                <p>
                    Choose a city to open its attraction guide, view all places on the map,
                    and explore details below.
                </p>
            </div>

            <div class="city-grid" id="cityGuidesGrid"></div>
        </div>
    </section>
</div>

<?php include '../includes/user_bottom_footer.php'; ?>

<script>
window.TRAVELIX_SELECTED_CITY = <?php echo json_encode($selectedCity, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>

<script type="module">
import { firebaseConfig } from "../config/firebase-config.js";

import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";

import {
    getFirestore,
    collection,
    getDocs
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const db = getFirestore(app);

const citySearchSelect = document.getElementById('citySearchSelect');
const openGuideBtn = document.getElementById('openGuideBtn');
const cityGuidesGrid = document.getElementById('cityGuidesGrid');

const selectedCityFromUrl = String(window.TRAVELIX_SELECTED_CITY || '').trim();

function escapeHtml(value = '') {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function normalizeCityName(name) {
    return String(name || '').trim().toLowerCase();
}

function getCityImage(city) {
    const value = String(city.image || '').trim();
    if (!value) return '/travelix/images/default_destination.jpg';

    if (value.startsWith('http://') || value.startsWith('https://')) {
        return value;
    }

    if (value.startsWith('/travelix/location_images/')) {
        return value;
    }

    if (value.startsWith('/location_images/')) {
        return `/travelix${value}`;
    }

    if (value.includes('location_images/')) {
        return `/travelix/${value.replace(/^\/+/, '')}`;
    }

    if (value.startsWith('/')) return value;

    return `/travelix/location_images/${value}`;
}

function getCityDescription(city) {
    return city.description ||
        city.cityDescription ||
        city.about ||
        city.why_go ||
        `Explore ${city.name} with Travelix city guides, attractions, and map-based planning.`;
}

function mergeCities(staticList, adminList) {
    const map = new Map();

    staticList.forEach((city) => {
        if (!city.name) return;
        map.set(normalizeCityName(city.name), {
            name: city.name,
            image: city.image || '',
            description: city.description || ''
        });
    });

    adminList.forEach((city) => {
        if (!city.name) return;
        const key = normalizeCityName(city.name);

        if (map.has(key)) {
            const old = map.get(key);
            map.set(key, {
                ...old,
                ...city,
                name: old.name || city.name,
                image: city.image || old.image || '',
                description: city.description || old.description || ''
            });
        } else {
            map.set(key, city);
        }
    });

    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
}

async function loadAdminAddedCities() {
    const adminCities = [];

    try {
        const snapshot = await getDocs(collection(db, 'trip_destinations'));

        snapshot.forEach((docSnap) => {
            const data = docSnap.data() || {};

            const cityName =
                data.city ||
                data.cityName ||
                data.name ||
                data.destination ||
                '';

            if (!cityName) return;

            let image = data.image || data.cityImage || data.imageUrl || '';
            let description = data.description || data.cityDescription || data.about || '';

            if (!description && Array.isArray(data.places) && data.places.length) {
                const firstLocation = data.places[0] || {};
                description = firstLocation.description || firstLocation.why_go || `Explore ${cityName} attractions added by admin.`;
            }

            if (!image && Array.isArray(data.places) && data.places.length) {
                const withImage = data.places.find((p) => p && (p.image || p.imageUrl));
                if (withImage) image = withImage.image || withImage.imageUrl || '';
            }

            adminCities.push({
                name: cityName,
                image,
                description: description || `Explore ${cityName} attractions added by admin.`
            });
        });
    } catch (error) {
        console.error('Failed to load admin cities:', error);
    }

    return adminCities;
}

function renderCityDropdown(cities) {
    citySearchSelect.innerHTML = '<option value="">Select a Pakistani city</option>';

    cities.forEach((city) => {
        const option = document.createElement('option');
        option.value = city.name;
        option.textContent = city.name;

        if (selectedCityFromUrl && normalizeCityName(selectedCityFromUrl) === normalizeCityName(city.name)) {
            option.selected = true;
        }

        citySearchSelect.appendChild(option);
    });
}

function renderCityCards(cities) {
    cityGuidesGrid.innerHTML = cities.map((city) => {
        const image = getCityImage(city);
        const description = getCityDescription(city);

        return `
            <div class="city-card">
                <div class="city-card-image-wrap">
                    <img
                        src="${escapeHtml(image)}"
                        alt="${escapeHtml(city.name)}"
                        class="city-card-image"
                        onerror="this.onerror=null;this.src='/travelix/images/default_destination.jpg';"
                    >
                </div>

                <div class="city-card-body">
                    <h4>${escapeHtml(city.name)}</h4>
                    <p>${escapeHtml(description)}</p>
                    <a href="/travelix/plan_guides/geoCategory.php?city=${encodeURIComponent(city.name)}">
                        Open ${escapeHtml(city.name)} Guide
                    </a>
                </div>
            </div>
        `;
    }).join('');
}

function goToGuide(city) {
    if (!city) {
        Swal.fire({
            icon: 'warning',
            title: 'Select City',
            text: 'Please select a Pakistani city first.',
            confirmButtonColor: '#1484B4'
        });
        return;
    }

    Swal.fire({
        title: 'Opening guide...',
        text: 'Please wait while we open the city attraction guide.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    setTimeout(() => {
        window.location.href = `/travelix/plan_guides/geoCategory.php?city=${encodeURIComponent(city)}`;
    }, 500);
}

openGuideBtn?.addEventListener('click', function () {
    goToGuide(citySearchSelect?.value || '');
});

document.querySelectorAll('.quick-city-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
        const city = this.dataset.city || '';

        if (citySearchSelect) {
            citySearchSelect.value = city;
        }

        goToGuide(city);
    });
});

async function initGuidersPage() {
    citySearchSelect.innerHTML = '<option value="">Loading cities...</option>';
    cityGuidesGrid.innerHTML = '';

    const adminCities = await loadAdminAddedCities();
    const mergedCities = mergeCities([], adminCities);

    renderCityDropdown(mergedCities);
    renderCityCards(mergedCities);

    if (selectedCityFromUrl) {
        citySearchSelect.value = selectedCityFromUrl;
    }
}

initGuidersPage();
</script>

</body>
</html>