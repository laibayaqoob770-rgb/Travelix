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
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    header('Location: ' . $baseUrl . '/dashboard/user_dashboard.php');
    exit;
}

$firstName = (string)($currentUser['first_name'] ?? 'Admin');
$lastName = (string)($currentUser['last_name'] ?? '');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
$fullName = trim($firstName . ' ' . $lastName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1d4ed8;
            --primary-dark: #123a9c;
            --primary-soft: #eaf2ff;
            --accent: #60a5fa;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 18px 45px rgba(29, 78, 216, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        }

        .admin-page {
            display: flex;
            gap: 18px;
            padding: 14px;
            min-height: 100vh;
        }

        .admin-content {
            flex: 1;
            min-width: 0;
        }

        .page-hero,
        .controls-card,
        .table-card,
        .stats-card {
            background: #fff;
            border-radius: 22px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .page-hero {
            padding: 18px 22px;
            margin-bottom: 16px;
        }

        .page-hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .page-hero h1 {
            margin: 0 0 5px;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 26px;
        }

        .page-hero p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .hero-badge {
            padding: 8px 13px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 800;
        }

        .stats-wrap {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .stats-card {
            padding: 16px 18px;
            position: relative;
            overflow: hidden;
        }

        .stats-card::after {
            content: "";
            position: absolute;
            top: -35px;
            right: -20px;
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: rgba(96,165,250,0.13);
        }

        .stats-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 7px;
            position: relative;
            z-index: 1;
        }

        .stats-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1;
            position: relative;
            z-index: 1;
        }

        .controls-card {
            padding: 16px;
            margin-bottom: 16px;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1.5fr 0.7fr 0.7fr;
            gap: 12px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 14px;
            font-size: 13px;
            border: 1px solid rgba(15,23,42,0.10);
            box-shadow: none !important;
        }

        .btn-main,
        .btn-soft {
            min-height: 46px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 800;
            border: none;
        }

        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-soft {
            background: #eef4ff;
            color: var(--primary-dark);
            padding: 0 18px;
        }

        .table-card {
            overflow: hidden;
        }

        .table-card-head {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(15,23,42,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-card-head h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .table-card-head p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            padding: 0 10px 10px;
        }

        .table {
            width: 100%;
            margin: 0;
            table-layout: fixed;
            vertical-align: middle;
            min-width: 820px;
        }

        .table thead th {
            border: none;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 14px 10px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px 10px;
            border-top: 1px solid rgba(15,23,42,0.06);
            font-size: 13px;
            vertical-align: middle;
        }

        .col-user { width: 24%; }
        .col-phone { width: 16%; }
        .col-cnic { width: 16%; }
        .col-status { width: 14%; }
        .col-created { width: 11%; }
        .col-actions { width: 19%; }

        .table th.col-actions,
        .table td:last-child {
            text-align: left;
        }

        .action-group {
            justify-content: flex-start;
        }

        .user-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
        }

        .user-info {
            min-width: 0;
        }

        .user-name {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
            line-height: 1.2;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            color: var(--muted);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .short-text {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 76px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-status.verified {
            background: #dcfce7;
            color: #166534;
        }

        .badge-status.unverified {
            background: #fee2e2;
            color: #b91c1c;
        }

        .action-group {
            display: flex;
            gap: 7px;
            flex-wrap: nowrap;
        }

        .btn-action {
            border: none;
            border-radius: 11px;
            padding: 8px 11px;
            font-size: 11px;
            font-weight: 800;
            transition: 0.25s ease;
            white-space: nowrap;
        }

        .btn-view {
            background: #eef4ff;
            color: var(--primary-dark);
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty-state,
        .loading-row {
            padding: 28px 15px !important;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 1199px) {
            .controls-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stats-wrap {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .table {
                min-width: 900px;
            }
        }

        @media (max-width: 991px) {
            .admin-page {
                display: block;
                padding: 0;
            }

            .admin-content {
                padding: 0 14px 14px;
            }

            .page-hero {
                margin-top: 12px;
            }
        }

        @media (max-width: 767px) {
            .controls-grid,
            .stats-wrap {
                grid-template-columns: 1fr;
            }

            .page-hero h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="page-hero">
            <div class="page-hero-top">
                <div>
                    <h1>Manage Users</h1>
                    <p>View registered users, search instantly, and manage accounts safely.</p>
                </div>
                <div class="hero-badge">Live Firebase Users</div>
            </div>
        </section>

        <section class="stats-wrap">
            <div class="stats-card">
                <div class="stats-label">Total Users</div>
                <div class="stats-number" id="totalUsersCount">0</div>
            </div>

            <div class="stats-card">
                <div class="stats-label">Users</div>
                <div class="stats-number" id="normalUsersCount">0</div>
            </div>

            <div class="stats-card">
                <div class="stats-label">Email Verified</div>
                <div class="stats-number" id="verifiedUsersCount">0</div>
            </div>
        </section>

        <section class="controls-card">
            <div class="controls-grid">
                <input type="text" id="searchInput" class="form-control" placeholder="Search name, email, phone, CNIC">

                <select id="verificationFilter" class="form-select">
                    <option value="all">All Status</option>
                    <option value="verified">Verified</option>
                    <option value="unverified">Unverified</option>
                </select>

                <button type="button" class="btn btn-main" id="refreshBtn">Refresh Users</button>
            </div>
        </section>

        <section class="table-card">
            <div class="table-card-head">
                <div>
                    <h3>User Records</h3>
                    <p>Compact view for all Firebase users.</p>
                </div>
                <button type="button" class="btn btn-soft" id="resetFiltersBtn">Reset Filters</button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="col-user">User</th>
                            <th class="col-phone">Phone</th>
                            <th class="col-cnic">CNIC</th>
                            <th class="col-status">Email Status</th>
                            <th class="col-created">Created</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="6" class="loading-row">Loading users...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    function showPageLoader(url, message = 'Opening page...') {
        Swal.fire({
            title: 'Please wait...',
            text: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => {
            window.location.href = url;
        }, 700);
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('.loader-link');
        if (!link) return;

        const targetUrl = link.getAttribute('data-href');
        if (!targetUrl || targetUrl === '#') return;

        e.preventDefault();
        const isLogout = targetUrl.includes('/auth/logout.php');
        showPageLoader(targetUrl, isLogout ? 'Logging out...' : 'Opening page...');
    });
})();
</script>

<script type="module">
    import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import {
        getAuth,
        onAuthStateChanged
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";
    import {
        getFirestore,
        collection,
        getDocs
    } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    import { firebaseConfig } from "<?php echo $baseUrl; ?>/config/firebase-config.js";

    const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);

    function waitForFirebaseAuth() {
        return new Promise((resolve) => {
            onAuthStateChanged(auth, (user) => {
                resolve(user || null);
            });
        });
    }

    const searchInput = document.getElementById('searchInput');
    const verificationFilter = document.getElementById('verificationFilter');
    const refreshBtn = document.getElementById('refreshBtn');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');
    const usersTableBody = document.getElementById('usersTableBody');

    const totalUsersCount = document.getElementById('totalUsersCount');
    const normalUsersCount = document.getElementById('normalUsersCount');
    const verifiedUsersCount = document.getElementById('verifiedUsersCount');

    const DEFAULT_PROFILE = "<?php echo $baseUrl; ?>/images/default_profile.png";
    let allUsers = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';

        try {
            if (typeof value === 'object' && typeof value.toDate === 'function') {
                return value.toDate().toLocaleDateString();
            }

            if (typeof value === 'object' && value.seconds) {
                return new Date(value.seconds * 1000).toLocaleDateString();
            }

            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString();
            }
        } catch (e) {}

        return '-';
    }

    function updateStats(users) {
        const total = users.length;
        const normal = users.length;
        const verified = users.filter(user => user.emailVerified === true).length;

        totalUsersCount.textContent = total;
        normalUsersCount.textContent = normal;
        verifiedUsersCount.textContent = verified;
    }

    function getFilteredUsers() {
        const searchValue = searchInput.value.trim().toLowerCase();
        const verificationValue = verificationFilter.value;

        return allUsers.filter(user => {
            const isVerified = user.emailVerified === true;

            const searchableText = [
                user.firstName || '',
                user.lastName || '',
                `${user.firstName || ''} ${user.lastName || ''}`.trim(),
                user.email || '',
                user.phone || '',
                user.cnic || ''
            ].join(' ').toLowerCase();

            const matchesSearch = !searchValue || searchableText.includes(searchValue);
            const matchesVerification =
                verificationValue === 'all' ||
                (verificationValue === 'verified' && isVerified) ||
                (verificationValue === 'unverified' && !isVerified);

            return matchesSearch && matchesVerification;
        });
    }

    function renderUsersTable(users) {
        if (!users.length) {
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">No users found.</td>
                </tr>
            `;
            return;
        }

        usersTableBody.innerHTML = users.map(user => {
            const firstName = user.firstName || 'User';
            const lastName = user.lastName || '';
            const fullName = `${firstName} ${lastName}`.trim();
            const verified = user.emailVerified === true;
            const profileImage = user.profileImage || DEFAULT_PROFILE;

            return `
                <tr>
                    <td>
                        <div class="user-meta">
                            <img src="${escapeHtml(profileImage)}" alt="User" class="user-avatar"
                                 onerror="this.onerror=null;this.src='${escapeHtml(DEFAULT_PROFILE)}';">
                            <div class="user-info">
                                <div class="user-name" title="${escapeHtml(fullName)}">${escapeHtml(fullName)}</div>
                                <div class="user-email" title="${escapeHtml(user.email || '-')}">${escapeHtml(user.email || '-')}</div>
                            </div>
                        </div>
                    </td>

                    <td><span class="short-text" title="${escapeHtml(user.phone || '-')}">${escapeHtml(user.phone || '-')}</span></td>

                    <td><span class="short-text" title="${escapeHtml(user.cnic || '-')}">${escapeHtml(user.cnic || '-')}</span></td>

                    <td>
                        <span class="badge-status ${verified ? 'verified' : 'unverified'}">
                            ${verified ? 'Verified' : 'Unverified'}
                        </span>
                    </td>

                    <td><span class="short-text">${escapeHtml(formatDate(user.createdAt))}</span></td>

                    <td>
                        <div class="action-group">
                            <button type="button"
                                    class="btn-action btn-view view-user-btn"
                                    data-uid="${escapeHtml(user.uid || '')}">
                                View
                            </button>

                            <button type="button"
                                    class="btn-action btn-delete delete-user-btn"
                                    data-uid="${escapeHtml(user.uid || '')}"
                                    data-name="${escapeHtml(fullName)}">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderFilteredUsers() {
        renderUsersTable(getFilteredUsers());
    }

    async function loadUsers() {
        try {
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="loading-row">Loading users...</td>
                </tr>
            `;

            const authUser = await waitForFirebaseAuth();

            if (!authUser) {
                throw new Error("Firebase Auth user is not logged in. Please log out and log in again.");
            }

            const snapshot = await getDocs(collection(db, 'users'));
            const users = [];

            snapshot.forEach((docSnap) => {
                const data = docSnap.data() || {};
                const role = (data.role || 'user').toLowerCase();

                if (role === 'admin') return;

                users.push({
                    docId: docSnap.id,
                    uid: data.uid || docSnap.id,
                    firstName: data.firstName || '',
                    lastName: data.lastName || '',
                    email: data.email || '',
                    phone: data.phone || '',
                    cnic: data.cnic || '',
                    profileImage: data.profileImage || DEFAULT_PROFILE,
                    provider: data.provider || '',
                    emailVerified: data.emailVerified === true,
                    createdAt: data.createdAt || null
                });
            });

            users.sort((a, b) => {
                const nameA = `${a.firstName} ${a.lastName}`.trim().toLowerCase();
                const nameB = `${b.firstName} ${b.lastName}`.trim().toLowerCase();
                return nameA.localeCompare(nameB);
            });

            allUsers = users;
            updateStats(allUsers);
            renderFilteredUsers();
        } catch (error) {
            console.error('Users Load Error:', error);
            usersTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state" style="color:#b91c1c;">
                        Failed to load users. ${escapeHtml(error.message || 'Please check Firebase connection.')}
                    </td>
                </tr>
            `;
        }
    }

    function getUserByUid(uid) {
        return allUsers.find(user => user.uid === uid);
    }

    async function handleDeleteUser(uid, userName) {
        const user = getUserByUid(uid);

        if (!user) {
            Swal.fire({
                icon: 'error',
                title: 'User not found',
                text: 'Unable to find this user.'
            });
            return;
        }

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Delete User?',
            html: `
                <div style="text-align:left;">
                    <p><strong>${escapeHtml(userName || 'This user')}</strong> will be deleted.</p>
                    <p style="color:#b91c1c; font-weight:700;">This action cannot be undone.</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        try {
            Swal.fire({
                title: 'Deleting user...',
                text: 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('uid', uid);

            const response = await fetch('<?php echo $baseUrl; ?>/admin_manage/delete_user.php', {
                method: 'POST',
                body: formData
            });

            const raw = await response.text();
            let resultData = {};

            try {
                resultData = JSON.parse(raw);
            } catch (e) {
                throw new Error(raw || 'Invalid server response.');
            }

            if (!response.ok || !resultData.success) {
                throw new Error(resultData.message || 'Unable to delete user.');
            }

            allUsers = allUsers.filter(item => item.uid !== uid);
            updateStats(allUsers);
            renderFilteredUsers();

            Swal.fire({
                icon: 'success',
                title: 'User Deleted',
                text: resultData.message || 'The user was deleted successfully.'
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: error.message || 'Something went wrong.'
            });
        }
    }

    function handleViewUser(uid) {
        const user = getUserByUid(uid);
        if (!user) return;

        const fullName = `${user.firstName || 'User'} ${user.lastName || ''}`.trim();

        Swal.fire({
            title: fullName,
            html: `
                <div style="text-align:left; line-height:1.8; font-size:14px;">
                    <p><strong>UID:</strong> ${escapeHtml(user.uid || '-')}</p>
                    <p><strong>Email:</strong> ${escapeHtml(user.email || '-')}</p>
                    <p><strong>Phone:</strong> ${escapeHtml(user.phone || '-')}</p>
                    <p><strong>CNIC:</strong> ${escapeHtml(user.cnic || '-')}</p>
                    <p><strong>Email Verified:</strong> ${user.emailVerified ? 'Yes' : 'No'}</p>
                    <p><strong>Created:</strong> ${escapeHtml(formatDate(user.createdAt))}</p>
                </div>
            `,
            confirmButtonText: 'Close',
            width: 600
        });
    }

    searchInput.addEventListener('input', renderFilteredUsers);
    verificationFilter.addEventListener('change', renderFilteredUsers);

    refreshBtn.addEventListener('click', async function () {
        Swal.fire({
            title: 'Refreshing users...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        await loadUsers();
        Swal.close();
    });

    resetFiltersBtn.addEventListener('click', function () {
        searchInput.value = '';
        verificationFilter.value = 'all';
        renderFilteredUsers();
    });

    document.addEventListener('click', function (e) {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn && !deleteBtn.disabled) {
            handleDeleteUser(deleteBtn.dataset.uid, deleteBtn.dataset.name);
            return;
        }

        const viewBtn = e.target.closest('.view-user-btn');
        if (viewBtn) {
            handleViewUser(viewBtn.dataset.uid);
        }
    });

    loadUsers();
</script>
</body>
</html>