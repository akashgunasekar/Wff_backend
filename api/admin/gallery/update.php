<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['id'])) {
    sendResponse(false, null, "ID is required.", 422);
}

$id = (int)$data['id'];
$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;
$title = !empty($data['title']) ? sanitizeInput($data['title']) : '';
$date = !empty($data['date']) ? sanitizeInput($data['date']) : null;
$status = isset($data['status']) ? (int)$data['status'] : 1;
$cover_image = sanitizeInput($data['cover_image'] ?? ''); 

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // verify album exists
    $stmt = $db->prepare("SELECT id FROM gallery_albums WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(false, null, "Album not found.", 404);
    }
    
    $stmt = $db->prepare("UPDATE gallery_albums SET title = ?, date = ?, cover_image = ?, status = ?, event_id = ? WHERE id = ?");
    $stmt->execute([$title, $date, $cover_image, $status, $event_id, $id]);
    
    logAdminAction($db, $admin['id'], 'GALLERY_ALBUM_UPDATED', "Updated gallery album ID: {$id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Album updated successfully."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to update album.", 500);
}
