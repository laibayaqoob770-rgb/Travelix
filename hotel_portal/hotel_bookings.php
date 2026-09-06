<?php
/* ================================================================
   Hotel Staff — Bookings View
   Shows all bookings for the staff member's assigned hotel
================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

require_once __DIR__ . '/includes/resolve_hotel.php';
ensure_staff_hotel_linked();

$staff     = $_SESSION['hotel_staff'];
$staffName = $staff['first_name'] ?? explode(' ', $staff['name'] ?? 'Staff')[0];
$hotelName = $staff['hotel_name'] ?? 'Your Hotel';

// hotel_id: prefer URL param (when coming from portal with ?hotel_id=...), fallback to session
$hotelId = trim($_GET['hotel_id'] ?? $staff['hotel_id'] ?? '');

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Bookings — <?= htmlspecialchars($hotelName) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/css/travelix_notifications.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4fa;min-height:100vh;}

/* Topbar */
.topbar{background:linear-gradient(135deg,#0f3460,#1a5276,#2471a3);color:#fff;padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 20px rgba(15,52,96,.3);position:sticky;top:0;z-index:100;}
.topbar-left{display:flex;align-items:center;gap:14px;}
.back-btn{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.14);color:#fff;border:none;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s;}
.back-btn:hover{background:rgba(255,255,255,.24);color:#fff;}
.topbar-title{font-size:17px;font-weight:800;}
.topbar-sub{font-size:11px;opacity:.72;margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.staff-pill{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.12);border-radius:12px;padding:7px 14px;}
.staff-avatar{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;}
.staff-info{line-height:1.3;}
.staff-name{font-size:13px;font-weight:700;}
.staff-role{font-size:10px;opacity:.7;}

/* Page */
.page-body{max-width:1200px;margin:0 auto;padding:28px 16px;}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
@media(max-width:768px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr 1fr;}}
.stat-card{background:#fff;border-radius:18px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;}
.stat-icon{width:48px;height:48px;min-width:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.stat-icon.blue{background:#eff6ff;color:#2563eb;}
.stat-icon.green{background:#f0fdf4;color:#16a34a;}
.stat-icon.orange{background:#fff7ed;color:#ea580c;}
.stat-icon.purple{background:#faf5ff;color:#7c3aed;}
.stat-val{font-size:26px;font-weight:900;color:#0f172a;line-height:1;}
.stat-label{font-size:12px;color:#64748b;margin-top:3px;font-weight:500;}

/* Bookings card */
.bookings-card{background:#fff;border-radius:22px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;}
.bookings-header{padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.bookings-header h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}

/* Filter tabs */
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;}
.filter-tab{padding:7px 18px;border-radius:999px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;}
.filter-tab:hover{border-color:#0f3460;color:#0f3460;}
.filter-tab.active{background:#0f3460;border-color:#0f3460;color:#fff;}

/* Table */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;}
th{padding:12px 16px;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;white-space:nowrap;border-bottom:1px solid #f1f5f9;}
td{padding:14px 16px;font-size:14px;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tbody tr:hover{background:#fafbff;}
tbody tr:last-child td{border-bottom:none;}

.guest-cell{display:flex;flex-direction:column;}
.guest-email{font-weight:700;color:#0f172a;font-size:13px;}
.guest-meta{font-size:11.5px;color:#94a3b8;margin-top:2px;}

.date-cell{white-space:nowrap;}
.date-main{font-weight:600;color:#0f172a;}
.date-sub{font-size:11.5px;color:#94a3b8;margin-top:1px;}

.rooms-pill{display:inline-flex;align-items:center;gap:5px;background:#eff6ff;color:#2563eb;padding:4px 12px;border-radius:999px;font-size:12.5px;font-weight:700;}
.guests-pill{display:inline-flex;align-items:center;gap:5px;background:#f0fdf4;color:#16a34a;padding:4px 12px;border-radius:999px;font-size:12.5px;font-weight:700;}

.amount-cell{font-weight:800;color:#0f172a;}

.status-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize;}
.status-confirmed{background:#dcfce7;color:#166534;}
.status-pending{background:#fef9c3;color:#854d0e;}
.status-cancelled{background:#fee2e2;color:#991b1b;}

/* Payment review actions */
.payment-actions{display:flex;flex-direction:column;gap:6px;align-items:flex-start;}
.pay-action-btn{border:none;border-radius:9px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s;}
.pay-action-btn.view{background:#eff6ff;color:#2563eb;}
.pay-action-btn.confirm{background:#dcfce7;color:#166534;}
.pay-action-btn.reject{background:#fee2e2;color:#991b1b;}
.pay-action-btn:hover{opacity:.8;}
.payment-verified{font-size:12px;color:#94a3b8;}

/* Empty state */
.empty-state{padding:60px 20px;text-align:center;color:#94a3b8;}
.empty-icon{font-size:48px;margin-bottom:14px;opacity:.5;}
.empty-title{font-size:17px;font-weight:700;color:#64748b;margin-bottom:6px;}
.empty-desc{font-size:14px;}

/* Loading state */
.loading-row td{text-align:center;padding:40px;color:#94a3b8;font-size:14px;}

/* Portal Sub-Nav */
.portal-subnav{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;display:flex;gap:4px;overflow-x:auto;position:sticky;top:64px;z-index:99;}
.portal-subnav a{display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13.5px;font-weight:700;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:.2s;}
.portal-subnav a:hover{color:#0f3460;background:#f8fafc;}
.portal-subnav a.active{color:#0f3460;border-bottom-color:#0f3460;}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-left">
        <a href="/travelix/hotel_portal/index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <div>
            <div class="topbar-title">📋 Bookings</div>
            <div class="topbar-sub"><?= htmlspecialchars($hotelName) ?></div>
        </div>
    </div>
    <div class="topbar-right">
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

        <div class="staff-pill">
            <div class="staff-avatar"><?= strtoupper(substr($staffName, 0, 1)) ?></div>
            <div class="staff-info">
                <div class="staff-name"><?= htmlspecialchars($staffName) ?></div>
                <div class="staff-role">Hotel Staff</div>
            </div>
        </div>
    </div>
</div>

<div class="portal-subnav">
    <a href="/travelix/hotel_portal/index.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="/travelix/hotel_portal/hotel_bookings.php<?= $hotelId ? '?hotel_id=' . urlencode($hotelId) : '' ?>" class="active"><i class="fas fa-calendar-alt"></i> Bookings</a>
    <a href="/travelix/hotel_portal/commission.php"><i class="fas fa-money-bill-wave"></i> Payouts</a>
    <a href="/travelix/hotel_portal/refunds.php"><i class="fas fa-rotate-left"></i> Refunds</a>
    <a href="/travelix/hotel_portal/discounts.php"><i class="fas fa-tag"></i> Discounts</a>
    <a href="/travelix/hotel_portal/edit_hotel.php<?= $hotelId ? '?edit=' . urlencode($hotelId) : '' ?>"><i class="fas fa-pen"></i> Edit Hotel</a>
</div>

<!-- Page Body -->
<div class="page-body">

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="stat-val" id="statTotal">—</div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-val" id="statConfirmed">—</div>
                <div class="stat-label">Confirmed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-bed"></i></div>
            <div>
                <div class="stat-val" id="statRooms">—</div>
                <div class="stat-label">Rooms Booked</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="stat-val" id="statRevenue">—</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="bookings-card">
        <div class="bookings-header">
            <h2>All Reservations</h2>
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">All</button>
                <button class="filter-tab" data-filter="confirmed">Confirmed</button>
                <button class="filter-tab" data-filter="pending">Pending</button>
                <button class="filter-tab" data-filter="cancelled">Cancelled</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Rooms</th>
                        <th>Guests</th>
                        <th>Nights</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody id="bookingsBody">
                    <tr class="loading-row">
                        <td colspan="9"><i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Loading bookings...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
<script src="/travelix/assets/js/travelix_portal_notifications.js"></script>
<script>
const firebaseConfig = {
    apiKey:            "<?= FIREBASE_API_KEY ?>",
    authDomain:        "<?= FIREBASE_PROJECT_ID ?>.firebaseapp.com",
    projectId:         "<?= FIREBASE_PROJECT_ID ?>",
    storageBucket:     "<?= FIREBASE_PROJECT_ID ?>.firebasestorage.app",
    messagingSenderId: "<?= FIREBASE_MESSAGING_SENDER_ID ?>",
    appId:             "<?= FIREBASE_APP_ID ?>"
};
if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
const db = firebase.firestore();

const HOTEL_ID = <?= json_encode($hotelId) ?>;

if (HOTEL_ID) {
    window.travelixInitPortalNotifications({
        db: db,
        filters: [['audience', '==', 'hotel'], ['hotelId', '==', HOTEL_ID]],
        manageUrl: '/travelix/hotel_portal/notifications.php'
    });
}

let allBookings = [];
let activeFilter = 'all';

// trips.bookedHotel is a one-time snapshot taken when the guest saved their
// trip — it never updated itself when the underlying hotel_bookings status
// changed, so a cancelled/rejected booking still looked "booked" forever on
// the guest's Saved Trips page. Push the current status/refund info onto
// every trip linked via hotelBookingId whenever this booking's status changes.
async function syncLinkedTripBookingStatus(bookingId, fields) {
    try {
        const snap = await db.collection('trips').where('hotelBookingId', '==', bookingId).get();
        const updates = [];
        snap.forEach((tripDoc) => {
            const patch = {};
            Object.keys(fields).forEach((key) => { patch['bookedHotel.' + key] = fields[key]; });
            updates.push(tripDoc.ref.update(patch));
        });
        await Promise.all(updates);
    } catch (e) {
        console.warn('Could not sync linked trip booking status:', e);
    }
}

function fmt(val) { return val ? String(val) : '—'; }

function fmtDate(val) {
    if (!val) return '—';
    try {
        const d = typeof val === 'object' && val.seconds ? new Date(val.seconds * 1000) : new Date(val);
        if (isNaN(d)) return fmt(val);
        return d.toLocaleDateString('en-PK', { day:'2-digit', month:'short', year:'numeric' });
    } catch { return fmt(val); }
}

function statusClass(s) {
    s = (s || '').toLowerCase();
    if (s === 'confirmed') return 'status-confirmed';
    if (s === 'cancelled') return 'status-cancelled';
    return 'status-pending';
}

function renderTable(filter) {
    const tbody = document.getElementById('bookingsBody');
    const rows = filter === 'all' ? allBookings : allBookings.filter(b => {
        const value = (b.bookingStatus || b.status || 'pending').toLowerCase();
        return filter === 'pending'
            ? ['pending','payment_verified','pending_hotel_confirmation'].includes(value)
            : value === filter;
    });

    if (rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">${filter === 'all' ? 'No bookings yet' : 'No ' + filter + ' bookings'}</div>
                <div class="empty-desc">${filter === 'all' ? 'Bookings will appear here once customers book rooms.' : 'No bookings match this filter.'}</div>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(b => {
        const status   = (b.bookingStatus || b.status || 'pending');
        const rooms    = Number(b.rooms || 1);
        const nights   = Number(b.nights || 1);
        const adults   = Number(b.adults || 1);
        const children = Number(b.children || 0);
        const total    = Number(b.hotelPrice || b.total_amount || 0);
        const email    = b.userEmail || b.guest_email || b.email || '—';
        const arrival  = fmtDate(b.arrivalDate || b.check_in || b.arrival_date);
        const depart   = fmtDate(b.departureDate || b.check_out || b.departure_date);

        const statusLabel = status.toLowerCase() === 'payment_verified' ? 'Hotel Payment Pending'
            : (status.toLowerCase() === 'pending_hotel_confirmation' ? 'Confirm Payment & Room' : status);
        return `<tr data-status="${status.toLowerCase()}">
            <td>
                <div class="guest-cell">
                    <span class="guest-email">${email}</span>
                    <span class="guest-meta">Booked ${fmtDate(b.createdAt)}</span>
                </div>
            </td>
            <td class="date-cell"><div class="date-main">${arrival}</div></td>
            <td class="date-cell"><div class="date-main">${depart}</div></td>
            <td><span class="rooms-pill"><i class="fas fa-bed"></i> ${rooms} room${rooms>1?'s':''}</span></td>
            <td><span class="guests-pill"><i class="fas fa-users"></i> ${adults + children}</span></td>
            <td style="font-weight:600;">${nights} night${nights>1?'s':''}</td>
            <td class="amount-cell">${total > 0 ? 'PKR ' + total.toLocaleString() : '—'}</td>
            <td>
                <span class="status-badge ${statusClass(status)}">${statusLabel}</span>
                ${status.toLowerCase() === 'cancelled' ? renderRefundNote(b) : ''}
            </td>
            <td>${renderPaymentActions(b, status)}</td>
        </tr>`;
    }).join('');
}

function renderRefundNote(b) {
    const refundAmount = Number(b.refundAmount || 0);
    const refundStatus = String(b.refundStatus || '').toLowerCase();

    if (refundAmount <= 0 || refundStatus === 'not_applicable') {
        return `<div style="font-size:11px;color:#94a3b8;margin-top:4px;">No refund due</div>`;
    }
    if (refundStatus === 'confirmed') {
        return `<div style="font-size:11px;color:#166534;margin-top:4px;">Refund confirmed by guest · PKR ${refundAmount.toLocaleString()}</div>`;
    }
    if (refundStatus === 'sent') {
        return `<div style="font-size:11px;color:#1e40af;margin-top:4px;">Refund sent — awaiting guest confirmation · PKR ${refundAmount.toLocaleString()}</div>`;
    }
    if (refundStatus === 'disputed') {
        return `<div style="font-size:11px;color:#991b1b;font-weight:700;margin-top:4px;">Guest disputed this refund — resend it · PKR ${refundAmount.toLocaleString()}</div>`;
    }
    if (refundStatus === 'escalated') {
        return `<div style="font-size:11px;color:#92400e;margin-top:4px;">Refund sent by Travelix · PKR ${refundAmount.toLocaleString()}</div>`;
    }
    return `<div style="font-size:11px;color:#92400e;margin-top:4px;">Refund owed to guest · PKR ${refundAmount.toLocaleString()}</div>`;
}

function renderPaymentActions(b, status) {
    const proofPath = b.payment?.proofImagePath || '';
    const paymentStatus = String(b.payment?.status || '').toLowerCase();
    const viewBtn = proofPath
        ? `<button type="button" class="pay-action-btn view" data-action="view-proof" data-id="${b.id}">📎 View Proof</button>`
        : '';

    if (['pending','payment_verified','pending_hotel_confirmation'].includes(status.toLowerCase())) {
        const payoutStatus = String(b.hotelPayoutStatus || 'not_sent').toLowerCase();
        const payoutProof = b.hotelPayoutProof || '';
        if (paymentStatus !== 'verified') {
            return `<div class="payment-actions">
                <span class="payment-verified" style="background:#fef3c7;color:#92400e;">Awaiting Travelix Verification</span>
            </div>`;
        }
        if (payoutStatus !== 'sent' || !payoutProof) {
            return `<div class="payment-actions"><span class="payment-verified" style="background:#eff6ff;color:#1d4ed8;">User Payment Verified — Awaiting Hotel Payout</span></div>`;
        }
        return `<div class="payment-actions">
            <button type="button" class="pay-action-btn view" data-action="view-payout-proof" data-id="${b.id}">📎 View Travelix Transfer Proof</button>
            <span class="payment-verified">Hotel Payment Sent: PKR ${Number(b.hotelPayoutAmount || b.hotelPrice || 0).toLocaleString()}</span>
            <button type="button" class="pay-action-btn confirm" data-action="confirm" data-id="${b.id}">✅ Payment Received & Confirm Room</button>
            <button type="button" class="pay-action-btn reject" data-action="reject" data-id="${b.id}">❌ Reject Booking</button>
        </div>`;
    }

    if (status.toLowerCase() === 'confirmed') {
        return `<div class="payment-actions">${viewBtn}<span class="payment-verified">Verified</span></div>`;
    }

    return viewBtn ? `<div class="payment-actions">${viewBtn}</div>` : '—';
}

function updateStats() {
    const activeBookings = allBookings.filter(b => (b.bookingStatus||b.status||'').toLowerCase() !== 'cancelled');

    const total     = allBookings.length;
    const confirmed = allBookings.filter(b => (b.bookingStatus||b.status||'').toLowerCase() === 'confirmed').length;
    const rooms     = activeBookings.reduce((s, b) => s + Number(b.rooms || 1), 0);
    const revenue   = activeBookings.reduce((s, b) => s + Number(b.hotelPrice || b.total_amount || 0), 0);

    document.getElementById('statTotal').textContent     = total;
    document.getElementById('statConfirmed').textContent = confirmed;
    document.getElementById('statRooms').textContent     = rooms;
    document.getElementById('statRevenue').textContent   = revenue > 0 ? 'PKR ' + revenue.toLocaleString() : '0';
}

async function loadBookings() {
    if (!HOTEL_ID) {
        document.getElementById('bookingsBody').innerHTML = `<tr><td colspan="9">
            <div class="empty-state">
                <div class="empty-icon">⚠️</div>
                <div class="empty-title">No hotel assigned</div>
                <div class="empty-desc">Your account is not linked to a hotel. Contact your administrator.</div>
            </div>
        </td></tr>`;
        ['statTotal','statConfirmed','statRooms','statRevenue'].forEach(id => document.getElementById(id).textContent = '0');
        return;
    }

    try {
        const snap = await db.collection('hotel_bookings')
            .where('hotelId', '==', HOTEL_ID)
            .orderBy('createdAt', 'desc')
            .get();

        allBookings = snap.docs.map(d => ({ id: d.id, ...d.data() }));
        updateStats();
        renderTable(activeFilter);
    } catch(e) {
        // Fallback without orderBy (index may not exist yet)
        try {
            const snap2 = await db.collection('hotel_bookings')
                .where('hotelId', '==', HOTEL_ID)
                .get();
            allBookings = snap2.docs.map(d => ({ id: d.id, ...d.data() }));
            // Sort client-side
            allBookings.sort((a, b) => {
                const ta = a.createdAt?.seconds || 0;
                const tb = b.createdAt?.seconds || 0;
                return tb - ta;
            });
            updateStats();
            renderTable(activeFilter);
        } catch(e2) {
            document.getElementById('bookingsBody').innerHTML = `<tr><td colspan="9">
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <div class="empty-title">Could not load bookings</div>
                    <div class="empty-desc">${e2.message}</div>
                </div>
            </td></tr>`;
        }
    }
}

// Payment review action clicks (view proof / confirm / reject) — delegated since rows re-render
document.getElementById('bookingsBody').addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const id = btn.dataset.id;
    const booking = allBookings.find(b => b.id === id);
    if (!booking) return;

    if (action === 'view-proof') {
        const path = booking.payment?.proofImagePath;
        if (path) window.open(path, '_blank');
        return;
    }
    if (action === 'view-payout-proof') {
        if (booking.hotelPayoutProof) window.open(booking.hotelPayoutProof, '_blank');
        return;
    }

    if (action === 'confirm') {
        if (String(booking.hotelPayoutStatus || '').toLowerCase() !== 'sent' || !booking.hotelPayoutProof) {
            Swal.fire({ icon: 'warning', title: 'Not Yet', text: "Travelix must send your hotel share with proof before this booking can be confirmed.", confirmButtonColor: '#1484B4' });
            return;
        }

        const result = await Swal.fire({
            icon: 'question',
            title: 'Payment received and room reserved?',
            text: 'Confirm the Travelix transfer reached your account and the room is reserved for this guest.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Confirm Both',
            confirmButtonColor: '#16a34a'
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch('/travelix/hotel_portal/ajax/confirm_booking_payment.php', {
                method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body:JSON.stringify({bookingId:id})
            });
            const resultData = await resp.json();
            if (!resultData.success) throw new Error(resultData.message || 'Could not confirm this booking.');

            syncLinkedTripBookingStatus(id, { status: 'confirmed' });

            Swal.fire({ icon: 'success', title: 'Booking Confirmed', confirmButtonColor: '#1484B4' });
            loadBookings();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#1484B4' });
        }
        return;
    }

    if (action === 'reject') {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Reject this booking?',
            html: 'The room will be released. The guest will be owed a full refund of the <strong>hotel charges only</strong>; the Travelix fee is non-refundable.',
            input: 'textarea',
            inputLabel: 'Reason for rejection',
            inputPlaceholder: 'Tell the guest why this booking cannot be accepted...',
            inputValidator: value => !String(value || '').trim() ? 'Please enter a rejection reason.' : undefined,
            showCancelButton: true,
            confirmButtonText: 'Yes, reject',
            confirmButtonColor: '#dc2626'
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch('/travelix/hotel_portal/ajax/reject_booking.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ bookingId: id, reason: String(result.value || '').trim() })
            });
            const resultData = await resp.json();
            if (!resultData.success) throw new Error(resultData.message || 'Could not reject this booking.');

            syncLinkedTripBookingStatus(id, {
                status: 'cancelled',
                refundAmount: Number(resultData.refundAmount || 0),
                refundStatus: Number(resultData.refundAmount || 0) > 0 ? 'pending' : 'not_applicable'
            });

            Swal.fire({ icon: 'success', title: 'Booking Rejected', text: resultData.message, confirmButtonColor: '#1484B4' });
            loadBookings();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#1484B4' });
        }
    }
});

// Filter tab clicks
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        activeFilter = this.dataset.filter;
        renderTable(activeFilter);
    });
});

loadBookings();
</script>
</body>
</html>
