<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';

requireAdmin();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM hero_slides ORDER BY sort_order ASC, id DESC");
    $stmt->execute();
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If query succeeded but display_fee is missing (using select *), we check the first row
    if (!empty($slides) && !array_key_exists('display_fee', $slides[0])) {
        foreach ($slides as &$slide) {
            $stmtSettings = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $stmtSettings->execute(["hero_fee_{$slide['id']}"]);
            $slide['display_fee'] = $stmtSettings->fetchColumn() ?: null;
        }
    }
    
    sendResponse(true, $slides);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to fetch hero slides.", 500);
}
