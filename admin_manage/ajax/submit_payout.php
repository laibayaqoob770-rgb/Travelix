<?php
/**
 * Admin — record that a hotel's outstanding payout (100% of their confirmed
 * booking revenue — the 12% platform fee is charged to the guest on top,
 * never deducted from the hotel's share) has been sent.
 * Creates one payout_payments record covering every currently-due booking
 * for that hotel; the payout ledger recomputes paid/due purely by scanning
 * these records (never a flag stored on the booking itself).
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
$hotelId   = trim((string)($input['hotelId'] ?? ''));
$note      = trim((string)($input['note'] ?? ''));
$proofPath = trim((string)($input['proofPath'] ?? ''));

if ($hotelId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing hotel id.']);
    exit;
}

if ($proofPath === '' || strpos($proofPath, '/travelix/payment_proofs/') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Upload proof of the transfer before recording this payout.']);
    exit;
}

$hotel = hp_firestore_get($saPath, $projectId, 'hotels/' . $hotelId);
if (!$hotel) {
    echo json_encode(['success' => false, 'message' => 'Hotel not found.']);
    exit;
}

// Server recomputes the due amount itself — never trusts a browser-supplied amount.
$ledger = hp_hotel_payout($saPath, $projectId, $hotelId);
$dueRows = array_values(array_filter($ledger['rows'], fn($r) => $r['status'] === 'due'));

if (!$dueRows) {
    echo json_encode(['success' => false, 'message' => 'This hotel has no outstanding payout right now.']);
    exit;
}

$bookingIds = array_map(fn($r) => $r['id'], $dueRows);
$amount     = array_sum(array_map(fn($r) => $r['payout'], $dueRows));

$payoutId = hp_firestore_create($saPath, $projectId, 'payout_payments', [
    'hotelId'      => $hotelId,
    'hotelName'    => (string)($hotel['name'] ?? ''),
    'amount'       => $amount,
    'bookingIds'   => $bookingIds,
    'bookingCount' => count($bookingIds),
    'note'         => $note,
    'proofUrl'     => $proofPath,
    'status'       => 'pending',
    'sentAt'       => time(),
    'sentBy'       => (string)($_SESSION['user']['email'] ?? 'admin'),
]);

if ($payoutId === '') {
    echo json_encode(['success' => false, 'message' => 'Could not save the payout record. Please try again.']);
    exit;
}

// Awaits the hotel's confirmation before it counts as settled — mirrors the
// hotel's own booking-payment proof flow with the two sides swapped.
hp_firestore_create($saPath, $projectId, 'notifications', [
    'audience' => 'hotel',
    'hotelId' => $hotelId,
    'title' => 'Payout Sent — Please Confirm',
    'message' => 'Travelix sent ' . hp_money($amount) . ' for ' . count($bookingIds) . ' booking(s). Please confirm you received it.',
    'type' => 'payout_pending',
    'icon' => 'fa-solid fa-hourglass-half',
    'link' => '/travelix/hotel_portal/commission.php',
    'isRead' => false,
    'createdAt' => date('c'),
]);

echo json_encode([
    'success' => true,
    'message' => 'Payout of ' . hp_money($amount) . ' recorded for ' . count($bookingIds) . ' booking(s) — awaiting hotel confirmation.',
]);
