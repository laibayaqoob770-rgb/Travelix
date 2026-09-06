<?php
/**
 * Hotel Portal — Refunds owed to guests (hotel-first refund flow).
 * The hotel holds guest money via its 100% payout, so it is responsible for
 * sending cancellation refunds directly — with a strict SLA: 24h warning,
 * 48h (or a second wrong amount) escalates to admin and disables the account.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['hotel_staff'])) { header('Location: /travelix/hotel_portal/login.php'); exit; }

$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once __DIR__ . '/includes/resolve_hotel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/commission_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/refund_lib.php';

$hotel = hp_load_portal_hotel();
hp_enforce_hotel_active($hotel);

$staff     = $_SESSION['hotel_staff'];
$staffName = $staff['first_name'] ?? explode(' ', $staff['name'])[0];
$hotelName = $hotel['name'] ?? '';
$hotelId   = $hotel['id'] ?? '';

$saPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

// Opportunistic SLA check — cheap on this small dataset, plus a standalone
// cron script (cron/check_refund_slas.php) covers it when nobody is browsing.
hp_check_refund_slas($saPath, FIREBASE_PROJECT_ID);

// 'pending' (owed, not sent), 'sent' (sent, awaiting guest confirmation),
// and 'disputed' (guest says it never arrived) are all still active on the
// hotel's plate — only 'confirmed'/'escalated' are truly done with them.
$activeStatuses = ['pending', 'sent', 'disputed'];
$pendingRefunds = [];
if ($hotelId) {
    $bookings = hp_firestore_query($saPath, FIREBASE_PROJECT_ID, 'hotel_bookings', 'hotelId', $hotelId);
    foreach ($bookings as $b) {
        if (in_array(strtolower((string)($b['refundStatus'] ?? '')), $activeStatuses, true) && (string)($b['refundOwner'] ?? '') === 'hotel') {
            $pendingRefunds[] = $b;
        }
    }
    usort($pendingRefunds, function ($a, $b) {
        // Disputed (urgent) first, then pending, then sent (just waiting).
        $rank = ['disputed' => 0, 'pending' => 1, 'sent' => 2];
        $ra = $rank[strtolower((string)($a['refundStatus'] ?? ''))] ?? 3;
        $rb = $rank[strtolower((string)($b['refundStatus'] ?? ''))] ?? 3;
        if ($ra !== $rb) return $ra <=> $rb;
        return (int)($a['refundEscalateAt'] ?? PHP_INT_MAX) <=> (int)($b['refundEscalateAt'] ?? PHP_INT_MAX);
    });

    // The guest's own saved payout account — so the hotel knows exactly
    // where to send the money without asking, same as admin now sees a
    // hotel's account before sending a payout.
    foreach ($pendingRefunds as &$refundRow) {
        $guestUid = (string)($refundRow['uid'] ?? '');
        $guestUser = $guestUid !== '' ? hp_firestore_get($saPath, FIREBASE_PROJECT_ID, 'users/' . $guestUid) : null;
        $refundRow['_guestPaymentMethod'] = (string)($guestUser['paymentMethod'] ?? '');
        $refundRow['_guestBankName'] = (string)($guestUser['bankName'] ?? '');
        $refundRow['_guestAccountNumber'] = (string)($guestUser['paymentAccountNumber'] ?? '');
    }
    unset($refundRow);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Refunds — <?= htmlspecialchars($hotelName ?: 'Travelix Hotel Portal') ?></title>
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

.warning-banner{background:#fef2f2;border:1.5px solid #fecaca;border-radius:16px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;}
.warning-banner i{font-size:24px;color:#dc2626;}
.warning-banner-text{font-size:13px;color:#991b1b;font-weight:600;}

.card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px;}
.card-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;}
.card-head h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}
.card-head p{font-size:12.5px;color:#94a3b8;margin:2px 0 0;}

.refund-row{padding:18px 24px;border-bottom:1px solid #f8fafc;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.refund-row:last-child{border-bottom:none;}
.refund-guest{font-weight:800;color:#0f172a;font-size:14.5px;}
.refund-sub{font-size:12px;color:#94a3b8;margin-top:2px;}
.refund-amount{font-size:20px;font-weight:900;color:#dc2626;white-space:nowrap;}
.refund-deadline{font-size:11.5px;font-weight:700;margin-top:4px;}
.refund-deadline.warn{color:#f59e0b;}
.refund-deadline.critical{color:#dc2626;}

.btn-send-refund{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;padding:11px 22px;border-radius:12px;font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;}
.btn-send-refund:hover{opacity:.9;}

.mono{font-family:monospace;}
.proof-link{color:#2471a3;font-weight:700;text-decoration:underline;font-size:12.5px;}
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
    <a href="/travelix/hotel_portal/refunds.php" class="active"><i class="fas fa-rotate-left"></i> Refunds</a>
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

    <div class="warning-banner">
        <i class="fas fa-triangle-exclamation"></i>
        <div class="warning-banner-text">
            You must send cancelled-booking refunds within 24 hours (or you'll get a warning) and 48 hours total —
            after that, or if you send the wrong amount twice, your account is disabled until Travelix admin reviews it.
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Refunds Owed to Guests</h2>
            <p>Send the exact amount shown to the guest's payout account, then attach proof. The guest must confirm receipt before this counts as settled.</p>
        </div>
        <div>
        <?php if (!$pendingRefunds): ?>
            <div class="empty">
                <div class="empty-icon">✅</div>
                <div style="font-size:15px;font-weight:700;color:#334155;">No refunds pending</div>
                <div style="margin-top:6px;">Cancelled bookings with a refund due will appear here.</div>
            </div>
        <?php else: foreach ($pendingRefunds as $b):
            $status = strtolower((string)($b['refundStatus'] ?? 'pending'));
            $isDisputed = $status === 'disputed';
            $isSent = $status === 'sent';
            $deadlineMs = $isDisputed ? (float)($b['refundDisputeEscalateAt'] ?? 0) : (float)($b['refundEscalateAt'] ?? 0);
            $hoursLeft = $deadlineMs > 0 ? round(($deadlineMs - microtime(true) * 1000) / 3600000, 1) : null;
            $deadlineCls = $hoursLeft !== null && $hoursLeft <= 6 ? 'critical' : 'warn';
        ?>
            <?php
                $guestAcctLabel = $b['_guestPaymentMethod'] === 'bank'
                    ? ($b['_guestBankName'] ?: 'Bank')
                    : ucfirst($b['_guestPaymentMethod'] ?: '');
            ?>
            <div class="refund-row" <?= $isDisputed ? 'style="background:#fef2f2;"' : '' ?>>
                <div>
                    <div class="refund-guest"><?= htmlspecialchars((string)($b['userEmail'] ?? 'Guest')) ?></div>
                    <div class="refund-sub"><?= (float)($b['refundPercent'] ?? 0) ?>% refund of their total payment</div>
                    <?php if ($isDisputed): ?>
                        <div class="refund-sub" style="margin-top:6px;color:#991b1b;font-weight:800;">
                            <i class="fas fa-triangle-exclamation"></i> Guest says this refund never arrived — resend it now.
                        </div>
                    <?php elseif ($isSent): ?>
                        <div class="refund-sub" style="margin-top:6px;color:#1e40af;font-weight:700;">
                            <i class="fas fa-hourglass-half"></i> Sent — waiting for the guest to confirm receipt.
                        </div>
                    <?php elseif ($b['_guestAccountNumber']): ?>
                        <div class="refund-sub" style="margin-top:6px;color:#0f172a;font-weight:700;">
                            <i class="fas fa-wallet" style="color:#059669;"></i>
                            Send to: <?= htmlspecialchars($guestAcctLabel) ?> — <span class="mono"><?= htmlspecialchars($b['_guestAccountNumber']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="refund-sub" style="margin-top:6px;color:#b45309;font-weight:700;">
                            <i class="fas fa-triangle-exclamation"></i> Guest hasn't saved a payout account yet — contact Travelix support.
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($b['refundProofUrl'])): ?>
                        <a href="<?= htmlspecialchars($b['refundProofUrl']) ?>" target="_blank" class="proof-link" style="display:block;margin-top:4px;">View Your Sent Proof</a>
                    <?php endif; ?>
                    <?php if ($hoursLeft !== null && !$isSent): ?>
                        <div class="refund-deadline <?= $deadlineCls ?>">
                            <i class="fas fa-clock"></i>
                            <?= $hoursLeft > 0 ? htmlspecialchars((string)$hoursLeft) . ' hour(s) left' : 'Overdue — act now' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:16px;">
                    <div class="refund-amount"><?= hp_money($b['refundAmount'] ?? 0) ?></div>
                    <?php if (!$isSent): ?>
                    <button type="button" class="btn-send-refund"
                        data-id="<?= htmlspecialchars($b['id']) ?>"
                        data-amount="<?= (float)($b['refundAmount'] ?? 0) ?>"
                        data-guest="<?= htmlspecialchars((string)($b['userEmail'] ?? 'Guest')) ?>"
                        data-acct-label="<?= htmlspecialchars($guestAcctLabel) ?>"
                        data-acct-number="<?= htmlspecialchars($b['_guestAccountNumber']) ?>">
                        <i class="fas fa-paper-plane"></i> <?= $isDisputed ? 'Resend Refund' : 'Send Refund' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

<?php endif; ?>
</div>

<input type="file" id="refundProofInput" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;">

<script>
document.querySelectorAll('.btn-send-refund').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const bookingId = btn.dataset.id;
        const requiredAmount = Number(btn.dataset.amount || 0);
        const guest = btn.dataset.guest;
        const acctLabel = btn.dataset.acctLabel || '';
        const acctNumber = btn.dataset.acctNumber || '';

        const res = await Swal.fire({
            icon: 'question',
            title: 'Send Refund to ' + guest,
            html: `
                <div style="text-align:left;font-size:13.5px;line-height:1.9;">
                    <div><b>Amount to send:</b> <span style="color:#059669;font-weight:800;font-size:16px;">PKR ${requiredAmount.toLocaleString()}</span></div>
                    <div><b>Send to:</b> ${acctLabel || '—'}</div>
                    <div><b>Account number:</b> <span style="font-family:monospace;">${acctNumber || '—'}</span></div>
                    <div style="margin-top:10px;font-size:12px;color:#64748b;">This is the exact amount owed — send it to the account above, then attach proof.</div>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'I sent it — Attach Proof',
            confirmButtonColor: '#059669',
        });
        if (!res.isConfirmed) return;

        const input = document.getElementById('refundProofInput');
        input.value = '';
        input.onchange = () => doSubmitRefund(bookingId, requiredAmount, input.files[0]);
        input.click();
    });
});

async function doSubmitRefund(bookingId, sentAmount, file) {
    if (!file) return;

    Swal.fire({ title: 'Uploading proof...', allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false, didOpen: () => Swal.showLoading() });

    try {
        const fd = new FormData();
        fd.append('proof', file);
        const upRes = await fetch('/travelix/hotel_portal/ajax/upload_refund_proof.php', { method: 'POST', body: fd });
        const upData = await upRes.json();
        if (!upData.success) throw new Error(upData.message || 'Upload failed.');

        Swal.update({ title: 'Recording refund...' });

        const res = await fetch('/travelix/hotel_portal/ajax/submit_hotel_refund.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ bookingId, sentAmount, proofPath: upData.path })
        });
        const data = await res.json();

        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Refund Sent', text: data.message, confirmButtonColor: '#0f3460' });
        } else if (data.disabled) {
            await Swal.fire({ icon: 'error', title: 'Account Disabled', text: data.message, confirmButtonColor: '#0f3460' });
        } else {
            await Swal.fire({ icon: 'warning', title: 'Amount Too Low', text: data.message, confirmButtonColor: '#0f3460' });
        }
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#0f3460' });
    }
}
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

    const hotelId = <?= json_encode($hotelId) ?>;
    if (hotelId) {
        window.travelixInitPortalNotifications({
            db: firebase.firestore(),
            filters: [['audience', '==', 'hotel'], ['hotelId', '==', hotelId]],
            manageUrl: '/travelix/hotel_portal/notifications.php'
        });
    }
})();
</script>
</body>
</html>
