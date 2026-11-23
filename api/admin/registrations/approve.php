<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../helpers/email.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    sendResponse(false, null, "Valid registration ID is required.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->beginTransaction();

    $stmt = $db->prepare("SELECT r.*, e.event_name FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        $db->rollBack();
        sendResponse(false, null, "Registration not found.", 404);
    }

    $oldStatus = $reg['status'];

    // Update registration status to confirmed
    $updateStmt = $db->prepare("UPDATE registrations SET status = 'confirmed' WHERE id = ?");
    $updateStmt->execute([$id]);

    // Fetch and update meta
    $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $metaStmt->execute(["registration_{$id}_meta"]);
    $metaJson = $metaStmt->fetchColumn();

    $meta = $metaJson ? json_decode($metaJson, true) : [];
    $meta['approval_status'] = 'approved';
    $meta['approved_at'] = date('Y-m-d H:i:s');
    $meta['approved_by'] = $admin['id'];
    $meta['approved_by_email'] = $admin['email'];
    unset($meta['rejection_reason']);
    unset($meta['rejected_at']);

    $setting_key = "registration_{$id}_meta";
    $setting_value = json_encode($meta);
    $saveMetaStmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $saveMetaStmt->execute([$setting_key, $setting_value, $setting_value]);

    // Update payment record to captured
    $payStmt = $db->prepare("SELECT id FROM payments WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $payStmt->execute([$id]);
    $existingPayId = $payStmt->fetchColumn();

    $totalAmount = isset($meta['pricing']['final_total']) ? $meta['pricing']['final_total'] : (int)($reg['total_amount'] ?: 0);

    if ($existingPayId) {
        $updatePayStmt = $db->prepare("UPDATE payments SET status = 'captured', razorpay_payment_id = COALESCE(razorpay_payment_id, ?), updated_at = NOW() WHERE id = ?");
        $updatePayStmt->execute(['UPI-ADMIN-APPROVED-' . time(), $existingPayId]);
    } else {
        $insertPayStmt = $db->prepare("INSERT INTO payments (registration_id, razorpay_order_id, razorpay_payment_id, amount, currency, status, method) VALUES (?, ?, ?, ?, 'INR', 'captured', 'upi_qr')");
        $insertPayStmt->execute([$id, 'MANUAL-' . time(), 'UPI-ADMIN-APPROVED-' . time(), $totalAmount * 100]);
    }

    // Insert admin audit log
    $auditStmt = $db->prepare("INSERT INTO admin_audit_logs (admin_id, action, registration_id, previous_status, new_status, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $admin['id'],
        'APPROVE_PAYMENT_ISSUE_TICKET',
        $id,
        $oldStatus,
        'confirmed',
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $db->commit();

    // Trigger Email 2: Official Ticket After Admin Approval
    $debugInfo = '';
    $emailSent = sendAthletePassEmail($reg['registration_number'], $db, $debugInfo);

    sendResponse(true, [
        "registration_id" => $id,
        "registration_number" => $reg['registration_number'],
        "status" => "confirmed",
        "email_sent" => $emailSent,
        "debug" => $debugInfo,
        "message" => $emailSent 
            ? "Payment verified, registration approved, and official ticket sent to athlete."
            : "Payment approved and confirmed. Note: Email notice - " . ($debugInfo ?: 'Ticket preview saved to uploads/emails')
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Admin Approve Registration Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to approve registration: " . $e->getMessage(), 500);
}
