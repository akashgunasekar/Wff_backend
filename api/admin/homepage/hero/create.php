<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../helpers/validation.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';
require_once __DIR__ . '/../../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['title']) || empty($data['image'])) {
    sendResponse(false, null, "Title and Image are required.", 422);
}

$title = sanitizeInput($data['title']);
$subtitle = sanitizeInput($data['subtitle'] ?? '');
$image = sanitizeInput($data['image']);
$date = !empty($data['date']) ? sanitizeInput($data['date']) : null;
$location = !empty($data['location']) ? sanitizeInput($data['location']) : null;
$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;
$display_fee = !empty($data['display_fee']) ? sanitizeInput($data['display_fee']) : null;
$sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$status = isset($data['status']) ? (int)$data['status'] : 1;
$status = isset($data['status']) ? (int)$data['status'] : 1;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $stmt = $db->prepare("INSERT INTO hero_slides (title, subtitle, image, date, location, event_id, display_fee, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $subtitle, $image, $date, $location, $event_id, $display_fee, $sort_order, $status]);
        $hero_id = $db->lastInsertId();
    } catch (PDOException $e) {
        $stmt = $db->prepare("INSERT INTO hero_slides (title, subtitle, image, date, location, event_id, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $subtitle, $image, $date, $location, $event_id, $sort_order, $status]);
        $hero_id = $db->lastInsertId();
        
        $stmtSettings = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmtSettings->execute(["hero_fee_{$hero_id}", $display_fee, $display_fee]);
    }
    
    logAdminAction($db, $admin['id'], 'HOMEPAGE_HERO_CREATED', "Created hero slide: {$title}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Hero slide created."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to create slide.", 500);
}
