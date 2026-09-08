<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Perform a simple query to ensure it's actually working
    $stmt = $db->query("SELECT 1");
    if ($stmt) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "database" => true,
            "message" => "WFF Tamil Nadu API is running"
        ]);
        exit();
    }
} catch (Exception $e) {
    error_log("Health Check Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "database" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}
