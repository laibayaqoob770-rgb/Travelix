<?php
/** Hotel rejects a paid booking and opens the hotel-funded refund workflow. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];
if (empty($_SESSION['hotel_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Hotel login required.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once __DIR__ . '/../includes/resolve_hotel.php';

$saPath = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;
$hotel = hp_get_staff_hotel($saPath, $projectId, (string)($_SESSION['hotel_staff']['uid'] ?? ''));
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim((string)($input['bookingId'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));
$booking = $bookingId !== '' ? hp_firestore_get($saPath, $projectId, 'hotel_bookings/' . $bookingId) : null;

if (!$hotel || !$booking || (string)($booking['hotelId'] ?? '') !== (string)($hotel['id'] ?? '')) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found for your hotel.']);
    exit;
}
if ($reason === '') {
    echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']);
    exit;
}
$status = strtolower((string)($booking['bookingStatus'] ?? $booking['status'] ?? ''));
if (!in_array($status, ['pending', 'payment_verified', 'pending_hotel_confirmation'], true)) {
    echo json_encode(['success' => false, 'message' => 'This booking can no longer be rejected.']);
    exit;
}
if (strtolower((string)($booking['hotelPayoutStatus'] ?? '')) !== 'sent' || empty($booking['hotelPayoutProof'])) {
    echo json_encode(['success' => false, 'message' => 'Travelix payout and its proof are required before rejection.']);
    exit;
}

$refundAmount = max(0, (float)($booking['hotelPrice'] ?? 0));
$nowMs = (int)round(microtime(true) * 1000);
$updates = [
    'bookingStatus' => 'cancelled',
    'cancelledAt' => date('c'),
    'updatedAt' => date('c'),
    'cancelReason' => 'Rejected by hotel: ' . $reason,
    'rejectedByHotelAt' => date('c'),
    'hotelPayoutStatus' => 'confirmed_received',
    'hotelPayoutConfirmedAt' => date('c'),
    'refundAmount' => $refundAmount,
    'refundPercent' => $refundAmount > 0 ? 100 : 0,
    'refundStatus' => $refundAmount > 0 ? 'pending' : 'not_applicable',
];
if ($refundAmount > 0) {
    $updates += [
        'refundOwner' => 'hotel',
        'refundRequestedAt' => $nowMs,
        'refundWarnAt' => $nowMs + 86400000,
        'refundEscalateAt' => $nowMs + 172800000,
        'refundWarned' => false,
        'refundWrongAttempt' => false,
    ];
}

$writes = [['path'=>'hotel_bookings/'.$bookingId,'mask'=>true,'data'=>$updates]];

$payoutId = (string)($booking['hotelPayoutId'] ?? '');
if ($payoutId !== '') {
    $writes[] = ['path'=>'payout_payments/'.$payoutId,'mask'=>true,'data'=>[
        'status' => 'confirmed',
        'confirmedAt' => time(),
        'confirmedBy' => (string)($_SESSION['hotel_staff']['email'] ?? ''),
        'bookingRejected' => true,
    ]];
}

$uid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
if ($uid !== '') {
    $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>[
        'userId' => $uid, 'uid' => $uid, 'title' => 'Booking Rejected',
        'message' => 'Your booking at ' . (string)($booking['hotelName'] ?? 'the hotel') . ' was rejected. Hotel-charge refund: PKR ' . number_format($refundAmount) . '. Travelix fee is non-refundable. Reason: ' . $reason,
        'type' => 'hotel_booking_rejected', 'icon' => 'bi-x-circle-fill',
        'link' => '/travelix/hotel/manage_bookings.php', 'isRead' => false, 'createdAt' => date('c'),
    ]];
}
if ($refundAmount > 0) {
    $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>[
        'audience' => 'hotel', 'hotelId' => (string)$hotel['id'], 'title' => 'Refund Owed to Guest',
        'message' => 'Refund PKR ' . number_format($refundAmount) . ' within 48 hours for the booking you rejected.',
        'type' => 'refund_pending', 'icon' => 'fa-solid fa-hourglass-half',
        'link' => '/travelix/hotel_portal/refunds.php', 'isRead' => false, 'createdAt' => date('c'),
    ]];
}
$writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>[
    'audience' => 'admin', 'title' => 'Hotel Rejected Booking',
    'message' => (string)($hotel['name'] ?? 'Hotel') . ' rejected a paid booking. Hotel refund due: PKR ' . number_format($refundAmount) . '.',
    'type' => 'hotel_booking_rejected', 'icon' => 'fa-solid fa-circle-xmark',
    'link' => '/travelix/admin_manage/booking_payments.php', 'isRead' => false, 'createdAt' => date('c'),
]];
if (!hp_firestore_commit($saPath, $projectId, $writes)) {
    echo json_encode(['success' => false, 'message' => 'Could not reject this booking.']);
    exit;
}

echo json_encode([
    'success' => true,
    'refundAmount' => $refundAmount,
    'message' => 'Booking cancelled. The hotel-charge refund is now due under the 24/48-hour refund process.',
]);
