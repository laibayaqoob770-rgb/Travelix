<?php
/**
 * Hotel Portal — change password for the signed-in staff member.
 * Requires the current password, so a hijacked session alone can't lock the owner out.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['hotel_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once __DIR__ . '/../includes/resolve_hotel.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$input       = json_decode(file_get_contents('php://input'), true) ?: [];
$currentPass = (string)($input['current_password'] ?? '');
$newPass     = (string)($input['new_password'] ?? '');

$email = (string)($_SESSION['hotel_staff']['email'] ?? '');
$uid   = (string)($_SESSION['hotel_staff']['uid'] ?? '');

if ($email === '' || $uid === '') {
    echo json_encode(['success' => false, 'message' => 'Session is incomplete. Please log in again.']);
    exit;
}
if ($currentPass === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter your current password.']);
    exit;
}
if (strlen($newPass) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    exit;
}
if ($currentPass === $newPass) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from your current password.']);
    exit;
}

// Confirm the current password really belongs to this account.
if (!hp_verify_password(FIREBASE_API_KEY, $email, $currentPass)) {
    echo json_encode(['success' => false, 'message' => 'Your current password is incorrect.']);
    exit;
}

[$ok, $err] = hp_set_user_password($saPath, FIREBASE_API_KEY, $uid, $newPass);
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Could not update the password: ' . $err]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Your password has been updated.']);
