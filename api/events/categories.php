<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    if (!isset($_GET['event_id'])) {
        sendResponse(false, null, "Event ID is required.", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM event_categories WHERE event_id = :event_id ORDER BY id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':event_id' => $_GET['event_id']]);
    $categories = $stmt->fetchAll();

    sendResponse(true, $categories);
} catch (Exception $e) {
    error_log("Event Categories API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch categories.", 500);
}
