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
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/user_dashboard.php');
    exit;
}

$firstName = (string)($currentUser['first_name'] ?? 'Admin');
$lastName = (string)($currentUser['last_name'] ?? '');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
$fullName = trim($firstName . ' ' . $lastName);

$selectedSlugFromUrl = trim((string)($_GET['city'] ?? ''));
$addLocationsModeFromUrl = (string)($_GET['mode'] ?? '') === 'add_locations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add City | Travelix Admin</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="/travelix/assets/vendor/leaflet/leaflet.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
    <script src="/travelix/assets/js/travelix_autocomplete.js"></script>
    <script src="/travelix/assets/vendor/leaflet/leaflet.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
            --bg: #f4f8ff;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 18px 45px rgba(29, 78, 216, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            font-family: Arial, sans-serif;
            color: var(--text);
        }

        .admin-page {
            display: flex;
            gap: 24px;
            padding: 16px;
            min-height: 100vh;
        }

        .admin-content {
            flex: 1;
            min-width: 0;
        }

        .page-hero,
        .admin-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
        }

        .page-hero {
            padding: 24px 28px;
            margin-bottom: 22px;
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-hero h1 {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 34px;
        }

        .page-hero p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
            max-width: 880px;
        }

        .hero-badge {
            padding: 10px 15px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 700;
            border: 1px solid rgba(29, 78, 216, 0.10);
        }

        .admin-card {
            padding: 24px;
            margin-bottom: 22px;
        }

        .section-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .admin-card h3 {
            margin: 0 0 6px;
            color: var(--primary-dark);
            font-size: 24px;
            font-weight: 800;
        }

        .card-subtext {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .admin-info-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #f4fbfd;
            border: 1px solid rgba(20, 132, 180, 0.16);
        }

        .admin-info-bar img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 2px solid rgba(15, 23, 42, 0.08);
        }

        .admin-info-bar h6 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .admin-info-bar p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }

        .header-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .toggle-box-wrap {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toggle-box {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            font-weight: 700;
            color: #0f172a;
        }

        .toggle-box input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #0b8f9b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .form-control,
        textarea,
        select {
            width: 100%;
            border: 1px solid rgba(15,23,42,0.10);
            border-radius: 16px;
            min-height: 52px;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            box-shadow: none;
            background: #fff;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .helper-text {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .existing-city-panel {
            display: none;
            margin-top: 10px;
            padding: 20px;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .existing-city-panel.show {
            display: block;
        }

        .existing-city-summary {
            margin-top: 14px;
            padding: 16px;
            border-radius: 16px;
            background: #f8fbfd;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .existing-city-summary h6 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .existing-city-summary p {
            margin: 0 0 8px;
            color: #475569;
            font-size: 14px;
            line-height: 1.7;
        }

        .locations-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .locations-toolbar h4 {
            margin: 0;
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
        }

        .locations-toolbar p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .btn-main,
        .btn-soft,
        .btn-danger-soft {
            border: none;
            border-radius: 14px;
            padding: 13px 18px;
            font-weight: 800;
            transition: 0.25s ease;
        }

        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-main:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-soft {
            background: #eef4ff;
            color: var(--primary-dark);
        }

        .btn-soft:hover {
            transform: translateY(-1px);
        }

        .btn-danger-soft {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-danger-soft:hover {
            transform: translateY(-1px);
        }

        .location-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }

        .location-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .location-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: #eaf2ff;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
        }

        .location-card h5 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        .existing-locations-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .existing-location-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eaf2ff;
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .location-map-preview {
            height: 260px;
            width: 100%;
            border-radius: 14px;
            overflow: hidden;
            margin-top: 10px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #eef2f7;
            display: none;
        }

        .location-map-preview.show {
            display: block;
        }

        .map-helper-text {
            font-size: 12px;
            color: #57667a;
            margin-top: 6px;
        }

        .card-divider {
            height: 1px;
            background: rgba(15, 23, 42, 0.08);
            margin: 18px 0;
        }

        .list-editor {
            display: grid;
            gap: 10px;
        }

        .list-row {
            display: flex;
            gap: 10px;
        }

        .list-row input {
            flex: 1;
        }

        .image-preview {
            width: 100%;
            max-width: 220px;
            height: 140px;
            border-radius: 16px;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: #f8fafc;
            display: none;
            margin-top: 10px;
        }

        .image-preview.show {
            display: block;
        }

        .empty-locations {
            padding: 26px;
            border: 1px dashed rgba(15, 23, 42, 0.18);
            border-radius: 20px;
            text-align: center;
            color: var(--muted);
            background: #f8fbfc;
        }

        .preview-box {
            background: #f8fbfd;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 18px;
            padding: 18px;
        }

        .preview-box h5 {
            margin: 0 0 10px;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        .preview-box p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 14px;
        }

        .preview-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .preview-stat {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 18px;
            padding: 16px;
        }

        .preview-stat h6 {
            margin: 0 0 6px;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .preview-stat p {
            margin: 0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .readonly-look {
            background: #f8fafc !important;
            color: #64748b !important;
            cursor: not-allowed;
        }

        @media (max-width: 1199px) {
            .admin-page {
                display: block;
                padding: 0;
            }

            .admin-content {
                padding: 0 16px 16px;
            }

            .preview-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .form-grid,
            .preview-summary-grid {
                grid-template-columns: 1fr;
            }

            .page-hero,
            .admin-card,
            .location-card {
                padding: 18px;
            }

            .page-hero h1 {
                font-size: 28px;
            }

            .list-row {
                flex-direction: column;
            }

            .footer-actions {
                justify-content: stretch;
            }

            .footer-actions button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="page-hero">
            <div class="hero-top">
                <div>
                    <h1>Add City</h1>
                    <p>
                        Create a new city or add more locations into an existing city. Existing-city mode lets admin
                        choose a city from dropdown and append only new locations to it.
                    </p>
                </div>
                <div class="hero-badge">Firebase + Location Images</div>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-info-bar">
                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Admin">
                <div>
                    <h6><?php echo htmlspecialchars($fullName ?: 'Admin'); ?></h6>
                    <p>Images will be uploaded to <strong>/location_images</strong> and destination data will be saved in Firebase.</p>
                </div>
            </div>

            <div class="header-controls">
                <div>
                    <span class="section-badge">Destination Entry</span>
                    <h3 id="formHeading">Create New City</h3>
                    <p class="card-subtext" id="formSubtext">
                        Add one city and one or more locations. If you want to add locations in an already existing city,
                        check the Add Locations checkbox below.
                    </p>
                </div>

                <div class="toggle-box-wrap">
                    <label class="toggle-box">
                        <input type="checkbox" id="enableAddLocationsMode">
                        <span>Add Locations</span>
                    </label>
                </div>
            </div>

            <div id="existingCityPanel" class="existing-city-panel">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="existingCitySelect">Select Existing City</label>
                        <input type="text" id="existingCitySelect" class="form-control" placeholder="Type to search existing cities...">
                        <div class="helper-text">
                            When you select a city, its city fields will auto fill and lock. You can then add only new locations.
                        </div>
                    </div>
                </div>

                <div class="existing-city-summary" id="existingCitySummary" style="display:none;">
                    <h6>Selected City Summary</h6>
                    <p><strong>City:</strong> <span id="existingSummaryCity">-</span></p>
                    <p><strong>Slug:</strong> <span id="existingSummarySlug">-</span></p>
                    <p><strong>Current Locations:</strong> <span id="existingSummaryLocations">0</span></p>
                    <div class="existing-locations-list" id="existingSummaryLocationsList"></div>
                    <p><strong>Description:</strong> <span id="existingSummaryDescription">-</span></p>
                </div>
            </div>

            <form id="addTripForm" novalidate>
                <input type="hidden" id="selectedExistingCitySlug" name="selectedExistingCitySlug" value="">
                <input type="hidden" id="addLocationsMode" name="addLocationsMode" value="0">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="cityName">City Name</label>
                        <input type="text" class="form-control" id="cityName" name="cityName" placeholder="e.g. Islamabad">
                        <button type="button" class="btn btn-soft mt-2" id="findCityLocationBtn">🔍 Find Location</button>
                        <div class="helper-text" id="cityLocationStatus"></div>
                    </div>

                    <input type="hidden" id="citySlug" name="citySlug" value="">

                    <div class="form-group">
                        <label class="form-label" for="centerLat">Map Center Latitude</label>
                        <input type="number" step="any" class="form-control" id="centerLat" name="centerLat" placeholder="Auto-filled by Find Location, or enter manually">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="centerLng">Map Center Longitude</label>
                        <input type="number" step="any" class="form-control" id="centerLng" name="centerLng" placeholder="Auto-filled by Find Location, or enter manually">
                    </div>

                    <div class="form-group full">
                        <label class="form-label">City Location Preview</label>
                        <div id="cityLocationMap" class="location-map-preview"></div>
                        <div class="map-helper-text">Drag the pin to fine-tune the exact city location — latitude/longitude above update automatically.</div>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="cityDescription">City Description</label>
                        <textarea id="cityDescription" name="cityDescription" placeholder="Write a short description about this city..."></textarea>
                    </div>
                </div>

                <div class="card-divider"></div>

                <div class="form-group full" id="famousPlacesWrap" style="display:none;">
                    <label class="form-label" for="famousPlacesSelect">Famous Places In This City</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <input type="text" id="famousPlacesSelect" class="form-control" style="flex:1;min-width:220px;" placeholder="Type to search famous places...">
                        <button type="button" class="btn btn-main" id="addFamousPlaceBtn">+ Add Selected Place</button>
                    </div>
                    <div class="helper-text" id="famousPlacesStatus">Fetched automatically once the city location is found. Each selected place is added as a location with its location, description, and image auto-filled.</div>
                </div>

                <div class="card-divider"></div>

                <div class="locations-toolbar">
                    <div>
                        <h4>Locations</h4>
                        <p id="locationHelperText">Add one or more locations for this city.</p>
                    </div>
                    <button type="button" class="btn btn-main" id="addLocationBtn">+ Add Location</button>
                </div>

                <div id="locationsContainer">
                    <div class="empty-locations" id="emptyLocationsState">
                        No location added yet. Click <strong>Add Location</strong> to begin.
                    </div>
                </div>

                <div class="card-divider"></div>

                <div class="preview-box">
                    <h5>Save Preview</h5>
                    <p>
                        Duplicate city locations will not be added again. If the city already exists, only new locations will be appended.
                    </p>

                    <div class="preview-summary-grid">
                        <div class="preview-stat">
                            <h6>City</h6>
                            <p id="previewCity">-</p>
                        </div>

                        <div class="preview-stat">
                            <h6>Slug</h6>
                            <p id="previewSlug">-</p>
                        </div>

                        <div class="preview-stat">
                            <h6>Locations</h6>
                            <p id="previewLocationCount">0</p>
                        </div>

                        <div class="preview-stat">
                            <h6>Mode</h6>
                            <p id="previewMode">Manual</p>
                        </div>
                    </div>
                </div>

                <div class="card-divider"></div>

                <div class="footer-actions">
                    <button type="button" class="btn btn-soft" id="resetFormBtn">Reset</button>
                    <button type="submit" class="btn btn-main" id="saveTripBtn">Save City</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    const BASE_URL = <?php echo json_encode($baseUrl); ?>;
    const PRESELECT_CITY_SLUG = <?php echo json_encode($selectedSlugFromUrl); ?>;
    const PRESELECT_ADD_LOCATIONS_MODE = <?php echo json_encode($addLocationsModeFromUrl); ?>;

    const form = document.getElementById('addTripForm');

    const enableAddLocationsMode = document.getElementById('enableAddLocationsMode');

    const existingCityPanel = document.getElementById('existingCityPanel');

    const existingCitySelect = document.getElementById('existingCitySelect');
    const selectedExistingCitySlug = document.getElementById('selectedExistingCitySlug');
    const addLocationsModeInput = document.getElementById('addLocationsMode');

    const existingCitySummary = document.getElementById('existingCitySummary');
    const existingSummaryCity = document.getElementById('existingSummaryCity');
    const existingSummarySlug = document.getElementById('existingSummarySlug');
    const existingSummaryLocations = document.getElementById('existingSummaryLocations');
    const existingSummaryLocationsList = document.getElementById('existingSummaryLocationsList');
    const existingSummaryDescription = document.getElementById('existingSummaryDescription');
    let currentExistingCity = null;

    const existingCitySelectAutocomplete = travelixAutocomplete(existingCitySelect, {
        getItems: () => existingCities.map((city) => ({
            value: city.slug || '',
            label: `${city.city} (${city.slug})`
        })),
        emptyText: 'No existing city matches that.',
        onSelect: (item) => {
            const selectedCity = existingCities.find((city) => city.slug === item.value);
            clearLocationCards();
            applyExistingCity(selectedCity || null);
            toggleEmptyLocations();
            refreshLocationLabels();
        },
        onClear: () => {
            clearLocationCards();
            applyExistingCity(null);
            toggleEmptyLocations();
            refreshLocationLabels();
        }
    });

    const cityName = document.getElementById('cityName');
    const citySlug = document.getElementById('citySlug');
    const centerLat = document.getElementById('centerLat');
    const centerLng = document.getElementById('centerLng');
    const cityDescription = document.getElementById('cityDescription');
    const findCityLocationBtn = document.getElementById('findCityLocationBtn');
    const cityLocationStatus = document.getElementById('cityLocationStatus');
    const famousPlacesWrap = document.getElementById('famousPlacesWrap');
    const famousPlacesSelect = document.getElementById('famousPlacesSelect');
    const addFamousPlaceBtn = document.getElementById('addFamousPlaceBtn');
    const famousPlacesStatus = document.getElementById('famousPlacesStatus');
    let famousPlaces = [];
    let selectedFamousPlaceIndex = '';

    const famousPlacesSelectAutocomplete = travelixAutocomplete(famousPlacesSelect, {
        getItems: () => {
            const addedNames = getAlreadyAddedPlaceNames();
            return famousPlaces
                .map((place, i) => ({ index: i, place }))
                .filter(({ place }) => !addedNames.has((place.name || '').trim().toLowerCase()))
                .map(({ index, place }) => ({ value: String(index), label: place.name }));
        },
        strict: false,
        emptyText: 'No matching famous place found.',
        onSelect: (item) => {
            selectedFamousPlaceIndex = item.value;
        },
        onClear: () => {
            selectedFamousPlaceIndex = '';
        }
    });

    function initMapPreview(container, latInput, lngInput) {
        let map = null;
        let marker = null;

        function ensureMap(lat, lng) {
            if (map) return;

            container.classList.add('show');

            map = L.map(container, { scrollWheelZoom: false }).setView([lat, lng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                latInput.value = pos.lat.toFixed(6);
                lngInput.value = pos.lng.toFixed(6);
                latInput.dispatchEvent(new Event('input', { bubbles: true }));
                lngInput.dispatchEvent(new Event('input', { bubbles: true }));
            });

            setTimeout(() => map.invalidateSize(), 150);
        }

        return {
            setPosition(lat, lng) {
                const numLat = Number(lat);
                const numLng = Number(lng);

                if (lat === '' || lng === '' || lat === null || lng === null || Number.isNaN(numLat) || Number.isNaN(numLng)) {
                    return;
                }

                if (!map) {
                    ensureMap(numLat, numLng);
                    return;
                }

                marker.setLatLng([numLat, numLng]);
                map.setView([numLat, numLng], Math.max(map.getZoom(), 14));
                setTimeout(() => map.invalidateSize(), 100);
            },
            reset() {
                if (map) {
                    map.remove();
                    map = null;
                    marker = null;
                }
                container.classList.remove('show');
            }
        };
    }

    const cityMapPreview = initMapPreview(document.getElementById('cityLocationMap'), centerLat, centerLng);

    const addLocationBtn = document.getElementById('addLocationBtn');
    const locationsContainer = document.getElementById('locationsContainer');
    const resetFormBtn = document.getElementById('resetFormBtn');

    const previewCity = document.getElementById('previewCity');
    const previewSlug = document.getElementById('previewSlug');
    const previewLocationCount = document.getElementById('previewLocationCount');
    const previewMode = document.getElementById('previewMode');

    const formHeading = document.getElementById('formHeading');
    const formSubtext = document.getElementById('formSubtext');
    const locationHelperText = document.getElementById('locationHelperText');

    let emptyLocationsState = document.getElementById('emptyLocationsState');
    let locationIndex = 0;
    let existingCities = [];

    function showLoader(title = 'Please wait...', text = 'Saving...') {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function closeLoader() {
        Swal.close();
    }

    function showError(title, text) {
        return Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonColor: '#1484B4'
        });
    }

    function showSuccess(title, text) {
        return Swal.fire({
            icon: 'success',
            title,
            text,
            confirmButtonColor: '#1484B4'
        });
    }

    async function fetchGeocode(query, kind) {
        const response = await fetch(`${BASE_URL}/admin_manage/ajax/geocode_lookup.php?kind=${encodeURIComponent(kind)}&q=${encodeURIComponent(query)}`, {
            method: 'GET',
            credentials: 'same-origin'
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Location lookup failed.');
        }

        return result;
    }

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setCityFieldsReadonly(isReadonly) {
        [cityName, citySlug, centerLat, centerLng, cityDescription].forEach((field) => {
            field.readOnly = isReadonly;
            if (isReadonly) {
                field.classList.add('readonly-look');
            } else {
                field.classList.remove('readonly-look');
            }
        });
    }

    function clearLocationCards() {
        locationsContainer.innerHTML = `<div class="empty-locations" id="emptyLocationsState">No location added yet. Click <strong>Add Location</strong> to begin.</div>`;
        emptyLocationsState = document.getElementById('emptyLocationsState');
        locationIndex = 0;
    }

    function createListRow(placeholder = 'Add item...') {
        const row = document.createElement('div');
        row.className = 'list-row';
        row.innerHTML = `
            <input type="text" class="form-control list-input" placeholder="${escapeHtml(placeholder)}">
            <button type="button" class="btn btn-danger-soft remove-list-item-btn">Remove</button>
        `;

        row.querySelector('.remove-list-item-btn')?.addEventListener('click', function () {
            row.remove();
            updatePreview();
        });

        row.querySelector('.list-input')?.addEventListener('input', updatePreview);
        return row;
    }

    function createLocationCard() {
        const index = locationIndex++;
        const card = document.createElement('div');
        card.className = 'location-card';
        card.dataset.index = String(index);

        card.innerHTML = `
            <div class="location-top">
                <div>
                    <div class="location-badge">Location ${index + 1}</div>
                    <h5>Location Details</h5>
                </div>
                <button type="button" class="btn btn-danger-soft remove-location-btn">Remove Location</button>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Location Name</label>
                    <input type="text" class="form-control location-name" name="locations[${index}][name]" placeholder="e.g. Centaurus Mall">
                    <button type="button" class="btn btn-soft mt-2 find-location-btn">🔍 Find Location</button>
                    <div class="helper-text location-lookup-status"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Location Image</label>
                    <input type="file" class="form-control location-image-file" name="locations[${index}][image]" accept="image/*">
                    <input type="hidden" class="location-image-url" name="locations[${index}][image_url]" value="">
                    <div class="helper-text">Upload your own image, or use Find Location to fetch one automatically. Uploaded file always wins.</div>
                    <img class="image-preview" alt="Location Preview">
                </div>

                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="any" class="form-control location-lat" name="locations[${index}][lat]" placeholder="Auto-filled by Find Location, or enter manually">
                </div>

                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="any" class="form-control location-lng" name="locations[${index}][lng]" placeholder="Auto-filled by Find Location, or enter manually">
                </div>

                <div class="form-group full">
                    <label class="form-label">Location Preview</label>
                    <div class="location-map-preview"></div>
                    <div class="map-helper-text">Drag the pin to fine-tune the exact spot — latitude/longitude above update automatically.</div>
                </div>

                <div class="form-group full">
                    <label class="form-label">Description About Location</label>
                    <textarea class="location-description" name="locations[${index}][description]" placeholder="Write a short description, or use Find Location to fetch one automatically..."></textarea>
                </div>
            </div>

            <div class="card-divider"></div>

            <div class="form-group">
                <label class="form-label">Why Go</label>
                <div class="list-editor why-go-list"></div>
                <div class="helper-text">Add reasons why users should visit this location.</div>
                <button type="button" class="btn btn-soft add-why-go-btn mt-2">+ Add Why Go</button>
            </div>

            <div class="form-group">
                <label class="form-label">Know Before You Go</label>
                <div class="list-editor know-before-list"></div>
                <div class="helper-text">Add travel tips, timing, or notes.</div>
                <button type="button" class="btn btn-soft add-know-before-btn mt-2">+ Add Travel Tip</button>
            </div>
        `;

        const whyGoList = card.querySelector('.why-go-list');
        const knowBeforeList = card.querySelector('.know-before-list');

        function addHiddenListInputs() {
            card.querySelectorAll('input[type="hidden"].dynamic-list-hidden').forEach(el => el.remove());

            const whyGoValues = Array.from(whyGoList.querySelectorAll('.list-input'))
                .map(input => input.value.trim())
                .filter(Boolean);

            const knowValues = Array.from(knowBeforeList.querySelectorAll('.list-input'))
                .map(input => input.value.trim())
                .filter(Boolean);

            whyGoValues.forEach((value) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'dynamic-list-hidden';
                hidden.name = `locations[${index}][why_go][]`;
                hidden.value = value;
                card.appendChild(hidden);
            });

            knowValues.forEach((value) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'dynamic-list-hidden';
                hidden.name = `locations[${index}][know_before_you_go][]`;
                hidden.value = value;
                card.appendChild(hidden);
            });
        }

        whyGoList.appendChild(createListRow('Why should users go here?'));
        knowBeforeList.appendChild(createListRow('Travel tip or note...'));
        addHiddenListInputs();

        card.querySelector('.add-why-go-btn')?.addEventListener('click', function () {
            const row = createListRow('Why should users go here?');
            row.querySelector('.list-input')?.addEventListener('input', addHiddenListInputs);
            row.querySelector('.remove-list-item-btn')?.addEventListener('click', () => {
                setTimeout(addHiddenListInputs, 0);
            });
            whyGoList.appendChild(row);
            addHiddenListInputs();
            updatePreview();
        });

        card.querySelector('.add-know-before-btn')?.addEventListener('click', function () {
            const row = createListRow('Travel tip or note...');
            row.querySelector('.list-input')?.addEventListener('input', addHiddenListInputs);
            row.querySelector('.remove-list-item-btn')?.addEventListener('click', () => {
                setTimeout(addHiddenListInputs, 0);
            });
            knowBeforeList.appendChild(row);
            addHiddenListInputs();
            updatePreview();
        });

        card.querySelectorAll('.list-input').forEach((input) => {
            input.addEventListener('input', addHiddenListInputs);
        });

        card.querySelectorAll('.remove-list-item-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                setTimeout(addHiddenListInputs, 0);
            });
        });

        card.querySelector('.remove-location-btn')?.addEventListener('click', function () {
            card.remove();
            refreshLocationLabels();
            toggleEmptyLocations();
            updatePreview();
            syncFamousPlacesAvailability();
        });

        const imageInput = card.querySelector('.location-image-file');
        const preview = card.querySelector('.image-preview');
        const imageUrlInput = card.querySelector('.location-image-url');
        const findLocationBtn = card.querySelector('.find-location-btn');
        const lookupStatus = card.querySelector('.location-lookup-status');
        const nameInput = card.querySelector('.location-name');
        const latInput = card.querySelector('.location-lat');
        const lngInput = card.querySelector('.location-lng');
        const descriptionInput = card.querySelector('.location-description');
        const mapContainer = card.querySelector('.location-map-preview');
        const mapPreview = initMapPreview(mapContainer, latInput, lngInput);

        [latInput, lngInput].forEach((field) => {
            field.addEventListener('input', () => {
                mapPreview.setPosition(latInput.value, lngInput.value);
            });
        });

        findLocationBtn?.addEventListener('click', async function () {
            const query = nameInput.value.trim();

            if (!query) {
                showError('Location Name Required', 'Please type a location name first.');
                return;
            }

            const originalText = findLocationBtn.textContent;
            findLocationBtn.disabled = true;
            findLocationBtn.textContent = 'Searching...';
            lookupStatus.textContent = '';

            try {
                const result = await fetchGeocode(query, 'place');
                const foundParts = [];

                if (result.lat !== null && result.lng !== null) {
                    latInput.value = result.lat;
                    lngInput.value = result.lng;
                    mapPreview.setPosition(result.lat, result.lng);
                    foundParts.push('location');
                }

                if (result.description) {
                    descriptionInput.value = result.description;
                    foundParts.push('description');
                }

                if (result.imageUrl) {
                    imageUrlInput.value = result.imageUrl;
                    preview.src = result.imageUrl;
                    preview.classList.add('show');
                    foundParts.push('image');
                }

                lookupStatus.textContent = foundParts.length
                    ? `Found ${foundParts.join(', ')} automatically. You can still edit or upload your own image.`
                    : 'No results found for this name. Please fill in the details manually.';

                addHiddenListInputs();
                updatePreview();
            } catch (error) {
                lookupStatus.textContent = error.message || 'Location lookup failed. Please fill in manually.';
            } finally {
                findLocationBtn.disabled = false;
                findLocationBtn.textContent = originalText;
            }
        });

        imageInput?.addEventListener('change', function () {
            const file = this.files?.[0];
            if (!file) {
                preview.src = '';
                preview.classList.remove('show');
                updatePreview();
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target?.result || '';
                preview.classList.add('show');
            };
            reader.readAsDataURL(file);
            updatePreview();
        });

        card.querySelectorAll('input, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                addHiddenListInputs();
                updatePreview();
                syncFamousPlacesAvailability();
            });
            field.addEventListener('change', () => {
                addHiddenListInputs();
                updatePreview();
                syncFamousPlacesAvailability();
            });
        });

        return card;
    }

    function refreshLocationLabels() {
        const cards = locationsContainer.querySelectorAll('.location-card');
        cards.forEach((card, index) => {
            const badge = card.querySelector('.location-badge');
            if (badge) badge.textContent = `Location ${index + 1}`;
        });
    }

    function toggleEmptyLocations() {
        const cards = locationsContainer.querySelectorAll('.location-card');
        if (emptyLocationsState) {
            emptyLocationsState.style.display = cards.length ? 'none' : 'block';
        }
    }

    function updatePreview() {
        const manualCount = locationsContainer.querySelectorAll('.location-card').length;

        previewCity.textContent = cityName.value.trim() || '-';
        previewSlug.textContent = slugify(citySlug.value.trim() || cityName.value.trim()) || '-';
        previewLocationCount.textContent = String(manualCount);

        if (enableAddLocationsMode.checked) {
            previewMode.textContent = 'Existing City + Manual';
        } else {
            previewMode.textContent = 'Manual';
        }
    }

    function resetManualFieldsOnly() {
        cityName.value = '';
        citySlug.value = '';
        centerLat.value = '';
        centerLng.value = '';
        cityDescription.value = '';
        citySlug.dataset.userEdited = '';
        cityMapPreview.reset();
    }

    function applyExistingCity(city) {
        currentExistingCity = city || null;

        if (!city) {
            resetManualFieldsOnly();
            existingCitySummary.style.display = 'none';
            existingSummaryLocationsList.innerHTML = '';
            selectedExistingCitySlug.value = '';
            setCityFieldsReadonly(false);
            famousPlaces = [];
            famousPlacesWrap.style.display = 'none';
            famousPlacesSelectAutocomplete.clear();
            famousPlacesStatus.textContent = '';
            updatePreview();
            return;
        }

        cityName.value = city.city || '';
        citySlug.value = city.slug || '';
        centerLat.value = city.center?.lat ?? '';
        centerLng.value = city.center?.lng ?? '';
        cityDescription.value = city.description || '';

        selectedExistingCitySlug.value = city.slug || '';

        existingSummaryCity.textContent = city.city || '-';
        existingSummarySlug.textContent = city.slug || '-';
        existingSummaryLocations.textContent = Array.isArray(city.places) ? city.places.length : 0;
        existingSummaryDescription.textContent = city.description || '-';

        existingSummaryLocationsList.innerHTML = '';
        (Array.isArray(city.places) ? city.places : []).forEach((place) => {
            const name = (place.name || '').trim();
            if (!name) return;
            const chip = document.createElement('span');
            chip.className = 'existing-location-chip';
            chip.textContent = name;
            existingSummaryLocationsList.appendChild(chip);
        });

        existingCitySummary.style.display = 'block';

        setCityFieldsReadonly(true);
        updatePreview();
        cityMapPreview.setPosition(city.center?.lat, city.center?.lng);

        if (city.city) {
            loadFamousPlaces(city.city);
        }
    }

    async function loadExistingCities() {
        try {
            const response = await fetch(`${BASE_URL}/admin_manage/ajax/get_trip_cities.php`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to load cities.');
            }

            existingCities = Array.isArray(result.cities) ? result.cities : [];
            existingCitySelectAutocomplete.refresh();

            if (PRESELECT_CITY_SLUG) {
                const selectedCity = existingCities.find(city => city.slug === PRESELECT_CITY_SLUG);
                if (selectedCity) {
                    existingCitySelectAutocomplete.setValue(`${selectedCity.city} (${selectedCity.slug})`);
                    applyExistingCity(selectedCity);
                }
            }
        } catch (error) {
            console.error(error);
            showError('Load Failed', error.message || 'Could not load existing cities.');
        }
    }

    function handleAddLocationsModeToggle() {
        const enabled = enableAddLocationsMode.checked;
        addLocationsModeInput.value = enabled ? '1' : '0';
        existingCityPanel.classList.toggle('show', enabled);

        if (enabled) {
            formHeading.textContent = 'Add Locations To Existing City';
            formSubtext.textContent = 'Select an existing city from dropdown, then add only new locations to that city.';
            locationHelperText.textContent = 'Add one or more new locations for the selected existing city.';
            clearLocationCards();
            loadExistingCities();
        } else {
            formHeading.textContent = 'Create New City';
            formSubtext.textContent = 'Add one city and one or more locations. If you want to add locations in an already existing city, check the Add Locations checkbox below.';
            locationHelperText.textContent = 'Add one or more locations for this city.';
            existingCitySelectAutocomplete.clear();
            selectedExistingCitySlug.value = '';
            existingCitySummary.style.display = 'none';
            existingSummaryLocationsList.innerHTML = '';
            currentExistingCity = null;
            setCityFieldsReadonly(false);
            resetManualFieldsOnly();
            updatePreview();
        }

        famousPlaces = [];
        famousPlacesWrap.style.display = 'none';
        famousPlacesSelectAutocomplete.clear();
        famousPlacesStatus.textContent = '';

        updatePreview();
    }

    function resetForm() {
        form.reset();

        clearLocationCards();

        if (PRESELECT_ADD_LOCATIONS_MODE) {
            enableAddLocationsMode.checked = true;
            handleAddLocationsModeToggle();
        } else {
            enableAddLocationsMode.checked = false;
            handleAddLocationsModeToggle();
        }

        updatePreview();
    }

    addLocationBtn.addEventListener('click', function () {
        if (enableAddLocationsMode.checked && !existingCitySelect.value) {
            showError('Select City First', 'Please select an existing city first, then add locations.');
            return;
        }

        const card = createLocationCard();
        locationsContainer.appendChild(card);
        toggleEmptyLocations();
        refreshLocationLabels();
        updatePreview();
    });

    enableAddLocationsMode.addEventListener('change', handleAddLocationsModeToggle);

    cityName.addEventListener('input', function () {
        if (enableAddLocationsMode.checked) return;
        citySlug.value = slugify(this.value);
        updatePreview();
    });

    [centerLat, centerLng, cityDescription].forEach((field) => {
        field.addEventListener('input', updatePreview);
    });

    [centerLat, centerLng].forEach((field) => {
        field.addEventListener('input', () => {
            cityMapPreview.setPosition(centerLat.value, centerLng.value);
        });
    });

    function getAlreadyAddedPlaceNames() {
        const names = new Set();

        if (currentExistingCity && Array.isArray(currentExistingCity.places)) {
            currentExistingCity.places.forEach((place) => {
                const name = (place.name || '').trim().toLowerCase();
                if (name) names.add(name);
            });
        }

        document.querySelectorAll('.location-name').forEach((input) => {
            const name = input.value.trim().toLowerCase();
            if (name) names.add(name);
        });

        return names;
    }

    function syncFamousPlacesAvailability() {
        famousPlacesSelectAutocomplete.refresh();
    }

    async function loadFamousPlaces(query) {
        famousPlacesWrap.style.display = 'block';
        famousPlacesSelectAutocomplete.clear();
        famousPlacesStatus.textContent = 'Searching for famous places in this city...';

        try {
            const response = await fetch(`${BASE_URL}/admin_manage/ajax/geocode_lookup.php?kind=city_places&q=${encodeURIComponent(query)}`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            const result = await response.json();

            famousPlaces = Array.isArray(result.places) ? result.places : [];

            famousPlacesStatus.textContent = famousPlaces.length
                ? `Found ${famousPlaces.length} nearby place(s). Type to search — places already added won't be shown again.`
                : 'No famous places found nearby. You can add locations manually below.';

            syncFamousPlacesAvailability();
        } catch (error) {
            famousPlaces = [];
            famousPlacesSelectAutocomplete.clear();
            famousPlacesStatus.textContent = 'Could not fetch famous places automatically. You can add locations manually below.';
        }
    }

    addFamousPlaceBtn.addEventListener('click', async function () {
        const idx = selectedFamousPlaceIndex;
        if (idx === '') {
            showError('Select A Place', 'Please select a famous place from the dropdown first.');
            return;
        }

        if (enableAddLocationsMode.checked && !existingCitySelect.value) {
            showError('Select City First', 'Please select an existing city first, then add locations.');
            return;
        }

        const place = famousPlaces[Number(idx)];
        if (!place) return;

        const card = createLocationCard();
        locationsContainer.appendChild(card);
        toggleEmptyLocations();
        refreshLocationLabels();

        const nameInput = card.querySelector('.location-name');
        const latInput = card.querySelector('.location-lat');
        const lngInput = card.querySelector('.location-lng');
        const descriptionInput = card.querySelector('.location-description');
        const imageUrlInput = card.querySelector('.location-image-url');
        const preview = card.querySelector('.image-preview');
        const lookupStatus = card.querySelector('.location-lookup-status');

        nameInput.value = place.name;
        nameInput.dispatchEvent(new Event('input', { bubbles: true }));

        // Use the accurate coordinates already returned by the nearby-places search
        // (re-geocoding by name alone can match an unrelated same-named place elsewhere).
        latInput.value = place.lat;
        lngInput.value = place.lng;
        latInput.dispatchEvent(new Event('input', { bubbles: true }));
        lngInput.dispatchEvent(new Event('input', { bubbles: true }));

        lookupStatus.textContent = 'Fetching description and image...';

        try {
            const result = await fetchGeocode(place.name, 'place');
            const foundParts = ['location'];

            if (result.description) {
                descriptionInput.value = result.description;
                descriptionInput.dispatchEvent(new Event('input', { bubbles: true }));
                foundParts.push('description');
            }

            if (result.imageUrl) {
                imageUrlInput.value = result.imageUrl;
                preview.src = result.imageUrl;
                preview.classList.add('show');
                foundParts.push('image');
            }

            lookupStatus.textContent = `Found ${foundParts.join(', ')} automatically. You can still edit or upload your own image.`;
        } catch (error) {
            lookupStatus.textContent = 'Location added. Could not fetch description/image automatically — please fill in manually.';
        }

        famousPlacesSelectAutocomplete.clear();
        selectedFamousPlaceIndex = '';
        syncFamousPlacesAvailability();

        updatePreview();
    });

    findCityLocationBtn.addEventListener('click', async function () {
        const query = cityName.value.trim();

        if (!query) {
            showError('City Name Required', 'Please type a city name first.');
            return;
        }

        const originalText = findCityLocationBtn.textContent;
        findCityLocationBtn.disabled = true;
        findCityLocationBtn.textContent = 'Searching...';
        cityLocationStatus.textContent = '';

        try {
            const result = await fetchGeocode(query, 'city');

            if (result.lat !== null && result.lng !== null) {
                centerLat.value = result.lat;
                centerLng.value = result.lng;
                cityLocationStatus.textContent = 'Location found and filled in. You can still edit it manually.';
                cityMapPreview.setPosition(result.lat, result.lng);
                updatePreview();
            } else {
                cityLocationStatus.textContent = 'No location found for this name. Please enter latitude/longitude manually.';
            }
        } catch (error) {
            cityLocationStatus.textContent = error.message || 'Location lookup failed. Please enter manually.';
        } finally {
            findCityLocationBtn.disabled = false;
            findCityLocationBtn.textContent = originalText;
        }

        loadFamousPlaces(query);
    });

    resetFormBtn.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset form?',
            text: 'All current city data will be cleared.',
            showCancelButton: true,
            confirmButtonText: 'Yes, reset',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                resetForm();
            }
        });
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            const hasManualLocations = locationsContainer.querySelectorAll('.location-card').length > 0;

            if (enableAddLocationsMode.checked && !existingCitySelect.value) {
                throw new Error('Please select an existing city first.');
            }

            if (!hasManualLocations) {
                throw new Error('Please add at least one location.');
            }

            showLoader(
                'Saving City...',
                'Please wait while location images and city data are being saved.'
            );

            const formData = new FormData(form);
            formData.append('addLocationsMode', enableAddLocationsMode.checked ? '1' : '0');
            formData.append('selectedExistingCitySlug', selectedExistingCitySlug.value || '');

            const response = await fetch(`${BASE_URL}/admin_manage/ajax/save_trip_destination.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const rawText = await response.text();

            let result;
            try {
                result = JSON.parse(rawText);
            } catch (error) {
                console.error('Raw server response:', rawText);
                throw new Error('Server did not return valid JSON. Check PHP errors or redirects.');
            }

            closeLoader();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to save city.');
            }

            await showSuccess(
                'City Saved',
                result.message || 'City saved successfully.'
            );

            resetForm();
        } catch (error) {
            closeLoader();
            showError('Save Failed', error.message || 'Unable to save city.');
        }
    });

    updatePreview();

    if (PRESELECT_ADD_LOCATIONS_MODE) {
        enableAddLocationsMode.checked = true;
        handleAddLocationsModeToggle();
    } else {
        handleAddLocationsModeToggle();
    }
</script>
</body>
</html>