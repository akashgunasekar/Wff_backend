<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

requireAdmin();

if (!isset($_GET['id'])) {
    sendResponse(false, null, "Album ID required.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $album = $stmt->fetch();
    
    if (!$album) {
        sendResponse(false, null, "Album not found.", 404);
    }
    
    $imgStmt = $db->prepare("SELECT * FROM gallery_images WHERE album_id = ? ORDER BY sort_order ASC, id ASC");
    $imgStmt->execute([$album['id']]);
    $album['images'] = $imgStmt->fetchAll();
    
    sendResponse(true, $album);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to fetch album.", 500);
}
