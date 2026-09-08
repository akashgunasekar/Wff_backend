<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';
require_once __DIR__ . '/../../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

if (empty($_FILES['image'])) {
    sendResponse(false, null, "Image file is required.", 422);
}

$file = $_FILES['image'];

// 1. Validate upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, null, "File upload error code: " . $file['error'], 400);
}

// 2. Validate file size (e.g. max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse(false, null, "File is too large. Maximum size is 5MB.", 400);
}

// 3. Detect MIME type server-side
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimeTypes)) {
    sendResponse(false, null, "Invalid file type. Only JPEG, PNG, and WebP are allowed.", 400);
}

// 4. Validate actual image contents
$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    sendResponse(false, null, "Invalid image content.", 400);
}

// 5. Generate safe random filename
$extension = '';
switch ($mimeType) {
    case 'image/jpeg': $extension = 'jpg'; break;
    case 'image/png': $extension = 'png'; break;
    case 'image/webp': $extension = 'webp'; break;
}
$filename = 'hero_' . bin2hex(random_bytes(16)) . '.' . $extension;

// 6. Define safe upload path
$uploadDir = realpath(__DIR__ . '/../../../../uploads');
if (!$uploadDir) {
    mkdir(__DIR__ . '/../../../../uploads', 0755, true);
    $uploadDir = realpath(__DIR__ . '/../../../../uploads');
}

$heroDir = $uploadDir . '/hero';
if (!file_exists($heroDir)) {
    mkdir($heroDir, 0755, true);
}

$targetPath = $heroDir . DIRECTORY_SEPARATOR . $filename;

// Move physical file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, null, "Failed to save uploaded file.", 500);
}

$publicPath = '/uploads/hero/' . $filename;

sendResponse(true, [
    "image" => $publicPath,
    "message" => "Image uploaded successfully."
]);
