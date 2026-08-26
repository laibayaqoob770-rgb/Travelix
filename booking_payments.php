<?php
/* ================================================================
   New Booking Payments — Admin
   Guests now pay Travelix directly (centralized payment model), so
   admin — not the hotel — is the one who can actually verify a guest's
   payment proof. Hotels can only confirm a booking (room availability)
   after admin has verified the payment.
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

require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/commission_lib.php';

$projectId = FIREBASE_PROJECT_ID;
$saPath    = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

$adminName = trim(($currentUser['first_name'] ?? 'Admin') . ' ' . ($currentUser['last_name'] ?? ''));

$pendingBookings = hp_firestore_query($saPath, $projectId, 'hotel_bookings', 'payment.status', 'awaiting_verification');
$allVerifiedBookings = hp_firestore_query($saPath, $projectId, 'hotel_bookings', 'payment.status', 'verified');
$verifiedBookings = array_values(array_filter(
    $allVerifiedBookings,
    fn($b) => in_array(strtolower((string)($b['hotelPayoutStatus'] ?? 'not_sent')), ['', 'not_sent'], true)
));
$sentBookings = array_values(array_filter(
    $allVerifiedBookings,
    fn($b) => strtolower((string)($b['hotelPayoutStatus'] ?? '')) === 'sent'
));
usort($pendingBookings, function ($a, $b) {
    return (string)($a['createdAt'] ?? '') <=> (string)($b['createdAt'] ?? '');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>New Booking Payments — Travelix Admin</title>
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

.page-body{max-width:1280px;margin:0 auto;padding:32px 20px 48px;}

.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:26px;}
.sum-card{background:#fff;border-radius:18px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.06);border-left:5px solid #f59e0b;}
.sum-lbl{font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.sum-val{font-size:24px;font-weight:900;color:#92400e;line-height:1.1;}
.sum-sub{font-size:11.5px;color:#94a3b8;margin-top:4px;}

.card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 8px 28px rgba(15,23,42,.07);overflow:hidden;margin-bottom:24px;}
.card-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;}
.card-head h2{font-size:17px;font-weight:800;color:#0f172a;margin:0;}
.card-head p{font-size:12.5px;color:#94a3b8;margin:2px 0 0;}

.booking-row{padding:18px 24px;border-bottom:1px solid #f8fafc;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.booking-row:last-child{border-bottom:none;}
.b-guest{font-weight:800;color:#0f172a;font-size:14.5px;}
.b-sub{font-size:12px;color:#94a3b8;margin-top:2px;}
.b-amount{font-size:20px;font-weight:900;color:#0f172a;white-space:nowrap;}
.proof-link{display:inline-block;margin-top:6px;color:#1d4ed8;font-weight:700;text-decoration:underline;font-size:12.5px;}
.mono{font-family:monospace;}

.abtn{border:none;border-radius:9px;padding:9px 16px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s;margin-right:6px;}
.abtn:hover{opacity:.85;}
.abtn-ok{background:#dcfce7;color:#166534;}
.abtn-no{background:#fee2e2;color:#991b1b;}
.transfer-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;}
.proof-picker{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px dashed #93c5fd;border-radius:11px;background:#eff6ff;color:#1d4ed8;font-size:12.5px;font-weight:800;cursor:pointer;max-width:240px;}
.proof-picker:hover{background:#dbeafe}.proof-picker input{display:none}.proof-file-name{max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:#64748b;}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:800;}
@media(max-width:760px){.summary-grid{grid-template-columns:1fr}.booking-row{align-items:flex-start}.transfer-actions{justify-content:flex-start}.topbar{padding:0 14px}.topbar span{display:none}}

.empty{text-align:center;padding:48px 20px;color:#94a3b8;}
.empty-icon{font-size:42px;opacity:.3;margin-bottom:10px;}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-title"><i class="fas fa-file-invoice-dollar"></i> New Booking Payments</div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="travelix-notification-wrapper" id="travelixNotificationWrapper"><button type="button" class="travelix-notification-btn" id="travelixNotificationToggle"><i class="fa-solid fa-bell"></i><span class="travelix-notification-badge" id="travelixNotificationBadge">0</span></button><div class="travelix-notification-panel" id="travelixNotificationPanel"><div class="travelix-notification-header"><div><h6>Notifications</h6><span id="travelixNotificationCountText">0 new</span></div><div class="travelix-notification-header-actions"><button id="travelixRefreshNotificationsBtn" class="travelix-refresh-notification-btn"><i class="fa-solid fa-arrow-rotate-right"></i></button><button id="travelixReadAllBtn" class="travelix-read-all-btn">Read all</button></div></div><div class="travelix-notification-list" id="travelixNotificationList"></div></div></div>
        <span style="font-size:13px;opacity:.85;">👤 <?= htmlspecialchars($adminName) ?></span>
        <a href="/travelix/admin_manage/manage_hotels_portal.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Manage Hotels
        </a>
    </div>
</div>

<div class="page-body">

    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-lbl">Awaiting Your Verification</div>
            <div class="sum-val"><?= count($pendingBookings) ?></div>
            <div class="sum-sub">Booking(s) with payment proof not yet reviewed</div>
        </div>
        <div class="sum-card" style="border-left-color:#2563eb;">
            <div class="sum-lbl">Verified — Hotel Payment Due</div>
            <div class="sum-val" style="color:#1d4ed8;"><?= count($verifiedBookings) ?></div>
            <div class="sum-sub">Verified booking(s) waiting for hotel transfer proof</div>
        </div>
        <div class="sum-card" style="border-left-color:#f59e0b;">
            <div class="sum-lbl">Hotel Confirmation Pending</div>
            <div class="sum-val" style="color:#b45309;"><?= count($sentBookings) ?></div>
            <div class="sum-sub">Transfer proof submitted; only the hotel can confirm receipt</div>
        </div>
    </div>

    <div class="card" style="margin-top:24px;">
        <div class="card-head">
            <h2>Verified Payments — Pay Each Hotel</h2>
            <p>This is a separate audited step. Send only the hotel charges to its saved account and attach transfer proof. Travelix commission remains with Travelix.</p>
        </div>
        <div>
        <?php if (!$verifiedBookings): ?>
            <div class="empty"><div class="empty-icon">🏨</div><div style="font-size:15px;font-weight:700;color:#334155;">No hotel payments waiting</div></div>
        <?php else: foreach ($verifiedBookings as $b):
            $hotel = hp_firestore_get($saPath, $projectId, 'hotels/' . (string)($b['hotelId'] ?? '')) ?: [];
            $method = ucfirst((string)($hotel['paymentMethod'] ?? ''));
            $bank = (string)($hotel['bankName'] ?? '');
        ?>
            <div class="booking-row">
                <div>
                    <div class="b-guest"><?= htmlspecialchars((string)($b['hotelName'] ?? 'Hotel')) ?></div>
                    <div class="b-sub"><?= htmlspecialchars($method . ($bank ? ' · '.$bank : '')) ?> · <span class="mono"><?= htmlspecialchars((string)($hotel['paymentAccountNumber'] ?? 'Missing account')) ?></span></div>
                    <div class="b-sub">Booking <?= htmlspecialchars((string)$b['id']) ?> · User payment verified</div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div class="b-amount"><?= hp_money($b['hotelPrice'] ?? 0) ?></div>
                    <div class="transfer-actions">
                        <label class="proof-picker" for="hotelProof-<?= htmlspecialchars((string)$b['id']) ?>"><i class="fas fa-paperclip"></i><span class="proof-file-name" id="hotelProofName-<?= htmlspecialchars((string)$b['id']) ?>">Attach transfer proof</span><input type="file" id="hotelProof-<?= htmlspecialchars((string)$b['id']) ?>" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="showProofName('<?= htmlspecialchars((string)$b['id']) ?>',this)"></label>
                        <button type="button" class="abtn abtn-ok" onclick="payHotel('<?= htmlspecialchars((string)$b['id']) ?>')"><i class="fas fa-paper-plane"></i> Submit Transfer Proof</button>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Sent to Hotels — Awaiting Their Confirmation</h2><p>These are not confirmed by admin. The hotel must open the proof, check its account and confirm receipt; this page and dashboard then update automatically.</p></div>
        <?php if(!$sentBookings): ?><div class="empty"><div class="empty-icon">✅</div><div>No hotel confirmations pending.</div></div>
        <?php else: foreach($sentBookings as $b): ?>
            <div class="booking-row"><div><div class="b-guest"><?= htmlspecialchars((string)($b['hotelName']??'Hotel')) ?></div><div class="b-sub">Booking <?= htmlspecialchars((string)$b['id']) ?></div><?php if(!empty($b['hotelPayoutProof'])):?><a class="proof-link" target="_blank" href="<?= htmlspecialchars((string)$b['hotelPayoutProof']) ?>">View submitted transfer proof</a><?php endif;?></div><div style="display:flex;align-items:center;gap:12px;"><div class="b-amount"><?= hp_money($b['hotelPayoutAmount']??$b['hotelPrice']??0) ?></div><span class="status-pill"><i class="fas fa-hourglass-half"></i> Awaiting Hotel</span></div></div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Bookings Awaiting Payment Verification</h2>
            <p>Guests pay into Travelix's own account — you're the only one who can actually verify these proofs. Confirming lets the hotel then confirm the room.</p>
        </div>
        <div>
        <?php if (!$pendingBookings): ?>
            <div class="empty">
                <div class="empty-icon">✅</div>
                <div style="font-size:15px;font-weight:700;color:#334155;">Nothing waiting on you</div>
                <div style="margin-top:6px;">New bookings with a payment proof will appear here.</div>
            </div>
        <?php else: foreach ($pendingBookings as $b):
            $payment = is_array($b['payment'] ?? null) ? $b['payment'] : [];
            $proofUrl = (string)($payment['proofImagePath'] ?? '');
        ?>
            <div class="booking-row">
                <div>
                    <div class="b-guest"><?= htmlspecialchars((string)($b['userEmail'] ?? 'Guest')) ?></div>
                    <div class="b-sub"><?= htmlspecialchars((string)($b['hotelName'] ?? 'Hotel')) ?> · <?= htmlspecialchars((string)($b['arrivalDate'] ?? '-')) ?> to <?= htmlspecialchars((string)($b['departureDate'] ?? '-')) ?></div>
                    <div class="b-sub"><?= htmlspecialchars(ucfirst((string)($payment['method'] ?? ''))) ?> · <span class="mono"><?= htmlspecialchars((string)($payment['accountNumber'] ?? '')) ?></span></div>
                    <?php if ($proofUrl): ?>
                        <a href="<?= htmlspecialchars($proofUrl) ?>" target="_blank" class="proof-link">View Payment Proof</a>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:16px;">
                    <div class="b-amount"><?= hp_money($b['totalCharged'] ?? $b['hotelPrice'] ?? 0) ?></div>
                    <div>
                        <button type="button" class="abtn abtn-ok" onclick="verifyPayment('<?= htmlspecialchars($b['id']) ?>')">
                            <i class="fas fa-check"></i> Verify
                        </button>
                        <button type="button" class="abtn abtn-no" onclick="rejectPayment('<?= htmlspecialchars($b['id']) ?>')">
                            <i class="fas fa-xmark"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<script>
async function verifyPayment(bookingId) {
    const res = await Swal.fire({
        icon: 'question',
        title: 'Verify this payment?',
        text: 'Confirm you have checked the proof and the payment actually arrived in the Travelix account.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Verified',
        confirmButtonColor: '#16a34a'
    });
    if (!res.isConfirmed) return;

    try {
        const resp = await fetch('/travelix/admin_manage/ajax/verify_booking_payment.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ bookingId })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Could not verify the payment.');
        await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#133c96' });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#133c96' });
    }
}

async function rejectPayment(bookingId) {
    const note = await Swal.fire({
        icon: 'warning',
        title: 'Reject this payment?',
        text: 'The guest will be told their payment could not be verified and the booking will be cancelled.',
        input: 'text',
        inputPlaceholder: 'Reason (optional)',
        showCancelButton: true,
        confirmButtonText: 'Yes, Reject',
        confirmButtonColor: '#dc2626'
    });
    if (!note.isConfirmed) return;

    try {
        const resp = await fetch('/travelix/admin_manage/ajax/reject_booking_payment.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ bookingId, reason: note.value || '' })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Could not reject the payment.');
        await Swal.fire({ icon: 'success', title: data.message, confirmButtonColor: '#133c96' });
        window.location.reload();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Failed', text: err.message, confirmButtonColor: '#133c96' });
    }
}

async function payHotel(bookingId) {
    const input = document.getElementById('hotelProof-' + bookingId);
    const file = input?.files?.[0];
    if (!file) { Swal.fire({icon:'warning',title:'Transfer Proof Required',text:'Pay the hotel, then attach the transfer proof.',confirmButtonColor:'#133c96'}); return; }
    const confirmation = await Swal.fire({icon:'question',title:'Record hotel payment?',text:'Confirm the amount has actually been sent to the hotel account shown.',showCancelButton:true,confirmButtonText:'Yes, Upload Proof',confirmButtonColor:'#16a34a'});
    if (!confirmation.isConfirmed) return;
    try {
        Swal.fire({title:'Uploading transfer proof...',allowOutsideClick:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()});
        const fd = new FormData(); fd.append('proof', file);
        const uploadResp = await fetch('/travelix/admin_manage/ajax/upload_transfer_proof.php',{method:'POST',body:fd,credentials:'same-origin'});
        const upload = await uploadResp.json();
        if (!upload.success) throw new Error(upload.message || 'Proof upload failed.');
        const resp = await fetch('/travelix/admin_manage/ajax/pay_booking_hotel.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({bookingId,proofPath:upload.path})});
        const data = await resp.json(); if(!data.success) throw new Error(data.message || 'Could not record hotel payment.');
        await Swal.fire({icon:'success',title:'Transfer Proof Submitted',text:data.message,confirmButtonColor:'#133c96'}); location.reload();
    } catch (err) { Swal.fire({icon:'error',title:'Failed',text:err.message,confirmButtonColor:'#133c96'}); }
}
function showProofName(bookingId,input){const el=document.getElementById('hotelProofName-'+bookingId);if(el)el.textContent=input.files?.[0]?.name||'Attach transfer proof';}
</script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script><script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script><script src="/travelix/assets/js/travelix_portal_notifications.js"></script>
<script>if(!firebase.apps.length)firebase.initializeApp({apiKey:<?= json_encode(FIREBASE_API_KEY) ?>,authDomain:<?= json_encode(FIREBASE_PROJECT_ID.'.firebaseapp.com') ?>,projectId:<?= json_encode(FIREBASE_PROJECT_ID) ?>});const adminPaymentDb=firebase.firestore();window.travelixInitPortalNotifications({db:adminPaymentDb,filters:[['audience','==','admin']],manageUrl:'/travelix/admin_manage/notifications.php'});let paymentQueueReady=false;adminPaymentDb.collection('hotel_bookings').where('payment.status','==','verified').onSnapshot(()=>{if(paymentQueueReady){window.location.reload()}paymentQueueReady=true});</script>
</body>
</html>
