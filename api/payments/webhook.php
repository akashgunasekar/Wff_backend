<?php
// Ensure this is a server-to-server endpoint, no CORS needed for browsers.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Webhook endpoint only accepts POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$webhookSecret = getenv('RAZORPAY_WEBHOOK_SECRET');

if (empty($webhookSecret)) {
    error_log("Webhook Error: RAZORPAY_WEBHOOK_SECRET is not configured.");
    sendResponse(false, null, "Server configuration error", 500);
}

// Read raw request body EXACTLY ONCE
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($signature)) {
    error_log("Webhook Error: Missing signature.");
    sendResponse(false, null, "Missing signature", 400);
}

// Validate signature using Razorpay SDK
$api = new Api('dummy', 'dummy'); 
try {
    $api->utility->verifyWebhookSignature($payload, $signature, $webhookSecret);
} catch (SignatureVerificationError $e) {
    error_log("Webhook Error: Invalid signature.");
    sendResponse(false, null, "Invalid signature", 400);
}

// Parse payload
$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['event'])) {
    error_log("Webhook Error: Invalid JSON or missing event.");
    sendResponse(false, null, "Invalid payload", 400);
}

$event = $data['event'];
$webhook_id = $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? null; // Usually provided in headers, but can also use razorpay_payment_id
$payment_entity = $data['payload']['payment']['entity'] ?? null;
if (!$payment_entity && $event === 'order.paid') {
    $payment_entity = $data['payload']['payment']['entity'] ?? null;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Idempotency: We will check if the specific payment event state has already been reached 
    // by using the razorpay_payment_id and status in our payments table, as well as checking webhook_logs.
    
    // Let's rely on webhook_logs if webhook_id is present
    if ($webhook_id) {
        $stmt = $db->prepare("SELECT id FROM webhook_logs WHERE webhook_id = ?");
        $stmt->execute([$webhook_id]);
        if ($stmt->fetch()) {
            error_log("Webhook Info: Duplicate webhook received and ignored ($webhook_id).");
            sendResponse(true, null, "Already processed");
            exit;
        }
        
        $safe_payload = [
            "event" => $event,
            "account_id" => $data['account_id'] ?? ''
        ];
        $stmt = $db->prepare("INSERT INTO webhook_logs (webhook_id, event_type, payload) VALUES (?, ?, ?)");
        $stmt->execute([$webhook_id, $event, json_encode($safe_payload)]);
    }

    $db->beginTransaction();

    if ($event === 'payment.captured' || $event === 'order.paid') {
        if ($payment_entity) {
            $payment_id = $payment_entity['id'];
            $order_id = $payment_entity['order_id'];
            $amount = $payment_entity['amount'];
            $currency = $payment_entity['currency'];
            
            $stmt = $db->prepare("SELECT p.id, p.status, p.amount as db_amount, p.currency as db_currency, 
                                         r.id as registration_id, r.status as reg_status
                                  FROM payments p
                                  JOIN registrations r ON p.registration_id = r.id
                                  WHERE p.razorpay_order_id = ? FOR UPDATE");
            $stmt->execute([$order_id]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                // Verify amount and currency
                if ((int)$payment['db_amount'] === (int)$amount && $payment['db_currency'] === $currency) {
                    
                    if ($payment['status'] !== 'captured') {
                        $updatePayment = $db->prepare("UPDATE payments SET status = 'captured', razorpay_payment_id = ? WHERE id = ?");
                        $updatePayment->execute([$payment_id, $payment['id']]);
                    }
                    
                    if ($payment['reg_status'] === 'payment_pending') {
                        $updateReg = $db->prepare("UPDATE registrations SET status = 'paid' WHERE id = ?");
                        $updateReg->execute([$payment['registration_id']]);
                    }
                    
                    error_log("Webhook Success: Payment captured successfully for Order $order_id");
                } else {
                    error_log("Webhook Error: Amount or currency mismatch for Order $order_id");
                }
            } else {
                error_log("Webhook Error: Order $order_id not found in local database.");
            }
        }
    } 
    elseif ($event === 'payment.failed') {
        if ($payment_entity) {
            $order_id = $payment_entity['order_id'];
            $payment_id = $payment_entity['id'];
            $error_code = $payment_entity['error_code'] ?? 'UNKNOWN';
            $error_desc = $payment_entity['error_description'] ?? 'Unknown error';
            
            $stmt = $db->prepare("SELECT p.id, p.status, r.status as reg_status
                                  FROM payments p
                                  JOIN registrations r ON p.registration_id = r.id
                                  WHERE p.razorpay_order_id = ? FOR UPDATE");
            $stmt->execute([$order_id]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                if ($payment['status'] !== 'captured' && $payment['reg_status'] !== 'paid') {
                    $updatePayment = $db->prepare("UPDATE payments SET status = 'failed', razorpay_payment_id = ?, error_code = ?, error_description = ? WHERE id = ?");
                    $updatePayment->execute([$payment_id, $error_code, $error_desc, $payment['id']]);
                    error_log("Webhook Success: Payment marked as failed for Order $order_id");
                } else {
                    error_log("Webhook Warning: Received payment.failed for already captured payment Order $order_id.");
                }
            }
        }
    } else {
        error_log("Webhook Info: Ignored unhandled event $event");
    }

    $db->commit();
    sendResponse(true, null, "Webhook processed");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Webhook DB Error: " . $e->getMessage());
    sendResponse(false, null, "Internal server error", 500);
}
