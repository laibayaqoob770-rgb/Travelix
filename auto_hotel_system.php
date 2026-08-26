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
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Hotel System — Travelix Admin</title>
    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }

        .admin-layout { display: flex; gap: 24px; padding: 16px; min-height: 100vh; }
        .admin-main { flex: 1; min-width: 0; }

        /* Header */
        .system-header {
            background: linear-gradient(135deg, #133c96 0%, #1e50c0 50%, #2d6af0 100%);
            border-radius: 24px;
            padding: 36px 32px;
            color: #fff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .system-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .system-header h1 { font-size: 28px; font-weight: 800; margin-bottom: 6px; position: relative; }
        .system-header p { font-size: 15px; opacity: 0.85; position: relative; }

        /* Control Panel */
        .control-panel {
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .control-panel h3 { font-weight: 700; font-size: 18px; margin-bottom: 18px; color: #1a1a2e; }

        .city-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .city-card {
            background: #f8f9ff;
            border: 2px solid #e8ecf4;
            border-radius: 16px;
            padding: 16px 14px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
        }
        .city-card:hover { border-color: #2d6af0; background: #eef2ff; transform: translateY(-2px); }
        .city-card.selected { border-color: #2d6af0; background: #dce5ff; box-shadow: 0 4px 15px rgba(45,106,240,0.2); }
        .city-card.completed { border-color: #10b981; background: #ecfdf5; }
        .city-card .city-name { font-weight: 700; font-size: 15px; color: #1a1a2e; }
        .city-card .city-count { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .city-card .city-check {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #10b981;
            color: #fff;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
        .city-card.completed .city-check { display: flex; }

        .action-buttons { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn-start {
            padding: 14px 32px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #2d6af0, #1e50c0);
            color: #fff;
            box-shadow: 0 4px 15px rgba(45,106,240,0.3);
        }
        .btn-start:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(45,106,240,0.4); }
        .btn-start:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-start-all {
            padding: 14px 32px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        .btn-start-all:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
        .btn-start-all:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Progress Section */
        .progress-section {
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: none;
        }
        .progress-section.active { display: block; }
        .progress-section h3 { font-weight: 700; font-size: 18px; margin-bottom: 18px; color: #1a1a2e; }

        .progress-bar-wrap {
            background: #e8ecf4;
            border-radius: 12px;
            height: 16px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d6af0, #10b981);
            border-radius: 12px;
            width: 0%;
            transition: width 0.5s ease;
        }
        .progress-stats {
            display: flex;
            gap: 24px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: #f8f9ff;
            border-radius: 14px;
            padding: 14px 20px;
            text-align: center;
            flex: 1;
            min-width: 120px;
        }
        .stat-box .stat-num { font-size: 26px; font-weight: 800; color: #2d6af0; }
        .stat-box .stat-label { font-size: 12px; color: #6b7280; margin-top: 2px; }

        .current-action {
            padding: 14px 18px;
            background: #eef2ff;
            border-radius: 12px;
            font-size: 14px;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .current-action .spinner {
            width: 18px;
            height: 18px;
            border: 3px solid #e8ecf4;
            border-top-color: #2d6af0;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Hotel Feed */
        .hotel-feed { display: grid; gap: 14px; }

        .hotel-feed-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8f9ff;
            border: 1px solid #e8ecf4;
            border-radius: 16px;
            animation: slideIn 0.5s ease;
            transition: all 0.3s ease;
        }
        .hotel-feed-item.saving {
            border-color: #2d6af0;
            background: #eef2ff;
            box-shadow: 0 0 0 3px rgba(45,106,240,0.1);
        }
        .hotel-feed-item.saved {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .hotel-feed-item.skipped {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .hotel-feed-item.failed {
            border-color: #ef4444;
            background: #fef2f2;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .feed-img {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            object-fit: cover;
            flex-shrink: 0;
            background: #e8ecf4;
        }
        .feed-info { flex: 1; min-width: 0; }
        .feed-info h5 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .feed-info p { font-size: 13px; color: #6b7280; margin: 0; }
        .feed-meta { display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
        .feed-meta span { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 4px; }
        .feed-meta .text-warning { color: #f59e0b !important; }

        .feed-status {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .feed-status.saving { background: #eef2ff; }
        .feed-status.saving .spinner { width: 18px; height: 18px; border: 3px solid #e8ecf4; border-top-color: #2d6af0; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .feed-status.saved { background: #ecfdf5; color: #10b981; }
        .feed-status.skipped { background: #fffbeb; color: #f59e0b; }
        .feed-status.failed { background: #fef2f2; color: #ef4444; }

        /* Log */
        .log-section {
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: none;
        }
        .log-section.active { display: block; }
        .log-section h3 { font-weight: 700; font-size: 18px; margin-bottom: 14px; color: #1a1a2e; }
        .log-entries {
            background: #1a1a2e;
            border-radius: 14px;
            padding: 18px;
            max-height: 250px;
            overflow-y: auto;
            font-family: 'Fira Code', 'Consolas', monospace;
            font-size: 12.5px;
            line-height: 1.7;
        }
        .log-entries .log-line { color: #a0aec0; }
        .log-entries .log-line.success { color: #10b981; }
        .log-entries .log-line.error { color: #ef4444; }
        .log-entries .log-line.info { color: #60a5fa; }
        .log-entries .log-line.warn { color: #f59e0b; }

        @media (max-width: 768px) {
            .admin-layout { flex-direction: column; padding: 0; gap: 0; }
            .system-header { border-radius: 0 0 24px 24px; padding: 24px 18px; }
            .control-panel, .progress-section, .log-section { border-radius: 16px; margin: 0 10px 16px; padding: 20px 16px; }
            .city-grid { grid-template-columns: repeat(2, 1fr); }
            .hotel-feed-item { flex-wrap: wrap; }
            .feed-img { width: 56px; height: 56px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/admin_sidebar.php'; ?>

        <div class="admin-main">
            <!-- Header -->
            <div class="system-header">
                <h1><i class="fas fa-magic"></i> &nbsp;Auto Hotel System</h1>
                <p>The system automatically fills hotel data and saves it to your database — watch it happen in real-time!</p>
            </div>

            <!-- Control Panel -->
            <div class="control-panel">
                <h3><i class="fas fa-city"></i> &nbsp;Select Cities to Populate</h3>
                <div class="city-grid" id="cityGrid">
                    <div style="text-align:center; padding: 30px; grid-column: 1/-1; color: #6b7280;">
                        <div class="spinner" style="width:28px; height:28px; border:3px solid #e8ecf4; border-top-color:#2d6af0; border-radius:50%; animation:spin 0.8s linear infinite; margin: 0 auto 10px;"></div>
                        Loading cities...
                    </div>
                </div>
                <div class="action-buttons">
                    <button class="btn-start" id="btnStartSelected" disabled>
                        <i class="fas fa-play"></i> Start Selected City
                    </button>
                    <button class="btn-start-all" id="btnStartAll" disabled>
                        <i class="fas fa-rocket"></i> Start All Cities
                    </button>
                </div>
            </div>

            <!-- Progress -->
            <div class="progress-section" id="progressSection">
                <h3><i class="fas fa-tasks"></i> &nbsp;Live Progress</h3>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill" id="progressBar"></div>
                </div>
                <div class="progress-stats">
                    <div class="stat-box">
                        <div class="stat-num" id="statSaved">0</div>
                        <div class="stat-label">Saved</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num" id="statSkipped">0</div>
                        <div class="stat-label">Skipped</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num" id="statFailed">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num" id="statTotal">0</div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="current-action" id="currentAction" style="display:none;">
                    <div class="spinner"></div>
                    <span id="currentActionText">Preparing...</span>
                </div>
                <div class="hotel-feed" id="hotelFeed"></div>
            </div>

            <!-- Log -->
            <div class="log-section" id="logSection">
                <h3><i class="fas fa-terminal"></i> &nbsp;System Log</h3>
                <div class="log-entries" id="logEntries"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
    <script>
    (function () {
        const BASE = '<?php echo $baseUrl; ?>';
        const AJAX_URL = BASE + '/admin_manage/ajax/auto_populate_hotels.php';

        let allCities = [];
        let selectedCity = null;
        let isRunning = false;

        // Stats
        let stats = { saved: 0, skipped: 0, failed: 0, total: 0 };

        // DOM
        const cityGrid = document.getElementById('cityGrid');
        const btnStart = document.getElementById('btnStartSelected');
        const btnStartAll = document.getElementById('btnStartAll');
        const progressSection = document.getElementById('progressSection');
        const progressBar = document.getElementById('progressBar');
        const hotelFeed = document.getElementById('hotelFeed');
        const logSection = document.getElementById('logSection');
        const logEntries = document.getElementById('logEntries');
        const currentAction = document.getElementById('currentAction');
        const currentActionText = document.getElementById('currentActionText');

        function updateStats() {
            document.getElementById('statSaved').textContent = stats.saved;
            document.getElementById('statSkipped').textContent = stats.skipped;
            document.getElementById('statFailed').textContent = stats.failed;
            document.getElementById('statTotal').textContent = stats.total;
            const done = stats.saved + stats.skipped + stats.failed;
            const pct = stats.total > 0 ? Math.round((done / stats.total) * 100) : 0;
            progressBar.style.width = pct + '%';
        }

        function addLog(msg, type = '') {
            const line = document.createElement('div');
            line.className = 'log-line ' + type;
            const time = new Date().toLocaleTimeString();
            line.textContent = `[${time}] ${msg}`;
            logEntries.appendChild(line);
            logEntries.scrollTop = logEntries.scrollHeight;
        }

        function addHotelToFeed(hotel, status, message) {
            const stars = '★'.repeat(hotel.stars || 0) + '☆'.repeat(5 - (hotel.stars || 0));
            const price = 'PKR ' + Number(hotel.price_per_night || 0).toLocaleString();

            let statusIcon = '';
            if (status === 'saving') statusIcon = '<div class="spinner"></div>';
            else if (status === 'saved') statusIcon = '<i class="fas fa-check"></i>';
            else if (status === 'skipped') statusIcon = '<i class="fas fa-forward"></i>';
            else statusIcon = '<i class="fas fa-times"></i>';

            const item = document.createElement('div');
            item.className = 'hotel-feed-item ' + status;
            item.setAttribute('data-hotel-id', hotel.id || '');
            item.innerHTML = `
                <img src="${hotel.image || ''}" alt="${hotel.name || ''}" class="feed-img" onerror="this.src='${BASE}/images/default_profile.png'">
                <div class="feed-info">
                    <h5>${hotel.name || 'Unknown Hotel'}</h5>
                    <p>${hotel.address || ''}</p>
                    <div class="feed-meta">
                        <span class="text-warning">${stars}</span>
                        <span><i class="fas fa-star"></i> ${hotel.rating || 0}/10</span>
                        <span><i class="fas fa-tag"></i> ${price}/night</span>
                    </div>
                </div>
                <div class="feed-status ${status}">${statusIcon}</div>
            `;

            hotelFeed.prepend(item);
            return item;
        }

        function updateFeedItem(hotelId, newStatus, message) {
            const item = hotelFeed.querySelector(`[data-hotel-id="${hotelId}"]`);
            if (!item) return;

            item.className = 'hotel-feed-item ' + newStatus;

            let statusIcon = '';
            if (newStatus === 'saved') statusIcon = '<i class="fas fa-check"></i>';
            else if (newStatus === 'skipped') statusIcon = '<i class="fas fa-forward"></i>';
            else statusIcon = '<i class="fas fa-times"></i>';

            const statusEl = item.querySelector('.feed-status');
            if (statusEl) {
                statusEl.className = 'feed-status ' + newStatus;
                statusEl.innerHTML = statusIcon;
            }
        }

        // Load cities
        async function loadCities() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_cities');
                const resp = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await resp.json();

                if (!data.success) throw new Error(data.message);

                allCities = data.cities || [];
                renderCities();
            } catch (err) {
                cityGrid.innerHTML = `<div style="text-align:center; padding:20px; grid-column:1/-1; color:#ef4444;">
                    <i class="fas fa-exclamation-circle"></i> Failed to load cities: ${err.message}
                </div>`;
            }
        }

        function renderCities() {
            cityGrid.innerHTML = '';
            allCities.forEach(city => {
                const card = document.createElement('div');
                card.className = 'city-card';
                card.setAttribute('data-city', city.name);
                card.innerHTML = `
                    <div class="city-check"><i class="fas fa-check"></i></div>
                    <div class="city-name">${city.name}</div>
                    <div class="city-count">${city.hotelCount} hotels</div>
                `;
                card.addEventListener('click', () => {
                    if (isRunning) return;
                    document.querySelectorAll('.city-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    selectedCity = city.name;
                    btnStart.disabled = false;
                });
                cityGrid.appendChild(card);
            });
            btnStartAll.disabled = false;
        }

        // Run for one city
        async function runCity(cityName) {
            addLog(`Starting city: ${cityName}`, 'info');
            currentAction.style.display = 'flex';
            currentActionText.textContent = `Loading hotels for ${cityName}...`;

            // Get hotel list
            const fd = new FormData();
            fd.append('action', 'get_hotels');
            fd.append('city', cityName);

            let cityData;
            try {
                const resp = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message);
                cityData = data.data;
            } catch (err) {
                addLog(`Failed to load hotels for ${cityName}: ${err.message}`, 'error');
                return;
            }

            const hotels = cityData.hotels || [];
            addLog(`Found ${hotels.length} hotels in ${cityName}`, 'info');

            // Save hotels one by one
            for (let i = 0; i < hotels.length; i++) {
                const hotel = hotels[i];
                currentActionText.textContent = `Saving hotel ${i + 1}/${hotels.length}: ${hotel.name}`;
                addLog(`Saving: ${hotel.name}...`, '');

                // Show "saving" state in feed
                addHotelToFeed(hotel, 'saving', 'Saving...');

                // Small delay so user sees the animation
                await new Promise(r => setTimeout(r, 800));

                try {
                    const saveFd = new FormData();
                    saveFd.append('action', 'save_hotel');
                    saveFd.append('city', cityName);
                    saveFd.append('hotelIndex', i);

                    const resp = await fetch(AJAX_URL, { method: 'POST', body: saveFd });
                    const result = await resp.json();

                    if (result.success) {
                        if (result.skipped) {
                            stats.skipped++;
                            updateFeedItem(hotel.id, 'skipped');
                            addLog(`Skipped (duplicate): ${hotel.name}`, 'warn');
                        } else {
                            stats.saved++;
                            updateFeedItem(hotel.id, 'saved');
                            addLog(`✓ Saved: ${hotel.name} (Total in city: ${result.totalHotelsInCity})`, 'success');
                        }
                    } else {
                        stats.failed++;
                        updateFeedItem(hotel.id, 'failed');
                        addLog(`✗ Failed: ${hotel.name} — ${result.message}`, 'error');
                    }
                } catch (err) {
                    stats.failed++;
                    updateFeedItem(hotel.id, 'failed');
                    addLog(`✗ Error: ${hotel.name} — ${err.message}`, 'error');
                }

                updateStats();

                // Delay between hotels for the real-time visual effect
                await new Promise(r => setTimeout(r, 600));
            }

            // Mark city as completed
            const cityCard = document.querySelector(`.city-card[data-city="${cityName}"]`);
            if (cityCard) cityCard.classList.add('completed');

            addLog(`Completed city: ${cityName}`, 'success');
        }

        // Start selected city
        btnStart.addEventListener('click', async () => {
            if (!selectedCity || isRunning) return;
            isRunning = true;
            btnStart.disabled = true;
            btnStartAll.disabled = true;

            // Reset
            stats = { saved: 0, skipped: 0, failed: 0, total: 0 };
            hotelFeed.innerHTML = '';
            logEntries.innerHTML = '';
            progressSection.classList.add('active');
            logSection.classList.add('active');

            const city = allCities.find(c => c.name === selectedCity);
            stats.total = city ? city.hotelCount : 0;
            updateStats();

            addLog('Auto Hotel System started', 'info');
            addLog(`Mode: Single city — ${selectedCity}`, 'info');

            await runCity(selectedCity);

            currentAction.style.display = 'none';
            isRunning = false;
            btnStart.disabled = false;
            btnStartAll.disabled = false;

            addLog('━━━ System complete! ━━━', 'success');
            addLog(`Results: ${stats.saved} saved, ${stats.skipped} skipped, ${stats.failed} failed`, 'info');

            Swal.fire({
                icon: 'success',
                title: 'Hotels Added!',
                html: `<b>${stats.saved}</b> hotels saved to <b>${selectedCity}</b>.<br><small>Check your website — they appear in real-time!</small>`,
                confirmButtonColor: '#2d6af0'
            });
        });

        // Start all cities
        btnStartAll.addEventListener('click', async () => {
            if (isRunning) return;
            isRunning = true;
            btnStart.disabled = true;
            btnStartAll.disabled = true;

            // Reset
            stats = { saved: 0, skipped: 0, failed: 0, total: 0 };
            hotelFeed.innerHTML = '';
            logEntries.innerHTML = '';
            progressSection.classList.add('active');
            logSection.classList.add('active');

            // Count total hotels across all cities
            stats.total = allCities.reduce((sum, c) => sum + c.hotelCount, 0);
            updateStats();

            addLog('Auto Hotel System started', 'info');
            addLog(`Mode: All cities (${allCities.length} cities, ${stats.total} hotels)`, 'info');

            for (const city of allCities) {
                await runCity(city.name);
                // Pause between cities
                await new Promise(r => setTimeout(r, 1000));
            }

            currentAction.style.display = 'none';
            isRunning = false;
            btnStart.disabled = false;
            btnStartAll.disabled = false;

            addLog('━━━ All cities complete! ━━━', 'success');
            addLog(`Final: ${stats.saved} saved, ${stats.skipped} skipped, ${stats.failed} failed out of ${stats.total}`, 'info');

            Swal.fire({
                icon: 'success',
                title: 'All Hotels Added!',
                html: `<b>${stats.saved}</b> hotels saved across <b>${allCities.length}</b> cities.<br><small>Your website updates in real-time!</small>`,
                confirmButtonColor: '#2d6af0'
            });
        });

        // Init
        loadCities();
    })();
    </script>
</body>
</html>
