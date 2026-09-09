<?php
/**
 * Emails a branded PDF itinerary for one of the current user's saved trips
 * to their own registered email address.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$tripId = trim((string)($input['tripId'] ?? ''));
if ($tripId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing trip id.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/config/mail_config.php';
require_once $docRoot . $baseUrl . '/includes/send_mail.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/trip_pdf.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$trip = hp_firestore_get($saPath, $projectId, 'trips/' . $tripId);
if (!$trip) {
    echo json_encode(['success' => false, 'message' => 'Trip not found.']);
    exit;
}

$owner   = (string)($trip['uid'] ?? $trip['userId'] ?? '');
$viewer  = (string)($_SESSION['user']['uid'] ?? '');
$isAdmin = strtolower((string)($_SESSION['user']['role'] ?? '')) === 'admin';

if ($owner !== $viewer && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'You do not have access to this trip.']);
    exit;
}

$toEmail = (string)($trip['userEmail'] ?? $_SESSION['user']['email'] ?? '');
if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'No valid email address on this account.']);
    exit;
}

$userProfile = [];
if ($owner !== '') {
    $ownerDoc = hp_firestore_get($saPath, $projectId, 'users/' . $owner);
    if ($ownerDoc) {
        $userProfile = [
            'firstName' => $ownerDoc['firstName'] ?? '',
            'lastName'  => $ownerDoc['lastName'] ?? '',
            'email'     => $ownerDoc['email'] ?? '',
            'phone'     => $ownerDoc['phone'] ?? '',
        ];
    }
}

$pdf = travelix_generate_trip_pdf($trip, $userProfile);
$destination = (string)($trip['destination'] ?? $trip['toCity'] ?? 'Your Trip');

$html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4fa;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4fa;padding:40px 16px;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
      <tr>
        <td style="background:linear-gradient(135deg,#0f3460,#1a5276);padding:32px 40px;text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;">&#128506;</div>
          <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">Your Trip Itinerary</h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,.78);font-size:14px;">{$destination}</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="margin:0 0 14px;font-size:15px;color:#1e293b;">Hello,</p>
          <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.7;">
            Your trip to <b style="color:#0f3460;">{$destination}</b> has been saved on Travelix.
            A PDF copy of your itinerary is attached to this email.
          </p>
          <p style="margin:0;font-size:13px;color:#94a3b8;">Have a great trip!</p>
        </td>
      </tr>
      <tr>
        <td style="background:#f8faff;padding:18px 40px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; Travelix &middot; Smart Trip Planning</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

$destSlug = preg_replace('/[^a-zA-Z0-9]+/', '-', $destination);
$filename = 'Travelix-Trip-' . trim($destSlug, '-') . '.pdf';

$errorOut = '';
$sent = travelixSendMail($toEmail, 'Your Travelix Itinerary — ' . $destination, $html, $errorOut, [
    ['filename' => $filename, 'content' => $pdf, 'mime' => 'application/pdf'],
]);

if (!$sent) {
    echo json_encode(['success' => false, 'message' => 'Could not send the email: ' . $errorOut]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Itinerary emailed to ' . $toEmail]);
