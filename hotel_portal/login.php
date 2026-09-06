<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['hotel_staff'])) {
    header('Location: /travelix/hotel_portal/index.php'); exit;
}
$baseUrl = '/travelix';
require_once $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Hotel Staff Login — Travelix</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;min-height:100vh;background:linear-gradient(135deg,#0f3460 0%,#1a5276 50%,#2471a3 100%);display:flex;align-items:center;justify-content:center;padding:20px;}

.auth-wrap{width:100%;max-width:460px;}

.auth-brand{text-align:center;margin-bottom:28px;color:#fff;}
.auth-brand-icon{width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 14px;}
.auth-brand h1{font-size:26px;font-weight:800;margin-bottom:4px;}
.auth-brand p{font-size:14px;opacity:.8;}

.auth-card{background:#fff;border-radius:24px;padding:36px 32px;box-shadow:0 24px 60px rgba(0,0,0,.25);}
.auth-card h2{font-size:22px;font-weight:800;color:#1e293b;margin-bottom:4px;}
.auth-card .sub{font-size:13px;color:#64748b;margin-bottom:26px;}

.field-group{margin-bottom:18px;}
.field-label{font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;display:block;}
.field-input-wrap{position:relative;}
.field-input{width:100%;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:14px;color:#1e293b;background:#fafbfc;transition:.2s;outline:none;}
.field-input:focus{border-color:#2471a3;background:#fff;box-shadow:0 0 0 3px rgba(36,113,163,.12);}
.field-input.error{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.1);}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:15px;padding:0;}
.toggle-pw:hover{color:#374151;}

.submit-btn{width:100%;padding:14px;background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;margin-top:4px;}
.submit-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px rgba(15,52,96,.3);}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}

.divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#94a3b8;font-size:13px;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0;}

.switch-link{text-align:center;font-size:14px;color:#64748b;}
.switch-link a{color:#2471a3;font-weight:700;text-decoration:none;}
.switch-link a:hover{text-decoration:underline;}

.error-msg{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px;display:none;}
.error-msg.show{display:block;}
</style>
</head>
<body>

<div class="auth-wrap">
    <div class="auth-brand">
        <div class="auth-brand-icon">🏨</div>
        <h1>Travelix Hotel Portal</h1>
        <p>Sign in to manage your hotel listings</p>
    </div>

    <div class="auth-card">
        <h2>Welcome Back</h2>
        <p class="sub">Sign in to your hotel staff account</p>

        <div class="error-msg" id="errorMsg"></div>

        <div class="field-group">
            <label class="field-label">Email Address</label>
            <input type="email" class="field-input" id="fEmail" placeholder="staff@yourhotel.com" autocomplete="email">
        </div>
        <div class="field-group">
            <label class="field-label">Password</label>
            <div class="field-input-wrap">
                <input type="password" class="field-input" id="fPassword" placeholder="Enter your password" autocomplete="current-password">
                <button type="button" class="toggle-pw" onclick="togglePw('fPassword',this)"><i class="fas fa-eye"></i></button>
            </div>
        </div>

        <button class="submit-btn" id="loginBtn" onclick="doLogin()">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>

        <div style="text-align:center;margin-top:14px;">
            <a href="/travelix/hotel_portal/forgot_password.php"
               style="font-size:13.5px;color:#2471a3;font-weight:700;text-decoration:none;">
                Forgot your password?
            </a>
        </div>

        <div class="divider">or</div>

        <div class="switch-link">
            New hotel staff accounts are created by your Travelix administrator.
        </div>
    </div>
</div>

<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-auth-compat.js"></script>
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
const auth = firebase.auth();
const db   = firebase.firestore();

function showError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.classList.add('show');
}
function hideError() { document.getElementById('errorMsg').classList.remove('show'); }

if (new URLSearchParams(window.location.search).get('disabled') === '1') {
    showError('Your account was disabled because a guest refund was not sent in time. Contact Travelix admin to have it reviewed.');
}

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
    else { inp.type = 'password'; btn.innerHTML = '<i class="fas fa-eye"></i>'; }
}

async function doLogin() {
    hideError();
    const email = document.getElementById('fEmail').value.trim();
    const pass  = document.getElementById('fPassword').value;
    if (!email || !pass) { showError('Please enter your email and password.'); return; }

    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';

    try {
        const cred = await auth.signInWithEmailAndPassword(email, pass);
        const user = cred.user;

        // Fetch hotel_id and hotel_name from hotel_staff document
        let hotelId   = '';
        let hotelName = '';
        try {
            const staffDoc = await db.collection('hotel_staff').doc(user.uid).get();
            if (staffDoc.exists) {
                hotelId   = staffDoc.data().hotel_id   || '';
                hotelName = staffDoc.data().hotel_name || '';
            }
        } catch(e) { /* continue even if lookup fails */ }

        // Fallback — the hotel_staff doc may not be readable by this account (e.g. Firestore
        // rules), but hotels.staff_uid is the authoritative link and hotels are publicly
        // readable, so look the hotel up directly rather than leaving the staff unassigned.
        if (!hotelId) {
            try {
                const hotelSnap = await db.collection('hotels').where('staff_uid', '==', user.uid).limit(1).get();
                if (!hotelSnap.empty) {
                    hotelId   = hotelSnap.docs[0].id;
                    hotelName = hotelSnap.docs[0].data().name || '';
                }
            } catch(e) { /* continue even if lookup fails */ }
        }

        // Set PHP session. The server verifies this account is actually linked to a
        // hotel (and that the hotel is active) and rejects the login otherwise.
        const res = await fetch('/travelix/hotel_portal/ajax/set_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                uid:        user.uid,
                email:      user.email,
                name:       user.displayName || email.split('@')[0],
                hotel_id:   hotelId,
                hotel_name: hotelName
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = '/travelix/hotel_portal/index.php';
        } else {
            // Not hotel staff (or hotel disabled) — drop the Firebase session too.
            try { await auth.signOut(); } catch(e) {}
            showError(data.message || 'Session error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    } catch(err) {
        const msgs = {
            'auth/user-not-found':    'No account found with this email.',
            'auth/wrong-password':    'Incorrect password. Please try again.',
            'auth/invalid-email':     'Invalid email address.',
            'auth/too-many-requests': 'Too many attempts. Please try again later.',
            'auth/invalid-credential':'Invalid email or password.',
        };
        showError(msgs[err.code] || err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    }
}

// Allow Enter key
document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>
