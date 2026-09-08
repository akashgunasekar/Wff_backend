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

if (empty($data['event_id']) || empty(trim($data['name']))) {
    sendResponse(false, null, "Event ID and Category Name are required.", 422);
}

$event_id = (int)$data['event_id'];
$name = sanitizeInput($data['name']);
$age_group = isset($data['age_group']) ? sanitizeInput($data['age_group']) : '';
$gender = isset($data['gender']) ? sanitizeInput($data['gender']) : '';
$entry_fee = isset($data['entry_fee']) && trim($data['entry_fee']) !== '' ? sanitizeInput($data['entry_fee']) : null;
$availability = isset($data['availability']) ? sanitizeInput($data['availability']) : 'open';

$short_description = isset($data['short_description']) ? sanitizeInput($data['short_description']) : '';
$structure_type = isset($data['structure_type']) ? sanitizeInput($data['structure_type']) : 'single';
$weight_divisions = isset($data['weight_divisions']) && is_array($data['weight_divisions']) ? $data['weight_divisions'] : [];
$height_divisions = isset($data['height_divisions']) && is_array($data['height_divisions']) ? $data['height_divisions'] : [];
$display_order = isset($data['display_order']) ? (int)$data['display_order'] : 0;

if ($entry_fee !== null && (!is_numeric($entry_fee) || (float)$entry_fee < 0)) {
    sendResponse(false, null, "Entry fee must be a valid positive number if provided.", 422);
}

$validAvailability = ['open', 'closed', 'waitlist'];
if (!in_array($availability, $validAvailability)) {
    sendResponse(false, null, "Invalid availability status.", 422);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check event exists
    $stmt = $db->prepare("SELECT id FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    if (!$stmt->fetch()) {
        sendResponse(false, null, "Event not found.", 404);
    }
    
    $stmt = $db->prepare("INSERT INTO event_categories (event_id, name, age_group, gender, entry_fee, availability) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$event_id, $name, $age_group, $gender, $entry_fee, $availability]);
    $category_id = $db->lastInsertId();
    
    // Polyfill extended fields
    $ext_data = [
        'short_description' => $short_description,
        'structure_type' => $structure_type,
        'weight_divisions' => $weight_divisions,
        'height_divisions' => $height_divisions,
        'display_order' => $display_order
    ];
    $key = 'category_ext_' . $category_id;
    $json = json_encode($ext_data);
    $stmt_ext = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt_ext->execute([$key, $json, $json]);
    
    logAdminAction($db, $admin['id'], 'CATEGORY_CREATED', "Created category {$name} for Event {$event_id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Category created successfully."]);
} catch (Exception $e) {
    error_log("Category Create Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to create category.", 500);
}
