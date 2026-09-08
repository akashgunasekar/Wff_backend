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

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['id'])) {
    sendResponse(false, null, "Album ID is required.", 422);
}

$id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Verify album exists
    $stmt = $db->prepare("SELECT id FROM gallery_albums WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        sendResponse(false, null, "Album not found.", 404);
    }
    
    // Get all images to delete physical files
    $imgStmt = $db->prepare("SELECT image FROM gallery_images WHERE album_id = ?");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete from DB (gallery_images has ON DELETE CASCADE but let's be explicit or just delete album and files)
    $stmt = $db->prepare("DELETE FROM gallery_albums WHERE id = ?");
    $stmt->execute([$id]);
    
    $db->commit();
    
    // Delete physical files after successful DB deletion
    foreach ($images as $img) {
        $filepath = __DIR__ . '/../../../' . ltrim($img['image'], '/');
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
        }
    }
    
    logAdminAction($db, $admin['id'], 'GALLERY_ALBUM_DELETED', "Deleted gallery album ID: {$id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Album deleted successfully."]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendResponse(false, null, "Failed to delete album.", 500);
}
