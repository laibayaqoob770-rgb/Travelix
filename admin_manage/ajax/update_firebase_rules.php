<?php
/**
 * One-time script to open Firestore security rules so all collections work.
 * Run once via browser: /travelix/admin_manage/ajax/update_firebase_rules.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$baseUrl     = '/travelix';
$configPath  = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
$saPath      = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

if (!file_exists($configPath) || !file_exists($saPath)) {
    echo json_encode(['success' => false, 'message' => 'Config files not found.']);
    exit;
}

require_once $configPath;
$projectId = FIREBASE_PROJECT_ID;

// ── Build JWT access token ──
function b64url($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }

function getAccessToken($saPath) {
    $sa  = json_decode(file_get_contents($saPath), true);
    $now = time();
    $hdr = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $cls = b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud'   => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $sig = '';
    openssl_sign("$hdr.$cls", $sig, $sa['private_key'], 'SHA256');
    $jwt = "$hdr.$cls." . b64url($sig);

    $ch = curl_init($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp, true)['access_token'] ?? null;
}

try {
    $token = getAccessToken($saPath);
    if (!$token) throw new Exception('Failed to get access token');

    // Firestore rules — allow read/write on all needed collections
    $rules = [
        'source' => [
            'language' => 'firebase_rules',
            'files'    => [[
                'name'    => 'firestore.rules',
                'content' => <<<'RULES'
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {

    // Hotel listings — hotels fill via portal, users read
    match /hotels/{hotelId} {
      allow read: if true;
      allow write: if true;
    }

    // Live booking sessions — booking + management sync
    match /live_booking_sessions/{sessionId} {
      allow read, write: if true;
    }

    // Hotel bookings — authenticated users can create/read their own
    match /hotel_bookings/{bookingId} {
      allow read: if request.auth != null;
      allow create: if request.auth != null;
      allow update, delete: if request.auth != null && request.auth.uid == resource.data.userId;
    }

    // Hotel destinations (legacy auto-populate)
    match /hotel_destinations/{citySlug} {
      allow read: if true;
      allow write: if true;
    }

    // User data
    match /users/{userId} {
      allow read, write: if request.auth != null && request.auth.uid == userId;
    }

    // Everything else — allow read/write for now (tighten in production)
    match /{document=**} {
      allow read, write: if true;
    }
  }
}
RULES
            ]]
        ]
    ];

    $url = "https://firebaserules.googleapis.com/v1/projects/{$projectId}/releases/cloud.firestore";

    // First: create/update the ruleset
    $rulesetUrl = "https://firebaserules.googleapis.com/v1/projects/{$projectId}/rulesets";
    $ch = curl_init($rulesetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($rules),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $rsResp = curl_exec($ch);
    $rsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rsData = json_decode($rsResp, true);
    if ($rsCode < 200 || $rsCode >= 300) {
        throw new Exception('Ruleset create failed (' . $rsCode . '): ' . $rsResp);
    }

    $rulesetName = $rsData['name'] ?? null;
    if (!$rulesetName) throw new Exception('No ruleset name returned.');

    // Second: update the release to point to the new ruleset
    $ch2 = curl_init($url);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode(['release' => ['name' => "projects/{$projectId}/releases/cloud.firestore", 'rulesetName' => $rulesetName]]),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $relResp = curl_exec($ch2);
    $relCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($relCode < 200 || $relCode >= 300) {
        throw new Exception('Release update failed (' . $relCode . '): ' . $relResp);
    }

    echo json_encode(['success' => true, 'message' => 'Firebase security rules updated successfully!', 'ruleset' => $rulesetName]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
