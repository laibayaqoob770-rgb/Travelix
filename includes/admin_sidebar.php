<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
$currentUser = $_SESSION['user'] ?? [];

$firstName = (string)($currentUser['first_name'] ?? 'Admin');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
$currentPath = $_SERVER['PHP_SELF'] ?? '';

function adminSidebarActive($needle, $currentPath) {
    return strpos($currentPath, $needle) !== false ? 'active' : '';
}

require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
?>

<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/travelix_notifications.css">

<style>
    :root {
        --admin-sidebar-bg-1: #133c96;
        --admin-sidebar-bg-2: #0d2e75;
        --admin-sidebar-card: rgba(255,255,255,0.09);
        --admin-sidebar-card-border: rgba(255,255,255,0.14);
        --admin-sidebar-text: #ffffff;
        --admin-sidebar-muted: rgba(255,255,255,0.78);
    }

    .admin-mobile-header {
        display: none;
    }

    .admin-sidebar {
        width: 300px;
        min-width: 300px;
        max-width: 300px;
        position: sticky;
        top: 16px;
        height: calc(100vh - 32px);
        overflow-y: auto;
        align-self: flex-start;
        padding: 20px 18px;
        background: linear-gradient(180deg, var(--admin-sidebar-bg-1) 0%, var(--admin-sidebar-bg-2) 100%);
        border-radius: 28px;
        color: var(--admin-sidebar-text);
        box-shadow: 0 18px 45px rgba(13, 46, 117, 0.24);
        scrollbar-width: thin;
    }

    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .admin-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.22);
        border-radius: 999px;
    }

    .admin-sidebar-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        padding: 6px 4px 10px;
    }

    .admin-sidebar-brand-left {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .admin-sidebar-brand-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.12);
        font-size: 22px;
        font-weight: 800;
    }

    .admin-sidebar-brand-text h4 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #fff;
    }

    .admin-sidebar-brand-text span {
        display: block;
        margin-top: 2px;
        font-size: 13px;
        color: var(--admin-sidebar-muted);
    }

    /* The shared notification panel (assets/css/travelix_notifications.css)
       is a 410px-wide dropdown positioned `right:0` relative to its own
       trigger button — sized for a wide topbar. The admin sidebar is only
       300px wide, so that positioning pushed the panel hundreds of pixels
       off the left edge of the screen. Fixed-position it independently of
       the cramped sidebar column instead. */
    #travelixNotificationPanel {
        position: fixed;
        top: 90px;
        left: 20px;
        right: auto;
        width: min(380px, calc(100vw - 40px));
        max-width: calc(100vw - 40px);
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }

    .admin-profile-box {
        background: var(--admin-sidebar-card);
        border: 1px solid var(--admin-sidebar-card-border);
        border-radius: 24px;
        padding: 22px 16px;
        text-align: center;
        margin-bottom: 18px;
    }

    .admin-profile-box-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .admin-profile-image {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.18);
        background: #fff;
        margin-bottom: 14px;
    }

    .admin-profile-box h5 {
        margin: 0;
        font-size: 24px;
        font-weight: 800;
        color: #fff;
        line-height: 1.25;
    }

    .admin-profile-box p {
        margin: 6px 0 0;
        color: var(--admin-sidebar-muted);
        font-size: 14px;
    }

    .admin-profile-manage-btn {
        margin-top: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 16px;
        width: 100%;
        border: none;
        border-radius: 16px;
        background: rgba(255,255,255,0.14);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        transition: 0.25s ease;
        text-decoration: none;
    }

    .admin-profile-manage-btn:hover {
        background: rgba(255,255,255,0.22);
        color: #fff;
    }

    .admin-nav {
        display: grid;
        gap: 12px;
    }

    .admin-nav-link {
        text-decoration: none;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 16px;
        border-radius: 18px;
        background: rgba(255,255,255,0.07);
        border: 1px solid transparent;
        transition: 0.25s ease;
        font-weight: 700;
        font-size: 15px;
    }

    .admin-nav-link:hover,
    .admin-nav-link.active {
        color: #fff;
        background: rgba(255,255,255,0.16);
        border-color: rgba(255,255,255,0.18);
        transform: translateX(3px);
    }

    .admin-nav-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.14);
        font-size: 18px;
    }

    .admin-sidebar-footer {
        margin-top: 18px;
        padding-top: 4px;
    }

    .admin-logout-link {
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 800;
        border-radius: 18px;
        padding: 14px 16px;
        background: rgba(255,255,255,0.10);
        transition: 0.25s ease;
    }

    .admin-logout-link:hover {
        color: #fff;
        background: rgba(255,255,255,0.18);
    }

    @media (max-width: 991.98px) {
        .admin-mobile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 1100;
            background: linear-gradient(180deg, #133c96 0%, #0d2e75 100%);
            color: #fff;
            padding: 14px 16px;
            border-radius: 0 0 18px 18px;
            box-shadow: 0 10px 25px rgba(13, 46, 117, 0.20);
            margin-bottom: 14px;
        }

        .admin-mobile-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .admin-mobile-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.18);
            background: #fff;
        }

        .admin-mobile-text {
            min-width: 0;
        }

        .admin-mobile-text strong {
            display: block;
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .admin-mobile-text span {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.76);
            margin-top: 2px;
        }

        .admin-mobile-toggle {
            border: none;
            outline: none;
            box-shadow: none;
            background: rgba(255,255,255,0.14);
            color: #fff;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .admin-sidebar {
            position: fixed;
            top: 72px;
            left: 12px;
            right: 12px;
            width: auto;
            min-width: auto;
            max-width: none;
            height: calc(100vh - 92px);
            z-index: 1200;
            border-radius: 24px;
            transform: translateY(-10px);
            opacity: 0;
            pointer-events: none;
            transition: 0.25s ease;
        }

        .admin-sidebar.open {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
    }

    @media (min-width: 992px) {
        .admin-mobile-header {
            display: none !important;
        }
    }

    /* ── Theme toggle switch ── */
    .admin-theme-toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        width: 100%;
        margin-top: 14px;
        padding: 12px 16px;
        border-radius: 16px;
        background: rgba(255,255,255,0.14);
        transition: 0.25s ease;
    }

    .admin-theme-toggle-row:hover {
        background: rgba(255,255,255,0.22);
    }

    .admin-theme-toggle-label {
        color: #fff;
        font-weight: 700;
        font-size: 14px;
    }

    .theme-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .theme-switch input {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        cursor: pointer;
        z-index: 1;
    }

    .theme-switch-track {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.28);
        border-radius: 999px;
        transition: background 0.25s ease;
    }

    .theme-switch input:checked + .theme-switch-track {
        background: #133c96;
    }

    .theme-switch-thumb {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        line-height: 1;
        box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        transition: transform 0.25s ease;
    }

    .theme-switch input:checked ~ .theme-switch-track .theme-switch-thumb {
        transform: translateX(24px);
    }

    .theme-switch input:focus-visible + .theme-switch-track {
        outline: 2px solid #fff;
        outline-offset: 2px;
    }

    /* ── Dark theme overrides ──
       Pages that already use the shared --primary/--text/--muted/--border/--bg/--card
       design tokens re-theme automatically. The selectors below cover the admin
       pages that use hardcoded colors instead of those tokens. */
    :root[data-theme="dark"] {
        --primary-soft: #1e293b;
        --primary-dark: #7dabff;
        --text: #e2e8f0;
        --muted: #94a3b8;
        --border: rgba(255,255,255,0.10);
        --bg: #0f172a;
        --card: #1e293b;
    }

    :root[data-theme="dark"] body {
        background: #0f172a !important;
        color: #e2e8f0;
    }

    :root[data-theme="dark"] .page-hero,
    :root[data-theme="dark"] .controls-card,
    :root[data-theme="dark"] .stats-card,
    :root[data-theme="dark"] .admin-card,
    :root[data-theme="dark"] .form-card,
    :root[data-theme="dark"] .page-header + .stats-row .stat-box,
    :root[data-theme="dark"] .stat-box,
    :root[data-theme="dark"] .table-card,
    :root[data-theme="dark"] .toolbar,
    :root[data-theme="dark"] .control-panel,
    :root[data-theme="dark"] .progress-section,
    :root[data-theme="dark"] .log-section,
    :root[data-theme="dark"] .existing-city-panel,
    :root[data-theme="dark"] .existing-city-summary,
    :root[data-theme="dark"] .bulk-panel,
    :root[data-theme="dark"] .bulk-columns-box,
    :root[data-theme="dark"] .hotel-card,
    :root[data-theme="dark"] .preview-box,
    :root[data-theme="dark"] .preview-stat,
    :root[data-theme="dark"] .staff-box,
    :root[data-theme="dark"] .location-map-preview,
    :root[data-theme="dark"] .dashboard-hero,
    :root[data-theme="dark"] .stat-card,
    :root[data-theme="dark"] .progress-card,
    :root[data-theme="dark"] .quick-link,
    :root[data-theme="dark"] .card,
    :root[data-theme="dark"] .sum-card,
    :root[data-theme="dark"] #hotelMap {
        background: #1e293b !important;
        border-color: rgba(255,255,255,0.08) !important;
        color: #e2e8f0;
    }

    :root[data-theme="dark"] thead th {
        background: #16213a !important;
        color: #94a3b8 !important;
        border-color: rgba(255,255,255,0.08) !important;
    }

    :root[data-theme="dark"] tbody td {
        color: #cbd5e1 !important;
        border-color: rgba(255,255,255,0.06) !important;
    }

    :root[data-theme="dark"] tbody tr:hover td {
        background: #24324f !important;
    }

    :root[data-theme="dark"] .hname,
    :root[data-theme="dark"] h1, :root[data-theme="dark"] h2, :root[data-theme="dark"] h3,
    :root[data-theme="dark"] h4, :root[data-theme="dark"] h5, :root[data-theme="dark"] h6,
    :root[data-theme="dark"] .form-label,
    :root[data-theme="dark"] .field-label,
    :root[data-theme="dark"] .toggle-box,
    :root[data-theme="dark"] .section-label,
    :root[data-theme="dark"] .user-name,
    :root[data-theme="dark"] .stat-val,
    :root[data-theme="dark"] .sum-val,
    :root[data-theme="dark"] .preview-name,
    :root[data-theme="dark"] .preview-price,
    :root[data-theme="dark"] .preview-stat p,
    :root[data-theme="dark"] .search-wrap input {
        color: #f1f5f9 !important;
    }

    :root[data-theme="dark"] .haddr,
    :root[data-theme="dark"] .helper-text,
    :root[data-theme="dark"] .card-subtext,
    :root[data-theme="dark"] .section-desc {
        color: #94a3b8 !important;
    }

    /* Many admin forms hardcode dark-gray text via inline style="color:#..."
       (e.g. helper notes under inputs). Those inline colors are unreadable
       against the dark background and can't be reached by a class selector,
       so match the inline style text directly. */
    :root[data-theme="dark"] [style*="color:#374151"],
    :root[data-theme="dark"] [style*="color: #374151"],
    :root[data-theme="dark"] [style*="color:#1e293b"],
    :root[data-theme="dark"] [style*="color: #1e293b"],
    :root[data-theme="dark"] [style*="color:#6b7280"],
    :root[data-theme="dark"] [style*="color: #6b7280"],
    :root[data-theme="dark"] [style*="color:#64748b"],
    :root[data-theme="dark"] [style*="color: #64748b"],
    :root[data-theme="dark"] [style*="color:#475569"],
    :root[data-theme="dark"] [style*="color: #475569"],
    :root[data-theme="dark"] [style*="color:#334155"],
    :root[data-theme="dark"] [style*="color: #334155"] {
        color: #cbd5e1 !important;
    }

    :root[data-theme="dark"] .search-wrap,
    :root[data-theme="dark"] .field-input,
    :root[data-theme="dark"] .form-control,
    :root[data-theme="dark"] .form-select,
    :root[data-theme="dark"] input[type="text"],
    :root[data-theme="dark"] input[type="email"],
    :root[data-theme="dark"] input[type="number"],
    :root[data-theme="dark"] input[type="password"],
    :root[data-theme="dark"] select,
    :root[data-theme="dark"] textarea,
    :root[data-theme="dark"] .filter-sel {
        background: #16213a !important;
        border-color: rgba(255,255,255,0.12) !important;
        color: #e2e8f0 !important;
    }

    :root[data-theme="dark"] .field-input::placeholder,
    :root[data-theme="dark"] .form-control::placeholder,
    :root[data-theme="dark"] input::placeholder,
    :root[data-theme="dark"] textarea::placeholder {
        color: #64748b !important;
    }

    :root[data-theme="dark"] .card-divider,
    :root[data-theme="dark"] .section-divider {
        border-color: rgba(255,255,255,0.08) !important;
    }

    :root[data-theme="dark"] .btn-soft {
        background: #24324f !important;
        color: #cbd5e1 !important;
    }

    :root[data-theme="dark"] .readonly-look {
        background: #111827 !important;
        color: #64748b !important;
    }

    :root[data-theme="dark"] .progress-track {
        background: #16213a !important;
    }

    :root[data-theme="dark"] .travelix-ac-list {
        background: #1e293b;
        border-color: rgba(255,255,255,0.12);
    }

    :root[data-theme="dark"] .travelix-ac-item {
        color: #e2e8f0;
    }

    :root[data-theme="dark"] .travelix-ac-item:hover,
    :root[data-theme="dark"] .travelix-ac-item.active {
        background: #24324f;
    }

    :root[data-theme="dark"] .swal2-popup {
        background: #1e293b !important;
        color: #e2e8f0 !important;
    }

    :root[data-theme="dark"] .swal2-title,
    :root[data-theme="dark"] .swal2-html-container {
        color: #e2e8f0 !important;
    }
</style>

<script>
    (function () {
        const savedTheme = localStorage.getItem('travelix_admin_theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        if (!document.querySelector('link[rel="icon"]')) {
            document.head.insertAdjacentHTML('beforeend', '<link rel="icon" type="image/png" href="/travelix/images/favicon.png">');
        }
    })();
</script>

<div class="admin-mobile-header">
    <div class="admin-mobile-header-left">
        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Admin" class="admin-mobile-avatar">
        <div class="admin-mobile-text">
            <strong>Welcome, <?php echo htmlspecialchars($firstName); ?></strong>
            <span>Travelix Admin</span>
        </div>
    </div>
    <button type="button" class="admin-mobile-toggle" id="adminSidebarToggle">☰</button>
</div>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
        <div class="admin-sidebar-brand-left">
            <div class="admin-sidebar-brand-icon">T</div>
            <div class="admin-sidebar-brand-text">
                <h4>Travelix</h4>
                <span>Admin Panel</span>
            </div>
        </div>

        <div class="travelix-notification-wrapper" id="travelixNotificationWrapper">
            <button type="button" class="travelix-notification-btn" id="travelixNotificationToggle" title="Notifications" aria-label="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="travelix-notification-badge" id="travelixNotificationBadge">0</span>
            </button>

            <div class="travelix-notification-panel" id="travelixNotificationPanel">
                <div class="travelix-notification-header">
                    <div>
                        <h6>Notifications</h6>
                        <span class="travelix-notification-count-text" id="travelixNotificationCountText">0 new</span>
                    </div>
                    <div class="travelix-notification-header-actions">
                        <button type="button" class="travelix-refresh-notification-btn" id="travelixRefreshNotificationsBtn" title="Refresh notifications" aria-label="Refresh notifications">
                            <i class="fa-solid fa-arrow-rotate-right"></i>
                        </button>
                        <button type="button" class="travelix-read-all-btn" id="travelixReadAllBtn">Read all</button>
                    </div>
                </div>
                <div class="travelix-notification-list" id="travelixNotificationList">
                    <div class="travelix-notification-empty">Loading notifications...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-profile-box">
        <a href="#"
           class="admin-profile-box-link loader-link"
           data-href="<?php echo $baseUrl; ?>/profile/manage_admin_profile.php">
            <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Admin Profile" class="admin-profile-image">
            <h5>Welcome, <?php echo htmlspecialchars($firstName); ?></h5>
            <p>Administrator Access</p>
        </a>

        <a href="#"
           class="admin-profile-manage-btn loader-link"
           data-href="<?php echo $baseUrl; ?>/profile/manage_admin_profile.php">
            ⚙ Manage Profile
        </a>

        <div class="admin-theme-toggle-row">
            <span class="admin-theme-toggle-label" id="adminThemeLabel">Dark Mode</span>
            <label class="theme-switch" for="adminThemeToggle">
                <input type="checkbox" id="adminThemeToggle" role="switch" aria-label="Toggle dark mode">
                <span class="theme-switch-track">
                    <span class="theme-switch-thumb"><span id="adminThemeIcon">🌙</span></span>
                </span>
            </label>
        </div>
    </div>

    <nav class="admin-nav">
        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/dashboard/admin_dashboard.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/dashboard/admin_dashboard.php">
            <span class="admin-nav-icon">📊</span>
            <span>Dashboard</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/manage_users.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/manage_users.php">
            <span class="admin-nav-icon">👤</span>
            <span>Manage Users</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/manage_trips.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/manage_trips.php">
            <span class="admin-nav-icon">🧳</span>
            <span>Manage Cities</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/add_trips.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/add_trips.php">
            <span class="admin-nav-icon">➕</span>
            <span>Add Trip</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/manage_hotels_portal.php', $currentPath) ?: adminSidebarActive('/admin_manage/manage_hotels.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/manage_hotels_portal.php">
            <span class="admin-nav-icon">🏨</span>
            <span>Manage Hotels</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/admin_hotel_form.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/admin_hotel_form.php">
            <span class="admin-nav-icon">➕</span>
            <span>Add Hotel</span>
        </a>

<a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/manage_feedback.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/manage_feedback.php">
            <span class="admin-nav-icon">💬</span>
            <span>Manage Feedback</span>
        </a>

        <a href="#"
           class="admin-nav-link loader-link <?php echo adminSidebarActive('/admin_manage/payment_settings.php', $currentPath); ?>"
           data-href="<?php echo $baseUrl; ?>/admin_manage/payment_settings.php">
            <span class="admin-nav-icon">💳</span>
            <span>Payment Settings</span>
        </a>

    </nav>

    <div class="admin-sidebar-footer">
        <a href="#"
           class="admin-logout-link loader-link"
           data-href="<?php echo $baseUrl; ?>/auth/logout.php">
            Logout
        </a>
    </div>
</aside>

<script>
(function () {
    const themeToggle = document.getElementById('adminThemeToggle');
    const themeIcon = document.getElementById('adminThemeIcon');
    const themeLabel = document.getElementById('adminThemeLabel');

    function reflectTheme(theme) {
        if (!themeIcon || !themeLabel) return;
        if (theme === 'dark') {
            themeIcon.textContent = '☀️';
            themeLabel.textContent = 'Light Mode';
            if (themeToggle) themeToggle.checked = true;
        } else {
            themeIcon.textContent = '🌙';
            themeLabel.textContent = 'Dark Mode';
            if (themeToggle) themeToggle.checked = false;
        }
    }

    reflectTheme(document.documentElement.getAttribute('data-theme'));

    if (themeToggle) {
        themeToggle.addEventListener('change', function () {
            const nextTheme = themeToggle.checked ? 'dark' : 'light';

            if (nextTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }

            localStorage.setItem('travelix_admin_theme', nextTheme);
            reflectTheme(nextTheme);
        });
    }
})();

(function () {
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar = document.getElementById('adminSidebar');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (window.innerWidth >= 992) return;
            if (!sidebar.classList.contains('open')) return;
            if (sidebar.contains(e.target) || toggleBtn.contains(e.target)) return;
            sidebar.classList.remove('open');
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('open');
            }
        });
    }

    function showPageLoader(url, message = 'Opening page...') {
        Swal.fire({
            title: 'Please wait...',
            text: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        setTimeout(() => {
            window.location.href = url;
        }, 700);
    }

    document.querySelectorAll('.loader-link').forEach(link => {
        link.addEventListener('click', function (e) {
            const targetUrl = this.getAttribute('data-href');
            if (!targetUrl || targetUrl === '#') return;

            e.preventDefault();

            if (window.innerWidth < 992 && sidebar) {
                sidebar.classList.remove('open');
            }

            const isLogout = targetUrl.includes('/auth/logout.php');
            showPageLoader(targetUrl, isLogout ? 'Logging out...' : 'Opening page...');
        });
    });
})();
</script>

<script src="<?php echo $baseUrl; ?>/assets/js/travelix_portal_notifications.js"></script>
<script>
(function () {
    // admin_sidebar.php is included near the top of every admin page, before
    // that page's own Firebase <script> tags (every admin_manage/*.php page
    // loads firebase-app-compat + firebase-firestore-compat itself). Loading
    // a SECOND copy of those SDK scripts here would silently create a second,
    // incompatible Firestore "instance" — FieldValue.serverTimestamp()
    // sentinels from one copy get rejected as invalid by the other copy's db
    // object ("unsupported field value: a custom ... object"). So: wait for
    // the page to finish loading (its own Firebase scripts included), reuse
    // that single instance, and only load the SDK ourselves as a fallback
    // for the rare admin page that doesn't already load Firebase.
    const firebaseConfig = {
        apiKey:            "<?= FIREBASE_API_KEY ?>",
        authDomain:        "<?= FIREBASE_PROJECT_ID ?>.firebaseapp.com",
        projectId:         "<?= FIREBASE_PROJECT_ID ?>",
        storageBucket:     "<?= FIREBASE_PROJECT_ID ?>.firebasestorage.app",
        messagingSenderId: "<?= FIREBASE_MESSAGING_SENDER_ID ?>",
        appId:             "<?= FIREBASE_APP_ID ?>"
    };

    function startNotifications() {
        if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
        window.travelixInitPortalNotifications({
            db: firebase.firestore(),
            filters: [['audience', '==', 'admin']],
            manageUrl: '/travelix/admin_manage/notifications.php'
        });
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    window.addEventListener('load', function () {
        if (window.firebase && firebase.apps) {
            startNotifications();
            return;
        }
        loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js')
            .then(() => loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js'))
            .then(startNotifications)
            .catch((e) => console.warn('Could not load Firebase for admin notifications:', e));
    });
})();
</script>
