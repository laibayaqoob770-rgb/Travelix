<?php
/**
 * Admin — upload proof of an outgoing transfer (hotel payout or guest
 * refund), so the recipient can verify the money actually moved before the
 * admin marks it sent. Mirrors hotel/ajax/upload_payment_proof.php.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['proof'])) {
    echo json_encode(['success' => false, 'message' => 'No proof file uploaded.']);
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/payment_proofs';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Unable to create payment_proofs folder.']);
        exit;
    }
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$file = $_FILES['proof'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
    exit;
}

$tmpName = $file['tmp_name'] ?? '';
if (!$tmpName || !is_uploaded_file($tmpName)) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload.']);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    echo json_encode(['success' => false, 'message' => 'Allowed file types: jpg, jpeg, png, webp, pdf.']);
    exit;
}

$finalName = 'transfer_' . bin2hex(random_bytes(6)) . '_' . time() . '.' . $extension;
$destination = $uploadDir . '/' . $finalName;

if (!move_uploaded_file($tmpName, $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

echo json_encode(['success' => true, 'path' => '/travelix/payment_proofs/' . $finalName]);
