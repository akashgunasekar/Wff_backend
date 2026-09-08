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

if ($width < 600 || $height < 250) {
    sendResponse(false, null, "Banner image is too small. Recommended size: 1200 × 500 px.", 400);
}

// target aspect ratio is 12:5 (2.4)
$aspectRatio = $width / $height;
if ($aspectRatio < 2.3 || $aspectRatio > 2.5) {
    sendResponse(false, null, "Winner Announcement banner must use a 12:5 landscape ratio. Recommended size: 1200 × 500 px.", 400);
}

$extension = '';
switch ($mimeType) {
    case 'image/jpeg': $extension = 'jpg'; break;
    case 'image/png': $extension = 'png'; break;
    case 'image/webp': $extension = 'webp'; break;
}
$filename = 'banner_' . bin2hex(random_bytes(16)) . '.' . $extension;

$uploadDir = realpath(__DIR__ . '/../../../uploads');
if (!$uploadDir) {
    mkdir(__DIR__ . '/../../../uploads', 0755, true);
    $uploadDir = realpath(__DIR__ . '/../../../uploads');
}

$eventsDir = $uploadDir . '/events';
if (!file_exists($eventsDir)) {
    mkdir($eventsDir, 0755, true);
}

$targetPath = $eventsDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, null, "Failed to save uploaded file.", 500);
}

$publicPath = '/uploads/events/' . $filename;

sendResponse(true, [
    "image" => $publicPath,
    "message" => "Banner uploaded successfully."
]);
