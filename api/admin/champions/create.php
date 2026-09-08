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

if (empty(trim($data['athlete_name'])) || empty(trim($data['title_won'])) || empty(trim($data['event_name']))) {
    sendResponse(false, null, "Athlete name, Title won, and Event name are required.", 422);
}

$athlete_name = sanitizeInput($data['athlete_name']);
$title_won = sanitizeInput($data['title_won']);
$event_name = sanitizeInput($data['event_name']);
$category = isset($data['category']) ? sanitizeInput($data['category']) : '';
$year = isset($data['year']) ? sanitizeInput($data['year']) : '';
$photo = isset($data['photo']) ? sanitizeInput($data['photo']) : '';
$sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO champions (athlete_name, title_won, event_name, event_id, category, photo, year, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$athlete_name, $title_won, $event_name, $event_id, $category, $photo, $year, $sort_order]);
    $champion_id = $db->lastInsertId();
    
    logAdminAction($db, $admin['id'], 'CHAMPION_CREATED', "Created champion {$athlete_name}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["id" => $champion_id, "message" => "Champion created successfully."]);
} catch (Exception $e) {
    error_log("Champion Create Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to create champion.", 500);
}
