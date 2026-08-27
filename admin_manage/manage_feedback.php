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

$configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
if (!file_exists($configPath)) {
    die('firebase_config.php file not found.');
}
require_once $configPath;

if (!defined('FIREBASE_PROJECT_ID') || !FIREBASE_PROJECT_ID) {
    die('FIREBASE_PROJECT_ID is missing in firebase_config.php.');
}

$serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    die('firebase-service-account.json file not found.');
}

$projectId = FIREBASE_PROJECT_ID;
$pageTitle = 'Manage Feedback | Travelix Admin';
$flash = null;

/* -------------------------------------------------------
   Helpers
------------------------------------------------------- */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base64url_encode_custom($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token_manage($serviceAccountPath)
{
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

    if (!$serviceAccount) {
        throw new Exception('Unable to read firebase-service-account.json.');
    }

    $clientEmail = $serviceAccount['client_email'] ?? '';
    $privateKey  = $serviceAccount['private_key'] ?? '';
    $tokenUri    = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

    if ($clientEmail === '' || $privateKey === '') {
        throw new Exception('Invalid service account file.');
    }

    $now = time();

    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claimSet = [
        'iss'   => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud'   => $tokenUri,
        'exp'   => $now + 3600,
        'iat'   => $now
    ];

    $jwtHeader = base64url_encode_custom(json_encode($header));
    $jwtClaim  = base64url_encode_custom(json_encode($claimSet));
    $signatureInput = $jwtHeader . '.' . $jwtClaim;

    $signature = '';
    $success = openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
    if (!$success) {
        throw new Exception('Failed to sign JWT with service account private key.');
    }

    $jwt = $signatureInput . '.' . base64url_encode_custom($signature);

    $postFields = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt
    ]);

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || !$response) {
        throw new Exception('Failed to get Google access token. ' . $error);
    }

    $json = json_decode($response, true);
    $accessToken = $json['access_token'] ?? '';

    if ($accessToken === '') {
        throw new Exception('Google access token missing in token response.');
    }

    return $accessToken;
}

function firestore_request_manage($method, $url, $accessToken, $body = null)
{
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return [$status, $response, $error];
}

function firestore_patch_document_manage($docUrl, $accessToken, $fieldsToUpdate)
{
    $query = [];
    foreach (array_keys($fieldsToUpdate) as $fieldPath) {
        $query[] = 'updateMask.fieldPaths=' . urlencode($fieldPath);
    }

    $url = $docUrl . '?' . implode('&', $query);

    $payload = [
        'fields' => $fieldsToUpdate
    ];

    return firestore_request_manage('PATCH', $url, $accessToken, $payload);
}

function firestore_value_string($field, $default = '')
{
    if (!is_array($field)) return $default;
    if (isset($field['stringValue'])) return (string)$field['stringValue'];
    if (isset($field['timestampValue'])) return (string)$field['timestampValue'];
    if (isset($field['booleanValue'])) return $field['booleanValue'] ? 'true' : 'false';
    return $default;
}

function firestore_value_bool($field, $default = false)
{
    if (!is_array($field)) return $default;
    if (isset($field['booleanValue'])) return (bool)$field['booleanValue'];
    return $default;
}

function parse_contact_document($doc)
{
    $fields = $doc['fields'] ?? [];
    $namePath = $doc['name'] ?? '';
    $docId = '';

    if ($namePath !== '') {
        $parts = explode('/', $namePath);
        $docId = end($parts);
    }

    return [
        'id'                => $docId,
        'senderUid'         => firestore_value_string($fields['senderUid'] ?? null),
        'senderName'        => firestore_value_string($fields['senderName'] ?? null),
        'senderEmail'       => firestore_value_string($fields['senderEmail'] ?? null),
        'subject'           => firestore_value_string($fields['subject'] ?? null),
        'message'           => firestore_value_string($fields['message'] ?? null),
        'status'            => firestore_value_string($fields['status'] ?? null, 'new'),
        'adminReply'        => firestore_value_string($fields['adminReply'] ?? null),
        'adminReplyBy'      => firestore_value_string($fields['adminReplyBy'] ?? null),
        'adminReplyAt'      => firestore_value_string($fields['adminReplyAt'] ?? null),
        'reviewedAt'        => firestore_value_string($fields['reviewedAt'] ?? null),
        'createdAt'         => firestore_value_string($fields['createdAt'] ?? null),
        'showOnContactPage' => firestore_value_bool($fields['showOnContactPage'] ?? null, false),
        'isLoggedInUser'    => firestore_value_bool($fields['isLoggedInUser'] ?? null, false),
    ];
}

function fetch_all_contact_documents($projectId, $accessToken)
{
    $documents = [];
    $pageToken = '';

    do {
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/contact_messages?pageSize=100";
        if ($pageToken !== '') {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        [$status, $response, $error] = firestore_request_manage('GET', $url, $accessToken);

        if ($status < 200 || $status >= 300) {
            throw new Exception('Failed to fetch contact messages from Firebase. ' . $error);
        }

        $json = json_decode($response, true);
        if (!$json) {
            throw new Exception('Invalid Firebase response while loading contact messages.');
        }

        foreach (($json['documents'] ?? []) as $doc) {
            $documents[] = $doc;
        }

        $pageToken = (string)($json['nextPageToken'] ?? '');
    } while ($pageToken !== '');

    return $documents;
}

function format_datetime_pretty($value)
{
    if (!$value) return '-';
    $time = strtotime($value);
    if (!$time) return $value;
    return date('d M Y, h:i A', $time);
}

/* -------------------------------------------------------
   Handle Actions
------------------------------------------------------- */

try {
    $accessToken = get_google_access_token_manage($serviceAccountPath);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $messageId = trim((string)($_POST['message_id'] ?? ''));

        if ($messageId === '') {
            throw new Exception('Message ID is required.');
        }

        $docUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/contact_messages/{$messageId}";

        if ($action === 'mark_reviewed') {
            $fieldsToUpdate = [
                'status' => ['stringValue' => 'reviewed'],
                'reviewedAt' => ['timestampValue' => gmdate('c')]
            ];

            [$saveStatus, $saveResponse, $saveError] = firestore_patch_document_manage($docUrl, $accessToken, $fieldsToUpdate);

            if ($saveStatus < 200 || $saveStatus >= 300) {
                throw new Exception('Failed to mark message as reviewed. ' . $saveError);
            }

            $flash = [
                'type'    => 'success',
                'title'   => 'Updated',
                'message' => 'Message marked as reviewed successfully.'
            ];
        }

        if ($action === 'save_reply') {
            $replyText = trim((string)($_POST['admin_reply'] ?? ''));
            if ($replyText === '') {
                throw new Exception('Please write a reply before saving.');
            }

            $fieldsToUpdate = [
                'adminReply'        => ['stringValue' => $replyText],
                'adminReplyBy'      => ['stringValue' => ($fullName ?: 'Admin')],
                'adminReplyAt'      => ['timestampValue' => gmdate('c')],
                'status'            => ['stringValue' => 'reviewed'],
                'reviewedAt'        => ['timestampValue' => gmdate('c')],
                'showOnContactPage' => ['booleanValue' => true]
            ];

            [$saveStatus, $saveResponse, $saveError] = firestore_patch_document_manage($docUrl, $accessToken, $fieldsToUpdate);

            if ($saveStatus < 200 || $saveStatus >= 300) {
                throw new Exception('Failed to save admin reply. ' . $saveError);
            }

            $flash = [
                'type'    => 'success',
                'title'   => 'Reply Saved',
                'message' => 'Admin reply saved successfully without changing the user message.'
            ];
        }
    }

    $documents = fetch_all_contact_documents($projectId, $accessToken);
    $messages = [];

    foreach ($documents as $doc) {
        $messages[] = parse_contact_document($doc);
    }

    usort($messages, function ($a, $b) {
        $aTime = strtotime($a['createdAt'] ?: '') ?: 0;
        $bTime = strtotime($b['createdAt'] ?: '') ?: 0;
        return $bTime <=> $aTime;
    });

} catch (Throwable $e) {
    $flash = [
        'type'    => 'error',
        'title'   => 'Error',
        'message' => $e->getMessage()
    ];
    $messages = [];
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$replyFilter  = trim((string)($_GET['reply'] ?? 'all'));

$filteredMessages = [];

foreach ($messages as $item) {
    $matchesSearch = true;
    $matchesStatus = true;
    $matchesReply  = true;

    if ($search !== '') {
        $needle = mb_strtolower($search);
        $haystack = mb_strtolower(implode(' ', [
            (string)$item['senderName'],
            (string)$item['senderEmail'],
            (string)$item['subject'],
            (string)$item['message'],
            (string)$item['adminReply']
        ]));
        $matchesSearch = mb_stripos($haystack, $needle) !== false;
    }

    if ($statusFilter !== 'all') {
        $matchesStatus = (($item['status'] ?: 'new') === $statusFilter);
    }

    $hasReply = trim((string)$item['adminReply']) !== '';
    if ($replyFilter === 'replied') {
        $matchesReply = $hasReply;
    } elseif ($replyFilter === 'not_replied') {
        $matchesReply = !$hasReply;
    }

    if ($matchesSearch && $matchesStatus && $matchesReply) {
        $filteredMessages[] = $item;
    }
}

$totalMessages = count($messages);
$totalNew = count(array_filter($messages, fn($m) => ($m['status'] ?: 'new') === 'new'));
$totalReviewed = count(array_filter($messages, fn($m) => ($m['status'] ?? '') === 'reviewed'));
$totalReplied = count(array_filter($messages, fn($m) => trim((string)($m['adminReply'] ?? '')) !== ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>

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
            --danger-soft: #fee2e2;
            --danger-text: #b91c1c;
            --success-soft: #dcfce7;
            --success-text: #166534;
            --warning-soft: #fff7ed;
            --warning-text: #c2410c;
            --card-bg: rgba(255,255,255,0.96);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(96,165,250,0.16), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            font-family: Arial, sans-serif;
            color: var(--text);
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

        .page-hero,
        .admin-card,
        .feedback-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
        }

        .page-hero {
            padding: 24px 28px;
            margin-bottom: 22px;
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-hero h1 {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 34px;
        }

        .page-hero p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
            max-width: 900px;
        }

        .hero-badge {
            padding: 10px 15px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 14px;
            font-weight: 700;
            border: 1px solid rgba(29, 78, 216, 0.10);
        }

        .admin-card {
            padding: 24px;
            margin-bottom: 22px;
        }

        .top-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .admin-info-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #f4fbfd;
            border: 1px solid rgba(20, 132, 180, 0.16);
        }

        .admin-info-bar img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 2px solid rgba(15, 23, 42, 0.08);
        }

        .admin-info-bar h6 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .admin-info-bar p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 20px;
            padding: 18px;
        }

        .stat-card h6 {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .stat-card p {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }

        .toolbar-row {
            display: grid;
            grid-template-columns: 1fr 220px 220px 140px;
            gap: 12px;
            margin-top: 18px;
        }

        .form-control,
        .form-select,
        textarea {
            width: 100%;
            border: 1px solid rgba(15,23,42,0.10);
            border-radius: 16px;
            min-height: 52px;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        .btn-main,
        .btn-success-soft {
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 800;
            transition: 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
        }

        .btn-main:hover { color: #fff; transform: translateY(-1px); }
        .btn-success-soft { background: var(--success-soft); color: var(--success-text); }

        .feedback-list { display: grid; gap: 18px; }
        .feedback-card { padding: 22px; }

        .feedback-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .feedback-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .badge-pill {
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-new { background: var(--warning-soft); color: var(--warning-text); }
        .badge-reviewed { background: var(--success-soft); color: var(--success-text); }
        .badge-subject { background: var(--primary-soft); color: var(--primary-dark); }

        .feedback-card h3 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .meta-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .message-box, .reply-box {
            margin-top: 16px;
            border-radius: 18px;
            padding: 16px;
        }

        .message-box {
            background: #f8fbff;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .reply-box {
            background: #f0fdf4;
            border: 1px solid rgba(22, 101, 52, 0.12);
        }

        .section-label {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #0f172a;
        }

        .section-text {
            margin: 0;
            color: #334155;
            font-size: 14px;
            line-height: 1.9;
            white-space: pre-wrap;
        }

        .reply-editor { margin-top: 16px; }
        .reply-editor textarea { min-height: 140px; resize: vertical; }

        .action-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .empty-state {
            padding: 40px 24px;
            border: 1px dashed rgba(15, 23, 42, 0.16);
            border-radius: 20px;
            text-align: center;
            color: var(--muted);
            background: #f8fbfc;
        }

        .empty-state h4 {
            margin: 0 0 10px;
            color: #0f172a;
            font-weight: 800;
        }

        @media (max-width: 1199px) {
            .admin-page { display: block; padding: 0; }
            .admin-content { padding: 0 16px 16px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .toolbar-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 767px) {
            .page-hero, .admin-card, .feedback-card { padding: 18px; }
            .page-hero h1 { font-size: 28px; }
            .stats-grid { grid-template-columns: 1fr; }
            .feedback-top { flex-direction: column; }
            .action-row > * { width: 100%; }
        }
    </style>
</head>
<body>
<div class="admin-page">
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="admin-content">
        <section class="page-hero">
            <div class="hero-top">
                <div>
                    <h1>Manage User Feedback</h1>
                    <p>
                        Review all contact requests sent by users, reply to them, and publish your feedback
                        so it appears live on the user Contact Us page below the form.
                    </p>
                </div>
                <div class="hero-badge">Server-Side Firebase Feedback</div>
            </div>
        </section>

        <section class="admin-card">
            <div class="top-info">
                <div class="admin-info-bar">
                    <img src="<?php echo h($profileImage); ?>" alt="Admin">
                    <div>
                        <h6><?php echo h($fullName ?: 'Admin'); ?></h6>
                        <p>Review requests, reply to users, and mark issues as reviewed.</p>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h6>Total Messages</h6>
                    <p><?php echo (int)$totalMessages; ?></p>
                </div>
                <div class="stat-card">
                    <h6>New</h6>
                    <p><?php echo (int)$totalNew; ?></p>
                </div>
                <div class="stat-card">
                    <h6>Reviewed</h6>
                    <p><?php echo (int)$totalReviewed; ?></p>
                </div>
                <div class="stat-card">
                    <h6>With Reply</h6>
                    <p><?php echo (int)$totalReplied; ?></p>
                </div>
            </div>

            <form method="GET" class="toolbar-row">
                <input
                    type="text"
                    name="search"
                    value="<?php echo h($search); ?>"
                    class="form-control"
                    placeholder="Search by name, email, subject, or message..."
                >

                <select name="status" class="form-select">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="reviewed" <?php echo $statusFilter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                </select>

                <select name="reply" class="form-select">
                    <option value="all" <?php echo $replyFilter === 'all' ? 'selected' : ''; ?>>All Replies</option>
                    <option value="replied" <?php echo $replyFilter === 'replied' ? 'selected' : ''; ?>>With Reply</option>
                    <option value="not_replied" <?php echo $replyFilter === 'not_replied' ? 'selected' : ''; ?>>No Reply</option>
                </select>

                <button type="submit" class="btn-main">Filter</button>
            </form>
        </section>

        <section class="feedback-list">
            <?php if (empty($filteredMessages)): ?>
                <div class="empty-state">
                    <h4>No feedback found</h4>
                    <p>No contact request matched the current filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($filteredMessages as $item): ?>
                    <?php $hasReply = trim((string)$item['adminReply']) !== ''; ?>
                    <article class="feedback-card">
                        <div class="feedback-top">
                            <div>
                                <h3><?php echo h($item['senderName'] ?: 'Unknown User'); ?></h3>
                                <p class="meta-text">
                                    <strong>Email:</strong> <?php echo h($item['senderEmail'] ?: '-'); ?><br>
                                    <strong>Created:</strong> <?php echo h(format_datetime_pretty($item['createdAt'])); ?><br>
                                    <strong>UID:</strong> <?php echo h($item['senderUid'] ?: 'Guest User'); ?>
                                </p>

                                <div class="feedback-badges">
                                    <span class="badge-pill badge-subject"><?php echo h($item['subject'] ?: 'No Subject'); ?></span>
                                    <span class="badge-pill <?php echo (($item['status'] ?: 'new') === 'reviewed') ? 'badge-reviewed' : 'badge-new'; ?>">
                                        <?php echo h($item['status'] ?: 'new'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="action-row">
                                <form method="POST" class="mark-reviewed-form">
                                    <input type="hidden" name="action" value="mark_reviewed">
                                    <input type="hidden" name="message_id" value="<?php echo h($item['id']); ?>">
                                    <button type="submit" class="btn-success-soft">Mark Reviewed</button>
                                </form>
                            </div>
                        </div>

                        <div class="message-box">
                            <div class="section-label">User Message</div>
                            <p class="section-text"><?php echo nl2br(h($item['message'] ?: '')); ?></p>
                        </div>

                        <?php if ($hasReply): ?>
                            <div class="reply-box">
                                <div class="section-label">Admin Reply</div>
                                <p class="section-text"><?php echo nl2br(h($item['adminReply'])); ?></p>
                                <p class="meta-text" style="margin-top:10px;">
                                    <strong>By:</strong> <?php echo h($item['adminReplyBy'] ?: 'Admin'); ?><br>
                                    <strong>Updated:</strong> <?php echo h(format_datetime_pretty($item['adminReplyAt'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="reply-editor">
                            <form method="POST" class="save-reply-form">
                                <input type="hidden" name="action" value="save_reply">
                                <input type="hidden" name="message_id" value="<?php echo h($item['id']); ?>">

                                <label class="section-label" for="reply_<?php echo h($item['id']); ?>">Write / Update Admin Reply</label>
                                <textarea
                                    id="reply_<?php echo h($item['id']); ?>"
                                    name="admin_reply"
                                    placeholder="Write your reply for this user..."
                                ><?php echo h($item['adminReply']); ?></textarea>

                                <div class="action-row">
                                    <button type="submit" class="btn-main">Save Reply</button>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    function showLoader(title = 'Please wait...', text = 'Processing request...') {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    document.querySelectorAll('.mark-reviewed-form').forEach((form) => {
        form.addEventListener('submit', function () {
            showLoader('Updating...', 'Marking message as reviewed...');
        });
    });

    document.querySelectorAll('.save-reply-form').forEach((form) => {
        form.addEventListener('submit', function (e) {
            const textarea = form.querySelector('textarea[name="admin_reply"]');
            if (!textarea || !textarea.value.trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Reply Required',
                    text: 'Please write a reply before saving.',
                    confirmButtonColor: '#1484B4'
                });
                return;
            }

            showLoader('Saving Reply...', 'Publishing admin feedback...');
        });
    });

    <?php if ($flash): ?>
    Swal.fire({
        icon: <?php echo json_encode($flash['type']); ?>,
        title: <?php echo json_encode($flash['title']); ?>,
        text: <?php echo json_encode($flash['message']); ?>,
        confirmButtonColor: '#1484B4'
    });
    <?php endif; ?>
</script>
</body>
</html>