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

if (empty(trim($data['id'] ?? ''))) {
    sendResponse(false, null, "Category ID is required.", 422);
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
    
    $stmt = $db->prepare("DELETE FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    
    logAdminAction($db, $admin['id'], 'MASTER_CATEGORY_DELETED', "Deleted master category ID {$id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Master category deleted successfully."]);
} catch (Exception $e) {
    error_log("Master Category Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete master category.", 500);
}
