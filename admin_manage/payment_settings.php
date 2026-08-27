<?php
/**
 * Admin — configure the single central Travelix payment account that all
 * guests now pay into (instead of each hotel's own account). Stored at
 * Firestore doc app_config/payment_account.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php'); exit;
}
$currentUser = $_SESSION['user'] ?? [];
if (strtolower((string)($currentUser['role'] ?? '')) !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/admin_dashboard.php'); exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Settings — Travelix Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;min-height:100vh;}
.admin-layout{display:flex;gap:24px;padding:16px;min-height:100vh;}
.admin-main{flex:1;min-width:0;max-width:720px;}
@media (max-width:991.98px){.admin-layout{display:block;padding:0;}.admin-main{padding:0 16px 16px;max-width:none;}}

.page-header{background:linear-gradient(135deg,#133c96,#1e50c0,#2d6af0);border-radius:22px;padding:26px 32px;color:#fff;margin-bottom:20px;}
.page-header h1{font-size:20px;font-weight:800;margin:0 0 3px;}
.page-header p{font-size:13px;opacity:.85;margin:0;}

.form-card{background:#fff;border-radius:22px;box-shadow:0 2px 14px rgba(0,0,0,.07);padding:28px;}
.field-group{margin-bottom:20px;}
.field-label{font-size:13px;font-weight:700;color:#374151;margin-bottom:7px;display:block;}
.field-input{width:100%;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:14px;color:#1e293b;background:#fafbfc;outline:none;}
.field-input:focus{border-color:#133c96;background:#fff;}
.notice{background:#eff6ff;border:1px solid rgba(29,78,216,.15);border-radius:14px;padding:14px 16px;font-size:13px;color:#1e3a8a;margin-bottom:22px;line-height:1.6;}
.save-btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#133c96,#2d6af0);color:#fff;border:none;padding:13px 28px;border-radius:14px;font-weight:800;font-size:15px;cursor:pointer;}
.save-btn:disabled{opacity:.6;cursor:not-allowed;}
</style>
</head>
<body>
<div class="admin-layout">
    <?php include $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1>💳 Payment Settings</h1>
            <p>The single account every guest pays into when booking a hotel.</p>
        </div>

        <div class="form-card">
            <div class="notice">
                <i class="fas fa-info-circle"></i>
                Every guest booking sends payment here. The guest pays the hotel's price plus a 12% platform fee; send each hotel its full price with proof from <a href="/travelix/admin_manage/booking_payments.php">Booking Payments</a>.
            </div>

            <div class="field-group">
                <label class="field-label">Payment Method</label>
                <select class="field-input" id="fMethod">
                    <option value="">— Select Method —</option>
                    <option value="easypaisa">EasyPaisa</option>
                    <option value="jazzcash">JazzCash</option>
                    <option value="raast">Raast</option>
                    <option value="bank">Bank Account</option>
                </select>
            </div>

            <div class="field-group">
                <label class="field-label">Account Number</label>
                <input type="text" class="field-input" id="fAccountNumber" placeholder="e.g. 03XXXXXXXXX">
            </div>

            <div class="field-group">
                <label class="field-label">Account Name <span style="color:#94a3b8;font-weight:500;">(optional)</span></label>
                <input type="text" class="field-input" id="fAccountName" placeholder="e.g. Travelix Pvt Ltd">
            </div>

            <div class="field-group">
                <label class="field-label">Till ID <span style="color:#94a3b8;font-weight:500;">(optional — for JazzCash/Raast QR "scan to pay" merchant tills)</span></label>
                <input type="text" class="field-input" id="fTillId" placeholder="e.g. 984046038">
            </div>

            <div class="field-group">
                <label class="field-label">Payment QR Code <span style="color:#94a3b8;font-weight:500;">(optional — guests scan this to pay instead of typing the account number)</span></label>
                <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <img id="qrPreview" src="" alt="Payment QR" style="width:160px;height:160px;object-fit:contain;border:1.5px solid #e2e8f0;border-radius:14px;background:#fafbfc;display:none;">
                    <div>
                        <input type="file" class="field-input" id="fQrFile" accept="image/*" style="max-width:280px;">
                        <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">JPG, PNG, or WEBP.</p>
                    </div>
                </div>
            </div>

            <button type="button" class="save-btn" id="saveBtn" onclick="savePaymentAccount()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </main>
</div>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore-compat.js"></script>
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

const fMethod = document.getElementById('fMethod');
const fAccountNumber = document.getElementById('fAccountNumber');
const fAccountName = document.getElementById('fAccountName');
const fTillId = document.getElementById('fTillId');
const fQrFile = document.getElementById('fQrFile');
const qrPreview = document.getElementById('qrPreview');
const saveBtn = document.getElementById('saveBtn');

let currentQrPath = '';

db.collection('app_config').doc('payment_account').get().then(doc => {
    if (!doc.exists) return;
    const data = doc.data();
    fMethod.value = data.method || '';
    fAccountNumber.value = data.accountNumber || '';
    fAccountName.value = data.accountName || '';
    fTillId.value = data.tillId || '';
    if (data.qrImagePath) {
        currentQrPath = data.qrImagePath;
        qrPreview.src = data.qrImagePath;
        qrPreview.style.display = 'block';
    }
}).catch(err => {
    console.warn('Could not load payment account:', err);
});

fQrFile.addEventListener('change', function () {
    const file = this.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => { qrPreview.src = e.target?.result || ''; qrPreview.style.display = 'block'; };
    reader.readAsDataURL(file);
});

async function uploadQrIfSelected() {
    const file = fQrFile.files?.[0];
    if (!file) return currentQrPath;

    const formData = new FormData();
    formData.append('qr', file);

    const resp = await fetch('/travelix/admin_manage/ajax/upload_payment_qr.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });
    const result = await resp.json();
    if (!result.success) throw new Error(result.message || 'Could not upload the QR image.');
    return result.path;
}

async function savePaymentAccount() {
    const method = fMethod.value;
    const accountNumber = fAccountNumber.value.trim();
    const accountName = fAccountName.value.trim();
    const tillId = fTillId.value.trim();

    if (!method) { Swal.fire({icon:'warning',title:'Required',text:'Select a payment method.',confirmButtonColor:'#133c96'}); return; }
    if (!accountNumber) { Swal.fire({icon:'warning',title:'Required',text:'Enter the account number.',confirmButtonColor:'#133c96'}); return; }

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const qrImagePath = await uploadQrIfSelected();

        await db.collection('app_config').doc('payment_account').set({
            method, accountNumber, accountName, tillId, qrImagePath,
            updatedAt: firebase.firestore.FieldValue.serverTimestamp(),
            updatedBy: <?= json_encode((string)($currentUser['email'] ?? 'admin')) ?>
        });

        Swal.fire({icon:'success',title:'Saved',text:'The central payment account has been updated.',confirmButtonColor:'#133c96'});
    } catch (e) {
        Swal.fire({icon:'error',title:'Save Failed',text:e.message,confirmButtonColor:'#133c96'});
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
    }
}
</script>
</body>
</html>
