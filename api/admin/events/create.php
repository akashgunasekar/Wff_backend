<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

$requiredFields = ['event_name', 'slug', 'event_date', 'venue', 'status'];
foreach ($requiredFields as $field) {
    if (empty(trim($data[$field]))) {
        sendResponse(false, null, "Field '{$field}' is required.", 422);
    }
}

$name = sanitizeInput($data['event_name']);
$slug = sanitizeInput($data['slug']);
$date = sanitizeInput($data['event_date']);
$venue = sanitizeInput($data['venue']);
$status = sanitizeInput($data['status']);
$banner = isset($data['banner_image']) ? sanitizeInput($data['banner_image']) : '';
$description = isset($data['description']) ? sanitizeInput($data['description']) : '';

$validStatuses = ['open', 'upcoming', 'closed'];
if (!in_array($status, $validStatuses)) {
    sendResponse(false, null, "Invalid status.", 422);
}

$statusMapToDb = [
    'open' => 'ongoing',
    'upcoming' => 'upcoming',
    'closed' => 'completed'
];
$dbStatus = $statusMapToDb[$status];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check for duplicate slug
    $stmt = $db->prepare("SELECT id FROM events WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        sendResponse(false, null, "An event with this slug already exists.", 409);
    }
    
    $stmt = $db->prepare("INSERT INTO events (event_name, slug, event_date, venue, status, banner_image, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $date, $venue, $dbStatus, $banner, $description]);
    $event_id = $db->lastInsertId();
    
    // Set default Tan Spray Price
    $ts_key = "event_{$event_id}_tan_spray_price";
    $ts_price = 1000;
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$ts_key, $ts_price, $ts_price]);
    
    logAdminAction($db, $admin['id'], 'EVENT_CREATED', "Created event {$name}", null, $dbStatus, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["id" => $event_id, "message" => "Event created successfully."]);
} catch (Exception $e) {
    error_log("Event Create Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to create event.", 500);
}
