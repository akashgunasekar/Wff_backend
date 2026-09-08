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
    sendResponse(false, null, "Registration ID is required.", 400);
}

$registration_id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, registration_number, athlete_name FROM registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        sendResponse(false, null, "Registration not found.", 404);
    }
    
    $db->beginTransaction();
    
    // Manually delete child records to bypass ON DELETE RESTRICT constraints
    $db->prepare("DELETE FROM payments WHERE registration_id = ?")->execute([$registration_id]);
    $db->prepare("DELETE FROM site_settings WHERE setting_key = ?")->execute(["registration_{$registration_id}_meta"]);
    
    $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    
    $db->commit();
    
    logAdminAction($db, $admin['id'], 'REGISTRATION_DELETED', "Deleted registration {$registration['registration_number']} for {$registration['athlete_name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Registration deleted successfully."]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete registration.", 500);
}
