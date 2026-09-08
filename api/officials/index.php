<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM officials WHERE status = 1 ORDER BY sort_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $officials = $stmt->fetchAll();

    sendResponse(true, $officials);
} catch (Exception $e) {
    error_log("Officials API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch officials.", 500);
}
