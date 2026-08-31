<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']['uid']);

$uid = $isLoggedIn ? (string)($_SESSION['user']['uid'] ?? '') : '';
$firstName = $isLoggedIn ? ($_SESSION['user']['first_name'] ?? 'User') : '';
$profileImage = $isLoggedIn
    ? ($_SESSION['user']['profile_image'] ?? ($baseUrl . '/images/default_profile.png'))
    : ($baseUrl . '/images/default_profile.png');

function isActive($pageName, $currentPage) {
    return $pageName === $currentPage ? 'active' : '';
}
?>

<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --theme-color: #1484B4;
    --theme-dark: #0f6d95;
    --nav-bg: #f3f3f3;
    --text-dark: #0f172a;
    --muted: #57667a;
    --danger: #dc2626;
    --success: #16a34a;
}

.travelix-topbar-wrapper {
    position: absolute;
    top: 18px;
    left: 0;
    width: 100%;
    z-index: 999;
    padding: 0 40px;
}

.travelix-topbar {
    background: var(--nav-bg);
    border-radius: 50px;
    padding: 18px 26px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.travelix-logo img {
    height: 52px;
    object-fit: contain;
}

.travelix-center-menu {
    display: flex;
    align-items: center;
    gap: 36px;
    margin: 0 auto;
}

.travelix-center-menu a {
    text-decoration: none;
    color: var(--text-dark);
    font-size: 18px;
    font-weight: 700;
    position: relative;
}

.travelix-center-menu a:hover,
.travelix-center-menu a.active {
    color: var(--theme-color);
}

.travelix-center-menu a.active::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 100%;
    height: 3px;
    border-radius: 10px;
    background: var(--theme-color);
}

.travelix-right-box {
    display: flex;
    align-items: center;
    gap: 14px;
}

.travelix-login-btn,
.travelix-signup-btn {
    background: var(--theme-color);
    color: #fff !important;
    border-radius: 40px;
    padding: 14px 28px;
    font-size: 17px;
    font-weight: 700;
    text-decoration: none;
}

.travelix-login-btn {
    background: transparent;
    color: var(--theme-color) !important;
    border: 2px solid var(--theme-color);
}

.travelix-notification-wrapper,
.travelix-profile-dropdown-wrapper {
    position: relative;
}

.travelix-notification-btn {
    position: relative;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(20,132,180,0.10);
    border: none;
    color: var(--theme-color);
    font-size: 21px;
    cursor: pointer;
}

.travelix-notification-badge {
    position: absolute;
    top: -5px;
    right: -2px;
    background: #dc3545;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    min-width: 22px;
    height: 22px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

.travelix-notification-panel {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 430px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 18px 45px rgba(0,0,0,0.16);
    display: none;
    overflow: hidden;
    z-index: 2500;
}

.travelix-notification-panel.show {
    display: block;
}

.travelix-notification-header {
    padding: 18px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.travelix-notification-header h6 {
    margin: 0;
    font-size: 22px;
    font-weight: 900;
    color: var(--text-dark);
}

.travelix-notification-count-text {
    color: var(--theme-color);
    font-weight: 800;
    font-size: 14px;
}

.travelix-notification-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.travelix-refresh-notification-btn,
.travelix-read-all-btn {
    border: none;
    background: transparent;
    color: var(--theme-color);
    font-weight: 800;
    cursor: pointer;
}

.travelix-refresh-notification-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(20,132,180,0.10);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.travelix-refresh-notification-btn:hover {
    background: rgba(20,132,180,0.18);
}

.travelix-refresh-notification-btn.loading i {
    animation: travelixSpin 0.7s linear infinite;
}

@keyframes travelixSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Scrollbar appears after more than 2 notifications */
.travelix-notification-list {
    max-height: 270px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(20,132,180,0.55) #eef7fb;
}

.travelix-notification-list::-webkit-scrollbar {
    width: 7px;
}

.travelix-notification-list::-webkit-scrollbar-track {
    background: #eef7fb;
    border-radius: 20px;
}

.travelix-notification-list::-webkit-scrollbar-thumb {
    background: rgba(20,132,180,0.55);
    border-radius: 20px;
}

.travelix-notification-item {
    position: relative;
    display: grid;
    grid-template-columns: 52px 1fr;
    gap: 12px;
    padding: 16px 30px 16px 18px;
    border-bottom: 1px solid #eef2f7;
    background: #fff;
    cursor: pointer;
    transition: background .15s ease;
}

.travelix-notification-item:hover {
    background: #f8fafc;
}

/* Visual cue that the row itself is clickable (jumps to the linked page) —
   separate from the Read/Delete buttons below the text. */
.travelix-notification-item-chevron {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #cbd5e1;
    font-size: 12px;
    transition: color .15s ease, transform .15s ease;
}

.travelix-notification-item:hover .travelix-notification-item-chevron {
    color: #1484B4;
    transform: translateY(-50%) translateX(2px);
}

.travelix-notification-item.unread {
    background: #f0faff;
}

.travelix-notification-icon {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #eaf7fc;
    color: var(--theme-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.travelix-notification-title {
    color: var(--text-dark);
    font-size: 15px;
    font-weight: 900;
    margin-bottom: 4px;
}

.travelix-notification-message {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.45;
    margin-bottom: 7px;
}

.travelix-notification-time {
    color: #4f5b6d;
    font-size: 12px;
    font-weight: 800;
}

.travelix-notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.travelix-notification-action-btn {
    border: none;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.travelix-mark-read-btn {
    background: #ecfdf5;
    color: #166534;
}

.travelix-delete-notification-btn {
    background: #fee2e2;
    color: #b91c1c;
}

.travelix-notification-empty {
    padding: 35px 20px;
    text-align: center;
    color: var(--muted);
    font-weight: 700;
}

.travelix-user-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    background: transparent;
    border: none;
    padding: 8px 12px;
    border-radius: 40px;
    cursor: pointer;
}

.travelix-user-btn:hover {
    background: rgba(20,132,180,0.08);
}

.travelix-user-btn img {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--theme-color);
}

.travelix-user-name {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-dark);
}

.travelix-dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    display: none;
    min-width: 240px;
    background: #fff;
    border: none;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    padding: 10px;
    z-index: 2200;
    list-style: none;
    margin: 0;
}

.travelix-dropdown-menu.show {
    display: block;
}

.travelix-dropdown-menu .dropdown-item {
    border-radius: 12px;
    padding: 12px 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.travelix-dropdown-menu .dropdown-item:hover {
    background: rgba(20,132,180,0.08);
    color: var(--theme-color);
}

.travelix-logout-item {
    color: #dc2626 !important;
}

.travelix-hamburger-btn {
    display: none;
}

@media (max-width: 991px) {
    .travelix-topbar-wrapper {
        padding: 0 14px;
    }

    .travelix-topbar {
        border-radius: 28px;
        padding: 12px 16px;
        flex-wrap: nowrap;
        gap: 6px;
    }

    .travelix-logo {
        min-width: 0;
        flex-shrink: 1;
    }

    .travelix-logo img {
        height: auto;
        width: 100px;
        max-width: 100%;
    }

    .travelix-hamburger-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(20,132,180,0.10);
        border: none;
        color: var(--theme-color);
        font-size: 19px;
        cursor: pointer;
        flex-shrink: 0;
        order: 2;
    }

    .travelix-center-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        right: 0;
        margin: 0;
        width: auto;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 28px rgba(0,0,0,0.14);
        flex-direction: column;
        align-items: stretch;
        padding: 10px;
        gap: 2px;
        order: 4;
        z-index: 1000;
    }

    .travelix-center-menu.show {
        display: flex;
    }

    .travelix-center-menu a {
        font-size: 16px;
        padding: 12px 16px;
        border-radius: 12px;
    }

    .travelix-center-menu a.active {
        background: rgba(20,132,180,0.08);
    }

    .travelix-center-menu a.active::after {
        display: none;
    }

    .travelix-right-box {
        order: 3;
        gap: 8px;
        flex-shrink: 0;
    }

    .travelix-login-btn,
    .travelix-signup-btn {
        padding: 9px 14px;
        font-size: 13px;
        white-space: nowrap;
    }

    .travelix-notification-btn {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }

    .travelix-user-btn img {
        width: 36px;
        height: 36px;
    }

    .travelix-user-name {
        display: none;
    }

    .travelix-notification-panel {
        right: -60px;
        width: min(94vw, 380px);
    }
}

@media (max-width: 420px) {
    .travelix-signup-btn {
        padding: 8px 10px;
        font-size: 12px;
    }

    .travelix-login-btn {
        padding: 8px 10px;
        font-size: 12px;
    }
}

/* ── Site-wide subtle 3D button press/lift ──
   This include is loaded on nearly every user-facing page, so this alone
   gives every native <button>, Bootstrap .btn, SweetAlert2 dialog button,
   and marked .btn-3d link a small lift-on-hover / press-on-click feel —
   without having to touch each page's own button markup. */
button:not(.no-3d):not([disabled]),
.btn:not(.no-3d),
.btn-3d,
.swal2-styled {
    transition: transform .15s ease, box-shadow .15s ease !important;
}

button:not(.no-3d):not([disabled]):hover,
.btn:not(.no-3d):hover,
.btn-3d:hover,
.swal2-styled:hover {
    transform: translateY(-2px);
}

button:not(.no-3d):not([disabled]):active,
.btn:not(.no-3d):active,
.btn-3d:active,
.swal2-styled:active {
    transform: translateY(1px) scale(.98);
}

@media (prefers-reduced-motion: reduce) {
    button:not(.no-3d),
    .btn:not(.no-3d),
    .btn-3d,
    .swal2-styled {
        transform: none !important;
        transition: none !important;
    }
}
</style>

<div class="travelix-topbar-wrapper">
    <div class="travelix-topbar">

        <a href="<?= $baseUrl; ?>/dashboard/user_dashboard.php" class="travelix-logo nav-loader-link" data-href="<?= $baseUrl; ?>/dashboard/user_dashboard.php">
            <?php
            $logoPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/images/logo.png';
            $logoVer  = file_exists($logoPath) ? filemtime($logoPath) : time();
            ?>
            <img src="<?= $baseUrl; ?>/images/logo.png?v=<?= $logoVer; ?>" alt="Travelix Logo" width="202" height="52">
        </a>

        <button type="button" class="travelix-hamburger-btn" id="travelixHamburgerToggle" aria-label="Menu" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>

        <div class="travelix-center-menu" id="travelixCenterMenu">
            <a href="<?= $baseUrl; ?>/dashboard/user_dashboard.php"
               class="nav-loader-link <?= isActive('user_dashboard.php', $currentPage); ?>"
               data-href="<?= $baseUrl; ?>/dashboard/user_dashboard.php">Home</a>

            <a href="<?= $baseUrl; ?>/plan_guides/guiders.php"
               class="nav-loader-link <?= isActive('guiders.php', $currentPage); ?>"
               data-href="<?= $baseUrl; ?>/plan_guides/guiders.php">Guiders</a>

            <a href="<?= $baseUrl; ?>/hotel/hotels.php"
               class="nav-loader-link <?= isActive('hotels.php', $currentPage); ?>"
               data-href="<?= $baseUrl; ?>/hotel/hotels.php">Hotels</a>

            <a href="<?= $baseUrl; ?>/contact/contactus.php"
               class="nav-loader-link <?= isActive('contactus.php', $currentPage); ?>"
               data-href="<?= $baseUrl; ?>/contact/contactus.php">Contact Us</a>
        </div>

        <div class="travelix-right-box">
            <?php if (!$isLoggedIn): ?>

                <a href="<?= $baseUrl; ?>/auth/login.php"
                   class="travelix-login-btn nav-loader-link"
                   data-href="<?= $baseUrl; ?>/auth/login.php">Login</a>

                <a href="<?= $baseUrl; ?>/auth/signup.php"
                   class="travelix-signup-btn nav-loader-link"
                   data-href="<?= $baseUrl; ?>/auth/signup.php">Create Account</a>

            <?php else: ?>

                <div class="travelix-notification-wrapper" id="travelixNotificationWrapper">
                    <button type="button" class="travelix-notification-btn" id="travelixNotificationToggle" title="Notifications" aria-label="Notifications">
                        <i class="bi bi-bell-fill"></i>
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
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>

                                <button type="button" class="travelix-read-all-btn" id="travelixReadAllBtn">Read all</button>
                            </div>
                        </div>

                        <div class="travelix-notification-list" id="travelixNotificationList">
                            <div class="travelix-notification-empty">Loading notifications...</div>
                        </div>
                    </div>
                </div>

                <div class="travelix-profile-dropdown-wrapper" id="travelixProfileDropdown">
                    <button class="travelix-user-btn" type="button" id="travelixProfileToggle">
                        <img src="<?= htmlspecialchars($profileImage); ?>" alt="Profile" width="46" height="46"
                             onerror="this.onerror=null;this.src='<?= $baseUrl; ?>/images/default_profile.png';">
                        <span class="travelix-user-name"><?= htmlspecialchars($firstName); ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>

                    <ul class="travelix-dropdown-menu" id="travelixProfileMenu">
                        <li>
                            <a class="dropdown-item nav-loader-link"
                               href="<?= $baseUrl; ?>/profile/manage_user_profile.php"
                               data-href="<?= $baseUrl; ?>/profile/manage_user_profile.php">
                                <i class="bi bi-person-circle"></i>
                                <span>Manage Profile</span>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item nav-loader-link"
                               href="<?= $baseUrl; ?>/hotel/manage_bookings.php"
                               data-href="<?= $baseUrl; ?>/hotel/manage_bookings.php">
                                <i class="bi bi-journal-check"></i>
                                <span>Manage Bookings</span>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item nav-loader-link"
                               href="<?= $baseUrl; ?>/manage_bookings/history.php"
                               data-href="<?= $baseUrl; ?>/manage_bookings/history.php">
                                <i class="bi bi-clock-history"></i>
                                <span>Saved Trips</span>
                            </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <a class="dropdown-item travelix-logout-item nav-loader-link"
                               href="<?= $baseUrl; ?>/auth/logout.php"
                               data-href="<?= $baseUrl; ?>/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<script>
if (!document.querySelector('link[rel="icon"]')) {
    document.head.insertAdjacentHTML('beforeend', '<link rel="icon" type="image/png" href="/travelix/images/favicon.png">');
}
</script>

<script type="module">
import { firebaseConfig } from "<?= $baseUrl; ?>/config/firebase-config.js";

import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
import {
    getFirestore,
    collection,
    addDoc,
    doc,
    updateDoc,
    deleteDoc,
    writeBatch,
    query,
    where,
    onSnapshot,
    getDocs,
    serverTimestamp
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const db = getFirestore(app);

const loggedInUserId = "<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>";
const baseUrl = "<?= $baseUrl; ?>";

const notificationToggle = document.getElementById("travelixNotificationToggle");
const notificationPanel = document.getElementById("travelixNotificationPanel");
const notificationWrapper = document.getElementById("travelixNotificationWrapper");
const notificationList = document.getElementById("travelixNotificationList");
const notificationBadge = document.getElementById("travelixNotificationBadge");
const notificationCountText = document.getElementById("travelixNotificationCountText");
const readAllBtn = document.getElementById("travelixReadAllBtn");
const refreshNotificationsBtn = document.getElementById("travelixRefreshNotificationsBtn");

const profileToggle = document.getElementById("travelixProfileToggle");
const profileMenu = document.getElementById("travelixProfileMenu");
const profileWrapper = document.getElementById("travelixProfileDropdown");

const hamburgerToggle = document.getElementById("travelixHamburgerToggle");
const centerMenu = document.getElementById("travelixCenterMenu");

let currentNotifications = [];
let unsubscribeNotifications = null;

function showNavLoader(title = "Loading...") {
    if (window.Swal) {
        Swal.fire({
            title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }
}

document.querySelectorAll(".nav-loader-link").forEach(link => {
    link.addEventListener("click", function (e) {
        const href = this.dataset.href || this.href;
        if (!href || href === "#") return;

        e.preventDefault();
        showNavLoader("Loading...");
        setTimeout(() => {
            window.location.href = href;
        }, 500);
    });
});

profileToggle?.addEventListener("click", function (e) {
    e.stopPropagation();
    profileMenu?.classList.toggle("show");
    notificationPanel?.classList.remove("show");
    centerMenu?.classList.remove("show");
});

notificationToggle?.addEventListener("click", async function (e) {
    e.stopPropagation();
    notificationPanel?.classList.toggle("show");
    profileMenu?.classList.remove("show");
    centerMenu?.classList.remove("show");

    if (notificationPanel?.classList.contains("show")) {
        await refreshNotifications(false);
    }
});

hamburgerToggle?.addEventListener("click", function (e) {
    e.stopPropagation();
    const isOpen = centerMenu?.classList.toggle("show");
    hamburgerToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    profileMenu?.classList.remove("show");
    notificationPanel?.classList.remove("show");
});

document.addEventListener("click", function (e) {
    if (e.target.closest(".swal2-container")) return;

    if (profileWrapper && !profileWrapper.contains(e.target)) {
        profileMenu?.classList.remove("show");
    }

    if (notificationWrapper && !notificationWrapper.contains(e.target)) {
        notificationPanel?.classList.remove("show");
    }

    if (centerMenu && !centerMenu.contains(e.target) && !hamburgerToggle?.contains(e.target)) {
        centerMenu.classList.remove("show");
        hamburgerToggle?.setAttribute("aria-expanded", "false");
    }
});

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function getIcon(type) {
    const icons = {
        trip_saved: "bi-map-fill",
        profile_updated: "bi-person-check-fill",
        hotel_booked: "bi-building-check",
        booking_updated: "bi-journal-check",
        booking_cancelled: "bi-x-circle-fill",
        booking_deleted: "bi-trash-fill",
        saved_trips: "bi-clock-history",
        feedback_request: "bi-star-fill",
        general: "bi-bell-fill"
    };

    return icons[type] || icons.general;
}

function getCreatedAtMs(createdAt) {
    if (!createdAt) return Date.now();

    if (createdAt.toDate) {
        return createdAt.toDate().getTime();
    }

    if (createdAt.seconds) {
        return createdAt.seconds * 1000;
    }

    const parsed = new Date(createdAt).getTime();
    return Number.isNaN(parsed) ? Date.now() : parsed;
}

function formatTime(createdAt) {
    if (!createdAt) return "Just now";

    const dateMs = getCreatedAtMs(createdAt);
    const seconds = Math.floor((Date.now() - dateMs) / 1000);

    if (seconds < 60) return "Just now";
    if (seconds < 3600) return Math.floor(seconds / 60) + " min ago";
    if (seconds < 86400) return Math.floor(seconds / 3600) + " hour ago";
    if (seconds < 604800) return Math.floor(seconds / 86400) + " day ago";

    return new Date(dateMs).toLocaleDateString();
}

function sortNotifications() {
    currentNotifications.sort((a, b) => {
        return getCreatedAtMs(b.createdAt) - getCreatedAtMs(a.createdAt);
    });
}

function updateNotificationCount() {
    const unreadCount = currentNotifications.filter(item => item.isRead !== true).length;

    if (notificationBadge) {
        notificationBadge.textContent = unreadCount > 99 ? "99+" : unreadCount;
        notificationBadge.style.display = unreadCount > 0 ? "inline-flex" : "none";
    }

    if (notificationCountText) {
        notificationCountText.textContent = unreadCount + " new";
    }
}

// Money-movement and booking-status notification types worth interrupting
// the user with a popup for, rather than making them notice the passive
// bell badge.
const POPUP_NOTIF_TYPES = new Set(["refund_pending", "refund_sent", "refund_confirmed", "refund_disputed", "payout_pending", "payout_sent", "hotel_booking_confirmed", "hotel_booking_rejected", "booking_cancelled"]);
let shownPopupIds = new Set();
let popupStorageKey = null;

function maybeShowLoginNotificationPopup() {
    if (!loggedInUserId) return;
    if (!popupStorageKey) {
        popupStorageKey = "travelix_popup_shown_ids_" + loggedInUserId;
        try {
            const stored = sessionStorage.getItem(popupStorageKey);
            if (stored) shownPopupIds = new Set(JSON.parse(stored));
        } catch (e) { /* sessionStorage unavailable — popups just won't dedupe across page loads */ }
    }

    // Anything unread and popup-worthy that hasn't been popped up yet this
    // session — not just the first batch at load, so a genuinely new
    // arrival (via the real-time listener below) gets its own popup
    // immediately instead of silently landing only in the passive bell badge.
    const items = currentNotifications.filter(item => item.isRead !== true && POPUP_NOTIF_TYPES.has(item.type) && !shownPopupIds.has(item.id));
    if (items.length === 0) return;

    items.forEach(item => shownPopupIds.add(item.id));
    try { sessionStorage.setItem(popupStorageKey, JSON.stringify([...shownPopupIds])); } catch (e) { /* ignore */ }

    const listHtml = items.slice(0, 5).map(item => `
        <div class="travelix-popup-notif-item" data-id="${escapeHtml(item.id)}" data-link="${escapeHtml(item.link || "")}"
             style="text-align:left;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;${item.link ? "cursor:pointer;" : ""}">
            <div style="font-weight:600;color:#111827;">${escapeHtml(item.title || "Notification")}</div>
            <div style="font-size:13px;color:#4b5563;margin-top:2px;">${escapeHtml(item.message || "")}</div>
            ${item.link ? '<div style="font-size:11.5px;color:#1484B4;font-weight:700;margin-top:4px;">Tap to open →</div>' : ""}
        </div>
    `).join("");

    // Exactly one actionable notification — skip the extra "View All" click
    // and go straight to the page it's about, instead of making the user
    // open the panel first and click again.
    const singleLink = items.length === 1 ? (items[0].link || "") : "";

    Swal.fire({
        icon: "info",
        title: items.length === 1 ? escapeHtml(items[0].title || "Travelix Update") : `${items.length} Travelix Updates`,
        html: `<div style="max-height:280px;overflow-y:auto;">${listHtml}</div>`,
        confirmButtonText: singleLink ? "Open" : "View All",
        confirmButtonColor: "#1484B4",
        showCancelButton: true,
        cancelButtonText: "Dismiss",
        didOpen: (popup) => {
            popup.querySelectorAll(".travelix-popup-notif-item[data-link]").forEach((el) => {
                const link = el.dataset.link;
                if (!link) return;
                el.addEventListener("click", async () => {
                    const id = el.dataset.id;
                    try { await updateDoc(doc(db, "notifications", id), { isRead: true, readAt: serverTimestamp() }); } catch (e) { /* non-blocking */ }
                    window.location.href = link;
                });
            });
        },
        // Deferred a tick, not run synchronously — the click that confirms
        // this SweetAlert also bubbles up to the document-level "click
        // outside closes the panel" listener above, which would otherwise
        // undo this in the very same event before the panel ever became
        // visible.
        preConfirm: () => {
            if (singleLink) {
                window.location.href = singleLink;
                return;
            }
            setTimeout(() => notificationPanel?.classList.add("show"), 0);
        }
    });
}

function renderNotifications() {
    sortNotifications();
    updateNotificationCount();
    maybeShowLoginNotificationPopup();

    if (!notificationList) return;

    if (currentNotifications.length === 0) {
        notificationList.innerHTML = `<div class="travelix-notification-empty">No notifications yet.</div>`;
        return;
    }

    notificationList.innerHTML = currentNotifications.map(item => {
        const isUnread = item.isRead !== true;
        const icon = item.icon || getIcon(item.type);

        return `
            <div class="travelix-notification-item ${isUnread ? "unread" : ""}" data-id="${escapeHtml(item.id)}">
                <div class="travelix-notification-icon">
                    <i class="bi ${escapeHtml(icon)}"></i>
                </div>

                <div>
                    <div class="travelix-notification-title">${escapeHtml(item.title || "Notification")}</div>
                    <div class="travelix-notification-message">${escapeHtml(item.message || "")}</div>
                    <div class="travelix-notification-time">${formatTime(item.createdAt)}</div>

                    <div class="travelix-notification-actions">
                        ${isUnread ? `
                            <button type="button"
                                    class="travelix-notification-action-btn travelix-mark-read-btn"
                                    data-action="read"
                                    data-id="${escapeHtml(item.id)}">
                                Read
                            </button>
                        ` : ""}

                        <button type="button"
                                class="travelix-notification-action-btn travelix-delete-notification-btn"
                                data-action="delete"
                                data-id="${escapeHtml(item.id)}">
                            Delete
                        </button>
                    </div>
                </div>
                ${item.link ? '<i class="bi bi-chevron-right travelix-notification-item-chevron"></i>' : ""}
            </div>
        `;
    }).join("");
}

function getNotificationsQuery() {
    return query(
        collection(db, "notifications"),
        where("userId", "==", loggedInUserId)
    );
}

async function refreshNotifications(showToast = true) {
    if (!loggedInUserId) return;

    try {
        refreshNotificationsBtn?.classList.add("loading");

        const snapshot = await getDocs(getNotificationsQuery());

        currentNotifications = [];
        snapshot.forEach(docSnap => {
            currentNotifications.push({
                id: docSnap.id,
                ...docSnap.data()
            });
        });

        renderNotifications();

        if (showToast) {
            Swal.fire({
                icon: "success",
                title: "Notifications refreshed",
                timer: 900,
                showConfirmButton: false
            });
        }
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "Refresh Failed",
            text: error.message || "Could not refresh notifications.",
            confirmButtonColor: "#1484B4"
        });
    } finally {
        refreshNotificationsBtn?.classList.remove("loading");
    }
}

function listenForNotifications() {
    if (!loggedInUserId) {
        if (notificationList) {
            notificationList.innerHTML = `<div class="travelix-notification-empty">No notifications yet.</div>`;
        }
        return;
    }

    if (unsubscribeNotifications) {
        unsubscribeNotifications();
    }

    unsubscribeNotifications = onSnapshot(
        getNotificationsQuery(),
        { includeMetadataChanges: true },
        (snapshot) => {
            currentNotifications = [];

            snapshot.forEach(docSnap => {
                currentNotifications.push({
                    id: docSnap.id,
                    ...docSnap.data()
                });
            });

            renderNotifications();
        },
        (error) => {
            console.warn("Notification load error:", error);

            currentNotifications = [];
            updateNotificationCount();

            if (notificationList) {
                notificationList.innerHTML = `<div class="travelix-notification-empty">No notifications yet.</div>`;
            }
        }
    );
}

notificationList?.addEventListener("click", async function (e) {
    const button = e.target.closest("button[data-action]");
    if (!button) return;

    const id = button.dataset.id;
    const action = button.dataset.action;

    if (!id) return;

    try {
        if (action === "read") {
            currentNotifications = currentNotifications.map(item => {
                if (item.id === id) {
                    return {
                        ...item,
                        isRead: true,
                        readAt: new Date()
                    };
                }
                return item;
            });

            renderNotifications();

            await updateDoc(doc(db, "notifications", id), {
                isRead: true,
                readAt: serverTimestamp()
            });
        }

        if (action === "delete") {
            const result = await Swal.fire({
                icon: "warning",
                title: "Delete notification?",
                text: "This notification will be removed.",
                showCancelButton: true,
                confirmButtonText: "Yes, delete",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#dc2626"
            });

            if (!result.isConfirmed) return;

            currentNotifications = currentNotifications.filter(item => item.id !== id);
            renderNotifications();

            await deleteDoc(doc(db, "notifications", id));
        }
    } catch (error) {
        await refreshNotifications(false);

        Swal.fire({
            icon: "error",
            title: "Action Failed",
            text: error.message || "Could not update notification.",
            confirmButtonColor: "#1484B4"
        });
    }
});

// Clicking anywhere on a notification (not its Read/Delete buttons) marks it
// read and jumps straight to whatever it's about — e.g. a feedback-request
// notification opens the feedback form directly instead of just linking to
// a page the user still has to search around on.
notificationList?.addEventListener("click", async function (e) {
    if (e.target.closest("button[data-action]")) return;

    const itemEl = e.target.closest(".travelix-notification-item");
    if (!itemEl) return;

    const id = itemEl.dataset.id;
    const item = currentNotifications.find(n => n.id === id);
    if (!item) return;

    if (item.isRead !== true && !String(id).startsWith("temp_")) {
        try {
            await updateDoc(doc(db, "notifications", id), {
                isRead: true,
                readAt: serverTimestamp()
            });
        } catch (error) {
            console.warn("Could not mark notification read:", error);
        }
    }

    if (item.link) {
        window.location.href = item.link;
    }
});

readAllBtn?.addEventListener("click", async function () {
    const unread = currentNotifications.filter(item => item.isRead !== true);

    if (unread.length === 0) {
        Swal.fire({
            icon: "info",
            title: "No unread notifications",
            text: "All notifications are already read.",
            confirmButtonColor: "#1484B4"
        });
        return;
    }

    try {
        currentNotifications = currentNotifications.map(item => ({
            ...item,
            isRead: true,
            readAt: item.readAt || new Date()
        }));

        renderNotifications();

        const batch = writeBatch(db);

        unread.forEach(item => {
            batch.update(doc(db, "notifications", item.id), {
                isRead: true,
                readAt: serverTimestamp()
            });
        });

        await batch.commit();

        Swal.fire({
            icon: "success",
            title: "Marked as Read",
            text: "All notifications have been marked as read.",
            confirmButtonColor: "#1484B4"
        });
    } catch (error) {
        await refreshNotifications(false);

        Swal.fire({
            icon: "error",
            title: "Failed",
            text: error.message || "Could not mark all notifications as read.",
            confirmButtonColor: "#1484B4"
        });
    }
});

refreshNotificationsBtn?.addEventListener("click", async function (e) {
    e.stopPropagation();
    await refreshNotifications(true);
});

window.travelixAddNotification = async function ({
    title = "Travelix Notification",
    message = "",
    type = "general",
    link = "",
    icon = ""
} = {}) {
    if (!loggedInUserId) return null;

    const tempId = "temp_" + Date.now();

    currentNotifications.unshift({
        id: tempId,
        userId: loggedInUserId,
        uid: loggedInUserId,
        title,
        message,
        type,
        icon: icon || getIcon(type),
        link,
        isRead: false,
        createdAt: new Date()
    });

    renderNotifications();

    const savedDoc = await addDoc(collection(db, "notifications"), {
        userId: loggedInUserId,
        uid: loggedInUserId,
        title,
        message,
        type,
        icon: icon || getIcon(type),
        link,
        isRead: false,
        createdAt: serverTimestamp()
    });

    currentNotifications = currentNotifications.filter(item => item.id !== tempId);
    renderNotifications();

    return savedDoc;
};

listenForNotifications();
refreshNotifications(false);

// Once a guest's checkout date has passed, nudge them to leave feedback —
// runs on every page load (this include is shared site-wide) so it fires
// on whichever page the user happens to visit next, not just Manage
// Bookings. feedbackNotified guards against re-notifying on every reload.
async function scanForFeedbackEligibleBookings() {
    if (!loggedInUserId) return;

    try {
        const bookingsRef = collection(db, "hotel_bookings");
        const [snap1, snap2] = await Promise.all([
            getDocs(query(bookingsRef, where("userId", "==", loggedInUserId))),
            getDocs(query(bookingsRef, where("uid", "==", loggedInUserId)))
        ]);

        const bookingMap = new Map();
        snap1.forEach(d => bookingMap.set(d.id, { id: d.id, ...d.data() }));
        snap2.forEach(d => bookingMap.set(d.id, { id: d.id, ...d.data() }));

        const todayStr = new Date().toISOString().slice(0, 10);

        for (const booking of bookingMap.values()) {
            const status = String(booking.bookingStatus || "confirmed").toLowerCase();
            const departure = String(booking.departureDate || "");

            if (status === "cancelled") continue;
            if (!departure || departure >= todayStr) continue;
            if (booking.feedbackGiven === true) continue;
            if (booking.feedbackNotified === true) continue;

            await window.travelixAddNotification?.({
                title: "How was your stay?",
                message: `Your stay at ${booking.hotelName || "the hotel"} is complete. Tap to share your feedback.`,
                type: "feedback_request",
                link: `${baseUrl}/hotel/manage_bookings.php?feedback=${encodeURIComponent(booking.id)}`
            });

            await updateDoc(doc(db, "hotel_bookings", booking.id), {
                feedbackNotified: true
            });
        }
    } catch (error) {
        console.warn("Feedback eligibility scan failed:", error);
    }
}

scanForFeedbackEligibleBookings();
</script>
