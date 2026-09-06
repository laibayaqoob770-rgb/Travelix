<?php
/**
 * Hotel Portal — Payouts received from Travelix.
 * Under the centralized payment model guests pay Travelix directly, so
 * there is nothing left for the hotel to "pay Travelix" — this page just
 * shows what Travelix has sent this hotel, with proof for each payout.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once __DIR__ . '/includes/resolve_hotel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/commission_lib.php';

$hotel = hp_load_portal_hotel();
hp_enforce_hotel_active($hotel);

$staff     = $_SESSION['hotel_staff'];
$staffName = $staff['first_name'] ?? explode(' ', $staff['name'])[0];
$hotelName = $hotel['name'] ?? '';
$hotelId   = $hotel['id'] ?? '';

$saPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
$payoutLedger = hp_hotel_payout($saPath, FIREBASE_PROJECT_ID, $hotelId);
$payoutsReceived = $payoutLedger['payouts'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Payouts — <?= htmlspecialchars($hotelName ?: 'Travelix Hotel Portal') ?></title>
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

.page-body{max-width:1200px;margin:0 auto;padding:28px 16px;}

.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.summary-grid.cols-4{grid-template-columns:repeat(4,1fr);}
@media(max-width:900px){.summary-grid,.summary-grid.cols-4{grid-template-columns:repeat(2,1fr);}}
@media(max-width:520px){.summary-grid,.summary-grid.cols-4{grid-template-columns:1fr;}}
.sum-card{background:#fff;border-radius:18px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.06);border-left:5px solid #cbd5e1;}
.sum-card.total{border-left-color:#2471a3;}
.sum-card.paid{border-left-color:#16a34a;}
.sum-card.due{border-left-color:#dc2626;}
.sum-card.pending{border-left-color:#f59e0b;}
.sum-lbl{font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.sum-val{font-size:24px;font-weight:900;color:#0f172a;line-height:1.1;}
.sum-sub{font-size:11.5px;color:#94a3b8;margin-top:4px;}

.card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px;}
.card-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.card-head h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}
.card-head p{font-size:12.5px;color:#94a3b8;margin:2px 0 0;}

.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;}
th{padding:12px 16px;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;white-space:nowrap;border-bottom:1px solid #f1f5f9;text-align:left;}
td{padding:14px 16px;font-size:13.5px;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tbody tr:hover{background:#fafbff;}
tbody tr:last-child td{border-bottom:none;}

.mono{font-family:monospace;font-size:12.5px;}
.amt{font-weight:800;color:#0f172a;white-space:nowrap;}

.empty{text-align:center;padding:48px 20px;color:#94a3b8;}
.empty-icon{font-size:42px;opacity:.3;margin-bottom:10px;}

.proof-link{color:#2471a3;font-weight:700;text-decoration:underline;font-size:12.5px;}

.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:800;white-space:nowrap;}
.pill-clear{color:#166534;background:#f0fdf4;}
.abtn{border:none;border-radius:9px;padding:7px 14px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s;}
.abtn:hover{opacity:.85;}
.abtn-ok{background:#dcfce7;color:#166534;}
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
    <a href="/travelix/hotel_portal/commission.php" class="active"><i class="fas fa-money-bill-wave"></i> Payouts</a>
    <a href="/travelix/hotel_portal/refunds.php"><i class="fas fa-rotate-left"></i> Refunds</a>
    <a href="/travelix/hotel_portal/discounts.php"><i class="fas fa-tag"></i> Discounts</a>
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

    <!-- Summary -->
    <div class="summary-grid cols-4">
        <div class="sum-card total">
            <div class="sum-lbl">Total Owed to You (100%)</div>
            <div class="sum-val"><?= hp_money($payoutLedger['total']) ?></div>
            <div class="sum-sub"><?= count($payoutLedger['rows']) ?> confirmed booking(s)</div>
        </div>
        <div class="sum-card paid">
            <div class="sum-lbl">Already Paid Out</div>
            <div class="sum-val" style="color:#166534;"><?= hp_money($payoutLedger['paid']) ?></div>
            <div class="sum-sub"><?= $payoutLedger['counts']['paid'] ?> booking(s) settled</div>
        </div>
        <div class="sum-card pending">
            <div class="sum-lbl">Awaiting Your Confirmation</div>
            <div class="sum-val" style="color:#92400e;"><?= hp_money($payoutLedger['pending']) ?></div>
            <div class="sum-sub"><?= $payoutLedger['counts']['pending'] ?> booking(s) sent, unconfirmed</div>
        </div>
        <div class="sum-card due">
            <div class="sum-lbl">Outstanding — Travelix Owes You</div>
            <div class="sum-val" style="color:#991b1b;"><?= hp_money($payoutLedger['due']) ?></div>
            <div class="sum-sub"><?= $payoutLedger['counts']['due'] ?> booking(s) not yet sent</div>
        </div>
    </div>

    <!-- Payouts received from Travelix (centralized payment model) -->
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Payouts Received from Travelix</h2>
                <p>Your full hotel price for every booking — the 12% platform fee is charged to the guest on top, never deducted from your share. Each entry includes the admin's transfer proof.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sent</th>
                        <th>Bookings Covered</th>
                        <th>Amount</th>
                        <th>Note</th>
                        <th>Proof</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$payoutsReceived): ?>
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="empty-icon">💸</div>
                            <div style="font-size:15px;font-weight:700;color:#334155;">No payouts received yet</div>
                        </div>
                    </td></tr>
                <?php else: foreach ($payoutsReceived as $p):
                    $sentAt = (int)($p['sentAt'] ?? 0);
                    $isConfirmed = (string)($p['status'] ?? 'pending') === 'confirmed'; ?>
                    <tr>
                        <td class="mono"><?= $sentAt ? date('d M Y, H:i', $sentAt) : '—' ?></td>
                        <td><?= (int)($p['bookingCount'] ?? count($p['bookingIds'] ?? [])) ?> booking(s)</td>
                        <td class="amt"><?= hp_money($p['amount'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string)($p['note'] ?? '') ?: '—') ?></td>
                        <td>
                            <?php if (!empty($p['proofUrl'])): ?>
                                <a href="<?= htmlspecialchars($p['proofUrl']) ?>" target="_blank" class="proof-link">View</a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isConfirmed): ?>
                                <span class="pill pill-clear">Confirmed</span>
                            <?php else: ?>
                                <button class="abtn abtn-ok" onclick='confirmPayout(<?= json_encode((string)$p['id']) ?>, <?= json_encode(hp_money($p['amount'] ?? 0)) ?>, <?= json_encode((string)($p['proofUrl'] ?? '')) ?>)'>
                                    <i class="fas fa-check"></i> Confirm Received
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
</div>

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

    const hotelId = <?= json_encode($hotelId) ?>;
    if (hotelId) {
        window.travelixInitPortalNotifications({
            db: firebase.firestore(),
            filters: [['audience', '==', 'hotel'], ['hotelId', '==', hotelId]],
            manageUrl: '/travelix/hotel_portal/notifications.php'
        });
    }
})();

function proofPreviewHtml(proofUrl) {
    if (!proofUrl) return '<div style="padding:14px;background:#fef2f2;color:#991b1b;border-radius:12px;font-weight:700;">Transfer proof is missing. You cannot confirm this payout.</div>';
    const safeUrl = String(proofUrl).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const isPdf = /\.pdf(?:$|\?)/i.test(proofUrl);
    return isPdf
        ? `<a href="${safeUrl}" target="_blank" style="display:block;padding:16px;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff;color:#1d4ed8;font-weight:800;text-align:center;"><i class="fas fa-file-pdf"></i> Open Transfer Proof PDF</a>`
        : `<a href="${safeUrl}" target="_blank" title="Open full proof"><img src="${safeUrl}" alt="Admin transfer proof" style="display:block;width:100%;max-height:300px;object-fit:contain;border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;"></a>`;
}

async function confirmPayout(payoutId, amount, proofUrl) {
    const res = await Swal.fire({
        title: 'Verify Proof & Confirm Payment',
        html: `<div style="text-align:left;">
            <div style="padding:10px 12px;background:#f0fdf4;color:#166534;border-radius:10px;margin-bottom:12px;font-weight:800;">Amount sent: ${amount}</div>
            ${proofPreviewHtml(proofUrl)}
            <p style="font-size:13px;color:#64748b;margin:12px 0 0;">Check this proof and your actual account. Confirming will automatically confirm the covered booking(s) for the guest and update Travelix admin.</p>
        </div>`,
        showCancelButton: true,
        confirmButtonText: 'Payment Received — Confirm Booking',
        confirmButtonColor: '#16a34a',
        showConfirmButton: Boolean(proofUrl),
        width: 650,
    });
    if (!res.isConfirmed) return;

    try {
        const resp = await fetch('/travelix/hotel_portal/ajax/confirm_payout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ payoutId })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Could not confirm the payout.');

        await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#2471a3' });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#2471a3' });
    }
}
</script>
</body>
</html>
