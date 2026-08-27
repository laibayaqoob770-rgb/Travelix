<?php
/** Admin sends one verified booking's hotel share, with mandatory proof. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];
if (!isset($_SESSION['user']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']); exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/commission_lib.php';
$saPath = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim((string)($input['bookingId'] ?? ''));
$proofPath = trim((string)($input['proofPath'] ?? ''));

if ($bookingId === '' || $proofPath === '' || strpos($proofPath, '/travelix/payment_proofs/') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Booking and valid hotel transfer proof are required.']); exit;
}
$booking = hp_firestore_get($saPath, $projectId, 'hotel_bookings/' . $bookingId);
if (!$booking || strtolower((string)($booking['payment']['status'] ?? '')) !== 'verified') {
    echo json_encode(['success' => false, 'message' => 'Verify the user payment before paying the hotel.']); exit;
}
if (!in_array(strtolower((string)($booking['hotelPayoutStatus'] ?? 'not_sent')), ['not_sent', ''], true)) {
    echo json_encode(['success' => false, 'message' => 'This hotel payout has already been recorded.']); exit;
}
$hotelId = (string)($booking['hotelId'] ?? '');
$hotel = $hotelId !== '' ? hp_firestore_get($saPath, $projectId, 'hotels/' . $hotelId) : null;
if (!$hotel || empty($hotel['paymentMethod']) || empty($hotel['paymentAccountNumber'])) {
    echo json_encode(['success' => false, 'message' => 'The hotel has no complete payout account details.']); exit;
}
$amount = (float)($booking['hotelPrice'] ?? $booking['hotelCharges'] ?? 0);
if ($amount <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid hotel payout amount.']); exit; }

$payoutId = hp_firestore_auto_id();
$writes = [['path'=>'payout_payments/'.$payoutId, 'data'=>[
    'hotelId' => $hotelId, 'hotelName' => (string)($booking['hotelName'] ?? $hotel['name'] ?? ''),
    'amount' => $amount, 'bookingIds' => [$bookingId], 'bookingCount' => 1,
    'proofUrl' => $proofPath, 'status' => 'pending', 'sentAt' => time(),
    'sentBy' => (string)($_SESSION['user']['email'] ?? 'admin'),
]]];

$writes[] = ['path'=>'hotel_bookings/'.$bookingId, 'mask'=>true, 'data'=>[
    'bookingStatus' => 'pending_hotel_confirmation',
    'hotelPayoutStatus' => 'sent', 'hotelPayoutId' => $payoutId,
    'hotelPayoutProof' => $proofPath, 'hotelPayoutAmount' => $amount,
    'hotelPayoutSentAt' => date('c'),
]];

$writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(), 'data'=>[
    'audience' => 'hotel', 'hotelId' => $hotelId,
    'title' => 'Payment Received — Confirm Booking',
    'message' => 'Travelix sent ' . hp_money($amount) . ' for ' . (string)($booking['hotelName'] ?? 'this booking') . '. Check your account and confirm the booking.',
    'type' => 'payout_sent', 'icon' => 'fa-solid fa-money-bill-transfer',
    'link' => '/travelix/hotel_portal/hotel_bookings.php?booking=' . rawurlencode($bookingId),
    'isRead' => false, 'createdAt' => date('c'),
]];
$guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
if ($guestUid !== '') $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(), 'data'=>[
    'userId' => $guestUid, 'uid' => $guestUid, 'title' => 'Hotel Payment Sent',
    'message' => 'Travelix sent the hotel its share. Waiting for the hotel to verify receipt and confirm your room.',
    'type' => 'payout_sent', 'icon' => 'bi-hourglass-split',
    'link' => '/travelix/hotel/manage_bookings.php', 'isRead' => false, 'createdAt' => date('c'),
]];
if (!hp_firestore_commit($saPath, $projectId, $writes)) { echo json_encode(['success'=>false,'message'=>'Could not record the payout.']); exit; }
echo json_encode(['success' => true, 'message' => 'Hotel payment recorded with proof. Awaiting hotel confirmation.']);
