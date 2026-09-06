<?php
/**
 * Hotel — submit a refund for a cancelled booking (hotel-first refund flow).
 *
 * The hotel enters how much they actually sent + attaches proof. The server
 * never trusts the claimed amount blindly: it compares it against the
 * booking's required refundAmount.
 *   - enough sent  -> refund marked sent, guest notified with proof.
 *   - short/wrong  -> first time: immediate warning + a final 24h window.
 *                     second time: account disabled immediately, refund
 *                     escalates to admin so the guest still gets paid.
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

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim((string)($input['bookingId'] ?? ''));
$sentAmount = (float)($input['sentAmount'] ?? 0);
$proofPath = trim((string)($input['proofPath'] ?? ''));

if ($bookingId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($proofPath === '' || strpos($proofPath, '/travelix/payment_proofs/') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Upload proof of the transfer before submitting.']);
    exit;
}

if ($sentAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter the amount you actually sent.']);
    exit;
}

$docPath = 'hotel_bookings/' . $bookingId;
$booking = hp_firestore_get($saPath, $projectId, $docPath);

if (!$booking || (string)($booking['hotelId'] ?? '') !== $hotelId) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

$currentRefundStatus = strtolower((string)($booking['refundStatus'] ?? ''));
$resubmittingAfterDispute = $currentRefundStatus === 'disputed';

if (!in_array($currentRefundStatus, ['pending', 'disputed'], true) || (string)($booking['refundOwner'] ?? '') !== 'hotel') {
    echo json_encode(['success' => false, 'message' => 'This refund is no longer waiting on you.']);
    exit;
}

$requiredAmount = (float)($booking['refundAmount'] ?? 0);
$staffEmail = (string)($_SESSION['hotel_staff']['email'] ?? 'hotel');
$guestUid = (string)($booking['uid'] ?? $booking['userId'] ?? '');

// Enough was sent — but this is NOT the final state. The guest still has to
// confirm they actually received it (mirrors the admin-pays-hotel payout
// flow, just with the two sides swapped) before it's truly settled.
if ($sentAmount >= $requiredAmount - 0.01) {
    hp_firestore_patch($saPath, $projectId, $docPath, [
        'refundStatus' => 'sent',
        'refundSentAmount' => $sentAmount,
        'refundProofUrl' => $proofPath,
        'refundSentAt' => date('c'),
        'refundSentBy' => $staffEmail,
        'refundDisputedAt' => null,
        'refundDisputeEscalateAt' => null,
    ]);

    // Keep the guest's saved-trip snapshot in step too.
    $linkedTrips = hp_firestore_query($saPath, $projectId, 'trips', 'hotelBookingId', $bookingId);
    foreach ($linkedTrips as $trip) {
        hp_firestore_patch($saPath, $projectId, 'trips/' . $trip['id'], [
            'bookedHotel.refundStatus' => 'sent',
            'bookedHotel.refundProofUrl' => $proofPath,
        ]);
    }

    if ($guestUid !== '') {
        hp_firestore_create($saPath, $projectId, 'notifications', [
            'userId' => $guestUid, 'uid' => $guestUid,
            'title' => $resubmittingAfterDispute ? 'Refund Sent Again — Please Confirm' : 'Refund Sent — Please Confirm',
            'message' => 'The hotel sent ' . hp_money($sentAmount) . ' to your payout account. Please confirm you received it.',
            'type' => 'refund_sent', 'icon' => 'bi-hourglass-split',
            'link' => '/travelix/hotel/manage_bookings.php?tab=refunds',
            'isRead' => false, 'createdAt' => date('c'),
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Refund recorded as sent — waiting for the guest to confirm receipt.']);
    exit;
}

// Short/wrong amount.
$hadWrongAttempt = (bool)($booking['refundWrongAttempt'] ?? false);

if (!$hadWrongAttempt) {
    hp_firestore_patch($saPath, $projectId, $docPath, [
        'refundWrongAttempt' => true,
        'refundWarned' => true,
        'refundEscalateAt' => (int) round(microtime(true) * 1000) + (24 * 3600 * 1000),
    ]);

    hp_firestore_create($saPath, $projectId, 'notifications', [
        'audience' => 'hotel', 'hotelId' => $hotelId,
        'title' => 'Refund Amount Insufficient',
        'message' => 'You sent ' . hp_money($sentAmount) . ' but ' . hp_money($requiredAmount) . ' is owed. Send the correct amount within 24 hours or your account will be disabled.',
        'type' => 'refund_pending', 'icon' => 'fa-solid fa-triangle-exclamation',
        'link' => '/travelix/hotel_portal/refunds.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]);

    hp_firestore_create($saPath, $projectId, 'notifications', [
        'audience' => 'admin',
        'title' => 'Hotel Sent Insufficient Refund',
        'message' => (string)($hotel['name'] ?? 'A hotel') . ' sent ' . hp_money($sentAmount) . ' instead of ' . hp_money($requiredAmount) . ' for a guest refund. They have 24 hours to correct it.',
        'type' => 'refund_pending', 'icon' => 'fa-solid fa-triangle-exclamation',
        'link' => '/travelix/admin_manage/refunds.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]);

    echo json_encode(['success' => false, 'message' => 'That amount is short. You have 24 hours to send the correct amount before your account is disabled.']);
    exit;
}

// Second wrong attempt — escalate immediately.
hp_firestore_patch($saPath, $projectId, $docPath, [
    'refundStatus' => 'escalated',
    'refundOwner' => 'admin',
]);

hp_firestore_patch($saPath, $projectId, 'hotels/' . $hotelId, [
    'disabled' => true,
    'disabledReason' => 'refund_sla',
    'disabledAt' => date('c'),
]);

hp_firestore_create($saPath, $projectId, 'notifications', [
    'audience' => 'hotel', 'hotelId' => $hotelId,
    'title' => 'Account Disabled — Refund Errors',
    'message' => 'You sent the wrong refund amount twice. Your account has been disabled until Travelix admin re-enables it.',
    'type' => 'refund_escalated', 'icon' => 'fa-solid fa-ban',
    'link' => '/travelix/hotel_portal/refunds.php',
    'isRead' => false, 'createdAt' => date('c'),
]);

hp_firestore_create($saPath, $projectId, 'notifications', [
    'audience' => 'admin',
    'title' => 'Refund Escalated — Hotel Failed to Pay',
    'message' => (string)($hotel['name'] ?? 'A hotel') . ' sent the wrong refund amount twice for a guest owed ' . hp_money($requiredAmount) . '. Their account has been disabled — please pay the guest directly.',
    'type' => 'refund_pending', 'icon' => 'fa-solid fa-triangle-exclamation',
    'link' => '/travelix/admin_manage/refunds.php',
    'isRead' => false, 'createdAt' => date('c'),
]);

echo json_encode(['success' => false, 'message' => 'Wrong amount sent again — your account has been disabled. Travelix admin will pay the guest and review your account.', 'disabled' => true]);
