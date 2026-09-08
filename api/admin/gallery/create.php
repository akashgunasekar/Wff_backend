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
$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;
$title = !empty($data['title']) ? sanitizeInput($data['title']) : '';
$date = !empty($data['date']) ? sanitizeInput($data['date']) : null;
$status = isset($data['status']) ? (int)$data['status'] : 1;
// Initially, new albums might not have a cover image until images are uploaded
$cover_image = sanitizeInput($data['cover_image'] ?? ''); 

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO gallery_albums (title, date, cover_image, status, event_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $date, $cover_image, $status, $event_id]);
    $albumId = $db->lastInsertId();
    
    logAdminAction($db, $admin['id'], 'GALLERY_ALBUM_CREATED', "Created gallery album ID: {$albumId}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["id" => $albumId, "message" => "Album created successfully."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to create album.", 500);
}
