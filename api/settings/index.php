<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $raw = $stmt->fetchAll();
    $settings = [];
    foreach($raw as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    sendResponse(true, $settings);
} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch settings.", 500);
}
