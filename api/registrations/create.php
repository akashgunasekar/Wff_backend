<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$jsonString = file_get_contents("php://input");
$data = json_decode($jsonString, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

// Check new required fields
$required = ['event_id', 'category_ids', 'athlete_name', 'date_of_birth', 'phone', 'email'];
foreach ($required as $field) {
    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        sendResponse(false, null, "Field '{$field}' is required.", 400);
    }
}
if (!is_array($data['category_ids']) || empty($data['category_ids'])) {
    sendResponse(false, null, "At least one category is required.", 400);
}

$event_id = (int)$data['event_id'];
$category_ids = array_map('intval', $data['category_ids']);
$primary_category_id = $category_ids[0]; // first category

$athlete_name = sanitizeInput($data['athlete_name']);
$dob = sanitizeInput($data['date_of_birth']);
$phone = sanitizeInput($data['phone']);
$email = sanitizeInput($data['email']);
$instagram_id = !empty($data['instagram_id']) ? sanitizeInput($data['instagram_id']) : null;
$height = !empty($data['height']) ? sanitizeInput($data['height']) : null;
$weight = !empty($data['weight']) ? sanitizeInput($data['weight']) : null;
$tan_spray_requested = !empty($data['tan_spray_requested']) ? true : false;
$payment_method = array_key_exists('payment_method', $data) && $data['payment_method'] === 'cash' ? 'cash' : 'online';

// Hardcoded defaults to pass strictly-defined DB schema without ALTER
$gender = 'Not Specified'; 
$address = '';
$city = '';
$state = '';
$emergency_name = '';
$emergency_phone = '';

if (!validateDate($dob)) {
    sendResponse(false, null, "Invalid date of birth format (expected YYYY-MM-DD).", 400);
}
if (!validatePhone($phone)) {
    sendResponse(false, null, "Invalid phone number.", 400);
}
if ($email && !validateEmail($email)) {
    sendResponse(false, null, "Invalid email address.", 400);
}

$ip = $_SERVER['REMOTE_ADDR'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // IP-based Rate Limiting (10 per 15 minutes)
    $db->exec("DELETE FROM login_attempts WHERE attempt_time < NOW() - INTERVAL 15 MINUTE AND email_attempt = 'RATE_LIMIT_REG'");
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND email_attempt = 'RATE_LIMIT_REG'");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 10) {
        sendResponse(false, null, "Too many registration attempts. Please try again later.", 429);
        exit;
    }
    
    $insert = $db->prepare("INSERT INTO login_attempts (ip_address, email_attempt) VALUES (?, 'RATE_LIMIT_REG')");
    $insert->execute([$ip]);
    
    $db->beginTransaction();

    // 1. Verify Event
    $stmt = $db->prepare("SELECT id, status FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        $db->rollBack();
        sendResponse(false, null, "Invalid event ID.", 400);
    }
    if ($event['status'] !== 'open' && $event['status'] !== 'ongoing') { // Account for legacy status
        $db->rollBack();
        sendResponse(false, null, "This event is not open for registration.", 400);
    }
    
    // Validate Cash
    if ($payment_method === 'cash') {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute(["event_{$event_id}_cash_enabled"]);
        if ($stmt->fetchColumn() !== '1') {
            $db->rollBack();
            sendResponse(false, null, "Cash payment is not enabled for this event.", 400);
        }
    }

    // 2. Pricing Engine
    $base_total = 0;
    $discount_total = 0;
    
    foreach ($category_ids as $index => $cat_id) {
        // Verify Category
        $stmt = $db->prepare("SELECT id, entry_fee, availability FROM event_categories WHERE id = ? AND event_id = ?");
        $stmt->execute([$cat_id, $event_id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            $db->rollBack();
            sendResponse(false, null, "Category ID {$cat_id} does not exist for this event.", 400);
        }
        if ($category['availability'] !== 'open') {
            $db->rollBack();
            sendResponse(false, null, "Category ID {$cat_id} is closed.", 400);
        }
        
        // Duplicate Protection per Category
        $stmt = $db->prepare("SELECT id FROM registrations WHERE event_id = ? AND category_id = ? AND phone = ?");
        $stmt->execute([$event_id, $cat_id, $phone]);
        if ($stmt->fetch()) {
            $db->rollBack();
            sendResponse(false, null, "You are already registered for this category.", 409);
        }
        
        $fee = (int)($category['entry_fee'] ?: 0);
        $base_total += $fee;
        if ($index > 0) {
            $discount_total += ($fee * 0.5);
        }
    }

    // 3. Tan Spray Rules
    $tan_spray_fee = 0;
    if ($tan_spray_requested) {
        // Fetch event tan spray price from site_settings (fallback 1000)
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute(["event_{$event_id}_tan_spray_price"]);
        $ts_setting = $stmt->fetchColumn();
        $event_tan_spray_price = $ts_setting !== false ? (int)$ts_setting : 1000;
        
        // Is this the first registration for this athlete (same event + phone)?
        $stmt = $db->prepare("SELECT id FROM registrations WHERE event_id = ? AND phone = ? LIMIT 1");
        $stmt->execute([$event_id, $phone]);
        $existing = $stmt->fetchColumn();
        
        if ($existing) {
            // Free!
            $tan_spray_fee = 0;
        } else {
            $tan_spray_fee = $event_tan_spray_price;
        }
    }

    $final_total = $base_total - $discount_total + $tan_spray_fee;

    // 4. Create Master Registration
    $query = "INSERT INTO registrations (
        registration_number, event_id, category_id, athlete_name, date_of_birth, 
        gender, phone, email, address, city, state, emergency_contact_name, emergency_contact_phone, status
    ) VALUES (
        'TEMP', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'payment_pending'
    )";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $event_id, $primary_category_id, $athlete_name, $dob, $gender, $phone, $email, 
        $address, $city, $state, $emergency_name, $emergency_phone
    ]);
    
    $registration_id = $db->lastInsertId();
    
    $year = date('Y');
    $reg_number = "WFFTN-{$year}-" . str_pad($registration_id, 6, "0", STR_PAD_LEFT);
    
    $updateStmt = $db->prepare("UPDATE registrations SET registration_number = ? WHERE id = ?");
    $updateStmt->execute([$reg_number, $registration_id]);
    
    // 5. Store Metadata JSON in site_settings to safely bypass schema limitations
    $meta = [
        'category_ids' => $category_ids, // all selected categories
        'instagram_id' => $instagram_id,
        'height' => $height,
        'weight' => $weight,
        'tan_spray_requested' => $tan_spray_requested,
        'pricing' => [
            'base_total' => $base_total,
            'discount_total' => $discount_total,
            'tan_spray_fee' => $tan_spray_fee,
            'final_total' => $final_total
        ]
    ];
    
    $setting_key = "registration_{$registration_id}_meta";
    $setting_value = json_encode($meta);
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$setting_key, $setting_value, $setting_value]);
    
    // 6. Create Cash Payment Record
    if ($payment_method === 'cash') {
        $payStmt = $db->prepare("INSERT INTO payments (registration_id, amount, currency, status, method) VALUES (?, ?, 'INR', 'created', 'cash')");
        $payStmt->execute([$registration_id, $final_total]);
    }
    
    $db->commit();
    
    sendResponse(true, [
        "registration_id" => $registration_id,
        "registration_number" => $reg_number,
        "status" => "payment_pending",
        "total_amount" => $final_total,
        "payment_method" => $payment_method
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to process registration.", 500);
}

