<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

$required = ['registration_number', 'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendResponse(false, null, "Field '{$field}' is required.", 400);
    }
}

$reg_number = sanitizeInput($data['registration_number']);
$order_id = sanitizeInput($data['razorpay_order_id']);
$payment_id = sanitizeInput($data['razorpay_payment_id']);
$signature = sanitizeInput($data['razorpay_signature']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Begin Verification Transaction
    $db->beginTransaction();
    
    // 1. Lock payment record (prevent race conditions)
    $stmt = $db->prepare("SELECT p.id, p.status, p.amount, p.currency, p.razorpay_order_id as db_order_id, r.id as registration_id, r.status as reg_status, c.entry_fee
                          FROM payments p
                          JOIN registrations r ON p.registration_id = r.id
                          JOIN event_categories c ON r.category_id = c.id
                          WHERE r.registration_number = ? AND p.razorpay_order_id = ? FOR UPDATE");
    $stmt->execute([$reg_number, $order_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $db->rollBack();
        sendResponse(false, null, "Payment order not found.", 404);
    }
    
    // 2. Idempotency Check
    if ($payment['status'] === 'captured' && $payment['reg_status'] === 'paid') {
        $db->rollBack();
        sendResponse(true, ["message" => "Payment already verified.", "status" => "captured"]);
        exit;
    }
    
    // 3. Ensure expected amount
    // The amount is validated during create-order and verified implicitly by Razorpay's signature
    if ((int)$payment['amount'] <= 0) {
        $db->rollBack();
        sendResponse(false, null, "Invalid payment amount in database.", 400);
    }
    
    $key_id = getenv('RAZORPAY_KEY_ID');
    $key_secret = getenv('RAZORPAY_KEY_SECRET');
    
    if (empty($key_id) || empty($key_secret)) {
        $db->rollBack();
        sendResponse(false, null, "Payment gateway is not configured.", 500);
    }
    
    // 4. Verify Signature using DB authoritative order ID
    $api = new Api($key_id, $key_secret);
    
    $attributes = [
        'razorpay_order_id' => $payment['db_order_id'],
        'razorpay_payment_id' => $payment_id,
        'razorpay_signature' => $signature
    ];
    
    try {
        $api->utility->verifyPaymentSignature($attributes);
    } catch(SignatureVerificationError $e) {
        $db->rollBack();
        sendResponse(false, null, "Payment verification failed. Invalid signature.", 400);
        exit;
    }
    
    // 5. Update Status
    $updatePayment = $db->prepare("UPDATE payments SET status = 'captured', razorpay_payment_id = ?, razorpay_signature = ? WHERE id = ?");
    $updatePayment->execute([$payment_id, $signature, $payment['id']]);
    
    $updateReg = $db->prepare("UPDATE registrations SET status = 'paid' WHERE id = ? AND status = 'payment_pending'");
    $updateReg->execute([$payment['registration_id']]);
    
    $db->commit();
    
    sendResponse(true, [
        "message" => "Payment verified successfully.",
        "registration_number" => $reg_number,
        "status" => "captured"
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Razorpay Verify Error: " . $e->getMessage());
    sendResponse(false, null, "Internal server error during verification.", 500);
}
