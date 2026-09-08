<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$newStatus = isset($data['status']) ? trim($data['status']) : '';

if ($id <= 0 || empty($newStatus)) {
    sendResponse(false, null, "Registration ID and new status are required.", 400);
}

$validStatuses = ['pending', 'payment_pending', 'paid', 'confirmed', 'cancelled', 'rejected'];
if (!in_array($newStatus, $validStatuses)) {
    sendResponse(false, null, "Invalid registration status.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->beginTransaction();

    $stmt = $db->prepare("SELECT status FROM registrations WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        $db->rollBack();
        sendResponse(false, null, "Registration not found.", 404);
    }

    $oldStatus = $registration['status'];

    if ($oldStatus === $newStatus) {
        $db->rollBack();
        sendResponse(true, ['status' => $newStatus], "Status is already {$newStatus}.");
        exit;
    }

    $updateStmt = $db->prepare("UPDATE registrations SET status = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $id]);

    // Insert audit log
    $auditStmt = $db->prepare("INSERT INTO admin_audit_logs (admin_id, action, registration_id, previous_status, new_status, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $admin['id'],
        'UPDATE_REGISTRATION_STATUS',
        $id,
        $oldStatus,
        $newStatus,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $db->commit();

    sendResponse(true, ['status' => $newStatus], "Registration status updated successfully.");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Admin Registration Status Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update registration status.", 500);
}
