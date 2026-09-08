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
if (empty($data['id']) || empty($data['title']) || empty($data['image'])) {
    sendResponse(false, null, "ID, Title, and Image are required.", 422);
}

$id = (int)$data['id'];
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
    
    $stmt = $db->prepare("SELECT image FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    $oldSlide = $stmt->fetch();
    
    try {
        $stmt = $db->prepare("UPDATE hero_slides SET title = ?, subtitle = ?, image = ?, date = ?, location = ?, event_id = ?, display_fee = ?, sort_order = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $subtitle, $image, $date, $location, $event_id, $display_fee, $sort_order, $status, $id]);
    } catch (PDOException $e) {
        $stmt = $db->prepare("UPDATE hero_slides SET title = ?, subtitle = ?, image = ?, date = ?, location = ?, event_id = ?, sort_order = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $subtitle, $image, $date, $location, $event_id, $sort_order, $status, $id]);
        
        $stmtSettings = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmtSettings->execute(["hero_fee_{$id}", $display_fee, $display_fee]);
    }
    
    if ($oldSlide && $oldSlide['image'] !== $image && !empty($oldSlide['image']) && strpos($oldSlide['image'], '/uploads/hero/') === 0) {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM hero_slides WHERE image = ?");
        $stmtCheck->execute([$oldSlide['image']]);
        if ($stmtCheck->fetchColumn() == 0) {
            $physicalPath = realpath(__DIR__ . '/../../../../' . ltrim($oldSlide['image'], '/'));
            if ($physicalPath && file_exists($physicalPath)) {
                unlink($physicalPath);
            }
        }
    }
    
    logAdminAction($db, $admin['id'], 'HOMEPAGE_HERO_UPDATED', "Updated hero slide: {$title}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Hero slide updated."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to update slide.", 500);
}
