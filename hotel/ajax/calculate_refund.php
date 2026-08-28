<?php
/**
 * Previews the refund a guest would get for cancelling a booking, based on
 * the hotel's cancellation policy — used to show "Your total was PKR X,
 * PKR Y will be refunded" before the user confirms cancellation.
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

$bookingId = trim((string)($_GET['bookingId'] ?? ''));
if ($bookingId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing booking id.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/commission_lib.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$booking = hp_firestore_get($saPath, $projectId, 'hotel_bookings/' . $bookingId);
if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (!hp_booking_belongs_to_session($saPath, $projectId, $booking, $_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this booking.']);
    exit;
}

$status = strtolower((string)($booking['bookingStatus'] ?? ''));
if ($status === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'This booking is already cancelled.']);
    exit;
}
if ($status !== 'confirmed') {
    echo json_encode(['success' => false, 'message' => 'Cancellation is available only after the hotel confirms the booking.']);
    exit;
}

$hotelId = (string)($booking['hotelId'] ?? '');
$hotel   = $hotelId !== '' ? hp_firestore_get($saPath, $projectId, 'hotels/' . $hotelId) : null;

// The Travelix platform fee is non-refundable. Hotel cancellation policy is
// applied only to the hotel charges that were paid out to the hotel.
$totalPaid   = (float)($booking['totalCharged'] ?? $booking['hotelPrice'] ?? $booking['total_amount'] ?? 0);
$hotelCharges = (float)($booking['hotelPrice'] ?? $booking['hotelCharges'] ?? $booking['total_amount'] ?? 0);
$arrivalDate = (string)($booking['arrivalDate'] ?? '');

$refund = hp_calculate_refund($hotel ?? [], $arrivalDate, $hotelCharges);

echo json_encode([
    'success'       => true,
    'totalPaid'     => $totalPaid,
    'hotelCharges'  => $hotelCharges,
    'platformFee'   => max(0, $totalPaid - $hotelCharges),
    'refundAmount'  => $refund['refundAmount'],
    'refundPercent' => $refund['refundPercent'],
    'canCancel'     => $refund['canCancel'],
    'reason'        => $refund['reason'],
]);
