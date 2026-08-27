<?php
/**
 * Admin — mark an ESCALATED refund as sent to the guest's payout account.
 * Refunds are the hotel's job first; this only fires for refunds a hotel
 * failed to pay within its SLA (see includes/refund_lib.php) — admin steps
 * in to keep the guest's trust. Reads the full booking doc first and writes
 * it back with only the refund fields changed (hp_firestore_set replaces
 * the whole document, so a blind partial write would wipe every other field
 * on the booking).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (!isset($_SESSION['user']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim((string)($input['bookingId'] ?? ''));
$proofPath = trim((string)($input['proofPath'] ?? ''));

if ($bookingId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($proofPath === '' || strpos($proofPath, '/travelix/payment_proofs/') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Upload proof of the transfer before marking this refund as sent.']);
    exit;
}

$docPath = 'hotel_bookings/' . $bookingId;
$booking = hp_firestore_get($saPath, $projectId, $docPath);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (strtolower((string)($booking['refundStatus'] ?? '')) !== 'escalated') {
    echo json_encode(['success' => false, 'message' => 'This refund is not escalated to admin — it is still the hotel\'s responsibility, or already sent.']);
    exit;
}

$booking['refundStatus'] = 'sent';
$booking['refundSentAt'] = time();
$booking['refundSentBy'] = (string)($_SESSION['user']['email'] ?? 'admin');
$booking['refundProofUrl'] = $proofPath;

unset($booking['id']); // never write the document id back as a field

if (!hp_firestore_set($saPath, $projectId, $docPath, $booking)) {
    echo json_encode(['success' => false, 'message' => 'Could not save the update. Please try again.']);
    exit;
}

// Keep the guest's saved-trip snapshot in step, so the trip history page
// reflects the refund being sent instead of staying stuck on "pending".
$linkedTrips = hp_firestore_query($saPath, $projectId, 'trips', 'hotelBookingId', $bookingId);
foreach ($linkedTrips as $trip) {
    hp_firestore_patch($saPath, $projectId, 'trips/' . $trip['id'], [
        'bookedHotel.refundStatus' => 'sent',
        'bookedHotel.refundProofUrl' => $proofPath,
    ]);
}

// Let the guest know their refund actually went out, with a direct link to
// where they can see the amount — previously mark-as-sent notified no one.
$guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
if ($guestUid !== '') {
    hp_firestore_create($saPath, $projectId, 'notifications', [
        'userId' => $guestUid,
        'uid' => $guestUid,
        'title' => 'Refund Sent',
        'message' => 'Travelix sent your refund of PKR ' . number_format((float)($booking['refundAmount'] ?? 0)) . ' directly to your payout account.',
        'type' => 'refund_sent',
        'icon' => 'bi-check-circle-fill',
        'link' => '/travelix/hotel/manage_bookings.php',
        'isRead' => false,
        'createdAt' => date('c'),
    ]);
}

echo json_encode(['success' => true, 'message' => 'Refund Marked as Sent']);
