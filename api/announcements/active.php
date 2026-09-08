<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM announcements WHERE status = 1 AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) ORDER BY sort_order ASC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $announcement = $stmt->fetch();

    sendResponse(true, $announcement ?: null);
} catch (Exception $e) {
    error_log("Announcements API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch announcement.", 500);
}
