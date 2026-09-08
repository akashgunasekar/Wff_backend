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

if (empty($data['id']) || empty(trim($data['name']))) {
    sendResponse(false, null, "Category ID and Name are required.", 422);
}

$category_id = (int)$data['id'];
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
    
    $stmt = $db->prepare("SELECT * FROM event_categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        sendResponse(false, null, "Category not found.", 404);
    }
    
    $stmt = $db->prepare("UPDATE event_categories SET name = ?, age_group = ?, gender = ?, entry_fee = ?, availability = ? WHERE id = ?");
    $stmt->execute([$name, $age_group, $gender, $entry_fee, $availability, $category_id]);
    
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
    
    if ($category['availability'] !== $availability) {
        logAdminAction($db, $admin['id'], 'CATEGORY_AVAILABILITY_CHANGED', "Changed availability of category {$name} to {$availability}", $category['availability'], $availability, $_SERVER['REMOTE_ADDR']);
    } else {
        logAdminAction($db, $admin['id'], 'CATEGORY_UPDATED', "Updated category {$name}", null, null, $_SERVER['REMOTE_ADDR']);
    }
    
    sendResponse(true, ["message" => "Category updated successfully."]);
} catch (Exception $e) {
    error_log("Category Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update category.", 500);
}
