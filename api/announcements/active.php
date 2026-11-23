<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM announcements 
              WHERE status = 1 
                AND (start_at IS NULL OR start_at <= NOW()) 
                AND (end_at IS NULL OR end_at >= NOW()) 
              ORDER BY sort_order ASC, id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, $announcements);
} catch (Exception $e) {
    error_log("Announcements API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch announcements.", 500);
}
