<?php
/**
 * Guest — confirm a hotel-sent refund was actually received. Flips the
 * booking from 'sent' to 'confirmed', which is what counts as truly settled
 * — mirrors the hotel's own confirm-payout flow with the two sides swapped
 * (hotel sends + proves, guest confirms).
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

$ok = hp_firestore_patch($saPath, $projectId, $docPath, [
    'refundStatus' => 'confirmed',
    'refundConfirmedAt' => date('c'),
]);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Could not confirm the refund. Please try again.']);
    exit;
}

$linkedTrips = hp_firestore_query($saPath, $projectId, 'trips', 'hotelBookingId', $bookingId);
foreach ($linkedTrips as $trip) {
    hp_firestore_patch($saPath, $projectId, 'trips/' . $trip['id'], [
        'bookedHotel.refundStatus' => 'confirmed',
    ]);
}

$hotelId = (string)($booking['hotelId'] ?? '');
if ($hotelId !== '') {
    hp_firestore_create($saPath, $projectId, 'notifications', [
        'audience' => 'hotel', 'hotelId' => $hotelId,
        'title' => 'Refund Confirmed by Guest',
        'message' => 'The guest confirmed they received the refund of ' . hp_money((float)($booking['refundSentAmount'] ?? $booking['refundAmount'] ?? 0)) . '. This refund is now settled.',
        'type' => 'refund_confirmed', 'icon' => 'fa-solid fa-circle-check',
        'link' => '/travelix/hotel_portal/refunds.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]);
}

echo json_encode(['success' => true, 'message' => 'Refund confirmed as received.']);
