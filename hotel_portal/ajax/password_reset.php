<?php
/**
 * Hotel Portal — password reset via a 6-digit code emailed to the staff address.
 *
 * Three steps:
 *   send_code      { email }                          -> emails a code
 *   verify_code    { email, code }                    -> returns a one-time reset token
 *   reset_password { email, token, password }         -> sets the new password
 *
 * The code is stored hashed with a short expiry and a hard attempt limit, and
 * responses never reveal whether an email is registered.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

const HP_CODE_TTL      = 600;  // seconds a code stays valid
const HP_TOKEN_TTL     = 600;  // seconds the post-verification token stays valid
const HP_MAX_ATTEMPTS  = 5;
const HP_RESEND_WINDOW = 60;   // seconds between code requests for one address

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/config/mail_config.php';
require_once $docRoot . $baseUrl . '/includes/send_mail.php';
require_once __DIR__ . '/../includes/resolve_hotel.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = trim($input['action'] ?? '');
$email  = strtolower(trim($input['email'] ?? ''));

function hp_fail($message, $extra = [])
{
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
    exit;
}

function hp_ok($extra = [])
{
    echo json_encode(array_merge(['success' => true], $extra));
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    hp_fail('Please enter a valid email address.');
}

$docPath = 'hotel_password_resets/' . md5($email);

/* ── Step 1: send the code ── */
if ($action === 'send_code') {
    $staff = hp_find_staff_by_email($saPath, $projectId, $email);

    // Throttle repeat requests regardless of whether the address is registered.
    $existing = hp_firestore_get($saPath, $projectId, $docPath);
    if ($existing && (int)($existing['created_at'] ?? 0) > time() - HP_RESEND_WINDOW) {
        hp_fail('A code was just sent. Please wait a minute before requesting another.');
    }

    // Unregistered address: respond identically so the form can't be used to
    // discover which emails exist.
    if (!$staff) {
        hp_ok(['message' => 'If this email is registered as hotel staff, a verification code has been sent.']);
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $stored = hp_firestore_set($saPath, $projectId, $docPath, [
        'email'      => $email,
        'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + HP_CODE_TTL,
        'created_at' => time(),
        'attempts'   => 0,
        'used'       => false,
        'token'      => '',
        'token_exp'  => 0,
    ]);

    if (!$stored) {
        hp_fail('Could not start the reset process. Please try again.');
    }

    $emailError = '';
    $html = travelixResetCodeEmail($email, (string)($staff['hotel_name'] ?? ''), $code, (int)(HP_CODE_TTL / 60));
    $sent = travelixSendMail($email, 'Your Travelix password reset code', $html, $emailError);

    if (!$sent) {
        hp_fail('Could not send the email: ' . $emailError);
    }

    hp_ok(['message' => 'If this email is registered as hotel staff, a verification code has been sent.']);
}

/* ── Step 2: verify the code ── */
if ($action === 'verify_code') {
    $code = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
    if (strlen($code) !== 6) {
        hp_fail('Please enter the 6-digit code from your email.');
    }

    $rec = hp_firestore_get($saPath, $projectId, $docPath);
    if (!$rec || empty($rec['code_hash'])) {
        hp_fail('No active reset request found. Please request a new code.');
    }
    if (!empty($rec['used'])) {
        hp_fail('This code has already been used. Please request a new one.');
    }
    if ((int)($rec['expires_at'] ?? 0) < time()) {
        hp_fail('This code has expired. Please request a new one.');
    }
    if ((int)($rec['attempts'] ?? 0) >= HP_MAX_ATTEMPTS) {
        hp_fail('Too many incorrect attempts. Please request a new code.');
    }

    if (!password_verify($code, (string)$rec['code_hash'])) {
        $rec['attempts'] = (int)($rec['attempts'] ?? 0) + 1;
        hp_firestore_set($saPath, $projectId, $docPath, $rec);
        $left = max(0, HP_MAX_ATTEMPTS - $rec['attempts']);
        hp_fail("Incorrect code. {$left} attempt(s) remaining.");
    }

    $token = bin2hex(random_bytes(16));
    $rec['token']     = $token;
    $rec['token_exp'] = time() + HP_TOKEN_TTL;
    $rec['attempts']  = 0;
    hp_firestore_set($saPath, $projectId, $docPath, $rec);

    hp_ok(['token' => $token]);
}

/* ── Step 3: set the new password ── */
if ($action === 'reset_password') {
    $token    = trim($input['token'] ?? '');
    $password = (string)($input['password'] ?? '');

    if (strlen($password) < 6) {
        hp_fail('Password must be at least 6 characters.');
    }

    $rec = hp_firestore_get($saPath, $projectId, $docPath);
    if (!$rec || empty($rec['token']) || $token === '') {
        hp_fail('Your reset session is invalid. Please start again.');
    }
    if (!hash_equals((string)$rec['token'], $token)) {
        hp_fail('Your reset session is invalid. Please start again.');
    }
    if ((int)($rec['token_exp'] ?? 0) < time()) {
        hp_fail('Your reset session expired. Please start again.');
    }
    if (!empty($rec['used'])) {
        hp_fail('This reset link has already been used. Please start again.');
    }

    $uid = hp_lookup_auth_uid($saPath, $projectId, $email);
    if ($uid === '') {
        hp_fail('Could not find this account. Please contact the Travelix admin.');
    }

    [$ok, $err] = hp_set_user_password($saPath, FIREBASE_API_KEY, $uid, $password);
    if (!$ok) {
        hp_fail('Could not update the password: ' . $err);
    }

    // Burn the record so the code/token cannot be replayed.
    hp_firestore_set($saPath, $projectId, $docPath, [
        'email'      => $email,
        'code_hash'  => '',
        'expires_at' => 0,
        'created_at' => (int)($rec['created_at'] ?? time()),
        'attempts'   => 0,
        'used'       => true,
        'token'      => '',
        'token_exp'  => 0,
    ]);

    hp_ok(['message' => 'Your password has been updated. You can now sign in.']);
}

hp_fail('Unknown action.');
