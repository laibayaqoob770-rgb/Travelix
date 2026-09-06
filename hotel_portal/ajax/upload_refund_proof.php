<?php
/**
 * Hotel — upload proof that a refund was sent to a guest. Mirrors
 * hotel/ajax/upload_payment_proof.php and admin_manage/ajax/upload_transfer_proof.php.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['hotel_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
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

$finalName = 'refund_' . bin2hex(random_bytes(6)) . '_' . time() . '.' . $extension;
$destination = $uploadDir . '/' . $finalName;

if (!move_uploaded_file($tmpName, $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

echo json_encode(['success' => true, 'path' => '/travelix/payment_proofs/' . $finalName]);
