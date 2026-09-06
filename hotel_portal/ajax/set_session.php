<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['uid']) || empty($input['email'])) {
    echo json_encode(['success' => false, 'message' => 'Missing uid or email']);
    exit;
}

$uid = $input['uid'];

$_SESSION['hotel_staff'] = [
    'uid'        => $uid,
    'email'      => $input['email'],
    'name'       => $input['name']       ?? $input['email'],
    'first_name' => $input['first_name'] ?? explode(' ', ($input['name'] ?? $input['email']))[0],
    'hotel_id'   => trim($input['hotel_id']   ?? ''),
    'hotel_name' => trim($input['hotel_name'] ?? ''),
];

// Resolve the linked hotel server-side (admin credential — bypasses client rules).
require_once __DIR__ . '/../includes/resolve_hotel.php';
$hotel = hp_load_portal_hotel();

// Only accounts the admin has linked to a hotel may use the portal. Anything
// else (a guest account, an admin account, a staff member whose hotel was
// removed) is rejected here rather than being let in to an unusable portal.
if (!$hotel) {
    unset($_SESSION['hotel_staff']);
    echo json_encode([
        'success' => false,
        'reason'  => 'no_hotel',
        'message' => 'This account is not registered as hotel staff. Please sign in with the hotel staff email provided by the Travelix admin.',
    ]);
    exit;
}

if (strtolower((string)($hotel['status'] ?? 'active')) === 'inactive') {
    unset($_SESSION['hotel_staff']);
    echo json_encode([
        'success' => false,
        'reason'  => 'hotel_disabled',
        'message' => 'This hotel account has been disabled by the admin. Please contact Travelix support.',
    ]);
    exit;
}

if (!empty($hotel['disabled'])) {
    unset($_SESSION['hotel_staff']);
    echo json_encode([
        'success' => false,
        'reason'  => 'refund_sla_disabled',
        'message' => 'Your account has been disabled because a guest refund was not sent in time. Contact Travelix admin to have it reviewed and re-enabled.',
    ]);
    exit;
}

echo json_encode([
    'success'    => true,
    'hotel_id'   => $_SESSION['hotel_staff']['hotel_id'],
    'hotel_name' => $_SESSION['hotel_staff']['hotel_name'],
]);
