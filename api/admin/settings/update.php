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

if (!is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    foreach ($data as $key => $value) {
        $safeKey = sanitizeInput($key);
        $safeValue = trim((string)$value);
        
        // Validation
        if ($safeKey === 'contact_email' && !empty($safeValue)) {
            if (!validateEmail($safeValue)) {
                sendResponse(false, null, "Invalid email address format.", 422);
            }
        }
        if ($safeKey === 'phone' && !empty($safeValue)) {
            if (!validatePhone($safeValue)) {
                sendResponse(false, null, "Invalid phone number format.", 422);
            }
        }
        $urlKeys = ['facebook_url', 'instagram_url', 'youtube_url', 'website_url', 'contact_map_url'];
        if (in_array($safeKey, $urlKeys) && !empty($safeValue)) {
            $parsed = parse_url($safeValue);
            if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https']) || !filter_var($safeValue, FILTER_VALIDATE_URL)) {
                sendResponse(false, null, "Invalid URL format for $safeKey. Must be http/https.", 422);
            }
        }
        
        $safeValue = sanitizeInput($safeValue);
        
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$safeKey, $safeValue, $safeValue]);
    }
    
    $db->commit();
    
    logAdminAction($db, $admin['id'], 'SITE_SETTINGS_UPDATED', "Updated site settings", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Settings updated successfully."]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Settings Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update settings.", 500);
}
