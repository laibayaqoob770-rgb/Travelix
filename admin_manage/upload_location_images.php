<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');

$baseUrl = '/travelix';

function jsonResponse($success, $message, $files = [], $statusCode = 200, $extra = [])
{
    http_response_code($statusCode);

    $response = array_merge([
        'success' => $success,
        'message' => $message,
        'files' => $files
    ], $extra);

    ob_clean();
    echo json_encode($response);
    exit;
}

try {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
        jsonResponse(false, 'You must be logged in.', [], 401);
    }

    $currentUser = $_SESSION['user'] ?? [];
    $userRole = strtolower((string)($currentUser['role'] ?? 'user'));

    if ($userRole !== 'admin') {
        jsonResponse(false, 'Only admin can upload location images.', [], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.', [], 405);
    }

    if (!isset($_FILES['images'])) {
        jsonResponse(false, 'No images uploaded.', [], 422);
    }

    $uploadDir = dirname(__DIR__) . '/location_images';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            jsonResponse(false, 'Unable to create location_images folder.', [], 500);
        }
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $files = $_FILES['images'];
    $savedFiles = [];

    function travelixSanitizeFileName($name)
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return trim((string)$name, '_');
    }

    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        $originalName = $files['name'][$i] ?? '';
        $tmpName = $files['tmp_name'][$i] ?? '';
        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        if (!$tmpName || !is_uploaded_file($tmpName)) {
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = travelixSanitizeFileName($baseName);

        if ($safeBaseName === '') {
            $safeBaseName = 'location_image';
        }

        $finalName = $safeBaseName . '.' . $extension;
        $counter = 1;

        while (file_exists($uploadDir . '/' . $finalName)) {
            $finalName = $safeBaseName . '_' . $counter . '.' . $extension;
            $counter++;
        }

        $destination = $uploadDir . '/' . $finalName;

        if (!move_uploaded_file($tmpName, $destination)) {
            continue;
        }

        $savedFiles[] = [
            'original_name' => $originalName,
            'stored_name' => $finalName,
            'relative_path' => '/travelix/location_images/' . $finalName
        ];
    }

    if (!$savedFiles) {
        jsonResponse(false, 'No valid image files were uploaded. Allowed: jpg, jpeg, png, webp.', [], 422);
    }

    jsonResponse(true, 'Images uploaded successfully.', $savedFiles, 200);

} catch (Throwable $e) {
    jsonResponse(false, 'Upload failed: ' . $e->getMessage(), [], 500);
}