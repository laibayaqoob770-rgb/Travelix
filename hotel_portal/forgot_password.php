<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['hotel_staff'])) {
    header('Location: /travelix/hotel_portal/index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/travelix/images/favicon.png">
<title>Reset Password — Travelix Hotel Portal</title>
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

.steps-row{display:flex;align-items:center;gap:8px;margin-bottom:24px;}
.step-dot{flex:1;height:5px;border-radius:999px;background:#e2e8f0;transition:.3s;}
.step-dot.active{background:linear-gradient(135deg,#0f3460,#2471a3);}

.field-group{margin-bottom:18px;}
.field-label{font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;display:block;}
.field-input-wrap{position:relative;}
.field-input{width:100%;padding:12px 16px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:14px;color:#1e293b;background:#fafbfc;transition:.2s;outline:none;}
.field-input:focus{border-color:#2471a3;background:#fff;box-shadow:0 0 0 3px rgba(36,113,163,.12);}
.field-input.error{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.1);}
.code-input{text-align:center;font-size:28px;font-weight:900;letter-spacing:14px;font-family:monospace;padding:14px 16px;}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:15px;padding:0;}
.toggle-pw:hover{color:#374151;}
.field-hint{font-size:11.5px;color:#94a3b8;margin-top:5px;}

.submit-btn{width:100%;padding:14px;background:linear-gradient(135deg,#0f3460,#2471a3);color:#fff;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;margin-top:4px;}
.submit-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px rgba(15,52,96,.3);}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}

.link-btn{background:none;border:none;color:#2471a3;font-weight:700;font-size:13.5px;cursor:pointer;padding:0;text-decoration:none;}
.link-btn:hover{text-decoration:underline;}
.link-btn:disabled{color:#94a3b8;cursor:not-allowed;text-decoration:none;}

.msg{border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px;display:none;}
.msg.show{display:block;}
.msg.error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
.msg.info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;}

.sent-to{background:#f8faff;border:1px solid #dce8ff;border-radius:12px;padding:12px 16px;font-size:13px;color:#334155;margin-bottom:18px;}
.sent-to b{color:#0f3460;}
</style>
</head>
<body>

<div class="auth-wrap">
    <div class="auth-brand">
        <div class="auth-brand-icon">🔐</div>
        <h1>Reset Your Password</h1>
        <p>Travelix Hotel Portal</p>
    </div>

    <div class="auth-card">
        <div class="steps-row">
            <div class="step-dot active" id="dot1"></div>
            <div class="step-dot" id="dot2"></div>
            <div class="step-dot" id="dot3"></div>
        </div>

        <div class="msg" id="msgBox"></div>

        <!-- Step 1: email -->
        <div id="step1">
            <h2>Verify Your Email</h2>
            <p class="sub">We'll send a 6-digit code to your registered hotel staff email.</p>

            <div class="field-group">
                <label class="field-label">Email Address</label>
                <input type="email" class="field-input" id="fEmail" placeholder="staff@yourhotel.com" autocomplete="email">
            </div>

            <button class="submit-btn" id="sendBtn" onclick="sendCode()">
                <i class="fas fa-paper-plane"></i> Send Verification Code
            </button>
        </div>

        <!-- Step 2: code -->
        <div id="step2" style="display:none;">
            <h2>Enter the Code</h2>
            <p class="sub">Check your inbox for the 6-digit verification code.</p>

            <div class="sent-to">
                Code sent to <b id="sentToEmail"></b>
            </div>

            <div class="field-group">
                <label class="field-label">6-Digit Code</label>
                <input type="text" class="field-input code-input" id="fCode" maxlength="6" inputmode="numeric" placeholder="000000" autocomplete="one-time-code">
                <div class="field-hint">The code expires in 10 minutes.</div>
            </div>

            <button class="submit-btn" id="verifyBtn" onclick="verifyCode()">
                <i class="fas fa-check"></i> Verify Code
            </button>

            <div style="text-align:center;margin-top:14px;">
                <button class="link-btn" id="resendBtn" onclick="sendCode(true)">Resend code</button>
            </div>
        </div>

        <!-- Step 3: new password -->
        <div id="step3" style="display:none;">
            <h2>Set a New Password</h2>
            <p class="sub">Choose a strong password you haven't used before.</p>

            <div class="field-group">
                <label class="field-label">New Password</label>
                <div class="field-input-wrap">
                    <input type="password" class="field-input" id="fPassword" placeholder="At least 6 characters" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('fPassword',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label">Confirm New Password</label>
                <div class="field-input-wrap">
                    <input type="password" class="field-input" id="fPassword2" placeholder="Re-enter your password" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('fPassword2',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <button class="submit-btn" id="resetBtn" onclick="resetPassword()">
                <i class="fas fa-key"></i> Update Password
            </button>
        </div>

        <div style="text-align:center;margin-top:20px;">
            <a href="/travelix/hotel_portal/login.php" class="link-btn">
                <i class="fas fa-arrow-left"></i> Back to Sign In
            </a>
        </div>
    </div>
</div>

<script>
const API = '/travelix/hotel_portal/ajax/password_reset.php';
let resetEmail = '';
let resetToken = '';

function showMsg(text, type) {
    const el = document.getElementById('msgBox');
    el.textContent = text;
    el.className = 'msg show ' + (type || 'error');
}
function hideMsg() { document.getElementById('msgBox').className = 'msg'; }

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
    else { inp.type = 'password'; btn.innerHTML = '<i class="fas fa-eye"></i>'; }
}

function goStep(n) {
    [1,2,3].forEach(i => {
        document.getElementById('step' + i).style.display = (i === n) ? 'block' : 'none';
        document.getElementById('dot' + i).classList.toggle('active', i <= n);
    });
    hideMsg();
}

async function post(payload) {
    const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    });
    return res.json();
}

async function sendCode(isResend) {
    hideMsg();
    const email = isResend ? resetEmail : document.getElementById('fEmail').value.trim();
    if (!email) { showMsg('Please enter your email address.'); return; }

    const btn = isResend ? document.getElementById('resendBtn') : document.getElementById('sendBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = isResend ? 'Sending...' : '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
        const data = await post({ action: 'send_code', email });
        if (!data.success) { showMsg(data.message); return; }

        resetEmail = email;
        document.getElementById('sentToEmail').textContent = email;
        goStep(2);
        showMsg(data.message, 'info');
        document.getElementById('fCode').focus();
    } catch (e) {
        showMsg('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

async function verifyCode() {
    hideMsg();
    const code = document.getElementById('fCode').value.trim();
    if (code.length !== 6) { showMsg('Please enter the 6-digit code.'); return; }

    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    try {
        const data = await post({ action: 'verify_code', email: resetEmail, code });
        if (!data.success) { showMsg(data.message); return; }

        resetToken = data.token;
        goStep(3);
        document.getElementById('fPassword').focus();
    } catch (e) {
        showMsg('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Verify Code';
    }
}

async function resetPassword() {
    hideMsg();
    const p1 = document.getElementById('fPassword').value;
    const p2 = document.getElementById('fPassword2').value;

    if (p1.length < 6) { showMsg('Password must be at least 6 characters.'); return; }
    if (p1 !== p2)     { showMsg('Passwords do not match.'); return; }

    const btn = document.getElementById('resetBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    try {
        const data = await post({ action: 'reset_password', email: resetEmail, token: resetToken, password: p1 });
        if (!data.success) { showMsg(data.message); return; }

        await Swal.fire({
            icon: 'success',
            title: 'Password Updated',
            text: 'You can now sign in with your new password.',
            confirmButtonColor: '#0f3460'
        });
        window.location.href = '/travelix/hotel_portal/login.php';
    } catch (e) {
        showMsg('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
    }
}

// Digits only in the code box
document.getElementById('fCode').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

// Enter key advances the visible step
document.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    if (document.getElementById('step1').style.display !== 'none') sendCode();
    else if (document.getElementById('step2').style.display !== 'none') verifyCode();
    else resetPassword();
});
</script>
</body>
</html>
