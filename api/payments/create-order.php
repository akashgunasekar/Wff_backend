<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

use Razorpay\Api\Api;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

if (empty($data['registration_number'])) {
    sendResponse(false, null, "Registration number is required.", 400);
}

$reg_number = sanitizeInput($data['registration_number']);

$ip = $_SERVER['REMOTE_ADDR'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // IP-based Rate Limiting (10 per 15 minutes)
    $db->exec("DELETE FROM login_attempts WHERE attempt_time < NOW() - INTERVAL 15 MINUTE AND email_attempt = 'RATE_LIMIT_PAY'");
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND email_attempt = 'RATE_LIMIT_PAY'");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 10) {
        sendResponse(false, null, "Too many payment attempts. Please try again later.", 429);
        exit;
    }
    
    $insert = $db->prepare("INSERT INTO login_attempts (ip_address, email_attempt) VALUES (?, 'RATE_LIMIT_PAY')");
    $insert->execute([$ip]);
    
    // Validate registration
    $stmt = $db->prepare("SELECT r.id, r.status, c.entry_fee 
                          FROM registrations r
                          JOIN event_categories c ON r.category_id = c.id
                          WHERE r.registration_number = ?");
    $stmt->execute([$reg_number]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        sendResponse(false, null, "Registration not found.", 404);
    }
    
    if ($registration['status'] !== 'payment_pending') {
        sendResponse(false, null, "Registration is not in a payment pending state.", 409);
    }
    
    // Verify fee is valid
    $amount_paise = 0;
    
    // Check for multi-category/tan spray metadata
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["registration_{$registration['id']}_meta"]);
    $metaJson = $stmt->fetchColumn();
    
    if ($metaJson) {
        $meta = json_decode($metaJson, true);
        if (isset($meta['pricing']['final_total'])) {
            $amount_paise = (int)($meta['pricing']['final_total'] * 100);
        }
    }
    
    // Legacy fallback
    if ($amount_paise === 0) {
        if ($registration['entry_fee'] === null || !is_numeric($registration['entry_fee']) || $registration['entry_fee'] <= 0) {
            sendResponse(false, null, "Invalid entry fee configured for this category.", 400);
        }
        $amount_paise = (int)($registration['entry_fee'] * 100);
    }
    
    // Idempotency: Check existing usable order
    $stmt = $db->prepare("SELECT razorpay_order_id, amount FROM payments WHERE registration_id = ? AND status = 'created' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$registration['id']]);
    $existingOrder = $stmt->fetch();
    
    $key_id = getenv('RAZORPAY_KEY_ID');
    $key_secret = getenv('RAZORPAY_KEY_SECRET');
    
    if (!$key_id || !$key_secret) {
        sendResponse(false, null, "Payment gateway is not configured.", 500);
    }

    if ($existingOrder && (int)$existingOrder['amount'] === $amount_paise) {
        sendResponse(true, [
            "registration_number" => $reg_number,
            "razorpay_order_id" => $existingOrder['razorpay_order_id'],
            "amount" => $amount_paise,
            "currency" => "INR",
            "key_id" => $key_id
        ]);
        exit;
    }
    
    // Create new Razorpay order
    $api = new Api($key_id, $key_secret);
    
    $orderData = [
        'receipt'         => $reg_number,
        'amount'          => $amount_paise, 
        'currency'        => 'INR'
    ];
    
    $razorpayOrder = $api->order->create($orderData);
    
    // Store in DB
    $stmt = $db->prepare("INSERT INTO payments (registration_id, razorpay_order_id, amount, currency, status) VALUES (?, ?, ?, ?, 'created')");
    $stmt->execute([
        $registration['id'],
        $razorpayOrder['id'],
        $amount_paise,
        'INR'
    ]);
    
    sendResponse(true, [
        "registration_number" => $reg_number,
        "razorpay_order_id" => $razorpayOrder['id'],
        "amount" => $amount_paise,
        "currency" => "INR",
        "key_id" => $key_id
    ]);

} catch (Exception $e) {
    error_log("Razorpay Order Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to create payment order.", 500);
}
