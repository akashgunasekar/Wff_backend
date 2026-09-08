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

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['id'])) {
    sendResponse(false, null, "Image ID required.", 422);
}

$id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, album_id, image FROM gallery_images WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$img) {
        sendResponse(false, null, "Image not found.", 404);
    }
    
    // Check if album cover needs to be reset
    $albumStmt = $db->prepare("SELECT cover_image FROM gallery_albums WHERE id = ?");
    $albumStmt->execute([$img['album_id']]);
    $album = $albumStmt->fetch();
    if ($album && $album['cover_image'] === $img['image']) {
        $updateAlbum = $db->prepare("UPDATE gallery_albums SET cover_image = '' WHERE id = ?");
        $updateAlbum->execute([$img['album_id']]);
    }
    
    // Delete DB record
    $delStmt = $db->prepare("DELETE FROM gallery_images WHERE id = ?");
    $delStmt->execute([$id]);
    
    // Delete physical file safely
    // Construct real path and ensure it starts with our uploads dir
    $filepath = realpath(__DIR__ . '/../../../../' . ltrim($img['image'], '/'));
    $uploadDir = realpath(__DIR__ . '/../../../../uploads/gallery');
    
    if ($filepath && strpos($filepath, $uploadDir) === 0 && file_exists($filepath) && is_file($filepath)) {
        unlink($filepath);
    }
    
    logAdminAction($db, $admin['id'], 'GALLERY_IMAGE_DELETED', "Deleted image ID: {$id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Image deleted successfully."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to delete image.", 500);
}
