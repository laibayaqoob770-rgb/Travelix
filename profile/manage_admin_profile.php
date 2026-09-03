<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? ''));

if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/user_dashboard.php');
    exit;
}

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
    $fileName = 'admin_profile_' . $safeUid . '_' . time() . '.' . $ext;
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

    $_SESSION['user']['first_name'] = trim($_POST['firstName'] ?? ($_SESSION['user']['first_name'] ?? 'Admin'));
    $_SESSION['user']['last_name'] = trim($_POST['lastName'] ?? ($_SESSION['user']['last_name'] ?? ''));
    $_SESSION['user']['phone'] = trim($_POST['phone'] ?? ($_SESSION['user']['phone'] ?? ''));
    $_SESSION['user']['cnic'] = trim($_POST['cnic'] ?? ($_SESSION['user']['cnic'] ?? ''));
    $_SESSION['user']['role'] = 'admin';

    if (!empty($_POST['profileImage'])) {
        $_SESSION['user']['profile_image'] = trim($_POST['profileImage']);
    }

    echo json_encode(['success' => true]);
    exit;
}

$firstName = htmlspecialchars((string)($currentUser['first_name'] ?? 'Admin'));
$lastName = htmlspecialchars((string)($currentUser['last_name'] ?? ''));
$email = htmlspecialchars((string)($currentUser['email'] ?? ''));
$phone = htmlspecialchars((string)($currentUser['phone'] ?? ''));
$cnic = htmlspecialchars((string)($currentUser['cnic'] ?? ''));
$profileImage = htmlspecialchars((string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png')));
$fullName = trim($firstName . ' ' . $lastName) ?: 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admin Profile | Travelix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
            --bg: #f4f8ff;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 18px 45px rgba(29, 78, 216, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            color: var(--text);
        }

        body {
            overflow-y: auto;
        }

        .admin-page {
            display: flex;
            gap: 24px;
            padding: 16px;
            min-height: 100vh;
        }

        .admin-content {
            flex: 1;
            min-width: 0;
        }

        .profile-hero {
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 24px;
        }

        .profile-hero h1 {
            margin: 0 0 8px;
            font-size: 34px;
            font-weight: 900;
            color: var(--primary-dark);
        }

        .profile-hero p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .profile-card {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .profile-left,
        .profile-right {
            background: var(--card);
            border-radius: 26px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
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
            border: 8px solid var(--primary-soft);
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            font-size: 18px;
            box-shadow: 0 12px 30px rgba(29,78,216,0.30);
            cursor: pointer;
        }

        .profile-name {
            font-size: 30px;
            font-weight: 900;
            color: var(--primary-dark);
            margin-bottom: 8px;
            word-break: break-word;
        }

        .profile-email {
            color: var(--muted);
            font-size: 15px;
            word-break: break-word;
        }

        .admin-badge {
            display: inline-flex;
            margin-top: 16px;
            padding: 10px 16px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 800;
        }

        .profile-right {
            padding: 34px 36px;
        }

        .section-title {
            font-size: 30px;
            font-weight: 900;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }

        .section-subtitle {
            color: var(--muted);
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
            border-color: var(--primary);
            background: #fff;
        }

        .form-control[readonly] {
            background: var(--primary-soft);
            color: var(--muted);
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
            color: var(--primary-dark);
            cursor: pointer;
            margin-left: 8px;
        }

        .help-text {
            margin-top: 6px;
            font-size: 12px;
            color: var(--muted);
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-save:hover {
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-back {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .btn-back:hover {
            color: var(--primary-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 1100px) {
            .profile-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 991px) {
            .admin-page {
                display: block;
                padding: 0;
            }

            .admin-content {
                padding: 0 16px 16px;
            }
        }

        @media (max-width: 768px) {
            .profile-hero,
            .profile-right,
            .profile-left {
                padding: 22px 18px;
            }

            .profile-hero h1 {
                font-size: 28px;
            }

            .section-title {
                font-size: 26px;
            }

            .actions {
                justify-content: stretch;
            }

            .btn-save,
            .btn-back {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="profile-hero">
            <h1>Manage Admin Profile</h1>
            <p>Update your administrator account details, profile image, and password securely.</p>
        </section>

        <section class="profile-card">
            <aside class="profile-left">
                <div class="image-box">
                    <img src="<?php echo $profileImage; ?>"
                         id="profilePreview"
                         class="profile-preview"
                         alt="Admin Profile Image"
                         onerror="this.onerror=null;this.src='<?php echo $baseUrl; ?>/images/default_profile.png';">

                    <button type="button" class="image-upload-btn" id="chooseImageBtn">📷</button>
                    <input type="file" id="profileImageInput" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>
                </div>

                <div class="profile-name" id="profileNameText">
                    <?php echo htmlspecialchars($fullName); ?>
                </div>

                <div class="profile-email">
                    <?php echo $email; ?>
                </div>

                <div class="admin-badge">Administrator</div>
            </aside>

            <main class="profile-right">
                <h2 class="section-title">Account Information</h2>
                <p class="section-subtitle">Edit your admin personal information below.</p>

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

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Admin" readonly>
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
                        <a href="#"
                           class="btn-back loader-link"
                           data-href="<?php echo $baseUrl; ?>/dashboard/admin_dashboard.php">
                            Back to Dashboard
                        </a>

                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </main>
        </section>
    </main>
</div>

<script type="module">
    import { firebaseConfig } from "<?php echo $baseUrl; ?>/config/firebase-config.js";

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
        updateDoc,
        setDoc
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
            confirmButtonColor: "#1d4ed8"
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
        profileNameText.textContent = name || "Admin";
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

            const response = await fetch("manage_admin_profile.php", {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || "Image upload failed.");
            }

            uploadedProfileImage = data.profileImage;
            profilePreview.src = uploadedProfileImage;

            Swal.fire({
                icon: "success",
                title: "Profile Image Uploaded",
                text: "Your admin profile image has been uploaded successfully.",
                confirmButtonColor: "#1d4ed8"
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

                firstName.value = data.firstName || data.first_name || firstName.value;
                lastName.value = data.lastName || data.last_name || lastName.value;
                email.value = data.email || currentUser.email || email.value;
                phone.value = data.phone || phone.value;
                cnic.value = data.cnic || cnic.value;

                if (data.profileImage || data.profile_image) {
                    uploadedProfileImage = data.profileImage || data.profile_image;
                    profilePreview.src = uploadedProfileImage;
                }

                updateLiveName();
            }
        } catch (error) {
            showError(error.message || "Failed to load admin profile.");
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

        await fetch("manage_admin_profile.php", {
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

            await setDoc(doc(db, "users", currentUser.uid), {
                firstName: firstNameVal,
                lastName: lastNameVal,
                first_name: firstNameVal,
                last_name: lastNameVal,
                email: email.value,
                phone: phoneVal,
                cnic: cnicVal,
                profileImage: uploadedProfileImage,
                profile_image: uploadedProfileImage,
                role: "admin"
            }, { merge: true });

            await updatePhpSession(firstNameVal, lastNameVal, phoneVal, cnicVal, uploadedProfileImage);

            Swal.fire({
                icon: "success",
                title: "Admin Profile Updated",
                text: "Your admin profile has been updated successfully.",
                confirmButtonColor: "#1d4ed8"
            }).then(() => {
                window.location.reload();
            });

        } catch (error) {
            Swal.close();

            if (error.code === "auth/requires-recent-login") {
                showError("For password change, please log out and log in again, then try changing password.");
            } else if (error.code === "permission-denied") {
                showError("Firestore permission denied. Please update your Firebase rules for admin profile updates.");
            } else {
                showError(error.message || "Failed to update admin profile.");
            }
        }
    });

    loadFirestoreProfile();
</script>

<script src="/travelix/assets/vendor/bootstrap.bundle.min.js"></script>

</body>
</html>