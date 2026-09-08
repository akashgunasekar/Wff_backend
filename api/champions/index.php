<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM champions ORDER BY sort_order ASC, year DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $champions = $stmt->fetchAll();

    sendResponse(true, $champions);
} catch (Exception $e) {
    error_log("Champions API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch champions.", 500);
}
