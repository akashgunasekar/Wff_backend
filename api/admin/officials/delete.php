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
    
    $check = $db->prepare("SELECT id, name FROM officials WHERE id = ?");
    $check->execute([$id]);
    $official = $check->fetch();
    
    if (!$official) {
        sendResponse(false, null, "Official not found.", 404);
    }
    
    $stmt = $db->prepare("DELETE FROM officials WHERE id = ?");
    $stmt->execute([$id]);
    
    logAdminAction($db, $admin['id'], 'OFFICIAL_DELETED', "Deleted official {$official['name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Official deleted successfully."]);
} catch (Exception $e) {
    error_log("Official Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete official.", 500);
}
