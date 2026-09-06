<?php
/**
 * Hotel Portal — Discount management.
 * Hotel staff can offer a % discount on rooms for a chosen date range.
 * The discount comes off the hotel's own price before Travelix's 12%
 * platform fee is added — the hotel absorbs its own promotion, and the
 * guest always sees the discount clearly called out at checkout.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once __DIR__ . '/includes/resolve_hotel.php';

$hotel = hp_load_portal_hotel();
hp_enforce_hotel_active($hotel);

$staff     = $_SESSION['hotel_staff'];
$staffName = $staff['first_name'] ?? explode(' ', $staff['name'])[0];
$hotelName = $hotel['name'] ?? '';
$hotelId   = $hotel['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Discounts — <?= htmlspecialchars($hotelName ?: 'Travelix Hotel Portal') ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/css/travelix_notifications.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4fa;min-height:100vh;}

.topbar{background:linear-gradient(135deg,#0f3460,#1a5276,#2471a3);color:#fff;padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 20px rgba(15,52,96,.3);position:sticky;top:0;z-index:100;}
.topbar-brand{display:flex;align-items:center;gap:12px;}
.topbar-brand-icon{width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.topbar-brand-text{font-size:17px;font-weight:800;}
.topbar-brand-sub{font-size:11px;opacity:.72;margin-top:1px;}
.staff-pill{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.12);border-radius:12px;padding:7px 14px;}
.staff-avatar{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;}
.staff-name{font-size:13px;font-weight:700;}
.staff-role{font-size:10px;opacity:.7;}

.portal-subnav{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;display:flex;gap:4px;overflow-x:auto;position:sticky;top:64px;z-index:99;}
.portal-subnav a{display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13.5px;font-weight:700;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:.2s;}
.portal-subnav a:hover{color:#0f3460;background:#f8fafc;}
.portal-subnav a.active{color:#0f3460;border-bottom-color:#0f3460;}

.page-body{max-width:1000px;margin:0 auto;padding:28px 16px;}

.card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px;}
.card-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;}
.card-head h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}
.card-head p{font-size:12.5px;color:#94a3b8;margin:2px 0 0;}
.card-body{padding:22px 24px;}

.form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;align-items:end;}
@media(max-width:800px){.form-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.form-grid{grid-template-columns:1fr;}}
.field-group{display:flex;flex-direction:column;gap:6px;}
.field-label{font-size:12px;font-weight:700;color:#334155;}
.field-input{padding:11px 13px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;color:#0f172a;font-family:inherit;}
.field-input:focus{outline:none;border-color:#2471a3;}

.btn-add{grid-column:1 / -1;justify-self:start;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;padding:12px 26px;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.2s;}
.btn-add:hover{opacity:.9;}

.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;}
th{padding:12px 16px;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;white-space:nowrap;border-bottom:1px solid #f1f5f9;text-align:left;}
td{padding:14px 16px;font-size:13.5px;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tbody tr:hover{background:#fafbff;}
tbody tr:last-child td{border-bottom:none;}
.mono{font-family:monospace;font-size:12.5px;}

.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:800;white-space:nowrap;}
.pill-active{color:#166534;background:#f0fdf4;}
.pill-upcoming{color:#92400e;background:#fffbeb;}
.pill-expired{color:#64748b;background:#f1f5f9;}

.discount-percent{font-weight:900;color:#16a34a;font-size:15px;}

.delete-discount-btn{border:none;background:#fee2e2;color:#b91c1c;padding:8px 14px;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
.delete-discount-btn:hover{background:#fecaca;}

.empty{text-align:center;padding:48px 20px;color:#94a3b8;}
.empty-icon{font-size:42px;opacity:.3;margin-bottom:10px;}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-brand-icon">🏨</div>
        <div>
            <div class="topbar-brand-text">Hotel Management Portal</div>
            <div class="topbar-brand-sub"><?= htmlspecialchars($hotelName ?: 'Travelix Partner') ?></div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
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
            <div class="staff-avatar"><?= strtoupper(substr($staffName,0,1)) ?></div>
            <div>
                <div class="staff-name"><?= htmlspecialchars($staffName) ?></div>
                <div class="staff-role">Hotel Staff</div>
            </div>
        </div>
    </div>
</div>

<div class="portal-subnav">
    <a href="/travelix/hotel_portal/index.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="/travelix/hotel_portal/hotel_bookings.php<?= $hotelId ? '?hotel_id=' . urlencode($hotelId) : '' ?>"><i class="fas fa-calendar-alt"></i> Bookings</a>
    <a href="/travelix/hotel_portal/commission.php"><i class="fas fa-money-bill-wave"></i> Payouts</a>
    <a href="/travelix/hotel_portal/refunds.php"><i class="fas fa-rotate-left"></i> Refunds</a>
    <a href="/travelix/hotel_portal/discounts.php" class="active"><i class="fas fa-tag"></i> Discounts</a>
    <a href="/travelix/hotel_portal/edit_hotel.php<?= $hotelId ? '?edit=' . urlencode($hotelId) : '' ?>"><i class="fas fa-pen"></i> Edit Hotel</a>
</div>

<div class="page-body">

<?php if (!$hotelId): ?>
    <div class="card"><div class="empty">
        <div class="empty-icon">🏨</div>
        <div style="font-size:16px;font-weight:800;color:#334155;">No hotel assigned</div>
        <div style="margin-top:6px;">Your account is not linked to a hotel yet.</div>
    </div></div>
<?php else: ?>

    <div class="card">
        <div class="card-head">
            <h2>Add a Discount</h2>
            <p>Pick a date range and % off — it applies to every room booked for a check-in date inside that range. Travelix's 12% platform fee is still charged to the guest on top, calculated on the discounted price.</p>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field-group">
                    <label class="field-label">Start Date</label>
                    <input type="date" class="field-input" id="fStartDate">
                </div>
                <div class="field-group">
                    <label class="field-label">End Date</label>
                    <input type="date" class="field-input" id="fEndDate">
                </div>
                <div class="field-group">
                    <label class="field-label">Discount %</label>
                    <input type="number" class="field-input" id="fPercent" min="1" max="90" placeholder="e.g. 20">
                </div>
                <div class="field-group">
                    <label class="field-label">Note (optional)</label>
                    <input type="text" class="field-input" id="fNote" placeholder="e.g. Eid Sale">
                </div>
                <button type="button" class="btn-add" id="addDiscountBtn">
                    <i class="fas fa-plus"></i> Add Discount
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Your Discounts</h2>
            <p>Guests see these clearly marked as "Discount by Hotel" at checkout.</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date Range</th>
                        <th>Discount</th>
                        <th>Note</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="discountsBody">
                    <tr><td colspan="5"><div class="empty">Loading discounts...</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
</div>

<script>
function todayYMD() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function discountStatus(d) {
    const today = todayYMD();
    if (today < d.startDate) return { label: 'Upcoming', cls: 'pill-upcoming' };
    if (today > d.endDate) return { label: 'Expired', cls: 'pill-expired' };
    return { label: 'Active', cls: 'pill-active' };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

let currentDiscounts = [];

function renderDiscounts() {
    const tbody = document.getElementById('discountsBody');
    if (!tbody) return;

    if (!currentDiscounts.length) {
        tbody.innerHTML = `<tr><td colspan="5"><div class="empty">
            <div class="empty-icon">🏷️</div>
            <div style="font-size:15px;font-weight:700;color:#334155;">No discounts yet</div>
            <div style="margin-top:6px;">Add one above to run a promotion for a date range.</div>
        </div></td></tr>`;
        return;
    }

    const sorted = [...currentDiscounts].sort((a, b) => (b.startDate || '').localeCompare(a.startDate || ''));

    tbody.innerHTML = sorted.map(d => {
        const st = discountStatus(d);
        return `
            <tr>
                <td class="mono">${escapeHtml(d.startDate)} → ${escapeHtml(d.endDate)}</td>
                <td><span class="discount-percent">${escapeHtml(d.percent)}% OFF</span></td>
                <td>${escapeHtml(d.note || '—')}</td>
                <td><span class="pill ${st.cls}">${st.label}</span></td>
                <td>
                    <button type="button" class="delete-discount-btn" data-id="${escapeHtml(d.id)}">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

document.getElementById('discountsBody')?.addEventListener('click', async function (e) {
    const btn = e.target.closest('.delete-discount-btn');
    if (!btn) return;
    const id = btn.dataset.id;

    const result = await Swal.fire({
        icon: 'warning', title: 'Delete this discount?', showCancelButton: true,
        confirmButtonText: 'Yes, delete', confirmButtonColor: '#dc2626'
    });
    if (!result.isConfirmed) return;

    currentDiscounts = currentDiscounts.filter(d => d.id !== id);
    try {
        await window.travelixSaveDiscounts(currentDiscounts);
        renderDiscounts();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#0f3460' });
    }
});

document.getElementById('addDiscountBtn')?.addEventListener('click', async function () {
    const startDate = document.getElementById('fStartDate').value;
    const endDate = document.getElementById('fEndDate').value;
    const percent = Number(document.getElementById('fPercent').value);
    const note = document.getElementById('fNote').value.trim();

    if (!startDate || !endDate) {
        Swal.fire({ icon: 'warning', title: 'Dates Required', text: 'Please pick a start and end date.', confirmButtonColor: '#0f3460' });
        return;
    }
    if (endDate < startDate) {
        Swal.fire({ icon: 'warning', title: 'Invalid Range', text: 'End date must be on or after the start date.', confirmButtonColor: '#0f3460' });
        return;
    }
    if (!percent || percent < 1 || percent > 90) {
        Swal.fire({ icon: 'warning', title: 'Invalid Discount', text: 'Enter a discount between 1% and 90%.', confirmButtonColor: '#0f3460' });
        return;
    }

    const newDiscount = {
        id: 'd_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
        startDate, endDate, percent, note,
        createdAt: Date.now()
    };

    currentDiscounts.push(newDiscount);
    try {
        await window.travelixSaveDiscounts(currentDiscounts);
        renderDiscounts();
        document.getElementById('fStartDate').value = '';
        document.getElementById('fEndDate').value = '';
        document.getElementById('fPercent').value = '';
        document.getElementById('fNote').value = '';
        Swal.fire({ icon: 'success', title: 'Discount Added', timer: 1200, showConfirmButton: false });
    } catch (err) {
        currentDiscounts.pop();
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#0f3460' });
    }
});
</script>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
<script src="/travelix/assets/js/travelix_portal_notifications.js"></script>
<script>
(function () {
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

    const hotelId = <?= json_encode($hotelId) ?>;

    window.travelixSaveDiscounts = function (discounts) {
        if (!hotelId) return Promise.reject(new Error('No hotel assigned.'));
        return db.collection('hotels').doc(hotelId).update({ discounts: discounts });
    };

    if (hotelId) {
        db.collection('hotels').doc(hotelId).get().then(function (docSnap) {
            const data = docSnap.exists ? docSnap.data() : {};
            currentDiscounts = Array.isArray(data.discounts) ? data.discounts : [];
            renderDiscounts();
        }).catch(function () {
            currentDiscounts = [];
            renderDiscounts();
        });

        window.travelixInitPortalNotifications({
            db: db,
            filters: [['audience', '==', 'hotel'], ['hotelId', '==', hotelId]],
            manageUrl: '/travelix/hotel_portal/notifications.php'
        });
    }
})();
</script>
</body>
</html>
