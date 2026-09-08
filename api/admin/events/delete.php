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
    sendResponse(false, null, "Event ID is required.", 400);
}

$event_id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, event_name FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        sendResponse(false, null, "Event not found.", 404);
    }
    
    // Protect deletion if registrations exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $regCount = $stmt->fetchColumn();
    
    if ($regCount > 0) {
        sendResponse(false, null, "This event cannot be deleted because it has existing registration records.", 409);
    }
    
    // Note: event_categories has ON DELETE CASCADE. Safe to delete event if no registrations exist.
    $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    
    logAdminAction($db, $admin['id'], 'EVENT_DELETED', "Deleted event {$event['event_name']}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Event deleted successfully."]);
} catch (Exception $e) {
    error_log("Event Delete Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to delete event.", 500);
}
