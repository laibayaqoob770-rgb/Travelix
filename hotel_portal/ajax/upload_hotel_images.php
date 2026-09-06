<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['hotel_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['images'])) {
    echo json_encode(['success' => false, 'message' => 'No images uploaded.']);
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/hotel_images';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Unable to create hotel_images folder.']);
        exit;
    }
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$files = $_FILES['images'];
$saved = [];
$count = is_array($files['name']) ? count($files['name']) : 0;

for ($i = 0; $i < $count; $i++) {
    $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) continue;

    $tmpName = $files['tmp_name'][$i] ?? '';
    if (!$tmpName || !is_uploaded_file($tmpName)) continue;

    $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) continue;

    $finalName = 'hotel_' . bin2hex(random_bytes(6)) . '_' . time() . '.' . $extension;
    $destination = $uploadDir . '/' . $finalName;

    if (!move_uploaded_file($tmpName, $destination)) continue;

    $saved[] = '/travelix/hotel_images/' . $finalName;
}

if (!$saved) {
    echo json_encode(['success' => false, 'message' => 'No valid image files were uploaded. Allowed: jpg, jpeg, png, webp.']);
    exit;
}

echo json_encode(['success' => true, 'paths' => $saved]);
