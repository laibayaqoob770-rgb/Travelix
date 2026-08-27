<?php
/* ================================================================
   Payouts & Escalated Refunds — Admin
   Per-hotel payout ledger (what Travelix owes each hotel, 100% of price),
   plus refunds that hotels failed to pay within their SLA — admin pays
   those guests directly to keep their trust, and the hotel is disabled
   until reviewed.
================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php'); exit;
}
$currentUser = $_SESSION['user'] ?? [];
$userRole    = strtolower((string)($currentUser['role'] ?? 'user'));
if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/admin_dashboard.php'); exit;
}

// Hotel payouts are now handled per booking in Booking Payments. Keep this
// legacy URL as a safe redirect so it cannot create duplicate payouts.
header('Location: ' . $baseUrl . '/admin_manage/refunds.php'); exit;

require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/commission_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/refund_lib.php';

$projectId = FIREBASE_PROJECT_ID;
$saPath    = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

// Opportunistic SLA check — cheap on this small dataset, plus a standalone
// cron script (cron/check_refund_slas.php) covers it when nobody is browsing.
hp_check_refund_slas($saPath, $projectId);

$adminName = trim(($currentUser['first_name'] ?? 'Admin') . ' ' . ($currentUser['last_name'] ?? ''));

/* ── Load everything once, then compute per hotel ── */
$hotels      = hp_firestore_query($saPath, $projectId, 'hotels');
$allBookings = hp_firestore_query($saPath, $projectId, 'hotel_bookings');
$allPayouts  = hp_get_payout_payments($saPath, $projectId);

// Group bookings + payouts by hotel
$bookingsByHotel = [];
foreach ($allBookings as $b) {
    $hid = (string)($b['hotelId'] ?? '');
    if ($hid !== '') $bookingsByHotel[$hid][] = $b;
}
$payoutsByHotel = [];
foreach ($allPayouts as $p) {
    $hid = (string)($p['hotelId'] ?? '');
    if ($hid !== '') $payoutsByHotel[$hid][] = $p;
}

$payoutRows = [];
$grandPayout = ['total' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'pending' => 0.0];

foreach ($hotels as $h) {
    $hid    = (string)($h['id'] ?? '');
    $payoutLedger = hp_build_payout_ledger($bookingsByHotel[$hid] ?? [], $payoutsByHotel[$hid] ?? []);

    if ($payoutLedger['total'] > 0) {
        $payoutRows[] = [
            'id'                    => $hid,
            'name'                  => (string)($h['name'] ?? '(unnamed)'),
            'city'                  => (string)($h['city'] ?? ''),
            'email'                 => (string)($h['staff_email'] ?? ''),
            'paymentMethod'         => (string)($h['paymentMethod'] ?? ''),
            'paymentAccountNumber'  => (string)($h['paymentAccountNumber'] ?? ''),
            'bankName'              => (string)($h['bankName'] ?? ''),
            'ledger'                => $payoutLedger,
        ];

        $grandPayout['total'] += $payoutLedger['total'];
        $grandPayout['paid']  += $payoutLedger['paid'];
        $grandPayout['due']   += $payoutLedger['due'];
        $grandPayout['pending'] = ($grandPayout['pending'] ?? 0) + $payoutLedger['pending'];
    }
}

usort($payoutRows, function ($a, $b) {
    return $b['ledger']['due'] <=> $a['ledger']['due'];
});

// Guests waiting on a refund for a cancelled booking — money admin owes back
// under the centralized-payment model, tracked separately from commission.
$hotelNameById = [];
foreach ($hotels as $h) {
    $hotelNameById[(string)($h['id'] ?? '')] = (string)($h['name'] ?? '(unnamed)');
}

// Only ESCALATED refunds land here — a hotel missed its SLA (48h, or two
// wrong amounts) and got disabled. Anything still 'pending' is the hotel's
// own job now; it lives in their portal, not here.
$pendingRefunds = array_values(array_filter($allBookings, function ($b) {
    return strtolower((string)($b['bookingStatus'] ?? '')) === 'cancelled'
        && strtolower((string)($b['refundStatus'] ?? '')) === 'escalated'
        && (float)($b['refundAmount'] ?? 0) > 0;
}));

usort($pendingRefunds, function ($a, $b) {
    // cancelledAt is written client-side via Firestore's serverTimestamp(),
    // which decodes as an ISO-8601 string — not the plain epoch int that
    // submittedAt (written server-side via PHP time()) uses.
    return strtotime((string)($b['cancelledAt'] ?? '')) <=> strtotime((string)($a['cancelledAt'] ?? ''));
});

$totalRefundsPending = array_sum(array_map(fn($b) => (float)($b['refundAmount'] ?? 0), $pendingRefunds));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Commission Management — Travelix Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<link rel="stylesheet" href="/travelix/assets/css/travelix_notifications.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4fa;min-height:100vh;}

.topbar{background:linear-gradient(135deg,#133c96,#1a5bc4);color:#fff;padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 20px rgba(19,60,150,.28);position:sticky;top:0;z-index:100;}
.topbar-title{font-size:17px;font-weight:800;display:flex;align-items:center;gap:10px;}
.back-btn{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.14);color:#fff;border:none;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s;}
.back-btn:hover{background:rgba(255,255,255,.26);color:#fff;}

.page-body{max-width:1280px;margin:0 auto;padding:28px 16px;}

.summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.summary-grid.cols-4{grid-template-columns:repeat(4,1fr);}
.summary-grid.cols-1{grid-template-columns:1fr;max-width:360px;}
@media(max-width:1100px){.summary-grid,.summary-grid.cols-4{grid-template-columns:repeat(2,1fr);}}
@media(max-width:520px){.summary-grid,.summary-grid.cols-4,.summary-grid.cols-1{grid-template-columns:1fr;}}
.sum-card{background:#fff;border-radius:18px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.06);border-left:5px solid #cbd5e1;}
.sum-card.total{border-left-color:#133c96;}
.sum-card.paid{border-left-color:#16a34a;}
.sum-card.pending{border-left-color:#f59e0b;}
.sum-card.due{border-left-color:#dc2626;}
.sum-card.refunds{border-left-color:#dc2626;}
.sum-lbl{font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.sum-val{font-size:24px;font-weight:900;color:#0f172a;line-height:1.1;}
.sum-sub{font-size:11.5px;color:#94a3b8;margin-top:4px;}

.card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.07);overflow:hidden;margin-bottom:24px;}
.card-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;}
.card-head h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}
.card-head p{font-size:12.5px;color:#94a3b8;margin:2px 0 0;}

.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:#f8fafc;}
th{padding:12px 16px;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;white-space:nowrap;border-bottom:1px solid #f1f5f9;text-align:left;}
td{padding:14px 16px;font-size:13.5px;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle;}
tbody tr:hover{background:#fafbff;}
tbody tr:last-child td{border-bottom:none;}

.hname{font-weight:800;color:#0f172a;}
.hsub{font-size:11.5px;color:#94a3b8;margin-top:2px;}
.mono{font-family:monospace;font-size:12.5px;}
.money{font-weight:800;white-space:nowrap;}

.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:800;white-space:nowrap;}
.pill-clear{color:#166534;background:#f0fdf4;}
.pill-pending{color:#92400e;background:#fffbeb;}
.pill-due{color:#991b1b;background:#fef2f2;}

.progress{height:8px;border-radius:999px;background:#f1f5f9;overflow:hidden;display:flex;min-width:120px;}
.progress span{display:block;height:100%;}
.progress .p-paid{background:#16a34a;}
.progress .p-pending{background:#f59e0b;}

.abtn{border:none;border-radius:9px;padding:7px 14px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s;margin-right:6px;}
.abtn:hover{opacity:.85;}
.abtn-view{background:#eff6ff;color:#1d4ed8;}
.abtn-ok{background:#dcfce7;color:#166534;}
.abtn-no{background:#fee2e2;color:#991b1b;}

.empty{text-align:center;padding:48px 20px;color:#94a3b8;}
.empty-icon{font-size:42px;opacity:.3;margin-bottom:10px;}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-title"><i class="fas fa-percentage"></i> Commission &amp; Payouts</div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="travelix-notification-wrapper" id="travelixNotificationWrapper"><button type="button" class="travelix-notification-btn" id="travelixNotificationToggle"><i class="fa-solid fa-bell"></i><span class="travelix-notification-badge" id="travelixNotificationBadge">0</span></button><div class="travelix-notification-panel" id="travelixNotificationPanel"><div class="travelix-notification-header"><div><h6>Notifications</h6><span id="travelixNotificationCountText">0 new</span></div><div class="travelix-notification-header-actions"><button id="travelixRefreshNotificationsBtn" class="travelix-refresh-notification-btn"><i class="fa-solid fa-arrow-rotate-right"></i></button><button id="travelixReadAllBtn" class="travelix-read-all-btn">Read all</button></div></div><div class="travelix-notification-list" id="travelixNotificationList"></div></div></div>
        <span style="font-size:13px;opacity:.85;">👤 <?= htmlspecialchars($adminName) ?></span>
        <a href="/travelix/admin_manage/manage_hotels_portal.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Manage Hotels
        </a>
    </div>
</div>

<div class="page-body">

    <!-- One unified summary strip — the escalated-refund figure is the only
         one that's actually YOUR money to send; everything else is just
         visibility into hotel payouts. -->
    <div class="summary-grid">
        <div class="sum-card refunds">
            <div class="sum-lbl">Escalated Refunds — You Must Pay</div>
            <div class="sum-val" style="color:#dc2626;"><?= hp_money($totalRefundsPending) ?></div>
            <div class="sum-sub"><?= count($pendingRefunds) ?> refund(s) — hotel missed its SLA</div>
        </div>
        <div class="sum-card total">
            <div class="sum-lbl">Total Owed to Hotels</div>
            <div class="sum-val"><?= hp_money($grandPayout['total']) ?></div>
            <div class="sum-sub">Across <?= count($payoutRows) ?> hotel(s)</div>
        </div>
        <div class="sum-card paid">
            <div class="sum-lbl">Paid &amp; Confirmed</div>
            <div class="sum-val" style="color:#166534;"><?= hp_money($grandPayout['paid']) ?></div>
            <div class="sum-sub"><?= $grandPayout['total'] > 0 ? round($grandPayout['paid'] / $grandPayout['total'] * 100) : 0 ?>% settled</div>
        </div>
        <div class="sum-card pending">
            <div class="sum-lbl">Sent, Awaiting Confirmation</div>
            <div class="sum-val" style="color:#92400e;"><?= hp_money($grandPayout['pending']) ?></div>
        </div>
        <div class="sum-card due">
            <div class="sum-lbl">Not Yet Sent</div>
            <div class="sum-val" style="color:#991b1b;"><?= hp_money($grandPayout['due']) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Payouts Owed to Hotels</h2>
            <p>Guests pay Travelix directly (hotel price + 12% platform fee). Send each hotel their full price — 100%, never reduced by the platform fee — then record the payout below.</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Hotel</th>
                        <th>Total Payout (100%)</th>
                        <th>Confirmed Paid</th>
                        <th>Pending Confirmation</th>
                        <th>Outstanding</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$payoutRows): ?>
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="empty-icon">🏨</div>
                            <div style="font-size:15px;font-weight:700;color:#334155;">Nothing owed yet</div>
                            <div style="margin-top:6px;">Payouts appear once hotels have confirmed bookings under the new payment model.</div>
                        </div>
                    </td></tr>
                <?php else: foreach ($payoutRows as $row):
                    $pl = $row['ledger'];
                    $acctLabel = $row['paymentMethod'] === 'bank'
                        ? ($row['bankName'] ?: 'Bank')
                        : ucfirst($row['paymentMethod'] ?: '—'); ?>
                    <tr>
                        <td>
                            <div class="hname"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="hsub"><?= htmlspecialchars($row['city']) ?><?= $row['email'] ? ' · ' . htmlspecialchars($row['email']) : '' ?></div>
                        </td>
                        <td class="money"><?= hp_money($pl['total']) ?></td>
                        <td class="money" style="color:#166534;"><?= hp_money($pl['paid']) ?>
                            <div class="hsub"><?= $pl['counts']['paid'] ?> booking(s)</div>
                        </td>
                        <td class="money" style="color:#92400e;"><?= hp_money($pl['pending']) ?>
                            <div class="hsub"><?= $pl['counts']['pending'] ?> booking(s)</div>
                        </td>
                        <td class="money" style="color:#991b1b;"><?= hp_money($pl['due']) ?>
                            <div class="hsub"><?= $pl['counts']['due'] ?> booking(s)</div>
                        </td>
                        <td>
                            <?php if ($pl['due'] > 0): ?>
                                <button class="abtn abtn-ok" onclick="recordPayout('<?= htmlspecialchars($row['id']) ?>','<?= htmlspecialchars(addslashes($row['name'])) ?>','<?= htmlspecialchars(hp_money($pl['due'])) ?>','<?= htmlspecialchars(addslashes($acctLabel)) ?>','<?= htmlspecialchars(addslashes($row['paymentAccountNumber'])) ?>')">
                                    <i class="fas fa-paper-plane"></i> Record Payout
                                </button>
                            <?php elseif ($pl['pending'] > 0): ?>
                                <span class="pill pill-pending">Awaiting Hotel</span>
                            <?php else: ?>
                                <span class="pill pill-clear">Fully Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Refunds owed to guests -->
    <div class="card">
        <div class="card-head">
            <h2>Escalated Refunds — Hotel Failed to Pay</h2>
            <p>Refunds are the hotel's job first — this is the one case where you step in: the hotel missed its 48-hour SLA, sent the wrong amount twice, or a guest disputed a refund and it wasn't resolved within 24 hours. The hotel is disabled; pay the guest directly to keep their trust.</p>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Hotel</th>
                        <th>Cancelled</th>
                        <th>Refund %</th>
                        <th>Refund Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pendingRefunds): ?>
                    <tr><td colspan="6">
                        <div class="empty">
                            <div class="empty-icon">✅</div>
                            <div style="font-size:15px;font-weight:700;color:#334155;">No escalated refunds</div>
                            <div style="margin-top:6px;">Every hotel is refunding guests within its SLA — nothing needs your attention here.</div>
                        </div>
                    </td></tr>
                <?php else: foreach ($pendingRefunds as $b):
                    $cancelledAt = (string)($b['cancelledAt'] ?? '');
                    $cancelledTs = $cancelledAt !== '' ? strtotime($cancelledAt) : false;
                    $hid = (string)($b['hotelId'] ?? ''); ?>
                    <tr>
                        <td>
                            <div class="hname"><?= htmlspecialchars((string)($b['userEmail'] ?? '—')) ?></div>
                        </td>
                        <td><?= htmlspecialchars($hotelNameById[$hid] ?? (string)($b['hotelName'] ?? '—')) ?></td>
                        <td class="mono"><?= $cancelledTs ? date('d M Y, H:i', $cancelledTs) : '—' ?></td>
                        <td><?= (float)($b['refundPercent'] ?? 0) ?>%</td>
                        <td class="money" style="color:#dc2626;"><?= hp_money($b['refundAmount'] ?? 0) ?></td>
                        <td>
                            <button class="abtn abtn-ok" onclick="markRefundSent('<?= htmlspecialchars($b['id']) ?>','<?= htmlspecialchars(addslashes((string)($b['userEmail'] ?? ''))) ?>')">
                                <i class="fas fa-check"></i> Mark Sent
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
async function uploadTransferProof(file) {
    const fd = new FormData();
    fd.append('proof', file);
    const upRes = await fetch('/travelix/admin_manage/ajax/upload_transfer_proof.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const upData = await upRes.json();
    if (!upData.success) throw new Error(upData.message || 'Proof upload failed.');
    return upData.path;
}

async function recordPayout(hotelId, hotelName, dueAmount, acctLabel, acctNumber) {
    const acctStep = await Swal.fire({
        icon: 'info',
        title: 'Send this payout',
        html: `
            <div style="text-align:left;font-size:13.5px;line-height:1.9;">
                <div><b>Hotel:</b> ${hotelName}</div>
                <div><b>Amount to send:</b> <span style="color:#166534;font-weight:800;">${dueAmount}</span></div>
                <div><b>Send to:</b> ${acctLabel || '—'}</div>
                <div><b>Account number:</b> <span style="font-family:monospace;">${acctNumber || '—'}</span></div>
                <div style="margin-top:10px;font-size:12px;color:#64748b;">Send the exact amount above to this account, then continue to upload your transfer proof.</div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'I sent it — Continue',
        confirmButtonColor: '#133c96',
    });
    if (!acctStep.isConfirmed) return;

    const res = await Swal.fire({
        icon: 'question',
        title: 'Upload transfer proof',
        html: `Confirm you have sent <b>${dueAmount}</b> to <b>${hotelName}</b> for all their currently outstanding bookings.<br>
               <span style="font-size:12.5px;color:#64748b;">This will stay "Awaiting Hotel Confirmation" until the hotel confirms receipt.</span>`,
        input: 'file',
        inputAttributes: { accept: '.jpg,.jpeg,.png,.webp,.pdf' },
        inputPlaceholder: 'Transfer proof (screenshot/receipt)',
        showCancelButton: true,
        confirmButtonText: 'Yes, Record It',
        confirmButtonColor: '#16a34a',
        preConfirm: (file) => {
            if (!file) { Swal.showValidationMessage('Please upload proof of the transfer.'); return false; }
            return file;
        }
    });
    if (!res.isConfirmed || !res.value) return;

    const note = await Swal.fire({
        icon: 'question',
        title: 'Add a note? (optional)',
        input: 'text',
        inputPlaceholder: 'e.g. transfer reference',
        showCancelButton: true,
        confirmButtonText: 'Continue',
        confirmButtonColor: '#16a34a',
    });

    Swal.fire({ title: 'Uploading proof...', allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false, didOpen: () => Swal.showLoading() });

    try {
        const proofPath = await uploadTransferProof(res.value);

        Swal.update({ title: 'Saving...' });

        const resp = await fetch('/travelix/admin_manage/ajax/submit_payout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ hotelId, note: note.value || '', proofPath })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Could not record the payout.');

        await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#133c96' });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#133c96' });
    }
}

async function markRefundSent(bookingId, guestEmail) {
    const res = await Swal.fire({
        icon: 'question',
        title: 'Mark refund as sent?',
        html: `Confirm you have sent this refund to <b>${guestEmail}</b>'s payout account.<br>
               <span style="font-size:12.5px;color:#64748b;">Upload proof of the transfer — required before this can be recorded.</span>`,
        input: 'file',
        inputAttributes: { accept: '.jpg,.jpeg,.png,.webp,.pdf' },
        inputPlaceholder: 'Transfer proof (screenshot/receipt)',
        showCancelButton: true,
        confirmButtonText: 'Yes, Mark Sent',
        confirmButtonColor: '#16a34a',
        preConfirm: (file) => {
            if (!file) { Swal.showValidationMessage('Please upload proof of the transfer.'); return false; }
            return file;
        }
    });
    if (!res.isConfirmed || !res.value) return;

    Swal.fire({ title: 'Uploading proof...', allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false, didOpen: () => Swal.showLoading() });

    try {
        const proofPath = await uploadTransferProof(res.value);

        Swal.update({ title: 'Saving...' });

        const resp = await fetch('/travelix/admin_manage/ajax/mark_refund_sent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ bookingId, proofPath })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Could not update the refund.');

        await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#133c96' });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#133c96' });
    }
}

</script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script><script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script><script src="/travelix/assets/js/travelix_portal_notifications.js"></script><script>if(!firebase.apps.length)firebase.initializeApp({apiKey:<?= json_encode(FIREBASE_API_KEY) ?>,authDomain:<?= json_encode(FIREBASE_PROJECT_ID.'.firebaseapp.com') ?>,projectId:<?= json_encode(FIREBASE_PROJECT_ID) ?>});window.travelixInitPortalNotifications({db:firebase.firestore(),filters:[['audience','==','admin']],manageUrl:'/travelix/admin_manage/notifications.php'});</script>
</body>
</html>
