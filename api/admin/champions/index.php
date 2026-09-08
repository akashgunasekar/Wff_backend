<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $db = (new Database())->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM champions ORDER BY sort_order ASC, year DESC");
    $stmt->execute();
    $champions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, $champions);
} catch (Exception $e) {
    error_log("Admin Champions List Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch champions.", 500);
}
