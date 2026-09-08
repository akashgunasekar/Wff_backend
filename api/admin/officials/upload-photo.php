<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

if (empty($_FILES['image'])) {
    sendResponse(false, null, "Image file is required.", 422);
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, null, "File upload error code: " . $file['error'], 400);
}

if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse(false, null, "File is too large. Maximum size is 5MB.", 400);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimeTypes)) {
    sendResponse(false, null, "Please upload a valid image file (JPEG, PNG, WebP).", 400);
}

$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    sendResponse(false, null, "Invalid image content.", 400);
}

$width = $imageInfo[0];
$height = $imageInfo[1];

if ($width > $height) {
    sendResponse(false, null, "Official photo must be portrait or square size (height must be greater than or equal to width).", 400);
}

$extension = '';
switch ($mimeType) {
    case 'image/jpeg': $extension = 'jpg'; break;
    case 'image/png': $extension = 'png'; break;
    case 'image/webp': $extension = 'webp'; break;
}
$filename = 'official_' . bin2hex(random_bytes(16)) . '.' . $extension;

$uploadDir = realpath(__DIR__ . '/../../../uploads');
if (!$uploadDir) {
    mkdir(__DIR__ . '/../../../uploads', 0755, true);
    $uploadDir = realpath(__DIR__ . '/../../../uploads');
}

$officialsDir = $uploadDir . '/officials';
if (!file_exists($officialsDir)) {
    mkdir($officialsDir, 0755, true);
}

$targetPath = $officialsDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, null, "Failed to save uploaded file.", 500);
}

$publicPath = '/uploads/officials/' . $filename;

sendResponse(true, [
    "image" => $publicPath,
    "message" => "Photo uploaded successfully."
]);
