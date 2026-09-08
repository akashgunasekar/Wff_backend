<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    sendResponse(false, null, "Invalid registration ID.", 400);
}

try {
    $db = (new Database())->getConnection();

    // Get registration and event details
    $sql = "SELECT r.*, e.event_name, e.event_date, e.venue, c.name as category_name, c.entry_fee
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            JOIN event_categories c ON r.category_id = c.id
            WHERE r.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        sendResponse(false, null, "Registration not found.", 404);
    }
    
    // Check for multi-category meta
    $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $metaStmt->execute(["registration_{$registration['id']}_meta"]);
    $metaJson = $metaStmt->fetchColumn();
    
    $registration['categories'] = [$registration['category_name']];
    $registration['total_amount'] = $registration['entry_fee'] ? (int)$registration['entry_fee'] : 0;
    $registration['tan_spray_requested'] = false;
    $registration['height'] = null;
    $registration['weight'] = null;
    $registration['instagram_id'] = null;
    
    if ($metaJson) {
        $meta = json_decode($metaJson, true);
        if (isset($meta['category_ids']) && is_array($meta['category_ids'])) {
            $catIds = implode(',', array_map('intval', $meta['category_ids']));
            $catsStmt = $db->query("SELECT name FROM event_categories WHERE id IN ($catIds) ORDER BY FIELD(id, $catIds)");
            $registration['categories'] = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        if (isset($meta['pricing']['final_total'])) {
            $registration['total_amount'] = $meta['pricing']['final_total'];
        }
        if (isset($meta['tan_spray_requested'])) {
            $registration['tan_spray_requested'] = $meta['tan_spray_requested'];
        }
        if (isset($meta['height'])) $registration['height'] = $meta['height'];
        if (isset($meta['weight'])) $registration['weight'] = $meta['weight'];
        if (isset($meta['instagram_id'])) $registration['instagram_id'] = $meta['instagram_id'];
    }

    // Get payments history
    $paymentsSql = "SELECT id, razorpay_order_id, razorpay_payment_id, amount, currency, status, created_at, updated_at
                    FROM payments 
                    WHERE registration_id = ? 
                    ORDER BY id DESC";
    $paymentsStmt = $db->prepare($paymentsSql);
    $paymentsStmt->execute([$id]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determine latest payment
    $latestPayment = !empty($payments) ? $payments[0] : null;

    sendResponse(true, [
        'registration' => $registration,
        'payments' => $payments,
        'latest_payment' => $latestPayment
    ]);

} catch (Exception $e) {
    error_log("Admin Registration Show Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to load registration details.", 500);
}
