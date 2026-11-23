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
$reason = isset($data['rejection_reason']) ? trim($data['rejection_reason']) : 'Payment screenshot unreadable or transaction details invalid.';

if ($id <= 0) {
    sendResponse(false, null, "Valid registration ID is required.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->beginTransaction();

    $stmt = $db->prepare("SELECT status FROM registrations WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        $db->rollBack();
        sendResponse(false, null, "Registration not found.", 404);
    }

    $oldStatus = $reg['status'];

    // Update status to rejected
    $updateStmt = $db->prepare("UPDATE registrations SET status = 'rejected' WHERE id = ?");
    $updateStmt->execute([$id]);

    // Save rejection reason in meta
    $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $metaStmt->execute(["registration_{$id}_meta"]);
    $metaJson = $metaStmt->fetchColumn();

    $meta = $metaJson ? json_decode($metaJson, true) : [];
    $meta['approval_status'] = 'rejected';
    $meta['rejection_reason'] = $reason;
    $meta['rejected_at'] = date('Y-m-d H:i:s');
    $meta['rejected_by'] = $admin['id'];

    $setting_key = "registration_{$id}_meta";
    $setting_value = json_encode($meta);
    $saveMetaStmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $saveMetaStmt->execute([$setting_key, $setting_value, $setting_value]);

    // Insert admin audit log
    $auditStmt = $db->prepare("INSERT INTO admin_audit_logs (admin_id, action, registration_id, previous_status, new_status, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $admin['id'],
        'REJECT_PAYMENT_PROOF',
        $id,
        $oldStatus,
        'rejected',
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $db->commit();

    sendResponse(true, [
        "registration_id" => $id,
        "status" => "rejected",
        "rejection_reason" => $reason,
        "message" => "Payment rejected. Athlete can resubmit payment proof via registration-status."
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Admin Reject Registration Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to reject registration: " . $e->getMessage(), 500);
}
