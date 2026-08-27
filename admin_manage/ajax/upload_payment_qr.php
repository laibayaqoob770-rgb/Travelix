<?php
/**
 * Admin — upload the QR code image guests scan to pay into the central
 * Travelix payment account (JazzCash/Raast/EasyPaisa "scan to pay").
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['qr'])) {
    echo json_encode(['success' => false, 'message' => 'No QR image uploaded.']);
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/images/payment_qr';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Unable to create payment_qr folder.']);
        exit;
    }
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$file = $_FILES['qr'];

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
    echo json_encode(['success' => false, 'message' => 'Allowed file types: jpg, jpeg, png, webp.']);
    exit;
}

$finalName   = 'payment_qr_' . bin2hex(random_bytes(6)) . '_' . time() . '.' . $extension;
$destination = $uploadDir . '/' . $finalName;

if (!move_uploaded_file($tmpName, $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

echo json_encode(['success' => true, 'path' => '/travelix/images/payment_qr/' . $finalName]);
