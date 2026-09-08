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
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

if (empty($data['id'])) {
    sendResponse(false, null, "Event ID is required.", 400);
}

$event_id = (int)$data['id'];
$name = sanitizeInput($data['event_name']);
$slug = sanitizeInput($data['slug']);
$date = sanitizeInput($data['event_date']);
$venue = sanitizeInput($data['venue']);
$status = sanitizeInput($data['status']);
$banner = isset($data['banner_image']) ? sanitizeInput($data['banner_image']) : '';
$description = isset($data['description']) ? sanitizeInput($data['description']) : '';

$validStatuses = ['open', 'upcoming', 'closed'];
if (!in_array($status, $validStatuses)) {
    sendResponse(false, null, "Invalid status.", 422);
}

$statusMapToDb = [
    'open' => 'ongoing',
    'upcoming' => 'upcoming',
    'closed' => 'completed'
];
$dbStatus = $statusMapToDb[$status];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check event exists
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        sendResponse(false, null, "Event not found.", 404);
    }
    
    // Check for duplicate slug on other events
    $stmt = $db->prepare("SELECT id FROM events WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $event_id]);
    if ($stmt->fetch()) {
        sendResponse(false, null, "Another event with this slug already exists.", 409);
    }
    
    $stmt = $db->prepare("UPDATE events SET event_name = ?, slug = ?, event_date = ?, venue = ?, status = ?, banner_image = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $slug, $date, $venue, $dbStatus, $banner, $description, $event_id]);
    
    // Update Tan Spray Price
    if (isset($data['tan_spray_price'])) {
        $ts_price = (int)$data['tan_spray_price'];
        $ts_key = "event_{$event_id}_tan_spray_price";
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$ts_key, $ts_price, $ts_price]);
    }
    
    // Update Cash Enabled
    if (array_key_exists('cash_enabled', $data)) {
        $cash_val = $data['cash_enabled'] ? '1' : '0';
        $cash_key = "event_{$event_id}_cash_enabled";
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$cash_key, $cash_val, $cash_val]);
    }

    // Update Content Meta
    if (isset($data['content_meta'])) {
        $meta_key = "event_{$event_id}_meta";
        $meta_json = json_encode($data['content_meta']);
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$meta_key, $meta_json, $meta_json]);
    }
    
    if ($event['status'] !== $dbStatus) {
        logAdminAction($db, $admin['id'], 'EVENT_STATUS_CHANGED', "Changed event status to {$dbStatus}", $event['status'], $dbStatus, $_SERVER['REMOTE_ADDR']);
    } else {
        logAdminAction($db, $admin['id'], 'EVENT_UPDATED', "Updated event {$name}", null, null, $_SERVER['REMOTE_ADDR']);
    }
    
    sendResponse(true, ["message" => "Event updated successfully."]);
} catch (Exception $e) {
    error_log("Event Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update event.", 500);
}
