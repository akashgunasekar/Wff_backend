<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['id'])) {
    sendResponse(false, null, "ID is required.", 400);
}

$id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $check = $db->prepare("SELECT id, athlete_name FROM champions WHERE id = ?");
    $check->execute([$id]);
    $champion = $check->fetch();
    
    if (!$champion) {
        sendResponse(false, null, "Champion not found.", 404);
    }
    
    $stmt = $db->prepare("DELETE FROM champions WHERE id = ?");
    $stmt->execute([$id]);
    
    logAdminAction($db, $admin['id'], 'CHAMPION_DELETED', "Deleted champion {$champion['athlete_name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Champion deleted successfully."]);
} catch (Exception $e) {
    error_log("Champion Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete champion.", 500);
}
