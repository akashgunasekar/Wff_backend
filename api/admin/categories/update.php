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

if (empty(trim($data['id'] ?? '')) || empty(trim($data['name'] ?? ''))) {
    sendResponse(false, null, "Category ID and Name are required.", 422);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $id = sanitizeInput($data['id']);
    $key = 'master_category_' . $id;
    
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $existing = $stmt->fetchColumn();
    
    if (!$existing) {
        sendResponse(false, null, "Master category not found.", 404);
    }
    
    $catData = [
        'id' => $id,
        'name' => sanitizeInput($data['name']),
        'short_description' => sanitizeInput($data['short_description'] ?? ''),
        'age_group' => sanitizeInput($data['age_group'] ?? ''),
        'gender' => sanitizeInput($data['gender'] ?? 'Male'),
        'structure_type' => sanitizeInput($data['structure_type'] ?? 'single'),
        'weight_divisions' => isset($data['weight_divisions']) && is_array($data['weight_divisions']) ? $data['weight_divisions'] : [],
        'height_divisions' => isset($data['height_divisions']) && is_array($data['height_divisions']) ? $data['height_divisions'] : [],
        'display_order' => (int)($data['display_order'] ?? 0),
        'status' => sanitizeInput($data['status'] ?? 'active')
    ];
    
    $json = json_encode($catData);
    $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$json, $key]);
    
    logAdminAction($db, $admin['id'], 'MASTER_CATEGORY_UPDATED', "Updated master category {$catData['name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, $catData);
} catch (Exception $e) {
    error_log("Master Category Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update master category.", 500);
}
