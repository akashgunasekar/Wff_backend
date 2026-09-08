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
    sendResponse(false, null, "Category ID is required.", 400);
}

$category_id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, name FROM event_categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        sendResponse(false, null, "Category not found.", 404);
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $regCount = $stmt->fetchColumn();
    
    if ($regCount > 0) {
        sendResponse(false, null, "This category cannot be deleted because registrations already exist.", 409);
    }
    
    $stmt = $db->prepare("DELETE FROM event_categories WHERE id = ?");
    $stmt->execute([$category_id]);
    
    logAdminAction($db, $admin['id'], 'CATEGORY_DELETED', "Deleted category {$category['name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Category deleted successfully."]);
} catch (Exception $e) {
    error_log("Category Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete category.", 500);
}
