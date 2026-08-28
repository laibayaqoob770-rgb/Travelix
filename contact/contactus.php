<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

$currentUser = $_SESSION['user'] ?? [];

$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['user']) || isset($_SESSION['email']);

$userUid = (string)(
    $currentUser['uid']
    ?? $currentUser['user_id']
    ?? $currentUser['id']
    ?? $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? ''
);

$firstName = (string)($currentUser['first_name'] ?? $currentUser['firstName'] ?? '');
$lastName  = (string)($currentUser['last_name'] ?? $currentUser['lastName'] ?? '');
$email     = (string)($currentUser['email'] ?? $_SESSION['email'] ?? '');
$fullName  = trim($firstName . ' ' . $lastName);

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

/* -------------------------------------------------------
   Helpers
------------------------------------------------------- */

function respond_json($success, $message, $extra = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function base64url_encode_custom($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token_contact($serviceAccountPath)
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

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    $claimSet = [
        'iss'   => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud'   => $tokenUri,
        'exp'   => $now + 3600,
        'iat'   => $now
    ];

    $jwtHeader = base64url_encode_custom(json_encode($header));
    $jwtClaimSet = base64url_encode_custom(json_encode($claimSet));
    $signatureInput = $jwtHeader . '.' . $jwtClaimSet;

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

function firestore_request_contact($method, $url, $accessToken, $body = null)
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

function firestore_value_string($field, $default = '')
{
    if (!is_array($field)) return $default;
    if (isset($field['stringValue'])) return (string)$field['stringValue'];
    if (isset($field['timestampValue'])) return (string)$field['timestampValue'];
    return $default;
}

function firestore_value_bool($field, $default = false)
{
    if (!is_array($field)) return $default;
    if (isset($field['booleanValue'])) return (bool)$field['booleanValue'];
    return $default;
}

function parse_contact_document_public($doc)
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
        'createdAt'         => firestore_value_string($fields['createdAt'] ?? null),
        'showOnContactPage' => firestore_value_bool($fields['showOnContactPage'] ?? null, false),
    ];
}

function fetch_all_contact_documents_public($projectId, $accessToken)
{
    $documents = [];
    $pageToken = '';

    do {
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/contact_messages?pageSize=100";
        if ($pageToken !== '') {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        [$status, $response, $error] = firestore_request_contact('GET', $url, $accessToken);

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

function is_valid_name_contact($name)
{
    return preg_match('/^[A-Za-z\s]+$/', $name) === 1;
}

function is_valid_email_contact($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/* -------------------------------------------------------
   AJAX: Send Message
------------------------------------------------------- */

if (isset($_GET['ajax']) && $_GET['ajax'] === 'send_message') {
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond_json(false, 'Invalid request method.');
        }

        $name    = trim((string)($_POST['contactName'] ?? ''));
        $emailIn = trim((string)($_POST['contactEmail'] ?? ''));
        $subject = trim((string)($_POST['contactSubject'] ?? ''));
        $message = trim((string)($_POST['contactMessage'] ?? ''));

        if ($name === '') {
            respond_json(false, 'Please enter your full name.');
        }
        if (!is_valid_name_contact($name)) {
            respond_json(false, 'Name should only contain alphabets and spaces.');
        }
        if ($emailIn === '') {
            respond_json(false, 'Please enter your email address.');
        }
        if (!is_valid_email_contact($emailIn)) {
            respond_json(false, 'Please enter a valid email address.');
        }
        if ($subject === '') {
            respond_json(false, 'Please select a subject.');
        }
        if ($message === '') {
            respond_json(false, 'Please write your message.');
        }

        $accessToken = get_google_access_token_contact($serviceAccountPath);

        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/contact_messages";

        $payload = [
            'fields' => [
                'senderUid'         => ['stringValue' => $isLoggedIn ? $userUid : ''],
                'senderName'        => ['stringValue' => $name],
                'senderEmail'       => ['stringValue' => $emailIn],
                'subject'           => ['stringValue' => $subject],
                'message'           => ['stringValue' => $message],
                'status'            => ['stringValue' => 'new'],
                'adminReply'        => ['stringValue' => ''],
                'adminReplyBy'      => ['stringValue' => ''],
                'showOnContactPage' => ['booleanValue' => true],
                'isLoggedInUser'    => ['booleanValue' => (bool)$isLoggedIn],
                'createdAt'         => ['timestampValue' => gmdate('c')]
            ]
        ];

        [$saveStatus, $saveResponse, $saveError] = firestore_request_contact('POST', $url, $accessToken, $payload);

        if ($saveStatus < 200 || $saveStatus >= 300) {
            respond_json(false, 'Unable to save your message right now.', [
                'firebase_status' => $saveStatus,
                'firebase_error'  => $saveError
            ]);
        }

        respond_json(true, 'Your message has been saved successfully.');
    } catch (Throwable $e) {
        respond_json(false, $e->getMessage());
    }
}

/* -------------------------------------------------------
   AJAX: Live Feed
------------------------------------------------------- */

if (isset($_GET['ajax']) && $_GET['ajax'] === 'live_feed') {
    try {
        $accessToken = get_google_access_token_contact($serviceAccountPath);
        $documents = fetch_all_contact_documents_public($projectId, $accessToken);

        $messages = [];
        foreach ($documents as $doc) {
            $messages[] = parse_contact_document_public($doc);
        }

        usort($messages, function ($a, $b) {
            $aTime = strtotime($a['createdAt'] ?: '') ?: 0;
            $bTime = strtotime($b['createdAt'] ?: '') ?: 0;
            return $bTime <=> $aTime;
        });

        $visible = array_values(array_filter($messages, function ($item) {
            $hasMessage = trim((string)($item['message'] ?? '')) !== '';
            $hasReply   = trim((string)($item['adminReply'] ?? '')) !== '';
            $show       = !empty($item['showOnContactPage']);
            return $show && ($hasMessage || $hasReply);
        }));

        respond_json(true, 'Live feed loaded successfully.', [
            'items' => $visible
        ]);
    } catch (Throwable $e) {
        respond_json(false, $e->getMessage(), [
            'items' => []
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Travelix</title>

    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --primary: #1484B4;
            --primary-dark: #0f6d95;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --card-border: rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 18px 50px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 24px 60px rgba(15, 23, 42, 0.10);
            --soft-bg: #f8fbff;
            --white: #ffffff;
            --reply-bg: #f0fdf4;
            --reply-border: rgba(22, 101, 52, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(20,132,180,0.08), transparent 25%),
                radial-gradient(circle at top right, rgba(20,132,180,0.06), transparent 18%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            color: var(--text-dark);
        }

        .page-wrap {
            min-height: 100vh;
            padding: 140px 20px 80px;
        }

        .contact-hero {
            max-width: 1320px;
            margin: 0 auto 28px;
            background: linear-gradient(135deg, rgba(20,132,180,0.98), rgba(15,109,149,0.98));
            color: #fff;
            border-radius: 34px;
            padding: 40px;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
        }

        .contact-hero::before {
            content: "";
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .contact-hero::after {
            content: "";
            position: absolute;
            bottom: -80px;
            left: -60px;
            width: 240px;
            height: 240px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }

        .contact-hero-content {
            position: relative;
            z-index: 2;
        }

        .contact-hero-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .contact-hero h1 {
            margin: 0 0 12px;
            font-size: 54px;
            line-height: 1.06;
            font-weight: 800;
        }

        .contact-hero p {
            max-width: 860px;
            margin: 0;
            color: rgba(255,255,255,0.92);
            line-height: 1.9;
            font-size: 17px;
        }

        .contact-layout {
            max-width: 1320px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 24px;
            align-items: start;
        }

        .contact-card,
        .faq-card,
        .info-card,
        .live-card {
            background: var(--white);
            border: 1px solid var(--card-border);
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
        }

        .contact-card {
            padding: 32px;
            transition: 0.30s ease;
        }

        .faq-card,
        .info-card,
        .live-card {
            padding: 28px;
            transition: 0.30s ease;
        }

        .contact-card:hover,
        .faq-card:hover,
        .info-card:hover,
        .live-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .section-title {
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.15;
        }

        .section-subtitle {
            color: var(--text-muted);
            line-height: 1.9;
            margin-bottom: 22px;
            font-size: 15px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field-wrap.full {
            grid-column: 1 / -1;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
        }

        .field-input,
        .field-select,
        .field-textarea {
            width: 100%;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 18px;
            padding: 15px 16px;
            font-size: 15px;
            outline: none;
            transition: 0.25s ease;
            background: #f9fbff;
            color: var(--text-dark);
        }

        .field-input:hover,
        .field-select:hover,
        .field-textarea:hover {
            border-color: rgba(20,132,180,0.45);
        }

        .field-input:focus,
        .field-select:focus,
        .field-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20,132,180,0.12);
            background: #fff;
        }

        .field-textarea {
            min-height: 180px;
            resize: vertical;
        }

        .helper-note {
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .send-btn {
            margin-top: 22px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 15px 28px;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.3px;
            transition: 0.25s ease;
            width: 100%;
            box-shadow: 0 16px 30px rgba(20,132,180,0.22);
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(20,132,180,0.28);
        }

        .mini-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .mini-info-box {
            background: var(--soft-bg);
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 22px;
            padding: 18px;
        }

        .mini-info-box h5 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 800;
        }

        .mini-info-box p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.8;
        }

        .faq-card {
            overflow: hidden;
        }

        .faq-list {
            display: grid;
            gap: 14px;
        }

        .faq-item {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            overflow: hidden;
            transition: 0.25s ease;
            background: #fff;
        }

        .faq-item:hover {
            box-shadow: 0 12px 25px rgba(0,0,0,0.05);
        }

        .faq-question {
            width: 100%;
            border: none;
            background: #fff;
            padding: 18px 20px;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .faq-question:hover {
            background: rgba(20,132,180,0.04);
        }

        .faq-item.active .faq-question {
            background: rgba(20,132,180,0.08);
            color: var(--primary-dark);
        }

        .faq-icon {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 50%;
            background: rgba(20,132,180,0.10);
            color: var(--primary-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            transition: 0.25s ease;
        }

        .faq-item.active .faq-icon {
            transform: rotate(45deg);
            background: rgba(20,132,180,0.16);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.30s ease, padding 0.30s ease;
            padding: 0 20px;
            color: #475569;
            line-height: 1.9;
            background: #fff;
        }

        .faq-item.active .faq-answer {
            max-height: 220px;
            padding: 0 20px 18px;
        }

        .faq-answer p {
            margin: 0;
        }

        .live-section {
            max-width: 1320px;
            margin: 24px auto 0;
        }

        .live-card {
            overflow: hidden;
        }

        .live-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(20,132,180,0.08);
            color: var(--primary-dark);
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 800;
            font-size: 13px;
        }

        .feed-window {
            position: relative;
            height: 520px;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 22px;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            border: 1px solid rgba(15, 23, 42, 0.06);
            padding: 16px;
        }

        .feed-track {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .feed-window.auto-scroll .feed-track {
            animation: scrollFeed 35s linear infinite;
        }

        .feed-window.auto-scroll:hover .feed-track {
            animation-play-state: paused;
        }

        .feed-item {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }

        .feed-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .feed-head h5 {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        .feed-meta {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .subject-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf6fb;
            color: var(--primary-dark);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 800;
        }

        .feed-message,
        .feed-reply {
            border-radius: 18px;
            padding: 14px 16px;
            margin-top: 12px;
        }

        .feed-message {
            background: #f8fbff;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .feed-reply {
            background: var(--reply-bg);
            border: 1px solid var(--reply-border);
        }

        .feed-label {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #0f172a;
        }

        .feed-text {
            margin: 0;
            color: #334155;
            line-height: 1.9;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .feed-footer {
            margin-top: 10px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .feed-empty {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-muted);
            padding: 20px;
        }

        @keyframes scrollFeed {
            0% { transform: translateY(0); }
            100% { transform: translateY(calc(-100% + 520px)); }
        }

        @media (max-width: 1100px) {
            .contact-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .page-wrap {
                padding: 120px 14px 50px;
            }

            .contact-hero {
                padding: 26px 20px;
            }

            .contact-hero h1 {
                font-size: 36px;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 28px;
            }

            .feed-window {
                height: 460px;
            }

            @keyframes scrollFeed {
                0% { transform: translateY(0); }
                100% { transform: translateY(calc(-100% + 460px)); }
            }
        }
    </style>
</head>
<body>

<?php include '../includes/user_top_navbar.php'; ?>

<div class="page-wrap">
    <section class="contact-hero">
        <div class="contact-hero-content">
            <span class="contact-hero-badge">Travelix Support</span>
            <h1>Contact Us</h1>
            <p>
                Have a question, issue, booking concern, or general feedback? Send your message here.
                Your message will be saved in Firebase and reviewed by the admin team.
            </p>
        </div>
    </section>

    <section class="contact-layout">
        <div class="contact-card">
            <h2 class="section-title">Send us a message</h2>
            <p class="section-subtitle">
                Fill in the form below. If you are logged in, your details are filled automatically.
            </p>

            <form id="contactUsForm" novalidate>
                <div class="contact-grid">
                    <div class="field-wrap full">
                        <label class="field-label" for="contactName">Full Name</label>
                        <input
                            type="text"
                            class="field-input"
                            id="contactName"
                            name="contactName"
                            value="<?php echo htmlspecialchars($isLoggedIn ? $fullName : ''); ?>"
                            placeholder="Enter your full name"
                            required
                        >
                        <div class="helper-note">
                            Only alphabets and spaces are allowed.
                        </div>
                    </div>

                    <div class="field-wrap full">
                        <label class="field-label" for="contactEmail">Email Address</label>
                        <input
                            type="email"
                            class="field-input"
                            id="contactEmail"
                            name="contactEmail"
                            value="<?php echo htmlspecialchars($isLoggedIn ? $email : ''); ?>"
                            placeholder="Enter your email address"
                            required
                        >
                        <div class="helper-note">
                            Please enter a valid email address that includes @.
                        </div>
                    </div>

                    <div class="field-wrap full">
                        <label class="field-label" for="contactSubject">Subject</label>
                        <select class="field-select" id="contactSubject" name="contactSubject" required>
                            <option value="">Select subject</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Booking Support">Booking Support</option>
                            <option value="Payment Issue">Payment Issue</option>
                            <option value="Trip Planning Help">Trip Planning Help</option>
                            <option value="Technical Issue">Technical Issue</option>
                            <option value="Feedback / Suggestion">Feedback / Suggestion</option>
                        </select>
                    </div>

                    <div class="field-wrap full">
                        <label class="field-label" for="contactMessage">Message</label>
                        <textarea
                            class="field-textarea"
                            id="contactMessage"
                            name="contactMessage"
                            placeholder="Write your message here..."
                            required
                        ></textarea>
                        <div class="helper-note">
                            Your message will be saved in Firebase and can receive admin feedback below.
                        </div>
                    </div>
                </div>

                <button type="submit" class="send-btn" id="sendContactBtn">
                    Send Message
                </button>
            </form>
        </div>

        <div>
            <div class="info-card mb-4">
                <h2 class="section-title">Support information</h2>
                <p class="section-subtitle">
                    Your message is stored safely in Firebase and can be reviewed by the admin side.
                </p>

                <div class="mini-info-grid">
                    <div class="mini-info-box">
                        <h5>Booking help</h5>
                        <p>Use this form if you need help with hotel bookings, trip plans, payment concerns, or account issues.</p>
                    </div>

                    <div class="mini-info-box">
                        <h5>Admin replies</h5>
                        <p>When admin replies to your message, the response can appear below in the live feedback stream.</p>
                    </div>

                    <div class="mini-info-box">
                        <h5>Logged-in autofill</h5>
                        <p>If you are signed in, your basic details are filled automatically to save time.</p>
                    </div>
                </div>
            </div>

            <div class="faq-card">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">
                    Click a question to open the answer, and click it again to close it.
                </p>

                <div class="faq-list">
                    <div class="faq-item">
                        <button type="button" class="faq-question">
                            <span>How long does it take to get a reply?</span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <p>
                                Reply times depend on your support process. This form saves the message immediately in Firebase for admin review.
                            </p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question">
                            <span>Can I contact support without logging in?</span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <p>
                                Yes. If you are not logged in, the fields remain empty and you can manually enter your details before sending the message.
                            </p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question">
                            <span>Can I ask about trip planning and bookings together?</span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <p>
                                Yes. You can describe multiple concerns in one message, including hotel booking, trip planning, payment, or general feedback.
                            </p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question">
                            <span>Will my user ID be saved too?</span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <p>
                                Yes. If you are logged in, the sender UID is also stored with your message in Firebase.
                            </p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question">
                            <span>Can I report a technical bug here?</span>
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <p>
                                Yes. Select <strong>Technical Issue</strong> in the subject list and explain the issue clearly.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="live-section">
        <div class="live-card">
            <div class="live-header">
                <div>
                    <h2 class="section-title">Live Feedback Stream</h2>
                    <p class="section-subtitle" style="margin-bottom:0;">
                        Latest user messages and admin replies appear here automatically like a moving live comment feed.
                    </p>
                </div>
                <div class="live-badge">Live Updates</div>
            </div>

            <div class="feed-window" id="feedWindow">
                <div class="feed-empty" id="feedEmpty">
                    <div>
                        <h4 style="margin:0 0 8px; font-weight:800; color:#0f172a;">No messages yet</h4>
                        <p style="margin:0;">Once users send messages and admin replies, they will appear here automatically.</p>
                    </div>
                </div>
                <div class="feed-track" id="feedTrack" style="display:none;"></div>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/user_bottom_footer.php'; ?>

<script>
    const BASE_URL = <?php echo json_encode($baseUrl); ?>;
    const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
    const defaultFullName = <?php echo json_encode($fullName); ?>;
    const defaultEmail = <?php echo json_encode($email); ?>;

    const contactUsForm = document.getElementById('contactUsForm');
    const nameInput = document.getElementById('contactName');
    const feedTrack = document.getElementById('feedTrack');
    const feedEmpty = document.getElementById('feedEmpty');
    const feedWindow = document.getElementById('feedWindow');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showLoader(title = 'Please wait...', text = 'Submitting your message...') {
        Swal.fire({
            title,
            text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function closeLoader() {
        Swal.close();
    }

    function showError(title, text) {
        Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonColor: '#1484B4'
        });
    }

    function showSuccess(title, text) {
        return Swal.fire({
            icon: 'success',
            title,
            text,
            confirmButtonColor: '#1484B4'
        });
    }

    function validateForm(payload) {
        const nameRegex = /^[A-Za-z\s]+$/;

        if (!payload.name.trim()) {
            showError('Name Required', 'Please enter your full name.');
            return false;
        }

        if (!nameRegex.test(payload.name.trim())) {
            showError('Invalid Name', 'Name should only contain alphabets and spaces.');
            return false;
        }

        if (!payload.email.trim()) {
            showError('Email Required', 'Please enter your email address.');
            return false;
        }

        if (!payload.email.includes('@')) {
            showError('Invalid Email', 'Email must contain @ symbol.');
            return false;
        }

        if (!payload.subject.trim()) {
            showError('Subject Required', 'Please select a subject.');
            return false;
        }

        if (!payload.message.trim()) {
            showError('Message Required', 'Please write your message.');
            return false;
        }

        return true;
    }

    function formatDate(value) {
        if (!value) return '-';

        const date = new Date(value);
        if (isNaN(date.getTime())) return value;

        return date.toLocaleString();
    }

    function renderFeed(messages) {
        const visible = (messages || []).filter(item => {
            const hasMessage = (item.message || '').trim() !== '';
            const hasReply = (item.adminReply || '').trim() !== '';
            return !!item.showOnContactPage && (hasMessage || hasReply);
        });

        if (!visible.length) {
            feedEmpty.style.display = 'flex';
            feedTrack.style.display = 'none';
            feedTrack.innerHTML = '';
            feedWindow.classList.remove('auto-scroll');
            return;
        }

        const html = visible.map(item => `
            <div class="feed-item">
                <div class="feed-head">
                    <div>
                        <h5>${escapeHtml(item.senderName || 'User')}</h5>
                        <p class="feed-meta">
                            ${escapeHtml(item.senderEmail || '-')}<br>
                            ${escapeHtml(formatDate(item.createdAt))}
                        </p>
                    </div>
                    <div class="subject-badge">${escapeHtml(item.subject || 'No Subject')}</div>
                </div>

                <div class="feed-message">
                    <div class="feed-label">User Message</div>
                    <p class="feed-text">${escapeHtml(item.message || '')}</p>
                </div>

                ${(item.adminReply || '').trim() !== '' ? `
                    <div class="feed-reply">
                        <div class="feed-label">Admin Feedback</div>
                        <p class="feed-text">${escapeHtml(item.adminReply || '')}</p>
                        <div class="feed-footer">
                            By ${escapeHtml(item.adminReplyBy || 'Admin')} • ${escapeHtml(formatDate(item.adminReplyAt))}
                        </div>
                    </div>
                ` : ''}
            </div>
        `).join('');

        feedTrack.innerHTML = html;
        feedEmpty.style.display = 'none';
        feedTrack.style.display = 'flex';

        if (visible.length >= 4) {
            feedWindow.classList.add('auto-scroll');
        } else {
            feedWindow.classList.remove('auto-scroll');
        }
    }

    async function loadLiveFeed() {
        try {
            const response = await fetch(`${BASE_URL}/contact/contactus.php?ajax=live_feed`, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to load live feedback.');
            }

            renderFeed(result.items || []);
        } catch (error) {
            console.error('Live feed error:', error);
            feedEmpty.style.display = 'flex';
            feedTrack.style.display = 'none';
            feedTrack.innerHTML = '';
            feedWindow.classList.remove('auto-scroll');
        }
    }

    nameInput?.addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-z\s]/g, '');
    });

    contactUsForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const payload = {
            contactName: document.getElementById('contactName')?.value || '',
            contactEmail: document.getElementById('contactEmail')?.value || '',
            contactSubject: document.getElementById('contactSubject')?.value || '',
            contactMessage: document.getElementById('contactMessage')?.value || ''
        };

        if (!validateForm({
            name: payload.contactName,
            email: payload.contactEmail,
            subject: payload.contactSubject,
            message: payload.contactMessage
        })) return;

        try {
            showLoader();

            const formData = new FormData();
            formData.append('contactName', payload.contactName);
            formData.append('contactEmail', payload.contactEmail);
            formData.append('contactSubject', payload.contactSubject);
            formData.append('contactMessage', payload.contactMessage);

            const response = await fetch(`${BASE_URL}/contact/contactus.php?ajax=send_message`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();

            closeLoader();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to save your message right now.');
            }

            await showSuccess('Message Sent', result.message || 'Your message has been saved successfully.');

            contactUsForm.reset();

            if (isLoggedIn) {
                document.getElementById('contactName').value = defaultFullName;
                document.getElementById('contactEmail').value = defaultEmail;
            }

            await loadLiveFeed();

        } catch (error) {
            console.error('Contact form error:', error);
            closeLoader();
            showError('Send Failed', error.message || 'Unable to save your message right now. Please try again.');
        }
    });

    document.querySelectorAll('.faq-item').forEach((item) => {
        const btn = item.querySelector('.faq-question');

        btn.addEventListener('click', function () {
            item.classList.toggle('active');
        });
    });

    loadLiveFeed();
    setInterval(loadLiveFeed, 8000);
</script>

</body>
</html>