<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
$selectedCity = isset($_GET['city']) ? trim((string)$_GET['city']) : 'Islamabad';

if ($selectedCity === '') {
    $selectedCity = 'Islamabad';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($selectedCity); ?> Guide | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/travelix/assets/vendor/leaflet/leaflet.css">
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
            background: #f7fbff;
            color: var(--text-dark);
        }

        .hero-navbar-wrap {
            position: absolute;
            top: 18px;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .page-wrap {
            padding-top: 150px;
            padding-bottom: 60px;
        }

        .guide-hero {
            padding: 18px 20px 28px;
        }

        .guide-hero-card {
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(135deg, rgba(20,132,180,0.95), rgba(15,109,149,0.95));
            color: #fff;
            border-radius: 32px;
            padding: 34px;
            box-shadow: var(--shadow-soft);
        }

        .guide-breadcrumb {
            font-size: 14px;
            font-weight: 700;
            opacity: 0.95;
            margin-bottom: 12px;
        }

        .guide-hero-card h1 {
            font-size: 52px;
            line-height: 1.08;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .guide-hero-card p {
            max-width: 900px;
            font-size: 17px;
            line-height: 1.8;
            margin-bottom: 0;
            color: rgba(255,255,255,0.92);
        }

        .guide-layout {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .places-panel,
        .map-panel {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 28px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            height: calc(100vh - 170px);
            min-height: 760px;
            display: flex;
            flex-direction: column;
        }

        .panel-head {
            padding: 24px 24px 14px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .panel-head h2 {
            margin: 0 0 8px;
            font-size: 30px;
            font-weight: 800;
        }

        .panel-head p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .places-scroll {
            flex: 1;
            overflow: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .place-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 20px;
            background: #fff;
            transition: 0.3s ease;
            scroll-margin-top: 120px;
        }

        .place-card.active-place {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20,132,180,0.10);
        }

        .place-card:hover {
            transform: translateY(-2px);
        }

        .place-card-image {
            width: 100%;
            height: 260px;
            object-fit: cover;
            display: block;
            background: #e2e8f0;
        }

        .place-card-body {
            padding: 22px;
        }

        .place-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .place-number {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 15px;
            flex-shrink: 0;
        }

        .place-title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .place-description {
            color: #475569;
            line-height: 1.8;
            margin-bottom: 16px;
        }

        .info-box {
            background: #f8fbff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 18px;
            padding: 16px;
            margin-top: 14px;
        }

        .info-box h5 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 800;
        }

        .info-box ul,
        .info-box ol {
            margin: 0;
            padding-left: 20px;
            color: #475569;
            line-height: 1.8;
        }

        .place-btn-row {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .focus-map-btn {
            border: none;
            border-radius: 999px;
            padding: 11px 18px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
        }

        .focus-map-btn:hover {
            background: var(--primary-dark);
        }

        .map-panel {
            position: sticky;
            top: 110px;
        }

        #cityGuideMap {
            width: 100%;
            flex: 1;
            min-height: 0;
        }

        .map-note {
            padding: 14px 20px 20px;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .guide-marker-wrap {
            background: transparent !important;
            border: none !important;
        }

        .guide-marker {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            background: #ef476f;
            color: #fff;
            border: 3px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(0,0,0,0.18);
        }

        .guide-marker.active {
            background: var(--primary);
            transform: scale(1.08);
        }

        .empty-guide {
            border-radius: 22px;
            padding: 30px;
            background: #fff7ed;
            border: 1px solid rgba(245, 158, 11, 0.22);
            color: #92400e;
            line-height: 1.8;
            font-weight: 700;
        }

        @media (max-width: 1199px) {
            .guide-layout {
                grid-template-columns: 1fr;
            }

            .places-panel,
            .map-panel {
                height: auto;
                min-height: auto;
            }

            .map-panel {
                position: static;
            }

            #cityGuideMap {
                height: 520px;
                min-height: 520px;
                flex: unset;
            }

            .places-scroll {
                max-height: none;
                overflow: visible;
                flex: unset;
            }
        }

        @media (max-width: 767px) {
            .guide-hero-card {
                padding: 24px 20px;
            }

            .guide-hero-card h1 {
                font-size: 34px;
            }

            .panel-head h2 {
                font-size: 26px;
            }

            .place-card-image {
                height: 220px;
            }
        }
    </style>
</head>
<body>

<div class="hero-navbar-wrap">
    <?php include '../includes/user_top_navbar.php'; ?>
</div>

<div class="page-wrap">
    <section class="guide-hero">
        <div class="guide-hero-card">
            <div class="guide-breadcrumb">
                Travelix Guides &gt; <span id="breadcrumbCity"><?= htmlspecialchars($selectedCity); ?></span> &gt; Best Attractions
            </div>

            <h1>
                Top attractions and points to explore in
                <span id="heroCityName"><?= htmlspecialchars($selectedCity); ?></span>
            </h1>

            <p>
                Static places and admin-added places are loaded together. Click any numbered map marker
                to jump directly to that place section.
            </p>
        </div>
    </section>

    <section class="guide-layout">
        <div class="places-panel">
            <div class="panel-head">
                <h2><span id="panelCityName"><?= htmlspecialchars($selectedCity); ?></span> attraction guide</h2>
                <p>
                    Scroll through all major places. Click “Show on Map” to focus that point,
                    or click a map marker to automatically jump here.
                </p>
            </div>

            <div class="places-scroll" id="placesScrollWrap">
                <div id="placesGuideList">
                    <div class="empty-guide">Loading guide places...</div>
                </div>
            </div>
        </div>

        <div class="map-panel">
            <div class="panel-head">
                <h2><span id="mapCityName"><?= htmlspecialchars($selectedCity); ?></span> map points</h2>
                <p>Click any numbered point to jump to the matching attraction section.</p>
            </div>

            <div id="cityGuideMap"></div>

            <div class="map-note">
                Marker click → scrolls to the related section below.  
                Section button click → focuses the marker on the map.
            </div>
        </div>
    </section>
</div>

<?php include '../includes/user_bottom_footer.php'; ?>

<script src="/travelix/assets/vendor/leaflet/leaflet.js"></script>

<script>
window.staticCityPlaces = <?php include '../trips/data/city_places.php'; ?>;
window.selectedGuideCity = <?php echo json_encode($selectedCity); ?>;
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

const selectedCity = String(window.selectedGuideCity || 'Islamabad').trim();
const staticCityPlaces = window.staticCityPlaces || {};
const placesGuideList = document.getElementById('placesGuideList');

let guideMap = null;
let guideMarkers = [];
let cityData = null;

function escapeHtml(text = '') {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

function normalizeCityName(name) {
    return String(name || '').trim().toLowerCase();
}

function normalizeList(value) {
    if (Array.isArray(value)) return value;

    if (typeof value === 'string' && value.trim()) {
        return value
            .split(/\r?\n|,/)
            .map(item => item.trim())
            .filter(Boolean);
    }

    return [];
}

function numberOrFallback(value, fallback) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function getAdminImagePath(image) {
    if (!image) return '';

    const value = String(image).trim();

    if (!value) return '';

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
        const clean = value.replace(/^\/+/, '');
        return `/travelix/${clean}`;
    }

    return `/travelix/location_images/${value}`;
}

function getStaticImagePath(image) {
    if (!image) return '';

    const value = String(image).trim();

    if (!value) return '';

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
        return value;
    }

    return `/travelix/images/${value}`;
}

function getImagePath(place) {
    if (place.isAdminAdded) {
        return getAdminImagePath(place.image) || '/travelix/images/default_destination.jpg';
    }

    return getStaticImagePath(place.image) || '/travelix/images/default_destination.jpg';
}

function normalizePlace(place, index, fallbackCenter, isAdminAdded = false) {
    const lat = numberOrFallback(place.lat ?? place.latitude, fallbackCenter?.[0] ?? 33.6844);
    const lng = numberOrFallback(place.lng ?? place.longitude, fallbackCenter?.[1] ?? 73.0479);

    return {
        name: place.name || place.placeName || place.locationName || `Place ${index + 1}`,
        image: place.image || place.imageUrl || place.photo || '',
        lat,
        lng,
        description: place.description || place.about || '',
        why_go: normalizeList(place.why_go || place.whyGo || place.why || place.reasons),
        know_before_you_go: normalizeList(place.know_before_you_go || place.knowBeforeYouGo || place.tips),
        isAdminAdded
    };
}

function mergePlaces(staticPlaces = [], adminPlaces = [], center) {
    const map = new Map();

    staticPlaces.forEach((place, index) => {
        const normalized = normalizePlace(place, index, center, false);
        map.set(normalizeCityName(normalized.name), normalized);
    });

    adminPlaces.forEach((place, index) => {
        const normalized = normalizePlace(place, index, center, true);
        const key = normalizeCityName(normalized.name);

        if (map.has(key)) {
            const old = map.get(key);

            map.set(key, {
                ...old,
                ...normalized,
                image: normalized.image || old.image,
                description: normalized.description || old.description,
                why_go: normalized.why_go.length ? normalized.why_go : old.why_go,
                know_before_you_go: normalized.know_before_you_go.length ? normalized.know_before_you_go : old.know_before_you_go,
                isAdminAdded: normalized.isAdminAdded || old.isAdminAdded
            });
        } else {
            map.set(key, normalized);
        }
    });

    return Array.from(map.values());
}

async function loadAdminCityData() {
    let matchedCity = null;

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

            if (!cityName || normalizeCityName(cityName) !== normalizeCityName(selectedCity)) {
                return;
            }

            const centerLat = data.centerLat ?? data.lat ?? data.latitude ?? data.mapCenterLat;
            const centerLng = data.centerLng ?? data.lng ?? data.longitude ?? data.mapCenterLng;

            const locations =
                Array.isArray(data.locations) ? data.locations :
                Array.isArray(data.places) ? data.places :
                Array.isArray(data.attractions) ? data.attractions :
                [];

            matchedCity = {
                center: [
                    numberOrFallback(centerLat, null),
                    numberOrFallback(centerLng, null)
                ],
                places: locations
            };
        });
    } catch (error) {
        console.error('Admin city load failed:', error);
    }

    return matchedCity;
}

async function buildCityData() {
    const staticData = staticCityPlaces[selectedCity] || null;
    const adminData = await loadAdminCityData();

    const staticCenter = Array.isArray(staticData?.center) ? staticData.center : null;

    const adminCenter = Array.isArray(adminData?.center) &&
        adminData.center[0] !== null &&
        adminData.center[1] !== null
        ? adminData.center
        : null;

    const center = adminCenter || staticCenter || [33.6844, 73.0479];

    const staticPlaces = Array.isArray(staticData?.places) ? staticData.places : [];
    const adminPlaces = Array.isArray(adminData?.places) ? adminData.places : [];

    return {
        center,
        places: mergePlaces(staticPlaces, adminPlaces, center)
    };
}

function createMarkerIcon(number, active = false) {
    return L.divIcon({
        className: 'guide-marker-wrap',
        html: `<div class="guide-marker ${active ? 'active' : ''}">${number}</div>`,
        iconSize: [38, 38],
        iconAnchor: [19, 19]
    });
}

function setActiveCard(index) {
    document.querySelectorAll('.place-card').forEach((card) => {
        card.classList.remove('active-place');
    });

    const activeCard = document.getElementById(`guide-place-${index}`);
    if (activeCard) activeCard.classList.add('active-place');
}

function setActiveMarker(index) {
    guideMarkers.forEach((item, i) => {
        item.marker.setIcon(createMarkerIcon(i + 1, i === index));
    });
}

function scrollToPlace(index) {
    const target = document.getElementById(`guide-place-${index}`);
    if (!target) return;

    setActiveCard(index);
    setActiveMarker(index);

    target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function focusPlaceOnMap(index) {
    const item = guideMarkers[index];

    if (!item || !guideMap) return;

    setActiveCard(index);
    setActiveMarker(index);

    guideMap.flyTo([item.place.lat, item.place.lng], 13, {
        duration: 1.2
    });

    setTimeout(() => {
        item.marker.openPopup();
    }, 500);
}

function renderPlacesList() {
    if (!cityData?.places?.length) {
        placesGuideList.innerHTML = `
            <div class="empty-guide">
                No guide places found for ${escapeHtml(selectedCity)}.
                Please add locations for this city from the admin panel.
            </div>
        `;
        return;
    }

    placesGuideList.innerHTML = cityData.places.map((place, index) => {
        const whyGo = Array.isArray(place.why_go) ? place.why_go : [];
        const knowBefore = Array.isArray(place.know_before_you_go) ? place.know_before_you_go : [];

        return `
            <div class="place-card" id="guide-place-${index}">
                <img
                    class="place-card-image"
                    src="${escapeHtml(getImagePath(place))}"
                    alt="${escapeHtml(place.name)}"
                    onerror="this.onerror=null;this.src='/travelix/images/default_destination.jpg';"
                >

                <div class="place-card-body">
                    <div class="place-card-top">
                        <div class="place-number">${index + 1}</div>
                        <h3 class="place-title">${escapeHtml(place.name)}</h3>
                    </div>

                    <div class="place-description">
                        ${escapeHtml(place.description || '')}
                    </div>

                    <div class="info-box">
                        <h5>Why you should go</h5>
                        ${
                            whyGo.length
                                ? `<ol>${whyGo.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ol>`
                                : `<div>No details available.</div>`
                        }
                    </div>

                    <div class="info-box">
                        <h5>Know before you go</h5>
                        ${
                            knowBefore.length
                                ? `<ul>${knowBefore.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
                                : `<div>No travel tips available.</div>`
                        }
                    </div>

                    <div class="place-btn-row">
                        <button type="button" class="focus-map-btn" data-index="${index}">
                            Show on Map
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    document.querySelectorAll('.focus-map-btn').forEach((button) => {
        button.addEventListener('click', function () {
            focusPlaceOnMap(Number(this.dataset.index));
        });
    });
}

function initGuideMap() {
    if (typeof L === 'undefined') return;

    guideMap = L.map('cityGuideMap', {
        scrollWheelZoom: true
    }).setView(cityData.center, 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(guideMap);

    guideMarkers = [];

    if (!cityData?.places?.length) {
        guideMap.setView(cityData.center, 11);
        return;
    }

    cityData.places.forEach((place, index) => {
        const marker = L.marker([place.lat, place.lng], {
            icon: createMarkerIcon(index + 1, false)
        }).addTo(guideMap);

        marker.bindPopup(`
            <strong>${index + 1}. ${escapeHtml(place.name)}</strong><br>
            ${escapeHtml(place.description || '')}
        `);

        marker.on('click', function () {
            scrollToPlace(index);
        });

        guideMarkers.push({ marker, place });
    });

    if (cityData.places.length > 1) {
        const bounds = L.latLngBounds(cityData.places.map(place => [place.lat, place.lng]));
        guideMap.fitBounds(bounds, { padding: [40, 40] });
    } else if (cityData.places.length === 1) {
        guideMap.setView([cityData.places[0].lat, cityData.places[0].lng], 13);
    }
}

async function initPage() {
    cityData = await buildCityData();

    renderPlacesList();
    initGuideMap();
}

document.addEventListener('DOMContentLoaded', initPage);
window.focusPlaceOnMap = focusPlaceOnMap;
</script>

</body>
</html>