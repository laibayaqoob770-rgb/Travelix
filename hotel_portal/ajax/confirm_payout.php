<?php
/**
 * Hotel — confirm a payout was actually received. Flips the payout record
 * from 'pending' to 'confirmed', which is what hp_build_payout_ledger()
 * treats as truly "paid" — mirrors the hotel's own booking-payment proof
 * flow with the two sides swapped (admin sends + proves, hotel confirms).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (empty($_SESSION['hotel_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/commission_lib.php';
require_once __DIR__ . '/../includes/resolve_hotel.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$staffUid = (string)($_SESSION['hotel_staff']['uid'] ?? '');
$hotel    = hp_get_staff_hotel($saPath, $projectId, $staffUid);

if (!$hotel) {
    echo json_encode(['success' => false, 'message' => 'No hotel assigned to your account.']);
    exit;
}

$hotelId = (string)($hotel['id'] ?? '');

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$payoutId = trim((string)($input['payoutId'] ?? ''));

if ($payoutId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$payout = hp_firestore_get($saPath, $projectId, "payout_payments/{$payoutId}");
if (!$payout || (string)($payout['hotelId'] ?? '') !== $hotelId) {
    echo json_encode(['success' => false, 'message' => 'Payout not found.']);
    exit;
}

if ((string)($payout['status'] ?? 'pending') === 'confirmed') {
    echo json_encode(['success' => false, 'message' => 'This payout is already confirmed.']);
    exit;
}

$writes = [['path'=>"payout_payments/{$payoutId}",'mask'=>true,'data'=>[
    'status'      => 'confirmed',
    'confirmedAt' => time(),
    'confirmedBy' => (string)($_SESSION['hotel_staff']['email'] ?? ''),
]]];

// A hotel payout confirmation is also the final booking confirmation for
// every booking covered by this transfer. This keeps the hotel portal,
// admin payment queue and dashboard in sync without an admin-side button.
$bookingIds = is_array($payout['bookingIds'] ?? null) ? $payout['bookingIds'] : [];
foreach ($bookingIds as $bookingId) {
    $bookingId = (string)$bookingId;
    if ($bookingId === '') continue;
    $booking = hp_firestore_get($saPath, $projectId, 'hotel_bookings/' . $bookingId);
    if (!$booking || (string)($booking['hotelId'] ?? '') !== $hotelId) continue;

    $writes[] = ['path'=>'hotel_bookings/'.$bookingId,'mask'=>true,'data'=>[
        'bookingStatus' => 'confirmed',
        'hotelPayoutStatus' => 'confirmed_received',
        'hotelPayoutConfirmedAt' => date('c'),
        'roomConfirmedAt' => date('c'),
    ]];

    $guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
    if ($guestUid !== '') {
        $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>[
            'userId' => $guestUid, 'uid' => $guestUid,
            'title' => 'Booking Confirmed',
            'message' => (string)($hotel['name'] ?? 'The hotel') . ' confirmed receipt of its payment and reserved your room.',
            'type' => 'hotel_booking_confirmed', 'icon' => 'bi-building-check',
            'link' => '/travelix/hotel/manage_bookings.php',
            'isRead' => false, 'createdAt' => date('c'),
        ]];
    }
}

$writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>[
    'audience' => 'admin',
    'title' => 'Payout Confirmed',
    'message' => (string)($hotel['name'] ?? 'A hotel') . ' confirmed receipt of ' . hp_money((float)($payout['amount'] ?? 0)) . '.',
    'type' => 'payout_confirmed',
    'icon' => 'fa-solid fa-circle-check',
    'link' => '/travelix/admin_manage/booking_payments.php',
    'isRead' => false,
    'createdAt' => date('c'),
]];

if (!hp_firestore_commit($saPath, $projectId, $writes)) {
    echo json_encode(['success' => false, 'message' => 'Could not confirm the payout. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Payment receipt confirmed. Covered booking(s) are now confirmed automatically.']);
