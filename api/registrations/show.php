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
    
    $query = "SELECT r.id, r.registration_number, r.athlete_name, r.date_of_birth, r.age, r.height, r.phone, r.email, r.status, r.created_at,
                     e.event_name, e.event_date, e.venue, c.name as category_name, c.entry_fee, r.event_id
              FROM registrations r
              JOIN events e ON r.event_id = e.id
              JOIN event_categories c ON r.category_id = c.id
              WHERE r.registration_number = ?";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$reg_number]);
    $registration = $stmt->fetch();
    
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
    $registration['payment_proof'] = null;
    $registration['transaction_ref'] = null;
    $registration['rejection_reason'] = null;
    $registration['proof_uploaded_at'] = null;
    $registration['approved_at'] = null;
    
    if ($metaJson) {
        $meta = json_decode($metaJson, true);
        if (isset($meta['category_ids']) && is_array($meta['category_ids'])) {
            // Fetch all category names
            $catIds = implode(',', array_map('intval', $meta['category_ids']));
            $catsStmt = $db->query("SELECT name FROM event_categories WHERE id IN ($catIds) ORDER BY FIELD(id, $catIds)");
            if ($catsStmt) {
                $registration['categories'] = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        if (isset($meta['pricing']['final_total'])) {
            $registration['total_amount'] = $meta['pricing']['final_total'];
        }
        $registration['pricing'] = $meta['pricing'] ?? null;
        $registration['clinical_charge'] = isset($meta['pricing']['clinical_charge']) ? (int)$meta['pricing']['clinical_charge'] : 0;
        $registration['category_fee'] = isset($meta['pricing']['category_fee']) ? (int)$meta['pricing']['category_fee'] : (int)$registration['total_amount'];
        if (isset($meta['tan_spray_requested'])) {
            $registration['tan_spray_requested'] = $meta['tan_spray_requested'];
        }
        if (!empty($meta['height']) && empty($registration['height'])) {
            $registration['height'] = $meta['height'];
        }
        if (isset($meta['payment_proof'])) {
            $registration['payment_proof'] = $meta['payment_proof'];
        }
        if (isset($meta['transaction_ref'])) {
            $registration['transaction_ref'] = $meta['transaction_ref'];
        }
        if (isset($meta['rejection_reason'])) {
            $registration['rejection_reason'] = $meta['rejection_reason'];
        }
        if (isset($meta['proof_uploaded_at'])) {
            $registration['proof_uploaded_at'] = $meta['proof_uploaded_at'];
        }
        if (isset($meta['approved_at'])) {
            $registration['approved_at'] = $meta['approved_at'];
        }
    }
    
    // Auto-calculate age fallback if null
    if (empty($registration['age']) && !empty($registration['date_of_birth'])) {
        try {
            $bDate = new DateTime($registration['date_of_birth']);
            $registration['age'] = $bDate->diff(new DateTime())->y;
        } catch (Exception $e) {}
    }

    // Get razorpay payment id if paid
    if ($registration['status'] === 'paid' || $registration['status'] === 'confirmed') {
        $payStmt = $db->prepare("SELECT razorpay_payment_id FROM payments WHERE registration_id = ? AND status = 'captured' ORDER BY id DESC LIMIT 1");
        $payStmt->execute([$registration['id']]);
        $registration['razorpay_payment_id'] = $payStmt->fetchColumn() ?: 'UPI-ADMIN-VERIFIED';
    }
    
    sendResponse(true, $registration);
} catch (Exception $e) {
    error_log("Registration Show Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch registration details.", 500);
}
