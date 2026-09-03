<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_profile_image') {
    header('Content-Type: application/json');

    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid image.']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_images';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $originalName = $_FILES['profile_image']['name'];
    $tmpName = $_FILES['profile_image']['tmp_name'];
    $size = (int)$_FILES['profile_image']['size'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.']);
        exit;
    }

    if ($size > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size must be less than 2MB.']);
        exit;
    }

    $safeUid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']['uid']);
    $fileName = 'profile_' . $safeUid . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
        exit;
    }

    $publicPath = $baseUrl . '/profile_images/' . $fileName;
    $_SESSION['user']['profile_image'] = $publicPath;

    echo json_encode([
        'success' => true,
        'message' => 'Profile image uploaded successfully.',
        'profileImage' => $publicPath
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_session_profile') {
    header('Content-Type: application/json');

    $_SESSION['user']['first_name'] = trim($_POST['firstName'] ?? ($_SESSION['user']['first_name'] ?? 'User'));
    $_SESSION['user']['last_name'] = trim($_POST['lastName'] ?? ($_SESSION['user']['last_name'] ?? ''));
    $_SESSION['user']['phone'] = trim($_POST['phone'] ?? ($_SESSION['user']['phone'] ?? ''));
    $_SESSION['user']['cnic'] = trim($_POST['cnic'] ?? ($_SESSION['user']['cnic'] ?? ''));

    if (!empty($_POST['profileImage'])) {
        $_SESSION['user']['profile_image'] = trim($_POST['profileImage']);
    }

    echo json_encode(['success' => true]);
    exit;
}

$firstName = htmlspecialchars((string)($currentUser['first_name'] ?? ''));
$lastName = htmlspecialchars((string)($currentUser['last_name'] ?? ''));
$email = htmlspecialchars((string)($currentUser['email'] ?? ''));
$phone = htmlspecialchars((string)($currentUser['phone'] ?? ''));
$cnic = htmlspecialchars((string)($currentUser['cnic'] ?? ''));
$paymentMethod = htmlspecialchars((string)($currentUser['paymentMethod'] ?? ''));
$paymentAccountNumber = htmlspecialchars((string)($currentUser['paymentAccountNumber'] ?? ''));
$profileImage = htmlspecialchars((string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Profile | Travelix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/travelix/assets/css/travelix_3d_effects.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: #ffffff;
            color: #1e293b;
            overflow-x: hidden;
        }

        .profile-navbar-wrap {
            position: absolute;
            top: 20px;
            width: 100%;
            z-index: 1000;
        }

        .profile-hero {
            position: relative;
            min-height: 440px;
            background:
                linear-gradient(to bottom, rgba(0,0,0,0.30), rgba(0,0,0,0.24)),
                url('../images/slider1.webp') center/cover no-repeat;
            padding: 170px 20px 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
        }

        .profile-hero-content {
            max-width: 850px;
        }

        .profile-hero h1 {
            font-size: 58px;
            font-weight: 900;
            margin-bottom: 14px;
        }

        .profile-hero p {
            font-size: 18px;
            line-height: 1.7;
            margin: 0;
        }

        .profile-section {
            padding: 80px 20px;
            background: #ffffff;
        }

        .profile-container {
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
        }

        .profile-card {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 28px;
            align-items: stretch;
        }

        .profile-left,
        .profile-right {
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .profile-left {
            padding: 34px 26px;
            text-align: center;
        }

        .image-box {
            position: relative;
            width: 190px;
            height: 190px;
            margin: 0 auto 22px;
        }

        .profile-preview {
            width: 190px;
            height: 190px;
            border-radius: 50%;
            object-fit: cover;
            border: 8px solid #eaf2ff;
            background: #f8fafc;
        }

        .image-upload-btn {
            position: absolute;
            right: 12px;
            bottom: 14px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: #1484B4;
            color: #fff;
            font-size: 18px;
            box-shadow: 0 12px 30px rgba(20,132,180,0.30);
            cursor: pointer;
        }

        .profile-name {
            font-size: 34px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .profile-email {
            color: #64748b;
            font-size: 15px;
            word-break: break-word;
        }

        .profile-right {
            padding: 34px 36px;
        }

        .section-title {
            font-size: 34px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .section-subtitle {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 26px;
        }

        .form-label {
            color: #334155;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .form-control {
            height: 56px;
            border-radius: 16px;
            border: 1px solid rgba(15,23,42,0.10);
            background: #f8fafc;
            font-size: 15px;
            padding: 12px 16px;
            box-shadow: none !important;
        }

        .form-control:focus {
            border-color: #1484B4;
            background: #fff;
        }

        .form-control[readonly] {
            background: #eaf2ff;
            color: #64748b;
            cursor: not-allowed;
        }

        .password-toggle-card {
            margin-top: 8px;
            padding: 18px;
            border-radius: 20px;
            background: #f8fbff;
            border: 1px solid rgba(15,23,42,0.06);
        }

        .password-section {
            display: none;
            margin-top: 18px;
        }

        .password-section.show {
            display: block;
        }

        .password-wrap {
            position: relative;
        }

        .eye-btn {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            font-size: 18px;
            cursor: pointer;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .form-check-label {
            font-weight: 800;
            color: #1484B4;
            cursor: pointer;
            margin-left: 8px;
        }

        .help-text {
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .btn-save,
        .btn-back {
            min-height: 52px;
            border-radius: 40px;
            border: none;
            font-weight: 800;
            padding: 13px 28px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-save {
            background: #1484B4;
            color: #fff;
        }

        .btn-save:hover {
            background: #0f6d95;
        }

        .btn-back {
            background: #eef7fb;
            color: #1484B4;
        }

        .btn-back:hover {
            color: #0f6d95;
        }

        @media (max-width: 1100px) {
            .profile-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .profile-hero {
                min-height: 360px;
                padding: 150px 18px 60px;
            }

            .profile-hero h1 {
                font-size: 38px;
            }

            .profile-hero p {
                font-size: 16px;
            }

            .profile-section {
                padding: 50px 14px;
            }

            .profile-right,
            .profile-left {
                padding: 24px 18px;
            }

            .section-title {
                font-size: 28px;
            }

            .actions {
                justify-content: stretch;
            }

            .btn-save,
            .btn-back {
                width: 100%;
            }
        }

        /* ── Payout method — 3D card ── */
        .payout-3d-card {
            background: linear-gradient(145deg, #ffffff, #f3f7fd);
            border: 1px solid rgba(20, 132, 180, 0.14);
            border-radius: 20px;
            padding: 22px 24px;
            margin: 6px 0 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.10), 0 2px 8px rgba(15, 23, 42, 0.06);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 700px) {
            .payout-3d-card { grid-template-columns: 1fr; }
        }
        .payout-3d-card-full { grid-column: 1 / -1; }
        .payout-3d-card-header {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .payout-3d-card-icon {
            width: 38px; height: 38px; border-radius: 12px;
            background: linear-gradient(135deg, #1484B4, #0f3460);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 17px;
            box-shadow: 0 6px 14px rgba(20,132,180,.35);
        }
        .payout-3d-card-title { font-weight: 800; font-size: 15.5px; color: #0f172a; }
        .payout-3d-card-sub { font-size: 12px; color: #64748b; margin-top: 1px; }
    </style>
</head>
<body>

<div class="profile-navbar-wrap">
    <?php include '../includes/user_top_navbar.php'; ?>
</div>

<section class="profile-hero">
    <div class="profile-hero-content">
        <h1>Manage Profile</h1>
        <p>Update your Travelix account details, profile image, and password securely.</p>
    </div>
</section>

<section class="profile-section">
    <div class="profile-container">
        <div class="profile-card">
            <aside class="profile-left">
                <div class="image-box">
                    <img src="<?php echo $profileImage; ?>" id="profilePreview" class="profile-preview" alt="Profile Image"
                         onerror="this.onerror=null;this.src='<?php echo $baseUrl; ?>/images/default_profile.png';">

                    <button type="button" class="image-upload-btn" id="chooseImageBtn">📷</button>
                    <input type="file" id="profileImageInput" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>
                </div>

                <div class="profile-name" id="profileNameText">
                    <?php echo trim($firstName . ' ' . $lastName) ?: 'User'; ?>
                </div>

                <div class="profile-email">
                    <?php echo $email; ?>
                </div>
            </aside>

            <main class="profile-right">
                <h2 class="section-title">Account Information</h2>
                <p class="section-subtitle">Edit your personal information below.</p>

                <form id="profileForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control alpha-only" id="firstName" value="<?php echo $firstName; ?>" maxlength="30" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control alpha-only" id="lastName" value="<?php echo $lastName; ?>" maxlength="30" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" value="<?php echo $email; ?>" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone No</label>
                            <input type="text" class="form-control" id="phone" value="<?php echo $phone; ?>" placeholder="+92-3xx-xxxxxxx" maxlength="15" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">CNIC</label>
                            <input type="text" class="form-control" id="cnic" value="<?php echo $cnic; ?>" placeholder="xxxxx-xxxxxxx-x" maxlength="15" required>
                        </div>

                        <div class="col-12">
                            <div class="payout-3d-card tilt-card">
                                <div class="payout-3d-card-header">
                                    <div class="payout-3d-card-icon"><i class="bi bi-wallet2"></i></div>
                                    <div>
                                        <div class="payout-3d-card-title">Payout Method</div>
                                        <div class="payout-3d-card-sub">Used to send refunds back to you if you ever cancel a booking.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Payout Method <span class="text-danger">*</span></label>
                                    <select class="form-control" id="paymentMethod" required>
                                        <option value="">— Select Method —</option>
                                        <option value="easypaisa">EasyPaisa</option>
                                        <option value="jazzcash">JazzCash</option>
                                        <option value="raast">Raast</option>
                                        <option value="bank">Bank Account</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="bankNameWrap" style="display:none;">
                                    <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                    <select class="form-control" id="bankName">
                                        <option value="">— Select Bank —</option>
                                        <option value="Allied Bank">Allied Bank</option>
                                        <option value="Al Baraka Bank">Al Baraka Bank</option>
                                        <option value="Askari Bank">Askari Bank</option>
                                        <option value="Bank Al Habib">Bank Al Habib</option>
                                        <option value="Bank Alfalah">Bank Alfalah</option>
                                        <option value="Bank of Khyber">Bank of Khyber</option>
                                        <option value="Bank of Punjab">Bank of Punjab</option>
                                        <option value="BankIslami Pakistan">BankIslami Pakistan</option>
                                        <option value="Dubai Islamic Bank Pakistan">Dubai Islamic Bank Pakistan</option>
                                        <option value="Faysal Bank">Faysal Bank</option>
                                        <option value="Habib Bank Limited (HBL)">Habib Bank Limited (HBL)</option>
                                        <option value="Habib Metropolitan Bank">Habib Metropolitan Bank</option>
                                        <option value="JS Bank">JS Bank</option>
                                        <option value="MCB Bank">MCB Bank</option>
                                        <option value="MCB Islamic Bank">MCB Islamic Bank</option>
                                        <option value="Meezan Bank">Meezan Bank</option>
                                        <option value="National Bank of Pakistan">National Bank of Pakistan</option>
                                        <option value="Samba Bank">Samba Bank</option>
                                        <option value="Silk Bank">Silk Bank</option>
                                        <option value="Soneri Bank">Soneri Bank</option>
                                        <option value="Standard Chartered Bank">Standard Chartered Bank</option>
                                        <option value="Summit Bank">Summit Bank</option>
                                        <option value="United Bank Limited (UBL)">United Bank Limited (UBL)</option>
                                    </select>
                                </div>

                                <div class="mb-0 payout-3d-card-full">
                                    <label class="form-label" id="paymentAccountNumberLabel">Payout Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="paymentAccountNumber" value="<?php echo $paymentAccountNumber; ?>" placeholder="e.g. 03XXXXXXXXX" maxlength="34" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="password-toggle-card">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="changePasswordCheck">
                            <label class="form-check-label" for="changePasswordCheck">
                                Set New Password
                            </label>
                        </div>

                        <div class="password-section" id="passwordSection">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <div class="password-wrap">
                                        <input type="password" class="form-control password-field" id="newPassword" minlength="8" placeholder="New Password">
                                        <button type="button" class="eye-btn toggle-password" data-target="newPassword">👁</button>
                                    </div>
                                    <div class="help-text">Password must be at least 8 characters.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="password-wrap">
                                        <input type="password" class="form-control password-field" id="confirmPassword" minlength="8" placeholder="Confirm Password">
                                        <button type="button" class="eye-btn toggle-password" data-target="confirmPassword">👁</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="<?php echo $baseUrl; ?>/dashboard/user_dashboard.php"
                           class="btn-back loader-link"
                           data-href="<?php echo $baseUrl; ?>/dashboard/user_dashboard.php">
                            Back to Dashboard
                        </a>

                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</section>

<?php include '../includes/user_bottom_footer.php'; ?>

<script type="module">
    import { firebaseConfig } from "../config/firebase-config.js";

    import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import {
        getAuth,
        onAuthStateChanged,
        updateProfile,
        updatePassword
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";

    import {
        getFirestore,
        doc,
        getDoc,
        updateDoc
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);

    const profileForm = document.getElementById("profileForm");
    const firstName = document.getElementById("firstName");
    const lastName = document.getElementById("lastName");
    const email = document.getElementById("email");
    const phone = document.getElementById("phone");
    const cnic = document.getElementById("cnic");
    const paymentMethod = document.getElementById("paymentMethod");
    const paymentAccountNumber = document.getElementById("paymentAccountNumber");
    const bankNameWrap = document.getElementById("bankNameWrap");
    const bankName = document.getElementById("bankName");
    const paymentAccountNumberLabel = document.getElementById("paymentAccountNumberLabel");

    function syncBankFieldVisibility() {
        const isBank = paymentMethod.value === "bank";
        bankNameWrap.style.display = isBank ? "block" : "none";
        bankName.required = isBank;
        paymentAccountNumberLabel.innerHTML = (isBank ? "Account Number / IBAN" : "Payout Account Number") + ' <span class="text-danger">*</span>';
    }
    paymentMethod.addEventListener("change", syncBankFieldVisibility);
    syncBankFieldVisibility();

    const chooseImageBtn = document.getElementById("chooseImageBtn");
    const profileImageInput = document.getElementById("profileImageInput");
    const profilePreview = document.getElementById("profilePreview");
    const profileNameText = document.getElementById("profileNameText");

    const changePasswordCheck = document.getElementById("changePasswordCheck");
    const passwordSection = document.getElementById("passwordSection");
    const newPassword = document.getElementById("newPassword");
    const confirmPassword = document.getElementById("confirmPassword");

    let currentUser = null;
    let uploadedProfileImage = profilePreview.getAttribute("src");

    function showLoader(title = "Please wait...", text = "") {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function showError(message) {
        Swal.fire({
            icon: "error",
            title: "Oops...",
            text: message,
            confirmButtonColor: "#1484B4"
        });
    }

    function waitForFirebaseAuth() {
        return new Promise((resolve) => {
            onAuthStateChanged(auth, (user) => {
                resolve(user || null);
            });
        });
    }

    document.querySelectorAll(".alpha-only").forEach(input => {
        input.addEventListener("input", function () {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, "").replace(/\s{2,}/g, " ");
        });
    });

    function formatCNIC(value) {
        let digits = value.replace(/\D/g, "").slice(0, 13);
        let formatted = "";

        if (digits.length > 0) formatted += digits.slice(0, 5);
        if (digits.length > 5) formatted += "-" + digits.slice(5, 12);
        if (digits.length > 12) formatted += "-" + digits.slice(12, 13);

        return formatted;
    }

    function formatPhone(value) {
        let digits = value.replace(/\D/g, "");

        if (digits.startsWith("92")) digits = digits.slice(2);
        if (digits.startsWith("0")) digits = digits.slice(1);

        digits = digits.slice(0, 10);

        let formatted = "+92";
        if (digits.length > 0) formatted += "-" + digits.slice(0, 3);
        if (digits.length > 3) formatted += "-" + digits.slice(3, 10);

        return formatted;
    }

    cnic.addEventListener("input", function () {
        this.value = formatCNIC(this.value);
    });

    phone.addEventListener("focus", function () {
        if (!this.value) this.value = "+92";
    });

    phone.addEventListener("input", function () {
        this.value = formatPhone(this.value);
    });

    firstName.addEventListener("input", updateLiveName);
    lastName.addEventListener("input", updateLiveName);

    function updateLiveName() {
        const name = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
        profileNameText.textContent = name || "User";
    }

    changePasswordCheck.addEventListener("change", function () {
        passwordSection.classList.toggle("show", this.checked);

        if (!this.checked) {
            newPassword.value = "";
            confirmPassword.value = "";
        }
    });

    document.querySelectorAll(".toggle-password").forEach(button => {
        button.addEventListener("click", function () {
            const target = document.getElementById(this.dataset.target);
            target.type = target.type === "password" ? "text" : "password";
        });
    });

    document.querySelectorAll(".loader-link").forEach(link => {
        link.addEventListener("click", function (e) {
            const targetUrl = this.dataset.href || this.href;

            if (!targetUrl || targetUrl === "#") return;

            e.preventDefault();

            showLoader("Loading...", "Please wait while we take you there.");

            setTimeout(() => {
                window.location.href = targetUrl;
            }, 700);
        });
    });

    chooseImageBtn.addEventListener("click", function () {
        profileImageInput.click();
    });

    profileImageInput.addEventListener("change", async function () {
        const file = this.files[0];

        if (!file) return;

        const allowed = ["image/jpeg", "image/jpg", "image/png", "image/webp"];

        if (!allowed.includes(file.type)) {
            showError("Only JPG, JPEG, PNG, and WEBP images are allowed.");
            this.value = "";
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showError("Image size must be less than 2MB.");
            this.value = "";
            return;
        }

        const formData = new FormData();
        formData.append("action", "upload_profile_image");
        formData.append("profile_image", file);

        try {
            showLoader("Uploading image...");

            const response = await fetch("manage_user_profile.php", {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || "Image upload failed.");
            }

            uploadedProfileImage = data.profileImage;
            profilePreview.src = uploadedProfileImage;

            try {
                await window.travelixAddNotification?.({
                    title: "Profile Image Updated",
                    message: "Your profile image has been updated successfully.",
                    type: "profile_updated",
                    link: "/travelix/profile/manage_user_profile.php"
                });
            } catch (notificationError) {
                console.warn("Notification not added:", notificationError);
            }

            Swal.fire({
                icon: "success",
                title: "Profile Image Uploaded",
                text: "Your profile image has been uploaded successfully.",
                confirmButtonColor: "#1484B4"
            });
        } catch (error) {
            Swal.close();
            showError(error.message || "Failed to upload image.");
        }
    });

    async function loadFirestoreProfile() {
        try {
            currentUser = await waitForFirebaseAuth();

            if (!currentUser) {
                showError("Firebase Auth user is not logged in. Please log out and log in again.");
                return;
            }

            const userRef = doc(db, "users", currentUser.uid);
            const userSnap = await getDoc(userRef);

            if (userSnap.exists()) {
                const data = userSnap.data();

                firstName.value = data.firstName || firstName.value;
                lastName.value = data.lastName || lastName.value;
                email.value = data.email || currentUser.email || email.value;
                phone.value = data.phone || phone.value;
                cnic.value = data.cnic || cnic.value;
                paymentMethod.value = data.paymentMethod || paymentMethod.value;
                paymentAccountNumber.value = data.paymentAccountNumber || paymentAccountNumber.value;
                bankName.value = data.bankName || "";
                syncBankFieldVisibility();

                if (data.profileImage) {
                    uploadedProfileImage = data.profileImage;
                    profilePreview.src = data.profileImage;
                }

                updateLiveName();
            }
        } catch (error) {
            showError(error.message || "Failed to load profile.");
        }
    }

    async function updatePhpSession(firstNameVal, lastNameVal, phoneVal, cnicVal, profileImageVal) {
        const formData = new FormData();
        formData.append("action", "update_session_profile");
        formData.append("firstName", firstNameVal);
        formData.append("lastName", lastNameVal);
        formData.append("phone", phoneVal);
        formData.append("cnic", cnicVal);
        formData.append("profileImage", profileImageVal);

        await fetch("manage_user_profile.php", {
            method: "POST",
            body: formData
        });
    }

    profileForm.addEventListener("submit", async function (e) {
        e.preventDefault();

        const firstNameVal = firstName.value.trim();
        const lastNameVal = lastName.value.trim();
        const phoneVal = phone.value.trim();
        const cnicVal = cnic.value.trim();
        const paymentMethodVal = paymentMethod.value.trim();
        const paymentAccountNumberVal = paymentAccountNumber.value.trim();
        const bankNameVal = bankName.value.trim();
        const newPasswordVal = newPassword.value;
        const confirmPasswordVal = confirmPassword.value;

        if (!/^[A-Za-z\s]+$/.test(firstNameVal) || !/^[A-Za-z\s]+$/.test(lastNameVal)) {
            showError("First name and last name must contain alphabets only.");
            return;
        }

        if (!/^\d{5}-\d{7}-\d{1}$/.test(cnicVal)) {
            showError("CNIC format must be xxxxx-xxxxxxx-x");
            return;
        }

        if (!/^\+92-\d{3}-\d{7}$/.test(phoneVal)) {
            showError("Phone number format must be +92-xxx-xxxxxxx");
            return;
        }

        if (!paymentMethodVal) {
            showError("Please select a payout method — this is where refunds will be sent if you ever cancel a booking.");
            return;
        }

        if (paymentMethodVal === "bank" && !bankNameVal) {
            showError("Please select your bank.");
            return;
        }

        if (!paymentAccountNumberVal) {
            showError("Please enter your payout account number.");
            return;
        }

        if (changePasswordCheck.checked) {
            if (newPasswordVal.length < 8) {
                showError("Password must be at least 8 characters long.");
                return;
            }

            if (newPasswordVal !== confirmPasswordVal) {
                showError("New password and confirm password do not match.");
                return;
            }
        }

        try {
            showLoader("Saving profile...");

            if (!currentUser) {
                currentUser = await waitForFirebaseAuth();
            }

            if (!currentUser) {
                throw new Error("Firebase Auth user is not logged in. Please log out and log in again.");
            }

            await updateProfile(currentUser, {
                displayName: `${firstNameVal} ${lastNameVal}`,
                photoURL: uploadedProfileImage
            });

            if (changePasswordCheck.checked) {
                await updatePassword(currentUser, newPasswordVal);
            }

            await updateDoc(doc(db, "users", currentUser.uid), {
                firstName: firstNameVal,
                lastName: lastNameVal,
                phone: phoneVal,
                cnic: cnicVal,
                paymentMethod: paymentMethodVal,
                paymentAccountNumber: paymentAccountNumberVal,
                bankName: paymentMethodVal === "bank" ? bankNameVal : "",
                profileImage: uploadedProfileImage
            });

            await updatePhpSession(firstNameVal, lastNameVal, phoneVal, cnicVal, uploadedProfileImage);

            try {
                await window.travelixAddNotification?.({
                    title: "Profile Updated",
                    message: "Your profile information has been updated successfully.",
                    type: "profile_updated",
                    link: "/travelix/profile/manage_user_profile.php"
                });
            } catch (notificationError) {
                console.warn("Notification not added:", notificationError);
            }

            Swal.fire({
                icon: "success",
                title: "Profile Updated",
                text: "Your profile has been updated successfully.",
                confirmButtonColor: "#1484B4"
            }).then(() => {
                window.location.reload();
            });

        } catch (error) {
            Swal.close();

            if (error.code === "auth/requires-recent-login") {
                showError("For password change, please log out and log in again, then try changing password.");
            } else {
                showError(error.message || "Failed to update profile.");
            }
        }
    });

    loadFirestoreProfile();
</script>

<script src="/travelix/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="/travelix/assets/js/travelix_3d_effects.js"></script>

</body>
</html>