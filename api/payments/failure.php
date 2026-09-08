<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

$required = ['registration_number', 'razorpay_order_id'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendResponse(false, null, "Field '{$field}' is required.", 400);
    }
}

$reg_number = sanitizeInput($data['registration_number']);
$order_id = sanitizeInput($data['razorpay_order_id']);
$error_code = !empty($data['error_code']) ? sanitizeInput($data['error_code']) : null;
$error_desc = !empty($data['error_description']) ? sanitizeInput($data['error_description']) : null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Find payment
    $stmt = $db->prepare("SELECT p.id, p.status, r.status as reg_status
                          FROM payments p
                          JOIN registrations r ON p.registration_id = r.id
                          WHERE r.registration_number = ? AND p.razorpay_order_id = ?");
    $stmt->execute([$reg_number, $order_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        sendResponse(false, null, "Payment order not found.", 404);
    }
    
    // Protect captured payments
    if ($payment['status'] === 'captured' || $payment['reg_status'] === 'paid') {
        sendResponse(false, null, "Payment is already successful. Cannot mark as failed.", 409);
    }
    
    // Mark payment as failed
    $updatePayment = $db->prepare("UPDATE payments SET status = 'failed', error_code = ?, error_description = ? WHERE id = ?");
    $updatePayment->execute([$error_code, $error_desc, $payment['id']]);
    
    // Registration remains 'payment_pending' to allow retry.
    
    sendResponse(true, [
        "message" => "Payment failure recorded.",
        "registration_number" => $reg_number,
        "status" => "payment_pending_retry_allowed"
    ]);

} catch (Exception $e) {
    error_log("Razorpay Failure Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to record failure.", 500);
}
