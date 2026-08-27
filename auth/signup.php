<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup | Travelix</title>

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
            max-width: 620px;
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
            box-shadow: 0 0 0 3px rgba(20, 132, 180, 0.25);
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

        .signup-btn {
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

        .signup-btn:hover {
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
            margin-bottom: 22px;
            text-align: justify;
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

        .row-gap-custom {
            row-gap: 8px;
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
            <div class="auth-title">Create Account</div>

            <form id="signupForm">
                <div class="row row-gap-custom">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control alpha-only" id="firstName" placeholder="First Name" maxlength="30" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control alpha-only" id="lastName" placeholder="Last Name" maxlength="30" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">CNIC</label>
                    <input type="text" class="form-control" id="cnic" placeholder="xxxxx-xxxxxxx-x" maxlength="15" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone No</label>
                    <input type="text" class="form-control" id="phone" placeholder="+92-3xx-xxxxxxx" maxlength="15" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" placeholder="Email" required>
                </div>

                <div class="row row-gap-custom">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Refund Payment Method</label>
                        <select class="form-control" id="paymentMethod" required>
                            <option value="">Select method</option>
                            <option value="bank">Bank Account</option>
                            <option value="easypaisa">EasyPaisa</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="raast">Raast</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="signupBankNameWrap" style="display:none;">
                        <label class="form-label">Bank Name</label>
                        <input type="text" class="form-control" id="bankName" placeholder="e.g. Meezan Bank">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Refund Account Number / IBAN</label>
                    <input type="text" class="form-control" id="paymentAccountNumber" placeholder="Account, IBAN or wallet mobile number" maxlength="34" required>
                    <small style="color:#64748b;">Cancellation refunds will be sent to this saved account.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="password-wrap">
                        <input type="password" class="form-control password-field" id="password" placeholder="Password" minlength="8" required>
                        <button type="button" class="eye-btn toggle-password" data-target="password">
                            <img src="../images/eye_close.png" alt="Toggle Password" class="eye-icon">
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <div class="password-wrap">
                        <input type="password" class="form-control password-field" id="confirmPassword" placeholder="Confirm Password" minlength="8" required>
                        <button type="button" class="eye-btn toggle-password" data-target="confirmPassword">
                            <img src="../images/eye_close.png" alt="Toggle Password" class="eye-icon">
                        </button>
                    </div>
                </div>

                <button type="submit" class="signup-btn">Sign up</button>
            </form>

            <div class="bottom-text">
                Already have an account?
                <a href="login.php" class="page-loader-link" data-href="login.php">Log in</a>
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
            <div class="join-title">Join Us</div>
            <div class="join-text">
                Create your Travelix account to plan trips, save your journey details,
                manage bookings, and explore Pakistan with a smarter travel experience.
            </div>
        </div>
    </div>
</div>

<script type="module">
    import { firebaseConfig } from '../config/firebase-config.js';

    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import {
        getAuth,
        createUserWithEmailAndPassword,
        updateProfile,
        sendEmailVerification
    } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    import {
        getFirestore,
        doc,
        setDoc,
        collection,
        query,
        where,
        getDocs,
        serverTimestamp
    } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js';

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);

    const signupForm = document.getElementById('signupForm');

    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const cnic = document.getElementById('cnic');
    const phone = document.getElementById('phone');
    const email = document.getElementById('email');
    const paymentMethod = document.getElementById('paymentMethod');
    const bankName = document.getElementById('bankName');
    const paymentAccountNumber = document.getElementById('paymentAccountNumber');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');

    paymentMethod.addEventListener('change', function () {
        const isBank = this.value === 'bank';
        document.getElementById('signupBankNameWrap').style.display = isBank ? '' : 'none';
        bankName.required = isBank;
    });

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

    function showSuccess(message, redirectUrl = 'login.php') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            confirmButtonColor: '#1484B4'
        }).then(() => {
            window.location.href = redirectUrl;
        });
    }

    function showVerificationEmailNotice(emailVal, redirectUrl = 'login.php') {
        Swal.fire({
            icon: 'info',
            title: 'Verify Your Email',
            html: `We've sent a verification link to <b>${emailVal}</b>.<br><br>
                   <b>Please also check your Spam / Junk folder</b> — verification emails sometimes land there instead of your inbox.`,
            confirmButtonText: 'Got it, Continue to Login',
            confirmButtonColor: '#1484B4'
        }).then(() => {
            window.location.href = redirectUrl;
        });
    }

    document.querySelectorAll('.alpha-only').forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '').replace(/\s{2,}/g, ' ');
        });
    });

    function formatCNIC(value) {
        let digits = value.replace(/\D/g, '').slice(0, 13);
        let formatted = '';

        if (digits.length > 0) formatted += digits.slice(0, 5);
        if (digits.length > 5) formatted += '-' + digits.slice(5, 12);
        if (digits.length > 12) formatted += '-' + digits.slice(12, 13);

        return formatted;
    }

    function formatPhone(value) {
        let digits = value.replace(/\D/g, '');

        if (digits.startsWith('92')) digits = digits.slice(2);
        if (digits.startsWith('0')) digits = digits.slice(1);

        digits = digits.slice(0, 10);

        let formatted = '+92';
        if (digits.length > 0) formatted += '-' + digits.slice(0, 3);
        if (digits.length > 3) formatted += '-' + digits.slice(3, 10);

        return formatted;
    }

    cnic.addEventListener('input', function () {
        this.value = formatCNIC(this.value);
    });

    phone.addEventListener('focus', function () {
        if (!this.value) this.value = '+92';
    });

    phone.addEventListener('input', function () {
        this.value = formatPhone(this.value);
    });

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

    async function isCNICAlreadyUsed(cnicValue) {
        const q = query(collection(db, 'users'), where('cnic', '==', cnicValue));
        const snapshot = await getDocs(q);
        return !snapshot.empty;
    }

    signupForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const firstNameVal = firstName.value.trim();
        const lastNameVal = lastName.value.trim();
        const cnicVal = cnic.value.trim();
        const phoneVal = phone.value.trim();
        const emailVal = email.value.trim();
        const paymentMethodVal = paymentMethod.value;
        const bankNameVal = bankName.value.trim();
        const paymentAccountNumberVal = paymentAccountNumber.value.trim();
        const passwordVal = password.value;
        const confirmPasswordVal = confirmPassword.value;

        if (!/^[A-Za-z\s]+$/.test(firstNameVal) || !/^[A-Za-z\s]+$/.test(lastNameVal)) {
            showError('First name and last name must contain alphabets only.');
            return;
        }

        if (!/^\d{5}-\d{7}-\d{1}$/.test(cnicVal)) {
            showError('CNIC format must be xxxxx-xxxxxxx-x');
            return;
        }

        if (!/^\+92-\d{3}-\d{7}$/.test(phoneVal)) {
            showError('Phone number format must be +92-xxx-xxxxxxx');
            return;
        }

        if (!emailVal.includes('@')) {
            showError('Email must contain @');
            return;
        }
        if (!paymentMethodVal || !paymentAccountNumberVal || (paymentMethodVal === 'bank' && !bankNameVal)) {
            showError('Payment method and refund account details are required.');
            return;
        }

        if (passwordVal.length < 8) {
            showError('Password must be at least 8 characters long.');
            return;
        }

        if (passwordVal !== confirmPasswordVal) {
            showError('Password and confirm password do not match.');
            return;
        }

        try {
            showLoader('Creating your account...');

            const exists = await isCNICAlreadyUsed(cnicVal);
            if (exists) {
                Swal.close();
                showError('This CNIC is already registered.');
                return;
            }

            const userCredential = await createUserWithEmailAndPassword(auth, emailVal, passwordVal);
            const user = userCredential.user;

            await updateProfile(user, {
                displayName: `${firstNameVal} ${lastNameVal}`
            });

            await sendEmailVerification(user);

            await setDoc(doc(db, 'users', user.uid), {
                uid: user.uid,
                firstName: firstNameVal,
                lastName: lastNameVal,
                cnic: cnicVal,
                phone: phoneVal,
                email: emailVal,
                paymentMethod: paymentMethodVal,
                bankName: paymentMethodVal === 'bank' ? bankNameVal : '',
                paymentAccountNumber: paymentAccountNumberVal,
                profileImage: '../images/default_profile.png',
                role: 'user',
                provider: 'password',
                emailVerified: false,
                createdAt: serverTimestamp()
            });

            showVerificationEmailNotice(emailVal);
        } catch (error) {
            Swal.close();

            if (error.code === 'auth/email-already-in-use') {
                showError('This email is already in use.');
            } else if (error.code === 'auth/invalid-email') {
                showError('Invalid email address.');
            } else if (error.code === 'auth/weak-password') {
                showError('Password is too weak.');
            } else {
                showError(error.message);
            }
        }
    });
</script>

</body>
</html>
