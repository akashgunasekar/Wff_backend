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

if (!isset($_POST['album_id']) || empty($_FILES['image'])) {
    sendResponse(false, null, "Album ID and Image are required.", 422);
}

$albumId = (int)$_POST['album_id'];
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
$filename = bin2hex(random_bytes(16)) . '.' . $extension;

// 6. Define safe upload path (never trust original name)
$uploadDir = realpath(__DIR__ . '/../../../../uploads/gallery');
if (!$uploadDir) {
    // If not exists, try to create (should exist based on mkdir)
    mkdir(__DIR__ . '/../../../../uploads/gallery', 0755, true);
    $uploadDir = realpath(__DIR__ . '/../../../../uploads/gallery');
}
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

// Move physical file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendResponse(false, null, "Failed to save uploaded file.", 500);
}

$publicPath = '/uploads/gallery/' . $filename;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify album exists
    $stmt = $db->prepare("SELECT id FROM gallery_albums WHERE id = ?");
    $stmt->execute([$albumId]);
    if (!$stmt->fetch()) {
        unlink($targetPath); // remove orphaned file
        sendResponse(false, null, "Album not found.", 404);
    }
    
    // Insert DB record
    $stmt = $db->prepare("INSERT INTO gallery_images (album_id, image, sort_order) VALUES (?, ?, 0)");
    $stmt->execute([$albumId, $publicPath]);
    $imageId = $db->lastInsertId();
    
    logAdminAction($db, $admin['id'], 'GALLERY_IMAGE_UPLOADED', "Uploaded image ID {$imageId} for album {$albumId}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, [
        "id" => $imageId,
        "image" => $publicPath,
        "message" => "Image uploaded successfully."
    ]);
} catch (Exception $e) {
    // If DB fails, remove physical file
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    error_log("Upload DB Error: " . $e->getMessage());
    sendResponse(false, null, "Database error during upload.", 500);
}
