<?php
/**
 * Admin — verify a guest's booking payment proof (centralized payment
 * model: the guest paid into Travelix's own account, so admin is the only
 * one who can actually confirm it arrived). Once verified, the hotel can
 * then confirm the room — hotels never "verify payment" themselves anymore.
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

$payment = is_array($booking['payment'] ?? null) ? $booking['payment'] : [];
if (strtolower((string)($payment['status'] ?? '')) !== 'awaiting_verification') {
    echo json_encode(['success' => false, 'message' => 'This payment is not waiting on verification.']);
    exit;
}

$writes = [['path'=>$docPath, 'mask'=>true, 'data'=>[
    'payment.status' => 'verified',
    'payment.adminVerifiedAt' => date('c'),
    'payment.adminVerifiedBy' => (string)($_SESSION['user']['email'] ?? 'admin'),
    'bookingStatus' => 'payment_verified',
    'hotelPayoutStatus' => 'not_sent',
]]];

$hotelId = (string)($booking['hotelId'] ?? '');
$guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
$amount = (float)($booking['totalCharged'] ?? $booking['hotelPrice'] ?? 0);

if ($guestUid !== '') {
    $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(), 'data'=>[
        'userId' => $guestUid, 'uid' => $guestUid,
        'title' => 'Payment Verified',
        'message' => 'Travelix verified your payment of ' . hp_money($amount) . '. Your hotel payout is now being processed.',
        'type' => 'payment_verified', 'icon' => 'bi-check-circle-fill',
        'link' => '/travelix/hotel/manage_bookings.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]];
}
if (!hp_firestore_commit($saPath, $projectId, $writes)) { echo json_encode(['success'=>false,'message'=>'Could not verify the payment. Please try again.']); exit; }

echo json_encode(['success' => true, 'message' => 'User payment verified. Now send the hotel its exact share with transfer proof.']);
