<?php
/**
 * Guest — dispute a hotel-sent refund ("I didn't receive this"). Flips the
 * booking to 'disputed' and gives the hotel a strict 24-hour window to
 * resolve it (resend + guest reconfirms) before it auto-escalates to admin
 * and the hotel account is disabled — see hp_check_refund_slas() path C.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/commission_lib.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim((string)($input['bookingId'] ?? ''));

if ($bookingId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$docPath = 'hotel_bookings/' . $bookingId;
$booking = hp_firestore_get($saPath, $projectId, $docPath);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (!hp_booking_belongs_to_session($saPath, $projectId, $booking, $_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this booking.']);
    exit;
}

if (strtolower((string)($booking['refundStatus'] ?? '')) !== 'sent') {
    echo json_encode(['success' => false, 'message' => 'This refund is not waiting on your confirmation.']);
    exit;
}

$nowMs = (int) round(microtime(true) * 1000);
$hotelId = (string)($booking['hotelId'] ?? '');

$ok = hp_firestore_patch($saPath, $projectId, $docPath, [
    'refundStatus' => 'disputed',
    'refundDisputedAt' => date('c'),
    'refundDisputeEscalateAt' => $nowMs + (24 * 3600 * 1000),
]);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Could not record the dispute. Please try again.']);
    exit;
}

if ($hotelId !== '') {
    hp_firestore_create($saPath, $projectId, 'notifications', [
        'audience' => 'hotel', 'hotelId' => $hotelId,
        'title' => 'Refund Disputed — Guest Says They Did Not Receive It',
        'message' => 'The guest says the ' . hp_money((float)($booking['refundSentAmount'] ?? $booking['refundAmount'] ?? 0)) . ' refund never arrived. Resolve this within 24 hours or your account will be disabled.',
        'type' => 'refund_disputed', 'icon' => 'fa-solid fa-triangle-exclamation',
        'link' => '/travelix/hotel_portal/refunds.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]);
}

hp_firestore_create($saPath, $projectId, 'notifications', [
    'audience' => 'admin',
    'title' => 'Refund Disputed',
    'message' => 'A guest disputed a refund sent by ' . (string)($booking['hotelName'] ?? 'a hotel') . '. The hotel has 24 hours to resolve it before their account is disabled.',
    'type' => 'refund_pending', 'icon' => 'fa-solid fa-triangle-exclamation',
    'link' => '/travelix/admin_manage/refunds.php',
    'isRead' => false, 'createdAt' => date('c'),
]);

echo json_encode(['success' => true, 'message' => 'Dispute recorded — the hotel has been notified and has 24 hours to resolve it.']);
