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
$email = (string)($currentUser['email'] ?? '');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
$fullName = trim($firstName . ' ' . $lastName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
            --bg: #f4f8ff;
            --card: #ffffff;
            --card-soft: #f8fbff;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 18px 45px rgba(29, 78, 216, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            color: var(--text);
        }

        body {
            overflow-y: auto;
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

        .dashboard-hero {
            background: rgba(255,255,255,0.90);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
            padding: 22px 24px;
            margin-bottom: 24px;
        }

        .dashboard-hero-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .dashboard-hero h1 {
            margin: 0 0 8px;
            font-size: 34px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .dashboard-hero p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-badge {
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 700;
            border: 1px solid rgba(29, 78, 216, 0.08);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            top: -28px;
            right: -16px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(96, 165, 250, 0.14);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 12px 25px rgba(29, 78, 216, 0.18);
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            position: relative;
            z-index: 1;
            font-size: 34px;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .stat-meta {
            position: relative;
            z-index: 1;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .dashboard-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .dashboard-card h3 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .subtext {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .chart-wrap {
            position: relative;
            min-height: 320px;
        }

        .mini-chart-wrap {
            position: relative;
            min-height: 280px;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .progress-card {
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .progress-card h4 {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .progress-item {
            margin-bottom: 16px;
        }

        .progress-item:last-child {
            margin-bottom: 0;
        }

        .progress-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .progress-head span {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e6eefc;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            width: 0%;
            transition: width 0.45s ease;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .quick-link {
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--border);
            padding: 18px;
            color: var(--text);
            transition: 0.25s ease;
            text-decoration: none;
        }

        .quick-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(29, 78, 216, 0.08);
            color: var(--primary-dark);
        }

        .quick-link .ql-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 22px;
        }

        .quick-link strong {
            display: block;
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .quick-link span {
            display: block;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .loading-note,
        .error-note {
            font-size: 14px;
            margin-top: 10px;
            color: var(--muted);
        }

        .error-note {
            color: #b91c1c;
        }

        @media (max-width: 1199px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-grid,
            .bottom-grid {
                grid-template-columns: 1fr;
            }

            .quick-links {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admin-page {
                display: block;
                padding: 0;
            }

            .admin-content {
                padding: 0 16px 16px;
            }
        }

        @media (max-width: 767px) {
            .stats-grid,
            .quick-links {
                grid-template-columns: 1fr;
            }

            .dashboard-hero,
            .dashboard-card,
            .progress-card,
            .stat-card {
                padding: 18px;
            }

            .dashboard-hero h1 {
                font-size: 28px;
            }

            .chart-wrap {
                min-height: 280px;
            }

            .mini-chart-wrap {
                min-height: 250px;
            }
        }
    </style>
</head>
<body>

<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="dashboard-hero">
            <div class="dashboard-hero-row">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p>
                        Welcome back, <?php echo htmlspecialchars($fullName ?: $firstName); ?>.
                        This dashboard shows live totals from Firebase for users, trips, hotels, and pending feedback.
                    </p>
                </div>

                <div class="hero-badges">
                    <div class="hero-badge">Live Firebase Data</div>
                    <div class="hero-badge">Responsive Layout</div>
                    <div class="hero-badge">Visual Analytics</div>
                </div>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-number" id="usersCount">0</div>
                <div class="stat-meta">Registered users in Firebase</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">Added Trips</div>
                    <div class="stat-icon">🧳</div>
                </div>
                <div class="stat-number" id="tripsCount">0</div>
                <div class="stat-meta">Trips saved by users and admin</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">Hotels</div>
                    <div class="stat-icon">🏨</div>
                </div>
                <div class="stat-number" id="hotelsCount">0</div>
                <div class="stat-meta">Hotel records in Firebase</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-label">Pending Feedback</div>
                    <div class="stat-icon">💬</div>
                </div>
                <div class="stat-number" id="feedbackCount">0</div>
                <div class="stat-meta">Feedback not reviewed by admin</div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <h3>Platform Overview</h3>
                <div class="subtext">Live collection comparison from Firebase.</div>
                <div class="chart-wrap">
                    <canvas id="overviewChart"></canvas>
                </div>
                <div class="loading-note" id="overviewStatus">Loading dashboard data...</div>
            </div>

            <div class="dashboard-card">
                <h3>Collection Distribution</h3>
                <div class="subtext">A quick visual breakdown of current platform records.</div>
                <div class="mini-chart-wrap">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </section>

        <section class="bottom-grid">
            <div class="progress-card">
                <h4>Collection Activity</h4>

                <div class="progress-item">
                    <div class="progress-head">
                        <span>Users</span>
                        <span id="usersPercent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="usersBar"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-head">
                        <span>Trips</span>
                        <span id="tripsPercent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="tripsBar"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-head">
                        <span>Hotels</span>
                        <span id="hotelsPercent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="hotelsBar"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-head">
                        <span>Pending Feedback</span>
                        <span id="feedbackPercent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="feedbackBar"></div>
                    </div>
                </div>
            </div>

            <div class="progress-card">
                <h4>Quick Access</h4>

                <div class="quick-links">
                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/manage_users.php">
                        <div class="ql-icon">👥</div>
                        <strong>Users</strong>
                        <span>Open user management</span>
                    </a>

                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/manage_trips.php">
                        <div class="ql-icon">🧳</div>
                        <strong>Cities</strong>
                        <span>View saved cities</span>
                    </a>

                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/add_trips.php">
                        <div class="ql-icon">➕</div>
                        <strong>Add Trip</strong>
                        <span>Create a new public trip</span>
                    </a>

                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/manage_hotels.php">
                        <div class="ql-icon">🏨</div>
                        <strong>Hotels</strong>
                        <span>Manage hotel records</span>
                    </a>

                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/add_hotels.php">
                        <div class="ql-icon">🏩</div>
                        <strong>Add Hotel</strong>
                        <span>Create a new hotel record</span>
                    </a>

                    <a href="#"
                       class="quick-link loader-link"
                       data-href="<?php echo $baseUrl; ?>/admin_manage/manage_feedback.php">
                        <div class="ql-icon">💬</div>
                        <strong>Feedback</strong>
                        <span>Review pending messages</span>
                    </a>
                </div>

                <div class="error-note" id="dashboardError" style="display:none;"></div>
            </div>
        </section>
    </main>
</div>

<script type="module">
    import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import {
        getAuth,
        onAuthStateChanged
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";
    import {
        getFirestore,
        collection,
        getDocs
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    import { firebaseConfig } from "<?php echo $baseUrl; ?>/config/firebase-config.js";

    const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);

    const usersCountEl = document.getElementById('usersCount');
    const tripsCountEl = document.getElementById('tripsCount');
    const hotelsCountEl = document.getElementById('hotelsCount');
    const feedbackCountEl = document.getElementById('feedbackCount');

    const usersPercentEl = document.getElementById('usersPercent');
    const tripsPercentEl = document.getElementById('tripsPercent');
    const hotelsPercentEl = document.getElementById('hotelsPercent');
    const feedbackPercentEl = document.getElementById('feedbackPercent');

    const usersBar = document.getElementById('usersBar');
    const tripsBar = document.getElementById('tripsBar');
    const hotelsBar = document.getElementById('hotelsBar');
    const feedbackBar = document.getElementById('feedbackBar');

    const overviewStatus = document.getElementById('overviewStatus');
    const dashboardError = document.getElementById('dashboardError');

    let overviewChart = null;
    let distributionChart = null;

    function waitForFirebaseAuth() {
        return new Promise((resolve) => {
            onAuthStateChanged(auth, (user) => {
                resolve(user || null);
            });
        });
    }

    async function fetchCollectionCount(name) {
        const snapshot = await getDocs(collection(db, name));
        return snapshot.size;
    }

    async function fetchPendingFeedbackCount() {
        const snapshot = await getDocs(collection(db, 'contact_messages'));
        let pendingCount = 0;

        snapshot.forEach((doc) => {
            const data = doc.data() || {};

            const reviewed =
                data.reviewed === true ||
                data.isReviewed === true ||
                data.status === 'reviewed' ||
                data.adminReviewed === true;

            if (!reviewed) {
                pendingCount++;
            }
        });

        return pendingCount;
    }

    function setText(el, value) {
        if (el) el.textContent = value;
    }

    function setWidth(el, value) {
        if (el) el.style.width = value + "%";
    }

    function animateNumber(el, target) {
        let current = 0;
        const increment = Math.max(1, Math.ceil(target / 30));

        const interval = setInterval(() => {
            current += increment;

            if (current >= target) {
                current = target;
                clearInterval(interval);
            }

            setText(el, current);
        }, 20);
    }

    function calculatePercentages(data) {
        const total = data.users + data.trips + data.hotels + data.feedback;

        if (total === 0) {
            return {
                users: 0,
                trips: 0,
                hotels: 0,
                feedback: 0
            };
        }

        return {
            users: Math.round((data.users / total) * 100),
            trips: Math.round((data.trips / total) * 100),
            hotels: Math.round((data.hotels / total) * 100),
            feedback: Math.round((data.feedback / total) * 100)
        };
    }

    function updateProgressBars(percentages) {
        setText(usersPercentEl, percentages.users + "%");
        setText(tripsPercentEl, percentages.trips + "%");
        setText(hotelsPercentEl, percentages.hotels + "%");
        setText(feedbackPercentEl, percentages.feedback + "%");

        setWidth(usersBar, percentages.users);
        setWidth(tripsBar, percentages.trips);
        setWidth(hotelsBar, percentages.hotels);
        setWidth(feedbackBar, percentages.feedback);
    }

    function createCharts(data) {
        const overviewCanvas = document.getElementById('overviewChart');
        const distributionCanvas = document.getElementById('distributionChart');

        if (!overviewCanvas || !distributionCanvas) return;

        const ctx1 = overviewCanvas.getContext('2d');
        const ctx2 = distributionCanvas.getContext('2d');

        if (overviewChart) overviewChart.destroy();
        if (distributionChart) distributionChart.destroy();

        overviewChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Users', 'Trips', 'Hotels', 'Feedback'],
                datasets: [{
                    label: 'Platform Data',
                    data: [data.users, data.trips, data.hotels, data.feedback],
                    borderWidth: 1,
                    borderRadius: 10,
                    backgroundColor: [
                        'rgba(29, 78, 216, 0.85)',
                        'rgba(59, 130, 246, 0.85)',
                        'rgba(96, 165, 250, 0.85)',
                        'rgba(147, 197, 253, 0.95)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        distributionChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Users', 'Trips', 'Hotels', 'Feedback'],
                datasets: [{
                    data: [data.users, data.trips, data.hotels, data.feedback],
                    borderWidth: 2,
                    backgroundColor: [
                        'rgba(29, 78, 216, 0.90)',
                        'rgba(59, 130, 246, 0.90)',
                        'rgba(96, 165, 250, 0.90)',
                        'rgba(191, 219, 254, 1)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    async function loadDashboard() {
        try {
            if (dashboardError) {
                dashboardError.style.display = "none";
                dashboardError.textContent = "";
            }

            if (overviewStatus) {
                overviewStatus.textContent = "Checking Firebase authentication...";
            }

            const authUser = await waitForFirebaseAuth();

            if (!authUser) {
                throw new Error("Firebase Auth user is not logged in. Please log out and log in again.");
            }

            console.log("Firebase Auth UID:", authUser.uid);

            if (overviewStatus) {
                overviewStatus.textContent = "Loading dashboard data...";
            }

            const [users, trips, hotels, feedback] = await Promise.all([
                fetchCollectionCount('users'),
                fetchCollectionCount('trips'),
                fetchCollectionCount('hotel_bookings'),
                fetchPendingFeedbackCount()
            ]);

            const data = { users, trips, hotels, feedback };

            animateNumber(usersCountEl, users);
            animateNumber(tripsCountEl, trips);
            animateNumber(hotelsCountEl, hotels);
            animateNumber(feedbackCountEl, feedback);

            const percentages = calculatePercentages(data);
            updateProgressBars(percentages);
            createCharts(data);

            if (overviewStatus) {
                overviewStatus.textContent = "Live data loaded successfully.";
            }
        } catch (error) {
            console.error("Dashboard Firebase Error:", error);

            if (overviewStatus) {
                overviewStatus.textContent = "";
            }

            if (dashboardError) {
                dashboardError.style.display = "block";

                if (error?.code === 'permission-denied') {
                    dashboardError.textContent = "Firestore permission denied. Firebase Auth is missing or your Firestore rules still need updating.";
                } else {
                    dashboardError.textContent = error.message || "Failed to load Firebase data. Check connection.";
                }
            }
        }
    }

    loadDashboard();
</script>
</body>
</html>