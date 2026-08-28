<?php
/** Returns the signed-in guest's bookings, including legacy same-email records. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];
if (empty($_SESSION['user']['uid'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Please log in again.']);
    exit;
}

require_once $docRoot.$baseUrl.'/config/firebase_config.php';
require_once $docRoot.$baseUrl.'/includes/firestore_admin.php';
$sa = $docRoot.$baseUrl.'/config/firebase-service-account.json';
$uid = (string)$_SESSION['user']['uid'];
$email = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
$rows = [];

foreach (hp_firestore_query($sa, FIREBASE_PROJECT_ID, 'hotel_bookings', 'userId', $uid) as $row) $rows[$row['id']] = $row;
foreach (hp_firestore_query($sa, FIREBASE_PROJECT_ID, 'hotel_bookings', 'uid', $uid) as $row) $rows[$row['id']] = $row;
if ($email !== '') {
    foreach (hp_firestore_query($sa, FIREBASE_PROJECT_ID, 'hotel_bookings', 'userEmail', $email) as $row) $rows[$row['id']] = $row;
}

// Older bookings may have mixed-case emails. Only use this fallback if the
// indexed lookups above found nothing, keeping normal requests fast.
if (!$rows && $email !== '') {
    foreach (hp_firestore_query($sa, FIREBASE_PROJECT_ID, 'hotel_bookings') as $row) {
        if (strtolower(trim((string)($row['userEmail'] ?? ''))) === $email) $rows[$row['id']] = $row;
    }
}

$bookings = array_values($rows);
usort($bookings, static function($a,$b){
    return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
});
echo json_encode(['success'=>true,'bookings'=>$bookings]);
