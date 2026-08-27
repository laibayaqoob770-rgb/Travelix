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
$addHotelsModeFromUrl = (string)($_GET['mode'] ?? '') === 'add_hotels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Hotels | Travelix Admin</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
    <script src="/travelix/assets/js/travelix_autocomplete.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
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
            background: rgba(255,255,255,0.96);
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
            max-width: 900px;
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

        .bulk-panel,
        .existing-city-panel {
            display: none;
            margin-top: 10px;
            margin-bottom: 22px;
            padding: 20px;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .bulk-panel.show,
        .existing-city-panel.show {
            display: block;
        }

        .bulk-columns-box {
            margin-top: 14px;
            padding: 16px;
            border-radius: 16px;
            background: #f8fbfd;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .bulk-columns-box h6 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .bulk-columns-box code {
            display: block;
            padding: 10px 12px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            color: #334155;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
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
        select.form-control {
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
            min-height: 110px;
            resize: vertical;
        }

        .helper-text {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .hotel-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .hotel-toolbar h4 {
            margin: 0;
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
        }

        .hotel-toolbar p {
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

        .btn-danger-soft {
            background: #fee2e2;
            color: #b91c1c;
        }

        .hotel-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }

        .hotel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .hotel-badge {
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

        .hotel-card h5 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
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
            max-width: 240px;
            height: 150px;
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

        .empty-hotels {
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
            .hotel-card {
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
                    <h1>Add Hotel Destination</h1>
                    <p>
                        Create a new hotel city or add more hotels into an existing city. Existing-city mode lets admin
                        choose a hotel city from dropdown and append only new hotels to it.
                    </p>
                </div>
                <div class="hero-badge">Firestore + Hotel Images</div>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-info-bar">
                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Admin">
                <div>
                    <h6><?php echo htmlspecialchars($fullName ?: 'Admin'); ?></h6>
                    <p>Hotel images will be uploaded to <strong>/hotel_images</strong> and hotel data will be saved in Firestore.</p>
                </div>
            </div>

            <div class="header-controls">
                <div>
                    <span class="section-badge">Hotel Entry</span>
                    <h3 id="formHeading">Create New Hotel City</h3>
                    <p class="card-subtext" id="formSubtext">
                        Add a city center and one or more hotels. If you want to add hotels in an already existing hotel city,
                        check the Add Hotels checkbox below.
                    </p>
                </div>

                <div class="toggle-box-wrap">
                    <label class="toggle-box">
                        <input type="checkbox" id="enableAddHotelsMode">
                        <span>Add Hotels</span>
                    </label>

                    <label class="toggle-box">
                        <input type="checkbox" id="enableBulkUpload">
                        <span>Upload Bulk Data</span>
                    </label>
                </div>
            </div>

            <div id="existingCityPanel" class="existing-city-panel">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="existingCitySelect">Select Existing Hotel City</label>
                        <input type="text" id="existingCitySelect" class="form-control" placeholder="Type to search existing hotel cities...">
                        <div class="helper-text">
                            When you select a city, city fields will auto fill and lock. You can then add only new hotels in that city.
                        </div>
                    </div>
                </div>

                <div class="existing-city-summary" id="existingCitySummary" style="display:none;">
                    <h6>Selected Hotel City Summary</h6>
                    <p><strong>City:</strong> <span id="existingSummaryCity">-</span></p>
                    <p><strong>Slug:</strong> <span id="existingSummarySlug">-</span></p>
                    <p><strong>Current Hotels:</strong> <span id="existingSummaryHotels">0</span></p>
                    <p><strong>Center:</strong> <span id="existingSummaryCenter">-</span></p>
                </div>
            </div>

            <div id="bulkPanel" class="bulk-panel">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="bulkDatasetFile">Bulk Dataset File (.csv or .txt)</label>
                        <input type="file" class="form-control" id="bulkDatasetFile" accept=".csv,.txt">
                        <div class="helper-text">
                            Upload a CSV or TXT file. TXT must contain comma-separated columns exactly like the required format below.
                        </div>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="bulkImages">Bulk Hotel Images</label>
                        <input type="file" class="form-control" id="bulkImages" accept="image/*" multiple>
                        <div class="helper-text">
                            Image file names must match the <strong>image_name</strong> column in the dataset.
                        </div>
                    </div>
                </div>

                <div class="bulk-columns-box">
                    <h6>Required dataset columns</h6>
                    <code>city, city_slug, center_lat, center_lng, hotel_id, hotel_name, image_name, price_per_night, rating, reviews, stars, address, lat, lng, features</code>
                    <div class="helper-text" style="margin-top:10px;">
                        Use <strong>|</strong> between multiple hotel features.
                    </div>
                </div>
            </div>

            <form id="addHotelForm" novalidate>
                <input type="hidden" id="selectedExistingCitySlug" name="selectedExistingCitySlug" value="">
                <input type="hidden" id="addHotelsMode" name="addHotelsMode" value="0">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="cityName">City Name</label>
                        <input type="text" class="form-control" id="cityName" name="cityName" placeholder="Type to search trip cities...">
                        <div class="helper-text">
                            In create mode, this loads trip cities automatically. In add-hotels mode, selected hotel city is auto-filled.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="citySlug">City Slug</label>
                        <input type="text" class="form-control" id="citySlug" name="citySlug" placeholder="e.g. islamabad" readonly>
                        <div class="helper-text">
                            Slug will auto-fill when you choose a city.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="centerLat">Map Center Latitude</label>
                        <input type="number" step="any" class="form-control" id="centerLat" name="centerLat" placeholder="e.g. 33.6844" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="centerLng">Map Center Longitude</label>
                        <input type="number" step="any" class="form-control" id="centerLng" name="centerLng" placeholder="e.g. 73.0479" readonly>
                    </div>
                </div>

                <div class="card-divider"></div>

                <div class="hotel-toolbar">
                    <div>
                        <h4>Hotels</h4>
                        <p id="hotelHelperText">Add one or more hotels for this city.</p>
                    </div>
                    <button type="button" class="btn btn-main" id="addHotelBtn">+ Add Hotel</button>
                </div>

                <div id="hotelsContainer">
                    <div class="empty-hotels" id="emptyHotelsState">
                        No hotel added yet. Click <strong>Add Hotel</strong> to begin.
                    </div>
                </div>

                <div class="card-divider"></div>

                <div class="preview-box">
                    <h5>Save Preview</h5>
                    <p>
                        Duplicate hotel names or IDs will not be added again. If the city already exists, only new hotels will be appended.
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
                            <h6>Hotels</h6>
                            <p id="previewHotelCount">0</p>
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
                    <button type="submit" class="btn btn-main" id="saveHotelBtn">Save Hotel Destination</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    const BASE_URL = <?php echo json_encode($baseUrl); ?>;
    const PRESELECT_CITY_SLUG = <?php echo json_encode($selectedSlugFromUrl); ?>;
    const PRESELECT_ADD_HOTELS_MODE = <?php echo json_encode($addHotelsModeFromUrl); ?>;

    const form = document.getElementById('addHotelForm');

    const enableBulkUpload = document.getElementById('enableBulkUpload');
    const enableAddHotelsMode = document.getElementById('enableAddHotelsMode');

    const bulkPanel = document.getElementById('bulkPanel');
    const existingCityPanel = document.getElementById('existingCityPanel');

    const bulkDatasetFile = document.getElementById('bulkDatasetFile');
    const bulkImages = document.getElementById('bulkImages');

    const existingCitySelect = document.getElementById('existingCitySelect');
    const selectedExistingCitySlug = document.getElementById('selectedExistingCitySlug');
    const addHotelsModeInput = document.getElementById('addHotelsMode');

    const existingCitySummary = document.getElementById('existingCitySummary');
    const existingSummaryCity = document.getElementById('existingSummaryCity');
    const existingSummarySlug = document.getElementById('existingSummarySlug');
    const existingSummaryHotels = document.getElementById('existingSummaryHotels');
    const existingSummaryCenter = document.getElementById('existingSummaryCenter');

    const cityName = document.getElementById('cityName');
    const citySlug = document.getElementById('citySlug');
    const centerLat = document.getElementById('centerLat');
    const centerLng = document.getElementById('centerLng');

    const addHotelBtn = document.getElementById('addHotelBtn');
    const hotelsContainer = document.getElementById('hotelsContainer');
    let emptyHotelsState = document.getElementById('emptyHotelsState');
    const resetFormBtn = document.getElementById('resetFormBtn');

    const previewCity = document.getElementById('previewCity');
    const previewSlug = document.getElementById('previewSlug');
    const previewHotelCount = document.getElementById('previewHotelCount');
    const previewMode = document.getElementById('previewMode');

    const formHeading = document.getElementById('formHeading');
    const formSubtext = document.getElementById('formSubtext');
    const hotelHelperText = document.getElementById('hotelHelperText');

    let hotelIndex = 0;
    let mergedTripCities = {};
    let existingHotelCities = [];

    const cityNameAutocomplete = travelixAutocomplete(cityName, {
        getItems: () => Object.keys(mergedTripCities)
            .sort((a, b) => a.localeCompare(b))
            .map((city) => ({ value: city, label: city })),
        emptyText: 'No matching trip city found.',
        onSelect: (item) => applySelectedTripCityData(item.value),
        onClear: () => applySelectedTripCityData('')
    });

    const existingCitySelectAutocomplete = travelixAutocomplete(existingCitySelect, {
        getItems: () => existingHotelCities.map((city) => ({
            value: city.slug || '',
            label: `${city.city} (${city.slug})`
        })),
        emptyText: 'No existing hotel city matches that.',
        onSelect: (item) => {
            const selectedCity = existingHotelCities.find((city) => city.slug === item.value);
            clearHotelCards();
            applyExistingHotelCity(selectedCity || null);
            toggleEmptyHotels();
            refreshHotelLabels();
        },
        onClear: () => {
            clearHotelCards();
            applyExistingHotelCity(null);
            toggleEmptyHotels();
            refreshHotelLabels();
        }
    });

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

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    function setCityFieldsReadonly(isReadonly) {
        [cityName, citySlug, centerLat, centerLng].forEach((field) => {
            if (field === cityName) {
                field.disabled = isReadonly;
            } else {
                field.readOnly = true;
            }

            if (isReadonly) {
                field.classList.add('readonly-look');
            } else {
                field.classList.remove('readonly-look');
            }
        });
    }

    async function loadTripCitiesForHotelDropdown() {
        try {
            const response = await fetch(`${BASE_URL}/trips/data/get_merged_city_places.php`, {
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load trip cities.');
            }

            const raw = await response.json();
            mergedTripCities = (raw && typeof raw === 'object') ? raw : {};
            populateCityDropdown();
        } catch (error) {
            console.error('Trip city dropdown load error:', error);
            mergedTripCities = {};
            populateCityDropdown();
        }
    }

    async function loadExistingHotelCities() {
        try {
            const response = await fetch(`${BASE_URL}/admin_manage/ajax/get_hotel_cities.php`, {
                cache: 'no-store',
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to load existing hotel cities.');
            }

            existingHotelCities = Array.isArray(result.cities) ? result.cities : [];
            populateExistingHotelCityDropdown();
        } catch (error) {
            console.error('Existing hotel city load error:', error);
            existingHotelCities = [];
            populateExistingHotelCityDropdown();
            showError('Load Failed', error.message || 'Could not load existing hotel cities.');
        }
    }

    function populateCityDropdown() {
        if (!cityName) return;

        const previousValue = cityName.value || '';
        const cityNames = Object.keys(mergedTripCities).sort((a, b) => a.localeCompare(b));

        cityNameAutocomplete.refresh();

        if (previousValue && cityNames.includes(previousValue)) {
            cityNameAutocomplete.setValue(previousValue);
            applySelectedTripCityData(previousValue);
        }
    }

    function populateExistingHotelCityDropdown() {
        if (!existingCitySelect) return;

        existingCitySelectAutocomplete.refresh();

        if (PRESELECT_CITY_SLUG) {
            const selectedCity = existingHotelCities.find(city => city.slug === PRESELECT_CITY_SLUG);
            if (selectedCity) {
                existingCitySelectAutocomplete.setValue(`${selectedCity.city} (${selectedCity.slug})`);
                applyExistingHotelCity(selectedCity);
            }
        }
    }

    function applySelectedTripCityData(selectedCity) {
        if (!selectedCity || !mergedTripCities[selectedCity]) {
            citySlug.value = '';
            centerLat.value = '';
            centerLng.value = '';
            updatePreview();
            return;
        }

        const cityData = mergedTripCities[selectedCity] || {};
        const center = cityData.center || {};

        let lat = '';
        let lng = '';

        if (Array.isArray(center)) {
            lat = center[0] ?? '';
            lng = center[1] ?? '';
        } else {
            lat = center.lat ?? '';
            lng = center.lng ?? '';
        }

        citySlug.value = slugify(selectedCity);
        centerLat.value = lat;
        centerLng.value = lng;
        updatePreview();
    }

    function applyExistingHotelCity(city) {
        if (!city) {
            cityNameAutocomplete.clear();
            citySlug.value = '';
            centerLat.value = '';
            centerLng.value = '';
            selectedExistingCitySlug.value = '';
            existingCitySummary.style.display = 'none';
            updatePreview();
            return;
        }

        cityNameAutocomplete.setValue(city.city || '');
        citySlug.value = city.slug || '';
        centerLat.value = city.center?.lat ?? '';
        centerLng.value = city.center?.lng ?? '';

        selectedExistingCitySlug.value = city.slug || '';

        existingSummaryCity.textContent = city.city || '-';
        existingSummarySlug.textContent = city.slug || '-';
        existingSummaryHotels.textContent = Array.isArray(city.hotels) ? city.hotels.length : 0;
        existingSummaryCenter.textContent = `${city.center?.lat ?? '-'}, ${city.center?.lng ?? '-'}`;
        existingCitySummary.style.display = 'block';

        updatePreview();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function clearHotelCards() {
        hotelsContainer.innerHTML = `<div class="empty-hotels" id="emptyHotelsState">No hotel added yet. Click <strong>Add Hotel</strong> to begin.</div>`;
        emptyHotelsState = document.getElementById('emptyHotelsState');
        hotelIndex = 0;
    }

    function createListRow(placeholder = 'Add feature...') {
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

    function createHotelCard() {
        const index = hotelIndex++;
        const card = document.createElement('div');
        card.className = 'hotel-card';
        card.dataset.index = String(index);

        card.innerHTML = `
            <div class="hotel-top">
                <div>
                    <div class="hotel-badge">Hotel ${index + 1}</div>
                    <h5>Hotel Details</h5>
                </div>
                <button type="button" class="btn btn-danger-soft remove-hotel-btn">Remove Hotel</button>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Hotel ID</label>
                    <input type="text" class="form-control hotel-id" name="hotels[${index}][id]" placeholder="e.g. isb_5">
                </div>

                <div class="form-group">
                    <label class="form-label">Hotel Name</label>
                    <input type="text" class="form-control hotel-name" name="hotels[${index}][name]" placeholder="e.g. Grand Peace House">
                </div>

                <div class="form-group">
                    <label class="form-label">Hotel Image</label>
                    <input type="file" class="form-control hotel-image-file" name="hotels[${index}][image]" accept="image/*">
                    <div class="helper-text">This image will be uploaded to <strong>/hotel_images</strong>.</div>
                    <img class="image-preview" alt="Hotel Preview">
                </div>

                <div class="form-group">
                    <label class="form-label">Price Per Night</label>
                    <input type="number" step="any" class="form-control hotel-price" name="hotels[${index}][price_per_night]" placeholder="e.g. 9105">
                </div>

                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <input type="number" step="0.1" class="form-control hotel-rating" name="hotels[${index}][rating]" placeholder="e.g. 8.9">
                </div>

                <div class="form-group">
                    <label class="form-label">Reviews</label>
                    <input type="number" class="form-control hotel-reviews" name="hotels[${index}][reviews]" placeholder="e.g. 52">
                </div>

                <div class="form-group">
                    <label class="form-label">Stars</label>
                    <input type="number" class="form-control hotel-stars" name="hotels[${index}][stars]" placeholder="e.g. 3">
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control hotel-address" name="hotels[${index}][address]" placeholder="e.g. Blue Area, Islamabad">
                </div>

                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="any" class="form-control hotel-lat" name="hotels[${index}][lat]" placeholder="e.g. 33.7008">
                </div>

                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="any" class="form-control hotel-lng" name="hotels[${index}][lng]" placeholder="e.g. 73.0551">
                </div>
            </div>

            <div class="card-divider"></div>

            <div class="form-group">
                <label class="form-label">Features</label>
                <div class="list-editor features-list"></div>
                <div class="helper-text">Add hotel features like WiFi, Parking, Breakfast, Family Rooms.</div>
                <button type="button" class="btn btn-soft add-feature-btn mt-2">+ Add Feature</button>
            </div>
        `;

        const featuresList = card.querySelector('.features-list');

        function addHiddenFeatureInputs() {
            card.querySelectorAll('input[type="hidden"].dynamic-feature-hidden').forEach(el => el.remove());

            const featureValues = Array.from(featuresList.querySelectorAll('.list-input'))
                .map(input => input.value.trim())
                .filter(Boolean);

            featureValues.forEach((value) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'dynamic-feature-hidden';
                hidden.name = `hotels[${index}][features][]`;
                hidden.value = value;
                card.appendChild(hidden);
            });
        }

        const firstRow = createListRow('e.g. Free WiFi');
        firstRow.querySelector('.list-input')?.addEventListener('input', addHiddenFeatureInputs);
        firstRow.querySelector('.remove-list-item-btn')?.addEventListener('click', () => {
            setTimeout(addHiddenFeatureInputs, 0);
        });
        featuresList.appendChild(firstRow);
        addHiddenFeatureInputs();

        card.querySelector('.add-feature-btn')?.addEventListener('click', function () {
            const row = createListRow('e.g. Parking');
            row.querySelector('.list-input')?.addEventListener('input', addHiddenFeatureInputs);
            row.querySelector('.remove-list-item-btn')?.addEventListener('click', () => {
                setTimeout(addHiddenFeatureInputs, 0);
            });
            featuresList.appendChild(row);
            addHiddenFeatureInputs();
            updatePreview();
        });

        card.querySelector('.remove-hotel-btn')?.addEventListener('click', function () {
            card.remove();
            refreshHotelLabels();
            toggleEmptyHotels();
            updatePreview();
        });

        const imageInput = card.querySelector('.hotel-image-file');
        const preview = card.querySelector('.image-preview');

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
                addHiddenFeatureInputs();
                updatePreview();
            });
            field.addEventListener('change', () => {
                addHiddenFeatureInputs();
                updatePreview();
            });
        });

        return card;
    }

    function refreshHotelLabels() {
        const cards = hotelsContainer.querySelectorAll('.hotel-card');
        cards.forEach((card, index) => {
            const badge = card.querySelector('.hotel-badge');
            if (badge) badge.textContent = `Hotel ${index + 1}`;
        });
    }

    function toggleEmptyHotels() {
        const cards = hotelsContainer.querySelectorAll('.hotel-card');
        if (emptyHotelsState) {
            emptyHotelsState.style.display = cards.length ? 'none' : 'block';
        }
    }

    function updatePreview() {
        const manualCount = hotelsContainer.querySelectorAll('.hotel-card').length;

        previewCity.textContent = cityName.value.trim() || '-';
        previewSlug.textContent = slugify(citySlug.value.trim() || cityName.value.trim()) || '-';
        previewHotelCount.textContent = String(manualCount);

        if (enableAddHotelsMode.checked && enableBulkUpload.checked && manualCount > 0) {
            previewMode.textContent = 'Existing City + Bulk + Manual';
        } else if (enableAddHotelsMode.checked && enableBulkUpload.checked) {
            previewMode.textContent = 'Existing City + Bulk';
        } else if (enableAddHotelsMode.checked) {
            previewMode.textContent = 'Existing City + Manual';
        } else if (enableBulkUpload.checked && manualCount > 0) {
            previewMode.textContent = 'Bulk + Manual';
        } else if (enableBulkUpload.checked) {
            previewMode.textContent = 'Bulk';
        } else {
            previewMode.textContent = 'Manual';
        }
    }

    function resetCreateModeFields() {
        cityNameAutocomplete.clear();
        citySlug.value = '';
        centerLat.value = '';
        centerLng.value = '';
    }

    async function handleAddHotelsModeToggle() {
        const enabled = enableAddHotelsMode.checked;
        addHotelsModeInput.value = enabled ? '1' : '0';
        existingCityPanel.classList.toggle('show', enabled);

        clearHotelCards();

        if (enabled) {
            formHeading.textContent = 'Add Hotels To Existing City';
            formSubtext.textContent = 'Select an existing hotel city from dropdown, then add only new hotels to that city.';
            hotelHelperText.textContent = 'Add one or more new hotels for the selected existing city.';
            setCityFieldsReadonly(true);
            await loadExistingHotelCities();
        } else {
            formHeading.textContent = 'Create New Hotel City';
            formSubtext.textContent = 'Add a city center and one or more hotels. If you want to add hotels in an already existing hotel city, check the Add Hotels checkbox below.';
            hotelHelperText.textContent = 'Add one or more hotels for this city.';
            existingCitySelectAutocomplete.clear();
            existingCitySummary.style.display = 'none';
            selectedExistingCitySlug.value = '';
            setCityFieldsReadonly(false);
            resetCreateModeFields();
            await loadTripCitiesForHotelDropdown();
        }

        updatePreview();
    }

    function resetForm() {
        form.reset();
        enableBulkUpload.checked = false;
        bulkPanel.classList.remove('show');

        clearHotelCards();

        if (PRESELECT_ADD_HOTELS_MODE) {
            enableAddHotelsMode.checked = true;
            handleAddHotelsModeToggle();
        } else {
            enableAddHotelsMode.checked = false;
            handleAddHotelsModeToggle();
        }

        updatePreview();
    }

    addHotelBtn.addEventListener('click', function () {
        if (enableAddHotelsMode.checked && !existingCitySelect.value) {
            showError('Select City First', 'Please select an existing hotel city first, then add hotels.');
            return;
        }

        if (!enableAddHotelsMode.checked && !cityName.value) {
            showError('Select City First', 'Please select a city first, then add hotels.');
            return;
        }

        const card = createHotelCard();
        hotelsContainer.appendChild(card);
        toggleEmptyHotels();
        refreshHotelLabels();
        updatePreview();
    });

    enableBulkUpload.addEventListener('change', function () {
        bulkPanel.classList.toggle('show', this.checked);
        updatePreview();
    });

    enableAddHotelsMode.addEventListener('change', function () {
        handleAddHotelsModeToggle();
    });

    [centerLat, centerLng, citySlug].forEach((field) => {
        field.addEventListener('input', updatePreview);
        field.addEventListener('change', updatePreview);
    });

    resetFormBtn.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset form?',
            text: 'All current hotel destination data will be cleared.',
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

    document.addEventListener('DOMContentLoaded', async function () {
        if (PRESELECT_ADD_HOTELS_MODE) {
            enableAddHotelsMode.checked = true;
        }
        await handleAddHotelsModeToggle();
        updatePreview();
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            const hasManualHotels = hotelsContainer.querySelectorAll('.hotel-card').length > 0;
            const hasBulkFiles = enableBulkUpload.checked && bulkDatasetFile.files?.length;

            if (enableAddHotelsMode.checked && !existingCitySelect.value) {
                throw new Error('Please select an existing hotel city first.');
            }

            if (!enableAddHotelsMode.checked && !cityName.value) {
                throw new Error('Please select a city first.');
            }

            if (!hasManualHotels && !hasBulkFiles) {
                throw new Error('Please add at least one manual hotel or upload a bulk dataset.');
            }

            showLoader(
                'Saving Hotel Destination...',
                'Please wait while hotel images and hotel data are being saved.'
            );

            const formData = new FormData(form);
            formData.append('bulkMode', enableBulkUpload.checked ? '1' : '0');
            formData.append('addHotelsMode', enableAddHotelsMode.checked ? '1' : '0');
            formData.append('selectedExistingCitySlug', selectedExistingCitySlug.value || '');

            if (enableBulkUpload.checked) {
                const bulkFile = bulkDatasetFile.files?.[0];
                if (!bulkFile) {
                    closeLoader();
                    throw new Error('Please upload a bulk dataset file.');
                }

                formData.append('bulkFile', bulkFile);

                const imageFiles = Array.from(bulkImages.files || []);
                imageFiles.forEach((file) => formData.append('bulkImages[]', file));
            }

            const response = await fetch(`${BASE_URL}/admin_manage/ajax/save_hotel_destination.php`, {
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
                throw new Error(result.message || 'Unable to save hotel destination.');
            }

            await showSuccess(
                'Hotel Destination Saved',
                result.message || 'Hotel destination saved successfully.'
            );

            resetForm();
        } catch (error) {
            closeLoader();
            showError('Save Failed', error.message || 'Unable to save hotel destination.');
        }
    });
</script>
</body>
</html>