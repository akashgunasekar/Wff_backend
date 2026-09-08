<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';

requireAdmin();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM announcements ORDER BY sort_order ASC, id DESC");
    $stmt->execute();
    sendResponse(true, $stmt->fetchAll());
} catch (Exception $e) {
    sendResponse(false, null, "Failed to fetch announcements.", 500);
}
