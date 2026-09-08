<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'master_category_%'");
    $rows = $stmt->fetchAll();
    
    $categories = [];
    foreach ($rows as $row) {
        $data = json_decode($row['setting_value'], true);
        if (is_array($data)) {
            $categories[] = $data;
        }
    }
    
    usort($categories, function($a, $b) {
        return ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0);
    });
    
    sendResponse(true, $categories);
} catch (Exception $e) {
    error_log("Master Categories List Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch master categories.", 500);
}
