<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Travelix</title>

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
            margin-bottom: 14px;
        }

        .auth-note {
            font-size: 17px;
            line-height: 1.7;
            color: #dffcff;
            margin-bottom: 28px;
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

        .reset-btn,
        .resend-btn {
            width: 100%;
            height: 58px;
            border: none;
            border-radius: 14px;
            font-size: 20px;
            font-weight: 800;
            transition: 0.3s ease;
        }

        .reset-btn {
            background: #16b7be;
            color: #053645;
            margin-top: 20px;
        }

        .reset-btn:hover {
            background: #11aab1;
        }

        .resend-btn {
            background: #eafcff;
            color: #075d70;
            margin-top: 14px;
            display: none;
        }

        .resend-btn:hover {
            background: #ffffff;
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

        .auth-right-inner {
            width: 100%;
            max-width: 420px;
            margin-left: 80px;
            position: relative;
        }

        .join-title {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 14px;
            text-align: left;
        }

        .join-text {
            font-size: 18px;
            line-height: 1.8;
            color: #eafcff;
            max-width: 350px;
            margin-bottom: 28px;
            text-align: justify;
        }

        .info-box {
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.20);
            border-radius: 18px;
            padding: 20px;
            color: #fff;
            line-height: 1.7;
            font-size: 16px;
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

            .join-title {
                font-size: 36px;
            }

            .join-text {
                font-size: 16px;
                max-width: 100%;
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
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-left">
        <div class="auth-card">
            <div class="auth-title">Forgot Password</div>

            <div class="auth-note">
                Please enter the registered email address. We will send a password reset link to your Gmail inbox.
            </div>

            <form id="forgotPasswordForm">
                <div class="mb-3">
                    <label class="form-label">Registered Email</label>
                    <input type="email" class="form-control" id="email" placeholder="Enter your registered email" required>
                </div>

                <button type="submit" class="reset-btn">Send Reset Email</button>
                <button type="button" class="resend-btn" id="resendBtn">Resend Email</button>
            </form>

            <div class="bottom-text">
                Remember your password?
                <a href="login.php" class="page-loader-link" data-href="login.php">Back to Login</a>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <a href="login.php"
           class="cross-link page-loader-link"
           data-href="login.php">
            <img src="../images/cross.png" alt="Close">
        </a>

        <div class="auth-right-inner">
            <div class="join-title">Reset Password</div>

            <div class="join-text">
                Enter your registered email address and Travelix will send you a secure password reset link.
            </div>

            <div class="info-box">
                After clicking the reset link in your Gmail, create a new password and then return to the login page.
            </div>
        </div>
    </div>
</div>

<script type="module">
    import { firebaseConfig } from '../config/firebase-config.js';

    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import {
        getAuth,
        sendPasswordResetEmail
    } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);

    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const email = document.getElementById('email');
    const resendBtn = document.getElementById('resendBtn');

    let lastEmail = '';

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

    function showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Email Sent',
            text: message,
            confirmButtonColor: '#1484B4'
        });
    }

    async function sendResetEmail(emailValue, isResend = false) {
        if (!emailValue || !emailValue.includes('@')) {
            showError('Please enter a valid registered email address.');
            return;
        }

        try {
            showLoader(isResend ? 'Resending email...' : 'Sending reset email...');

            await sendPasswordResetEmail(auth, emailValue, {
                url: window.location.origin + '/travelix/auth/login.php',
                handleCodeInApp: false
            });

            Swal.close();

            lastEmail = emailValue;
            resendBtn.style.display = 'block';

            showSuccess(
                isResend
                    ? 'Password reset email has been resent. Please check your Gmail inbox or spam folder.'
                    : 'Password reset email has been sent. Please check your Gmail inbox or spam folder.'
            );
        } catch (error) {
            Swal.close();

            if (error.code === 'auth/user-not-found') {
                showError('No account found with this email address.');
            } else if (error.code === 'auth/invalid-email') {
                showError('Please enter a valid email address.');
            } else if (error.code === 'auth/too-many-requests') {
                showError('Too many requests. Please wait a few minutes and try again.');
            } else {
                showError(error.message || 'Failed to send password reset email.');
            }
        }
    }

    forgotPasswordForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        await sendResetEmail(email.value.trim(), false);
    });

    resendBtn.addEventListener('click', async function () {
        const emailToUse = email.value.trim() || lastEmail;
        await sendResetEmail(emailToUse, true);
    });

    document.querySelectorAll('.page-loader-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const href = this.dataset.href || this.href;

            showLoader('Loading...');

            setTimeout(() => {
                window.location.href = href;
            }, 700);
        });
    });
</script>

</body>
</html>