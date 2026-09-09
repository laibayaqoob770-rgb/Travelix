<?php
/**
 * Streams a branded PDF itinerary for one of the current user's saved trips.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$baseUrl = '/travelix';
$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    http_response_code(401);
    echo 'Please log in to download this trip.';
    exit;
}

$tripId = trim($_GET['id'] ?? '');
if ($tripId === '') {
    http_response_code(400);
    echo 'Missing trip id.';
    exit;
}

require_once $docRoot . $baseUrl . '/config/firebase_config.php';
require_once $docRoot . $baseUrl . '/includes/firestore_admin.php';
require_once $docRoot . $baseUrl . '/includes/trip_pdf.php';

$saPath    = $docRoot . $baseUrl . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$trip = hp_firestore_get($saPath, $projectId, 'trips/' . $tripId);

if (!$trip) {
    http_response_code(404);
    echo 'Trip not found.';
    exit;
}

$owner   = (string)($trip['uid'] ?? $trip['userId'] ?? '');
$viewer  = (string)($_SESSION['user']['uid'] ?? '');
$isAdmin = strtolower((string)($_SESSION['user']['role'] ?? '')) === 'admin';

if ($owner !== $viewer && !$isAdmin) {
    http_response_code(403);
    echo 'You do not have access to this trip.';
    exit;
}

$userProfile = [];
if ($owner !== '') {
    $ownerDoc = hp_firestore_get($saPath, $projectId, 'users/' . $owner);
    if ($ownerDoc) {
        $userProfile = [
            'firstName' => $ownerDoc['firstName'] ?? '',
            'lastName'  => $ownerDoc['lastName'] ?? '',
            'email'     => $ownerDoc['email'] ?? '',
            'phone'     => $ownerDoc['phone'] ?? '',
        ];
    }
}

$pdf = travelix_generate_trip_pdf($trip, $userProfile);

$destSlug = preg_replace('/[^a-zA-Z0-9]+/', '-', (string)($trip['destination'] ?? $trip['toCity'] ?? 'trip'));
$filename = 'Travelix-Trip-' . trim($destSlug, '-') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
