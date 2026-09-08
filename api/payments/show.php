<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

if (empty($_GET['registration_number'])) {
    sendResponse(false, null, "Registration number is required.", 400);
}

$reg_number = sanitizeInput($_GET['registration_number']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT p.status, p.amount, p.currency, p.razorpay_order_id, p.razorpay_payment_id, p.created_at
              FROM payments p
              JOIN registrations r ON p.registration_id = r.id
              WHERE r.registration_number = ?
              ORDER BY p.id DESC LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$reg_number]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        sendResponse(false, null, "Payment record not found.", 404);
    }
    
    sendResponse(true, [
        "registration_number" => $reg_number,
        "status" => $payment['status'],
        "amount" => $payment['amount'],
        "currency" => $payment['currency'],
        "razorpay_order_id" => $payment['razorpay_order_id'],
        "razorpay_payment_id" => $payment['razorpay_payment_id'],
        "created_at" => $payment['created_at']
    ]);
} catch (Exception $e) {
    error_log("Payment Show Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch payment details.", 500);
}
