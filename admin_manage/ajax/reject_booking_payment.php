<?php
/**
 * Admin — reject a guest's booking payment proof (couldn't be verified as
 * actually received). Cancels the booking outright — no room was ever
 * confirmed by the hotel at this stage, so there's nothing to undo there.
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
$reason    = trim((string)($input['reason'] ?? ''));

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
    'payment.status' => 'rejected',
    'payment.adminRejectedAt' => date('c'),
    'payment.adminRejectedBy' => (string)($_SESSION['user']['email'] ?? 'admin'),
    'payment.adminRejectReason' => $reason,
    'bookingStatus' => 'rejected',
]]];

$guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');
if ($guestUid !== '') {
    $writes[] = ['path'=>'notifications/'.hp_firestore_auto_id(), 'data'=>[
        'userId' => $guestUid, 'uid' => $guestUid,
        'title' => 'Payment Could Not Be Verified',
        'message' => 'We could not verify your payment for ' . (string)($booking['hotelName'] ?? 'your booking') . ($reason !== '' ? (': ' . $reason) : '.') . ' Your booking has been cancelled. Please contact support if you believe this is a mistake.',
        'type' => 'general', 'icon' => 'bi-x-circle-fill',
        'link' => '/travelix/hotel/manage_bookings.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]];
}
if (!hp_firestore_commit($saPath, $projectId, $writes)) { echo json_encode(['success'=>false,'message'=>'Could not reject the payment. Please try again.']); exit; }

echo json_encode(['success' => true, 'message' => 'Payment rejected and the booking has been cancelled.']);
