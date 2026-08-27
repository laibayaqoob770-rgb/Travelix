<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_login_session') {
    header('Content-Type: application/json');

    $_SESSION['user'] = [
        'uid' => $_POST['uid'] ?? '',
        'first_name' => $_POST['first_name'] ?? 'User',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'profile_image' => $_POST['profile_image'] ?? '/travelix/images/default_profile.png',
        'role' => $_POST['role'] ?? 'user',
        'notification_count' => 0
    ];

    echo json_encode([
        'success' => true
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #0a5f70;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        .auth-left,
        .auth-right {
            width: 50%;
            min-height: 100vh;
            position: relative;
        }

        .auth-left {
            background: #075d70;
            padding: 32px 42px 40px;
            color: #fff;
            display: flex;
            align-items: center;
        }

        .auth-right {
            background: #0d95a4;
            clip-path: polygon(22% 0, 100% 0, 100% 100%, 0 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            padding: 30px;
            position: relative;
        }

        .auth-card {
            width: 100%;
            max-width: 520px;
        }

        .auth-title {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .form-label {
            font-size: 17px;
            margin-bottom: 8px;
            color: #eafcff;
        }

        .form-control {
            height: 60px;
            border-radius: 14px;
            border: none;
            font-size: 18px;
            padding: 12px 20px;
            background: #f3f3f3;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(20,132,180,0.25);
            border: none;
        }

        .password-wrap {
            position: relative;
        }

        .eye-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 0;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .eye-btn img {
            width: 24px;
            height: 24px;
            object-fit: contain;
            display: block;
        }

        .login-btn {
            width: 100%;
            height: 58px;
            border: none;
            border-radius: 14px;
            background: #16b7be;
            color: #053645;
            font-size: 20px;
            font-weight: 800;
            margin-top: 18px;
            transition: 0.3s ease;
        }

        .login-btn:hover {
            background: #11aab1;
        }

        .auth-right-inner {
            width: 100%;
            max-width: 420px;
            margin-left: 80px;
            position: relative;
        }

        .join-title {
            font-size: 60px;
            font-weight: 800;
            margin-bottom: 14px;
            text-align: left;
        }

        .join-text {
            font-size: 18px;
            line-height: 1.8;
            color: #eafcff;
            max-width: 320px;
            margin-bottom: 28px;
            text-align: justify;
        }

        .or-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 30px 0 28px;
            color: #fff;
            font-weight: 700;
            font-size: 17px;
        }

        .or-line {
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.7);
        }

        .google-btn {
            width: 100%;
            min-height: 58px;
            border: none;
            border-radius: 14px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            box-shadow: 0 10px 24px rgba(0,0,0,0.12);
        }

        .google-btn img {
            width: 24px;
            height: 24px;
        }

        .cross-link {
            position: absolute;
            top: 28px;
            right: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            transition: 0.3s ease;
            z-index: 30;
            text-decoration: none;
        }

        .cross-link:hover {
            background: rgba(255, 255, 255, 0.26);
            transform: scale(1.05);
        }

        .cross-link img {
            width: 18px;
            height: 18px;
            object-fit: contain;
            display: block;
        }

        .bottom-text {
            margin-top: 22px;
            text-align: center;
            font-size: 17px;
            color: #fff;
        }

        .bottom-text a {
            color: #11d6e0;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-row {
            text-align: right;
            margin-top: -4px;
            margin-bottom: 8px;
        }

        .forgot-link {
            color: #11d6e0;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-link:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        @media (max-width: 991px) {
            .auth-wrapper {
                flex-direction: column;
            }

            .auth-left,
            .auth-right {
                width: 100%;
                min-height: auto;
            }

            .auth-left {
                padding: 32px 24px 36px;
            }

            .auth-right {
                clip-path: none;
                padding: 40px 24px 60px;
            }

            .auth-card {
                max-width: 100%;
            }

            .auth-right-inner {
                margin-left: 0;
                max-width: 520px;
                padding-top: 20px;
            }

            .join-title {
                font-size: 42px;
            }

            .cross-link {
                position: fixed;
                top: 20px;
                right: 20px;
                left: auto;
                z-index: 9999;
                width: 44px;
                height: 44px;
                background: rgba(255, 255, 255, 0.20);
                backdrop-filter: blur(4px);
            }
        }

        @media (max-width: 576px) {
            .auth-left {
                padding: 28px 18px 34px;
            }

            .auth-title {
                font-size: 34px;
            }

            .form-control {
                height: 56px;
                font-size: 17px;
                padding: 12px 18px;
            }

            .cross-link {
                top: 14px;
                right: 14px;
                width: 42px;
                height: 42px;
            }

            .cross-link img {
                width: 16px;
                height: 16px;
            }

            .join-title {
                font-size: 36px;
            }

            .join-text {
                font-size: 16px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-left">
        <div class="auth-card">
            <div class="auth-title">Welcome Back</div>

            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" placeholder="Email" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="password-wrap">
                        <input type="password" class="form-control" id="password" placeholder="Password" minlength="8" required>
                        <button type="button" class="eye-btn toggle-password" data-target="password">
                            <img src="../images/eye_close.png" alt="Toggle Password" class="eye-icon">
                        </button>
                    </div>
                </div>

                <div class="forgot-row">
                    <a href="forget_password.php"
                    class="forgot-link page-loader-link"
                    data-href="forget_password.php">
                        Forgot Password?
                    </a>
                </div>

                <button type="submit" class="login-btn">Log in</button>
            </form>

            <div class="bottom-text">
                Don’t have an account?
                <a href="signup.php" class="page-loader-link" data-href="signup.php">Sign up</a>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <a href="../dashboard/user_dashboard.php"
           class="cross-link page-loader-link"
           data-href="../dashboard/user_dashboard.php">
            <img src="../images/cross.png" alt="Close">
        </a>

        <div class="auth-right-inner">
            <div class="join-title">Sign In</div>
            <div class="join-text">
                Log in to access your Travelix account, manage your trips,
                review your bookings, and continue your smarter travel journey.
            </div>

            <div class="or-row">
                <div class="or-line"></div>
                <div>OR</div>
                <div class="or-line"></div>
            </div>

            <button type="button" class="google-btn" id="googleLoginBtn">
                <img src="../images/google_logo.png" alt="Google">
                Sign in with Google
            </button>
        </div>
    </div>
</div>

<script type="module">
    import { firebaseConfig } from '../config/firebase-config.js';

    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import {
        getAuth,
        signInWithEmailAndPassword,
        signInWithPopup,
        GoogleAuthProvider,
        sendEmailVerification,
        signOut
    } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    import {
        getFirestore,
        doc,
        getDoc,
        setDoc,
        updateDoc,
        serverTimestamp
    } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js';

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);
    const provider = new GoogleAuthProvider();

    const loginForm = document.getElementById('loginForm');
    const googleLoginBtn = document.getElementById('googleLoginBtn');
    const email = document.getElementById('email');
    const password = document.getElementById('password');

    function showLoader(title = 'Please wait...') {
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: message,
            confirmButtonColor: '#1484B4'
        });
    }

    function showSuccess(message, redirectUrl = '../dashboard/user_dashboard.php') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            confirmButtonColor: '#1484B4'
        }).then(() => {
            window.location.href = redirectUrl;
        });
    }

    // trips/assets/create_trip.js keeps an in-progress trip draft in
    // localStorage under a fixed key (travelix_trip_draft_v3), with no user
    // scoping. On a shared/public device, if a different account logs in
    // afterward, they'd see the previous user's destination/dates/places
    // still filled in. Clear that (and related trip-flow keys) whenever the
    // uid logging in doesn't match whoever's data is currently cached.
    function clearStaleLocalDataIfDifferentUser(uid) {
        try {
            const lastUid = localStorage.getItem('travelix_last_uid');
            if (uid && lastUid && lastUid !== uid) {
                localStorage.removeItem('travelix_trip_draft_v3');
                localStorage.removeItem('travelix_return_to_step');
                localStorage.removeItem('travelix_step3_data');
                localStorage.removeItem('travelix_hotel_booking');
            }
            if (uid) localStorage.setItem('travelix_last_uid', uid);
        } catch (e) { /* localStorage unavailable — nothing to clean up */ }
    }

    async function createPhpSession(userData) {
        clearStaleLocalDataIfDifferentUser(userData.uid || '');

        const formData = new FormData();
        formData.append('action', 'set_login_session');
        formData.append('uid', userData.uid || '');
        formData.append('first_name', userData.firstName || 'User');
        formData.append('last_name', userData.lastName || '');
        formData.append('email', userData.email || '');
        formData.append('profile_image', userData.profileImage || '/travelix/images/default_profile.png');
        formData.append('role', userData.role || 'user');

        const response = await fetch('login.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        return result;
    }

    function getRedirectUrlByRole(role) {
        return role === 'admin'
            ? '../dashboard/admin_dashboard.php'
            : '../dashboard/user_dashboard.php';
    }

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            const icon = this.querySelector('.eye-icon');

            if (target.type === 'password') {
                target.type = 'text';
                icon.src = '../images/eye_open.png';
            } else {
                target.type = 'password';
                icon.src = '../images/eye_close.png';
            }
        });
    });

    document.querySelectorAll('.page-loader-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const href = this.dataset.href;
            showLoader('Loading...');
            setTimeout(() => {
                window.location.href = href;
            }, 700);
        });
    });

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const emailVal = email.value.trim();
        const passwordVal = password.value;

        if (!emailVal.includes('@')) {
            showError('Email must contain @');
            return;
        }

        if (passwordVal.length < 8) {
            showError('Password must be at least 8 characters long.');
            return;
        }

        try {
            showLoader('Logging you in...');

            const userCredential = await signInWithEmailAndPassword(auth, emailVal, passwordVal);
            const user = userCredential.user;

            if (!user.emailVerified) {
                await sendEmailVerification(user);
                await signOut(auth);
                Swal.close();
                showError('Your email is not verified. Please verify your email first.');
                return;
            }

            const userRef = doc(db, 'users', user.uid);
            const userSnap = await getDoc(userRef);

            if (!userSnap.exists()) {
                await signOut(auth);
                Swal.close();
                showError('User record not found.');
                return;
            }

            const userData = userSnap.data();
            const userRole = userData.role || 'user';
            const redirectUrl = getRedirectUrlByRole(userRole);

            await updateDoc(userRef, {
                emailVerified: true
            });

            const sessionResult = await createPhpSession({
                uid: user.uid,
                firstName: userData.firstName || 'User',
                lastName: userData.lastName || '',
                email: userData.email || user.email || '',
                profileImage: userData.profileImage || '/travelix/images/default_profile.png',
                role: userRole
            });

            if (!sessionResult.success) {
                await signOut(auth);
                Swal.close();
                showError('Unable to create login session.');
                return;
            }

            showSuccess('Login successful.', redirectUrl);
        } catch (error) {
            Swal.close();

            if (error.code === 'auth/invalid-credential') {
                showError('Invalid email or password.');
            } else if (error.code === 'auth/user-not-found') {
                showError('User not found.');
            } else if (error.code === 'auth/wrong-password') {
                showError('Wrong password.');
            } else {
                showError(error.message);
            }
        }
    });

    googleLoginBtn.addEventListener('click', async function () {
        try {
            showLoader('Signing in with Google...');

            const result = await signInWithPopup(auth, provider);
            const user = result.user;

            const userRef = doc(db, 'users', user.uid);
            const userSnap = await getDoc(userRef);

            let firstName = 'User';
            let lastName = '';
            let profileImage = user.photoURL || '/travelix/images/default_profile.png';
            let userRole = 'user';

            if (!userSnap.exists()) {
                const parts = (user.displayName || '').trim().split(' ');
                firstName = parts[0] || 'User';
                lastName = parts.slice(1).join(' ') || '';

                await setDoc(userRef, {
                    uid: user.uid,
                    firstName: firstName,
                    lastName: lastName,
                    cnic: '',
                    phone: '',
                    email: user.email || '',
                    profileImage: profileImage,
                    role: 'user',
                    provider: 'google',
                    emailVerified: true,
                    createdAt: serverTimestamp()
                });

                userRole = 'user';
            } else {
                const userData = userSnap.data();
                firstName = userData.firstName || 'User';
                lastName = userData.lastName || '';
                profileImage = userData.profileImage || profileImage;
                userRole = userData.role || 'user';

                await updateDoc(userRef, {
                    emailVerified: true
                });
            }

            const redirectUrl = getRedirectUrlByRole(userRole);

            const sessionResult = await createPhpSession({
                uid: user.uid,
                firstName: firstName,
                lastName: lastName,
                email: user.email || '',
                profileImage: profileImage,
                role: userRole
            });

            if (!sessionResult.success) {
                await signOut(auth);
                Swal.close();
                showError('Unable to create login session.');
                return;
            }

            showSuccess('Google login successful.', redirectUrl);
        } catch (error) {
            Swal.close();
            showError(error.message);
        }
    });

    window.__travelixLoginReady = true;
</script>

<script>
    // If the module script above fails to load (e.g. Firebase CDN blocked or
    // unreachable on the user's network), the login form silently does
    // nothing when clicked. This classic script always runs regardless, and
    // surfaces a clear message instead of a dead button.
    setTimeout(function () {
        if (window.__travelixLoginReady) return;

        const form = document.getElementById('loginForm');
        if (!form || document.getElementById('loginLoadWarning')) return;

        const warning = document.createElement('div');
        warning.id = 'loginLoadWarning';
        warning.style.cssText = 'margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#fff5f5;border:1px solid #fecaca;color:#b91c1c;font-size:13.5px;line-height:1.6;';
        warning.textContent = 'Login is having trouble loading — please check your internet connection and refresh the page. If this keeps happening, try a different network.';
        form.parentNode.insertBefore(warning, form);
    }, 4000);
</script>

</body>
</html>