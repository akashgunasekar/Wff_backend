<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get latest 5 active albums
    $query = "SELECT id FROM gallery_albums WHERE status = 1 ORDER BY date DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $albums = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($albums)) {
        sendResponse(true, []);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($albums), '?'));
    
    // Fetch up to 10 images per album, or just random/latest 50 images from these albums
    $imgQuery = "SELECT image, album_id FROM gallery_images WHERE album_id IN ($placeholders) ORDER BY sort_order ASC, id DESC LIMIT 50";
    $imgStmt = $db->prepare($imgQuery);
    $imgStmt->execute($albums);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, $images);
} catch (Exception $e) {
    error_log("Gallery Homepage API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch gallery images.", 500);
}
