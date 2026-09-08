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
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data['id'])) {
    sendResponse(false, null, "Invalid request data. ID is required.", 400);
}

$registration_id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check registration
    $stmt = $db->prepare("SELECT * FROM registrations WHERE id = ?");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        sendResponse(false, null, "Registration not found.", 404);
    }
    
    // Check payment
    $stmt = $db->prepare("SELECT * FROM payments WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$registration_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        sendResponse(false, null, "No payment record found.", 404);
    }
    
    if ($payment['method'] !== 'cash') {
        sendResponse(false, null, "Payment method is not cash.", 400);
    }
    
    if ($payment['status'] === 'captured' || $registration['status'] === 'paid') {
        // Idempotency: if already paid, just return success
        sendResponse(true, ["message" => "Cash already collected."]);
    }
    
    if ($payment['status'] !== 'created') {
        sendResponse(false, null, "Payment is in an invalid state for cash collection.", 400);
    }
    
    $db->beginTransaction();
    
    // Update payment
    $stmt = $db->prepare("UPDATE payments SET status = 'captured' WHERE id = ?");
    $stmt->execute([$payment['id']]);
    
    // Update registration
    $stmt = $db->prepare("UPDATE registrations SET status = 'paid' WHERE id = ?");
    $stmt->execute([$registration_id]);
    
    logAdminAction($db, $admin['id'], 'CASH_COLLECTED', "Collected cash for registration {$registration['registration_number']}", 'created', 'captured', $_SERVER['REMOTE_ADDR']);
    
    $db->commit();
    
    sendResponse(true, ["message" => "Cash collected successfully."]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Cash Collection Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to collect cash.", 500);
}
