<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['user']) || isset($_SESSION['email']);

if (!$isLoggedIn) {
    header('Location: /travelix/auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Hotel Bookings | Travelix</title>

<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

<style>
:root{
    --theme-color:#1484B4;
    --theme-dark:#0f6d95;
    --theme-soft:#eaf7fc;
    --text-dark:#0f172a;
    --text-muted:#64748b;
    --border-soft:rgba(15,23,42,.08);
    --danger:#e63946;
    --success:#16a34a;
    --warning:#f59e0b;
    --shadow-soft:0 16px 40px rgba(15,23,42,.08);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;background:#f5f9fc;color:var(--text-dark)}
.manage-hero{position:relative;color:#fff;padding:150px 20px 90px;margin-top:0;overflow:hidden;min-height:620px;display:flex;align-items:center}
.manage-hero::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(7,24,37,.70),rgba(20,132,180,.60));z-index:2}
.manage-hero-inner{position:relative;z-index:3;max-width:1280px;margin:0 auto;width:100%}
.manage-hero-slider{position:absolute;inset:0;z-index:1}
.manage-hero-slide{position:absolute;inset:0;opacity:0;transition:opacity .8s ease-in-out}
.manage-hero-slide.active{opacity:1}
.manage-hero-slide img{width:100%;height:100%;object-fit:cover;display:block}
.manage-hero-nav{position:absolute;left:50%;bottom:26px;transform:translateX(-50%);z-index:4;display:flex;gap:10px}
.manage-hero-dot{width:12px;height:12px;border-radius:50%;border:none;background:rgba(255,255,255,.45);cursor:pointer}
.manage-hero-dot.active{background:#fff;transform:scale(1.1)}
.manage-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);padding:8px 14px;border-radius:999px;font-size:13px;font-weight:700;margin-bottom:18px}
.manage-hero h1{margin:0 0 12px;font-size:54px;line-height:1.05;font-weight:800}
.manage-hero p{margin:0;max-width:880px;font-size:18px;line-height:1.8;color:rgba(255,255,255,.92)}
.manage-main{padding:32px 18px 60px}
.manage-container{max-width:1280px;margin:0 auto}
.manage-topbar{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center;margin-bottom:26px}
.manage-topbar-left h3{margin:0 0 4px;font-size:30px;font-weight:800}
.manage-topbar-left p{margin:0;color:var(--text-muted);font-size:15px}
.manage-actions{display:flex;flex-wrap:wrap;gap:12px}
.action-link{ text-decoration:none;background:var(--theme-color);color:#fff;border-radius:999px;padding:12px 18px;font-weight:700;font-size:15px;line-height:1;display:inline-flex;align-items:center;gap:10px;box-shadow:0 12px 24px rgba(20,132,180,.18);border:1.5px solid transparent;white-space:nowrap}
.action-link:hover{background:var(--theme-dark);color:#fff}
.action-link.secondary{background:var(--theme-soft);color:var(--theme-dark);border:1.5px solid rgba(20,132,180,.22);box-shadow:none}
.action-link.secondary:hover{background:#dbf0f9;color:var(--theme-dark)}
.action-link i{font-size:14px;line-height:1}
.manage-tabs{display:flex;gap:8px;margin-bottom:22px;border-bottom:1px solid var(--border-soft);}
.manage-tab{background:none;border:none;padding:12px 18px;font-size:15px;font-weight:700;color:var(--text-muted);cursor:pointer;border-bottom:3px solid transparent;display:inline-flex;align-items:center;gap:8px;transition:.2s;}
.manage-tab:hover{color:var(--theme-dark);}
.manage-tab.active{color:var(--theme-color);border-bottom-color:var(--theme-color);}
.manage-tab-badge{background:#dc2626;color:#fff;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:800;}
.refund-card{background:#fff;border:1px solid var(--border-soft);border-radius:20px;padding:20px 24px;box-shadow:var(--shadow-soft);margin-bottom:18px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;}
.refund-card-info h5{margin:0 0 6px;font-size:18px;font-weight:800;}
.refund-card-info .refund-card-sub{color:var(--text-muted);font-size:13.5px;margin-bottom:6px;}
.refund-card-amount{font-size:24px;font-weight:900;color:var(--danger);text-align:right;}
.refund-status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:800;white-space:nowrap;}
.refund-status-pill.sent{background:#ecfdf5;color:#166534;}
.refund-status-pill.pending{background:#fff7ed;color:#92400e;}
.refund-status-pill.awaiting{background:#eff6ff;color:#1e40af;}
.refund-status-pill.disputed{background:#fef2f2;color:#991b1b;}
.refund-proof-link{display:block;margin-top:6px;font-size:12.5px;color:var(--theme-color);font-weight:700;text-decoration:underline;}
.refund-confirm-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;}
.bookings-grid{display:grid;grid-template-columns:1fr;gap:22px}
.booking-card{background:#fff;border:1px solid var(--border-soft);border-radius:24px;overflow:hidden;box-shadow:var(--shadow-soft)}
.booking-card-inner{display:grid;grid-template-columns:320px 1fr}
.booking-image-wrap{position:relative;background:#dbeafe}
.booking-image-wrap img{width:100%;height:100%;min-height:100%;object-fit:cover;display:block}
.booking-image-fallback{height:100%;min-height:280px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#dff4ff,#ecfeff);color:var(--theme-dark);font-size:54px}
.booking-status-badge{position:absolute;top:16px;left:16px;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:800;color:#fff;background:var(--success)}
.booking-status-badge.pending{background:var(--warning)}
.booking-status-badge.cancelled{background:var(--danger)}
.booking-status-badge.completed{background:#6366f1}
.booking-content{padding:24px}
.booking-top{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.booking-title-wrap h4{margin:0 0 8px;font-size:28px;font-weight:800}
.booking-city{display:inline-flex;align-items:center;gap:8px;color:var(--theme-dark);font-weight:700;font-size:15px;background:var(--theme-soft);padding:8px 12px;border-radius:999px}
.booking-price-box{text-align:right}
.booking-price-box .total{display:block;font-size:30px;font-weight:800;line-height:1.1}
.booking-price-box .per-night{color:var(--text-muted);font-size:14px}
.booking-meta-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:14px;margin-bottom:18px}
.meta-card{background:#f8fbfd;border:1px solid rgba(15,23,42,.06);border-radius:18px;padding:14px}
.meta-card .label{display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:#7c8aa0;margin-bottom:8px}
.meta-card .value{font-size:16px;font-weight:700;line-height:1.6}
.booking-address{margin:0 0 14px;color:var(--text-muted);line-height:1.8;font-size:15px}
.booking-extra-row,.booking-actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.extra-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#f8fbfd;border:1px solid rgba(15,23,42,.08);color:#334155;font-size:14px;font-weight:700}
.card-btn{border:none;border-radius:999px;padding:12px 18px;font-size:14px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:10px;text-decoration:none}
.card-btn.primary{background:var(--theme-color);color:#fff}
.card-btn.light{background:#fff;color:var(--text-dark);border:1px solid rgba(15,23,42,.10)}
.card-btn.warning{background:#fffbeb;color:#92400e;border:1px solid rgba(245,158,11,.25)}
.card-btn.success{background:#ecfdf5;color:#166534;border:1px solid rgba(22,163,74,.18)}
.card-btn.danger{background:#fff1f2;color:#b91c1c;border:1px solid rgba(185,28,28,.14)}
.empty-box,.loading-box{background:#fff;border-radius:24px;padding:56px 22px;text-align:center;box-shadow:var(--shadow-soft);border:1px dashed rgba(15,23,42,.16)}
.empty-box>i,.loading-box>i{font-size:42px;color:var(--theme-color);margin-bottom:14px;display:block}
.empty-box h4,.loading-box h4{margin:0 0 10px;font-size:28px;font-weight:800}
.empty-box p,.loading-box p{max-width:680px;margin:0 auto 22px;color:var(--text-muted);line-height:1.8}
@media(max-width:1100px){
    .booking-card-inner{grid-template-columns:1fr}
    .booking-image-wrap{height:260px}
    .booking-meta-grid{grid-template-columns:repeat(2,minmax(140px,1fr))}
    .booking-price-box{text-align:left}
}
@media(max-width:768px){
    .manage-hero{padding:130px 16px 70px;min-height:520px}
    .manage-hero h1{font-size:38px}
    .manage-main{padding:24px 14px 50px}
    .booking-meta-grid{grid-template-columns:1fr}
}
</style>
</head>

<body>

<?php include '../includes/user_top_navbar.php'; ?>

<section class="manage-hero">
    <div class="manage-hero-slider">
        <div class="manage-hero-slide active"><img src="/travelix/images/hotel1.jpg" alt="Hotel 1"></div>
        <div class="manage-hero-slide"><img src="/travelix/images/hotel2.jpg" alt="Hotel 2"></div>
        <div class="manage-hero-slide"><img src="/travelix/images/hotel3.jpg" alt="Hotel 3"></div>
    </div>

    <div class="manage-hero-inner">
        <span class="manage-badge"><i class="fa-solid fa-hotel"></i> Travelix Hotel Bookings</span>
        <h1>Manage your booked hotels</h1>
        <p>View, edit, cancel, and add feedback for your hotel reservations.</p>
    </div>

    <div class="manage-hero-nav">
        <button type="button" class="manage-hero-dot active" data-slide="0"></button>
        <button type="button" class="manage-hero-dot" data-slide="1"></button>
        <button type="button" class="manage-hero-dot" data-slide="2"></button>
    </div>
</section>

<section class="manage-main">
    <div class="manage-container">
        <div class="manage-topbar">
            <div class="manage-topbar-left">
                <h3>Your Bookings</h3>
                <p>Completed bookings can receive feedback. Future bookings can edit dates.</p>
            </div>

            <div class="manage-actions">
                <a href="/travelix/hotel/hotels.php" class="action-link secondary js-nav-loading">
                    <i class="fa-solid fa-magnifying-glass"></i> Search Hotels
                </a>
                <a href="/travelix/trips/create_trip.php" class="action-link js-nav-loading">
                    <i class="fa-solid fa-route"></i> Create Trip
                </a>
            </div>
        </div>

        <div class="manage-tabs">
            <button type="button" class="manage-tab active" id="tabAllBookings" data-tab="all">
                <i class="fa-solid fa-hotel"></i> All Bookings
            </button>
            <button type="button" class="manage-tab" id="tabRefunds" data-tab="refunds">
                <i class="fa-solid fa-rotate-left"></i> Refunds
                <span class="manage-tab-badge" id="refundsTabBadge" style="display:none;">0</span>
            </button>
        </div>

        <div id="bookingsMount">
            <div class="loading-box">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <h4>Loading your bookings...</h4>
                <p>Please wait while we fetch your saved hotel reservations.</p>
            </div>
        </div>

        <div id="refundsMount" style="display:none;"></div>
    </div>
</section>

<?php include '../includes/user_bottom_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const slides = Array.from(document.querySelectorAll('.manage-hero-slide'));
    const dots = Array.from(document.querySelectorAll('.manage-hero-dot'));

    if (slides.length && dots.length) {
        let currentIndex = 0;
        let sliderInterval = null;

        function showSlide(index) {
            slides.forEach((slide, i) => slide.classList.toggle('active', i === index));
            dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
            currentIndex = index;
        }

        function startSlider() {
            sliderInterval = setInterval(() => {
                showSlide((currentIndex + 1) % slides.length);
            }, 4000);
        }

        dots.forEach(dot => {
            dot.addEventListener('click', function () {
                clearInterval(sliderInterval);
                showSlide(Number(this.dataset.slide || 0));
                startSlider();
            });
        });

        startSlider();
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('.js-nav-loading');
        if (!link) return;

        event.preventDefault();

        Swal.fire({
            title: 'Loading...',
            text: 'Opening page...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => {
            window.location.href = link.getAttribute('href');
        }, 700);
    });
});
</script>

<script type="module">
import { firebaseConfig } from "../config/firebase-config.js";

import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";

import {
    getAuth,
    onAuthStateChanged
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";

import {
    getFirestore,
    collection,
    query,
    where,
    getDoc,
    getDocs,
    updateDoc,
    addDoc,
    deleteDoc,
    doc,
    serverTimestamp
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
let phpSessionUid = <?= json_encode((string)($_SESSION['user']['uid'] ?? '')) ?>;
const bookingsMount = document.getElementById('bookingsMount');
const refundsMount = document.getElementById('refundsMount');

function waitForFirebaseAuth() {
    return new Promise((resolve) => {
        onAuthStateChanged(auth, (user) => resolve(user || null));
    });
}

async function resyncPhpUserSession(authUser) {
    if (phpSessionUid === authUser.uid) return;
    const userSnap = await getDoc(doc(db, 'users', authUser.uid));
    const profile = userSnap.exists() ? userSnap.data() : {};
    const formData = new FormData();
    formData.append('action', 'set_login_session');
    formData.append('uid', authUser.uid || '');
    formData.append('first_name', profile.firstName || 'User');
    formData.append('last_name', profile.lastName || '');
    formData.append('email', profile.email || authUser.email || '');
    formData.append('profile_image', profile.profileImage || '/travelix/images/default_profile.png');
    formData.append('role', profile.role || 'user');

    const response = await fetch('/travelix/auth/login.php', {
        method: 'POST', body: formData, credentials: 'same-origin'
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error('Could not synchronize your login session. Please log in again.');
    phpSessionUid = authUser.uid;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text || '');
    return div.innerHTML;
}

function formatMoney(value) {
    return `PKR ${Number(value || 0).toLocaleString()}`;
}

function todayDateOnly() {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function parseDateOnly(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function isBookingCompleted(booking) {
    const checkout = parseDateOnly(booking.departureDate);
    if (!checkout) return false;
    return checkout < todayDateOnly();
}


function getBookingStatusInfo(booking) {
    const value = String(booking.bookingStatus || 'confirmed').toLowerCase();

    if (value === 'cancelled') return { text: 'Cancelled', className: 'cancelled' };
    if (value === 'payment_rejected' || value === 'rejected') return { text: 'Payment Rejected', className: 'cancelled' };
    if (isBookingCompleted(booking)) return { text: 'Completed', className: 'completed' };
    if (value === 'pending') return { text: 'Payment Verification Pending', className: 'pending' };
    if (value === 'payment_verified') return { text: 'Hotel Payment Pending', className: 'pending' };
    if (value === 'pending_hotel_confirmation') return { text: 'Awaiting Hotel Confirmation', className: 'pending' };

    return { text: 'Confirmed', className: '' };
}

function getRefundStatusHtml(booking) {
    const refundAmount = Number(booking.refundAmount || 0);
    const refundStatus = String(booking.refundStatus || '').toLowerCase();

    if (refundAmount <= 0 || refundStatus === 'not_applicable') {
        return `<p class="booking-address"><i class="fa-solid fa-circle-info"></i> No refund applies to this cancelled booking.</p>`;
    }

    if (refundStatus === 'confirmed') {
        return `<p class="booking-address"><i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> Refund of ${formatMoney(refundAmount)} confirmed received — settled.</p>`;
    }

    if (refundStatus === 'sent') {
        return `<p class="booking-address"><i class="fa-solid fa-hourglass-half" style="color:#1e40af;"></i> Hotel sent ${formatMoney(refundAmount)} — <a href="/travelix/hotel/manage_bookings.php" style="color:#1484B4;font-weight:700;text-decoration:underline;">confirm you received it</a>.</p>`;
    }

    if (refundStatus === 'disputed') {
        return `<p class="booking-address"><i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> You reported not receiving ${formatMoney(refundAmount)} — the hotel has 24 hours to resolve it.</p>`;
    }

    return `<p class="booking-address"><i class="fa-solid fa-clock" style="color:#c2410c;"></i> Refund of ${formatMoney(refundAmount)} is pending — we'll send it to your payout account soon.</p>`;
}

function getGuestsText(booking) {
    const adults = Number(booking.adults || 0);
    const children = Number(booking.children || 0);
    const totalGuests = Number(booking.totalGuests || (adults + children) || 0);

    if (children > 0) return `${totalGuests} Guests (${adults} Adults, ${children} Children)`;
    return `${totalGuests || adults || 1} Guests`;
}

function getPaymentText(payment) {
    if (!payment || typeof payment !== 'object') return 'Not available';
    const status = String(payment.status || '').toLowerCase();
    if (status === 'verified') return 'Payment Verified';
    if (status === 'rejected') return 'Payment Rejected';
    if (status === 'awaiting_verification') return 'Awaiting Verification';
    return 'Not available';
}

function showActionLoading(message = 'Please wait...') {
    Swal.fire({
        title: 'Loading...',
        text: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });
}

function buildSearchAgainUrl(booking) {
    const params = new URLSearchParams({
        toCity: booking.toCity || '',
        arrivalDate: booking.arrivalDate || '',
        departureDate: booking.departureDate || '',
        travelers: booking.totalGuests || booking.adults || 1,
        rooms: booking.rooms || 1,
        adults: booking.adults || 1,
        children: booking.children || 0,
        source: booking.source || '',
        returnStep: booking.returnStep || '3',
        returnUrl: '/travelix/trips/create_trip.php'
    });

    return `/travelix/hotel/hotels.php?${params.toString()}`;
}

function buildCreateTripUrl(booking) {
    const params = new URLSearchParams({ returnStep: booking.returnStep || '3' });
    return `/travelix/trips/create_trip.php?${params.toString()}`;
}

function renderEmptyState() {
    bookingsMount.innerHTML = `
        <div class="empty-box">
            <i class="fa-solid fa-hotel"></i>
            <h4>No hotel bookings yet</h4>
            <p>You have not saved any hotel bookings yet.</p>
            <a href="/travelix/hotel/hotels.php" class="card-btn primary js-nav-loading-dynamic">
                <i class="fa-solid fa-magnifying-glass"></i>
                Find Hotels
            </a>
        </div>
    `;
    bindDynamicLoadingLinks();
}

function renderAuthError() {
    bookingsMount.innerHTML = `
        <div class="empty-box">
            <i class="fa-solid fa-circle-exclamation"></i>
            <h4>Firebase login not found</h4>
            <p>Please log out and log in again so Firebase Auth can verify your account.</p>
            <a href="/travelix/auth/login.php" class="card-btn primary js-nav-loading-dynamic">
                Go to Login
            </a>
        </div>
    `;
    bindDynamicLoadingLinks();
}

function renderBookings(bookings) {
    if (!Array.isArray(bookings) || !bookings.length) {
        renderEmptyState();
        return;
    }

    bookingsMount.innerHTML = `
        <div class="bookings-grid">
            ${bookings.map((booking) => {
                const status = getBookingStatusInfo(booking);
                const hasImage = !!String(booking.hotelImage || '').trim();
                const showBackToTrip = String(booking.source || '') === 'create_trip' && String(booking.returnStep || '') === '3';
                const completed = isBookingCompleted(booking);
                const cancelled = String(booking.bookingStatus || '').toLowerCase() === 'cancelled';
                const confirmedBooking = String(booking.bookingStatus || '').toLowerCase() === 'confirmed';
                const refundStatus = String(booking.refundStatus || '').toLowerCase();
                const canDeleteCancelled = cancelled && (
                    Number(booking.refundAmount || 0) <= 0 ||
                    refundStatus === 'not_applicable' ||
                    refundStatus === 'confirmed'
                );

                return `
                    <article class="booking-card" data-id="${escapeHtml(booking.id)}">
                        <div class="booking-card-inner">
                            <div class="booking-image-wrap">
                                ${hasImage
                                    ? `<img src="${escapeHtml(booking.hotelImage)}" alt="${escapeHtml(booking.hotelName || 'Hotel')}">`
                                    : `<div class="booking-image-fallback"><i class="fa-solid fa-hotel"></i></div>`
                                }

                                <span class="booking-status-badge ${status.className}">
                                    ${escapeHtml(status.text)}
                                </span>
                            </div>

                            <div class="booking-content">
                                <div class="booking-top">
                                    <div class="booking-title-wrap">
                                        <h4>${escapeHtml(booking.hotelName || 'Hotel')}</h4>
                                        <span class="booking-city">
                                            <i class="fa-solid fa-location-dot"></i>
                                            ${escapeHtml(booking.toCity || 'Unknown City')}
                                        </span>
                                    </div>

                                    <div class="booking-price-box">
                                        <span class="total">${formatMoney(booking.totalCharged || booking.hotelPrice || 0)}</span>
                                        <span class="per-night">
                                            ${booking.hotelPriceNight ? `${formatMoney(booking.hotelPriceNight)} / night` : 'Total paid (incl. platform fee)'}
                                        </span>
                                    </div>
                                </div>

                                <div class="booking-meta-grid">
                                    <div class="meta-card">
                                        <span class="label">Check-in</span>
                                        <span class="value">${escapeHtml(booking.arrivalDate || '-')}</span>
                                    </div>

                                    <div class="meta-card">
                                        <span class="label">Check-out</span>
                                        <span class="value">${escapeHtml(booking.departureDate || '-')}</span>
                                    </div>

                                    <div class="meta-card">
                                        <span class="label">Rooms</span>
                                        <span class="value">${escapeHtml(booking.rooms || 1)}</span>
                                    </div>

                                    <div class="meta-card">
                                        <span class="label">Guests</span>
                                        <span class="value">${escapeHtml(getGuestsText(booking))}</span>
                                    </div>
                                </div>

                                <p class="booking-address">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                    ${escapeHtml(booking.hotelAddress || 'Address not available')}
                                </p>

                                <div class="booking-extra-row">
                                    <span class="extra-pill"><i class="fa-solid fa-star"></i>${escapeHtml(booking.hotelRating || '0')} Rating</span>
                                    <span class="extra-pill"><i class="fa-solid fa-comments"></i>${escapeHtml(booking.hotelReviews || '0')} Reviews</span>
                                    <span class="extra-pill"><i class="fa-solid fa-bed"></i>${escapeHtml(booking.nights || 1)} Night(s)</span>
                                    <span class="extra-pill"><i class="fa-solid fa-credit-card"></i>${escapeHtml(getPaymentText(booking.payment))}</span>
                                </div>

                                ${cancelled ? getRefundStatusHtml(booking) : ''}

                                <div class="booking-actions">
                                    <a href="${buildSearchAgainUrl(booking)}" class="card-btn primary js-nav-loading-dynamic">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        Search Again
                                    </a>

                                    ${showBackToTrip ? `
                                        <a href="${buildCreateTripUrl(booking)}" class="card-btn light js-nav-loading-dynamic">
                                            <i class="fa-solid fa-arrow-left"></i>
                                            Back to Create Trip
                                        </a>
                                    ` : ''}

                                    ${completed && !cancelled && !booking.feedbackGiven ? `
                                        <button type="button" class="card-btn success add-feedback-btn" data-id="${escapeHtml(booking.id)}" data-hotel-id="${escapeHtml(booking.hotelId || '')}">
                                            <i class="fa-solid fa-comment-dots"></i>
                                            Rate Your Stay
                                        </button>
                                    ` : ''}

                                    ${completed && !cancelled && booking.feedbackGiven ? `
                                        <span class="card-btn light" style="cursor:default;">
                                            <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i>
                                            Feedback Submitted
                                        </span>
                                    ` : ''}

                                    ${!cancelled && confirmedBooking ? `
                                        <button type="button" class="card-btn danger cancel-booking-btn" data-id="${escapeHtml(booking.id)}" data-hotel-id="${escapeHtml(booking.hotelId || '')}" data-hotel-name="${escapeHtml(booking.hotelName || 'the hotel')}">
                                            <i class="fa-solid fa-ban"></i>
                                            Cancel Booking
                                        </button>
                                    ` : ''}

                                    ${!cancelled && !confirmedBooking ? `
                                        <span class="card-btn light" style="cursor:default;">
                                            <i class="fa-solid fa-hourglass-half" style="color:#b45309;"></i>
                                            Cancellation is available after the hotel receives payment and confirms the booking
                                        </span>
                                    ` : ''}

                                    ${canDeleteCancelled ? `
                                        <button type="button" class="card-btn danger delete-booking-btn" data-id="${escapeHtml(booking.id)}">
                                            <i class="fa-solid fa-trash"></i>
                                            Delete Booking
                                        </button>
                                    ` : ''}
                                    ${cancelled && !canDeleteCancelled ? `
                                        <span class="card-btn light" style="cursor:default;">
                                            <i class="fa-solid fa-lock" style="color:#b45309;"></i>
                                            Booking can be deleted after refund confirmation
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </article>
                `;
            }).join('')}
        </div>
    `;

    bindDynamicLoadingLinks();
    bindDeleteButtons();
    bindCancelButtons();
    bindFeedbackButtons();
}

function getRefundStatusPill(booking) {
    const status = String(booking.refundStatus || '').toLowerCase();
    if (status === 'confirmed') {
        return `<span class="refund-status-pill sent"><i class="fa-solid fa-circle-check"></i> Confirmed — Settled</span>`;
    }
    if (status === 'sent') {
        return `<span class="refund-status-pill awaiting"><i class="fa-solid fa-hourglass-half"></i> Sent — Confirm Below</span>`;
    }
    if (status === 'disputed') {
        return `<span class="refund-status-pill disputed"><i class="fa-solid fa-triangle-exclamation"></i> Disputed — Waiting on Hotel</span>`;
    }
    if (status === 'escalated') {
        return `<span class="refund-status-pill pending"><i class="fa-solid fa-shield-halved"></i> Travelix Is Sending This</span>`;
    }
    return `<span class="refund-status-pill pending"><i class="fa-solid fa-clock"></i> Pending — Not Sent Yet</span>`;
}

function renderRefunds(bookings) {
    const refundBookings = bookings.filter((b) => Number(b.refundAmount || 0) > 0);

    const badge = document.getElementById('refundsTabBadge');
    const needsAttentionCount = refundBookings.filter((b) => String(b.refundStatus || '').toLowerCase() === 'sent').length;
    if (badge) {
        badge.textContent = needsAttentionCount;
        badge.style.display = needsAttentionCount > 0 ? 'inline-flex' : 'none';
    }

    if (!refundBookings.length) {
        refundsMount.innerHTML = `
            <div class="empty-box">
                <i class="fa-solid fa-rotate-left"></i>
                <h4>No refunds</h4>
                <p>Refunds from cancelled bookings will show up here, whether they're still pending, sent, or confirmed.</p>
            </div>
        `;
        return;
    }

    refundsMount.innerHTML = refundBookings.map((booking) => {
        const proofUrl = booking.refundProofUrl || '';
        const status = String(booking.refundStatus || '').toLowerCase();
        const isAwaitingConfirmation = status === 'sent';
        return `
            <div class="refund-card">
                <div class="refund-card-info">
                    <h5>${escapeHtml(booking.hotelName || 'Hotel')}</h5>
                    <div class="refund-card-sub">
                        <i class="fa-solid fa-percent"></i> ${escapeHtml(booking.refundPercent || 0)}% of your total payment
                    </div>
                    <div class="refund-card-sub">
                        <i class="fa-solid fa-calendar-xmark"></i> Cancelled booking · ${escapeHtml(booking.arrivalDate || '-')} to ${escapeHtml(booking.departureDate || '-')}
                    </div>
                    ${getRefundStatusPill(booking)}
                    ${proofUrl ? `<a href="${escapeHtml(proofUrl)}" target="_blank" class="refund-proof-link">View Transfer Proof</a>` : ''}
                    ${isAwaitingConfirmation ? `
                        <div class="refund-confirm-row">
                            <button type="button" class="card-btn success refund-confirm-btn" data-id="${escapeHtml(booking.id)}">
                                <i class="fa-solid fa-check"></i> I Received It
                            </button>
                            <button type="button" class="card-btn danger refund-dispute-btn" data-id="${escapeHtml(booking.id)}">
                                <i class="fa-solid fa-triangle-exclamation"></i> I Didn't Receive This
                            </button>
                        </div>
                    ` : ''}
                </div>
                <div class="refund-card-amount">${formatMoney(booking.refundAmount)}</div>
            </div>
        `;
    }).join('');

    bindRefundActionButtons();
}

function bindRefundActionButtons() {
    document.querySelectorAll('.refund-confirm-btn').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const bookingId = this.dataset.id;
            const result = await Swal.fire({
                icon: 'question',
                title: 'Confirm you received this refund?',
                text: 'This confirms the money actually landed in your account.',
                showCancelButton: true,
                confirmButtonText: 'Yes, I Received It',
                confirmButtonColor: '#16a34a'
            });
            if (!result.isConfirmed) return;

            try {
                showActionLoading('Confirming refund...');
                const resp = await fetch('/travelix/hotel/ajax/confirm_refund.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ bookingId })
                });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message || 'Could not confirm the refund.');
                await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#1484B4' });
                loadBookings();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Failed', text: error.message, confirmButtonColor: '#1484B4' });
            }
        });
    });

    document.querySelectorAll('.refund-dispute-btn').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const bookingId = this.dataset.id;
            const result = await Swal.fire({
                icon: 'warning',
                title: "Didn't receive this refund?",
                text: 'The hotel will get 24 hours to resolve this. If they miss it, Travelix will send your refund directly.',
                showCancelButton: true,
                confirmButtonText: 'Yes, Report It',
                confirmButtonColor: '#dc2626'
            });
            if (!result.isConfirmed) return;

            try {
                showActionLoading('Reporting dispute...');
                const resp = await fetch('/travelix/hotel/ajax/dispute_refund.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ bookingId })
                });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message || 'Could not report the dispute.');
                await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#1484B4' });
                loadBookings();
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Failed', text: error.message, confirmButtonColor: '#1484B4' });
            }
        });
    });
}

function switchTab(tab) {
    const isRefunds = tab === 'refunds';
    document.getElementById('tabAllBookings').classList.toggle('active', !isRefunds);
    document.getElementById('tabRefunds').classList.toggle('active', isRefunds);
    bookingsMount.style.display = isRefunds ? 'none' : '';
    refundsMount.style.display = isRefunds ? '' : 'none';
}

document.getElementById('tabAllBookings')?.addEventListener('click', () => switchTab('all'));
document.getElementById('tabRefunds')?.addEventListener('click', () => switchTab('refunds'));
if (new URLSearchParams(window.location.search).get('tab') === 'refunds') switchTab('refunds');

function bindDynamicLoadingLinks() {
    document.querySelectorAll('.js-nav-loading-dynamic').forEach((link) => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            showActionLoading('Opening page...');
            window.location.href = this.getAttribute('href');
        });
    });
}

async function loadBookings() {
    try {
        const authUser = await waitForFirebaseAuth();

        if (!authUser) {
            renderAuthError();
            return;
        }

        // Keep server-side ownership checks aligned with the Firebase user
        // that this page actually used to load the booking list.
        await resyncPhpUserSession(authUser);

        const response = await fetch('/travelix/hotel/ajax/get_user_bookings.php', { credentials: 'same-origin' });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Could not load your bookings.');
        const bookings = Array.isArray(payload.bookings) ? payload.bookings : [];

        bookings.sort((a, b) => {
            const aTime = a.createdAt?.seconds || 0;
            const bTime = b.createdAt?.seconds || 0;
            return bTime - aTime;
        });

        renderBookings(bookings);
        renderRefunds(bookings);
        openFeedbackFromUrlOnce();
    } catch (error) {
        console.error('Error loading hotel bookings:', error);
        bookingsMount.innerHTML = `
            <div class="empty-box">
                <i class="fa-solid fa-circle-exclamation"></i>
                <h4>Could not load your bookings</h4>
                <p>${escapeHtml(error?.message || 'We could not fetch your hotel bookings right now.')}</p>
            </div>
        `;
    }
}

let feedbackAutoOpenDone = false;

// Arriving from the "How was your stay?" notification (?feedback=bookingId)
// should drop the guest straight into the feedback form for that exact
// booking, not just onto this page. Only fires once per page load so it
// doesn't reopen if the list re-renders later (e.g. after cancelling
// something else).
function openFeedbackFromUrlOnce() {
    if (feedbackAutoOpenDone) return;

    const bookingId = new URLSearchParams(window.location.search).get('feedback');
    if (!bookingId) return;

    feedbackAutoOpenDone = true;

    const button = document.querySelector(`.add-feedback-btn[data-id="${CSS.escape(bookingId)}"]`);
    if (button) {
        button.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => button.click(), 300);
    }
}

// trips.bookedHotel is a one-time snapshot taken when the guest saved their
// trip — it never updated itself when the underlying hotel_bookings status
// changed, so a cancelled booking still looked "booked" forever on the
// guest's Saved Trips page. Push the current status/refund info onto every
// trip linked via hotelBookingId whenever this booking's status changes.
async function syncLinkedTripBookingStatus(bookingId, fields) {
    try {
        const q = query(collection(db, 'trips'), where('hotelBookingId', '==', bookingId));
        const snap = await getDocs(q);
        const updates = [];
        snap.forEach((tripDoc) => {
            const patch = {};
            Object.keys(fields).forEach((key) => { patch['bookedHotel.' + key] = fields[key]; });
            updates.push(updateDoc(doc(db, 'trips', tripDoc.id), patch));
        });
        await Promise.all(updates);
    } catch (e) {
        console.warn('Could not sync linked trip booking status:', e);
    }
}

function bindCancelButtons() {
    document.querySelectorAll('.cancel-booking-btn').forEach((button) => {
        button.addEventListener('click', async function () {
            const bookingId = this.dataset.id || '';
            const hotelId = this.dataset.hotelId || '';
            const hotelNameForNotify = this.dataset.hotelName || 'the hotel';

            let preview;
            try {
                showActionLoading('Checking cancellation policy...');
                const currentAuthUser = await waitForFirebaseAuth();
                if (!currentAuthUser) throw new Error('Please log in again.');
                await resyncPhpUserSession(currentAuthUser);
                const resp = await fetch(`/travelix/hotel/ajax/calculate_refund.php?bookingId=${encodeURIComponent(bookingId)}`, {
                    credentials: 'same-origin'
                });
                preview = await resp.json();
                Swal.close();
            } catch (error) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Could Not Check Refund',
                    text: error?.message || 'Please try again.',
                    confirmButtonColor: '#1484B4'
                });
                return;
            }

            if (!preview.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Cancel',
                    text: preview.message || 'This booking could not be cancelled.',
                    confirmButtonColor: '#1484B4'
                });
                return;
            }

            const refundAmount = Number(preview.refundAmount || 0);
            const totalPaid = Number(preview.totalPaid || 0);
            const hotelCharges = Number(preview.hotelCharges || 0);
            const platformFee = Number(preview.platformFee || 0);
            const refundHtml = refundAmount > 0
                ? `Total paid: <b>PKR ${totalPaid.toLocaleString()}</b><br>Hotel charges: <b>PKR ${hotelCharges.toLocaleString()}</b><br>Travelix fee: <b>PKR ${platformFee.toLocaleString()} (non-refundable)</b><br><br>Hotel policy refund: <b>PKR ${refundAmount.toLocaleString()}</b> (${preview.refundPercent}%) to your saved payout account.`
                : `Total paid: <b>PKR ${totalPaid.toLocaleString()}</b><br>Travelix fee: <b>PKR ${platformFee.toLocaleString()} (non-refundable)</b><br><br>Based on the hotel's cancellation policy, no hotel-charge refund applies.`;

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Cancel this booking?',
                html: refundHtml,
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'No',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            try {
                showActionLoading('Cancelling booking...');

                // Refund is owed by the HOTEL first (it holds the guest's money via
                // its 100% payout) — with a 24h-warning / 48h-escalate SLA. See
                // includes/refund_lib.php for the enforcement side.
                const nowMs = Date.now();
                await updateDoc(doc(db, 'hotel_bookings', bookingId), {
                    bookingStatus: 'cancelled',
                    cancelledAt: serverTimestamp(),
                    updatedAt: serverTimestamp(),
                    refundAmount: refundAmount,
                    refundPercent: Number(preview.refundPercent || 0),
                    refundStatus: refundAmount > 0 ? 'pending' : 'not_applicable',
                    ...(refundAmount > 0 ? {
                        refundOwner: 'hotel',
                        refundRequestedAt: nowMs,
                        refundWarnAt: nowMs + (24 * 3600 * 1000),
                        refundEscalateAt: nowMs + (48 * 3600 * 1000),
                        refundWarned: false,
                        refundWrongAttempt: false
                    } : {})
                });

                syncLinkedTripBookingStatus(bookingId, {
                    status: 'cancelled',
                    refundAmount: refundAmount,
                    refundPercent: Number(preview.refundPercent || 0),
                    refundStatus: refundAmount > 0 ? 'pending' : 'not_applicable'
                });

                const notificationTasks = [];
                try {
                    notificationTasks.push(window.travelixAddNotification?.({
                        title: "Booking Cancelled",
                        message: refundAmount > 0
                            ? `Your hotel booking has been cancelled. A refund of PKR ${refundAmount.toLocaleString()} is pending.`
                            : "Your hotel booking has been cancelled successfully.",
                        type: "booking_cancelled",
                        link: "/travelix/hotel/manage_bookings.php"
                    }));
                } catch (notificationError) {
                    console.warn("Notification not added:", notificationError);
                }

                // Let the hotel know — both that occupancy changed, and (if a
                // refund is owed) that THEY must send it within the SLA, since
                // refunds are hotel-first now, not admin-first.
                try {
                    if (hotelId) {
                        notificationTasks.push(addDoc(collection(db, 'notifications'), {
                            audience: 'hotel',
                            hotelId: hotelId,
                            title: 'Booking Cancelled by Guest',
                            message: `A guest cancelled their booking at ${hotelNameForNotify}.`,
                            type: 'booking_cancelled',
                            link: '/travelix/hotel_portal/hotel_bookings.php',
                            isRead: false,
                            createdAt: serverTimestamp()
                        }));
                    }
                    if (refundAmount > 0 && hotelId) {
                        notificationTasks.push(addDoc(collection(db, 'notifications'), {
                            audience: 'hotel',
                            hotelId: hotelId,
                            title: 'Refund Owed to Guest',
                            message: `A guest cancelled their booking. Send them PKR ${refundAmount.toLocaleString()} within 48 hours.`,
                            type: 'refund_pending',
                            icon: 'fa-solid fa-hourglass-half',
                            link: '/travelix/hotel_portal/refunds.php',
                            isRead: false,
                            createdAt: serverTimestamp()
                        }));
                    }
                } catch (notificationError) {
                    console.warn("Hotel notification not added:", notificationError);
                }
                await Promise.allSettled(notificationTasks.filter(Boolean));

                await Swal.fire({
                    icon: 'success',
                    title: 'Booking Cancelled',
                    text: refundAmount > 0
                        ? `Your booking has been cancelled. PKR ${refundAmount.toLocaleString()} will be refunded to your payout account soon.`
                        : 'Your booking has been cancelled.',
                    confirmButtonColor: '#1484B4'
                });

                loadBookings();
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cancel Failed',
                    text: error?.message || 'Could not cancel booking.',
                    confirmButtonColor: '#1484B4'
                });
            }
        });
    });
}

function bindFeedbackButtons() {
    document.querySelectorAll('.add-feedback-btn').forEach((button) => {
        button.addEventListener('click', async function () {
            const bookingId = this.dataset.id || '';
            const hotelId = this.dataset.hotelId || '';
            const authUser = await waitForFirebaseAuth();

            if (!authUser) {
                Swal.fire('Login Required', 'Please login again.', 'warning');
                return;
            }

            const result = await Swal.fire({
                title: 'Add Hotel Feedback',
                html: `
                    <div style="text-align:left;">
                        <label style="font-weight:700;">Rating</label>
                        <select id="feedbackRating" class="swal2-input">
                            <option value="">Select Rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Very Good</option>
                            <option value="3">3 - Good</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>

                        <label style="font-weight:700;">Feedback</label>
                        <textarea id="feedbackMessage" class="swal2-textarea" placeholder="Write your hotel feedback..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Feedback',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#1484B4',
                preConfirm: () => {
                    const rating = document.getElementById('feedbackRating').value;
                    const message = document.getElementById('feedbackMessage').value.trim();

                    if (!rating) {
                        Swal.showValidationMessage('Please select rating.');
                        return false;
                    }

                    if (message.length < 5) {
                        Swal.showValidationMessage('Feedback must be at least 5 characters.');
                        return false;
                    }

                    return { rating, message };
                }
            });

            if (!result.isConfirmed || !result.value) return;

            try {
                showActionLoading('Saving feedback...');

                await addDoc(collection(db, 'hotel_feedback'), {
                    uid: authUser.uid,
                    userId: authUser.uid,
                    userEmail: authUser.email || '',
                    bookingId: bookingId,
                    hotelId: hotelId,
                    rating: Number(result.value.rating),
                    message: result.value.message,
                    status: 'submitted',
                    createdAt: serverTimestamp()
                });

                await updateDoc(doc(db, 'hotel_bookings', bookingId), {
                    feedbackGiven: true,
                    feedbackAt: serverTimestamp(),
                    updatedAt: serverTimestamp()
                });

                // Recompute the hotel's displayed rating/reviews from every
                // real guest feedback submitted for it — otherwise feedback
                // is collected but never actually shown to other guests.
                if (hotelId) {
                    try {
                        const feedbackSnap = await getDocs(query(collection(db, 'hotel_feedback'), where('hotelId', '==', hotelId)));
                        let sum = 0;
                        let count = 0;
                        feedbackSnap.forEach((d) => {
                            const r = Number(d.data().rating || 0);
                            if (r > 0) { sum += r; count++; }
                        });
                        if (count > 0) {
                            await updateDoc(doc(db, 'hotels', hotelId), {
                                rating: Math.round((sum / count) * 10) / 10,
                                reviews: count
                            });
                        }
                    } catch (aggregateError) {
                        console.warn('Could not update hotel aggregate rating:', aggregateError);
                    }
                }

                try {
                    await window.travelixAddNotification?.({
                        title: "Feedback Added",
                        message: "Your hotel feedback has been submitted successfully.",
                        type: "booking_updated",
                        link: "/travelix/hotel/manage_bookings.php"
                    });
                } catch (notificationError) {
                    console.warn("Notification not added:", notificationError);
                }
                await Swal.fire({
                    icon: 'success',
                    title: 'Feedback Added',
                    text: 'Thank you for your hotel feedback.',
                    confirmButtonColor: '#1484B4'
                });

                loadBookings();
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Feedback Failed',
                    text: error?.message || 'Could not save feedback.',
                    confirmButtonColor: '#1484B4'
                });
            }
        });
    });
}

function bindDeleteButtons() {
    document.querySelectorAll('.delete-booking-btn').forEach((button) => {
        button.addEventListener('click', async function () {
            const bookingId = this.dataset.id || '';

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Delete this booking?',
                text: 'This will remove the booking from Firebase.',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            try {
                showActionLoading('Deleting booking...');

                await deleteDoc(doc(db, 'hotel_bookings', bookingId));

            try {
                await window.travelixAddNotification?.({
                    title: "Booking Deleted",
                    message: "Your hotel booking has been deleted successfully.",
                    type: "booking_deleted",
                    link: "/travelix/hotel/manage_bookings.php"
                });
            } catch (notificationError) {
                console.warn("Notification not added:", notificationError);
            }

                await Swal.fire({
                    icon: 'success',
                    title: 'Booking Deleted',
                    text: 'The hotel booking has been removed successfully.',
                    confirmButtonColor: '#1484B4'
                });

                loadBookings();
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed',
                    text: error?.message || 'Could not delete booking.',
                    confirmButtonColor: '#1484B4'
                });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', loadBookings);
</script>

</body>
</html>
