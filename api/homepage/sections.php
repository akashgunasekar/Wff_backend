<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT section_key, title, subtitle, content FROM homepage_sections WHERE status = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach($raw as $row) {
        $formatted[$row['section_key']] = [
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'content' => $row['content']
        ];
    }

    sendResponse(true, $formatted);
} catch (Exception $e) {
    error_log("Homepage Sections API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch homepage sections.", 500);
}
