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

if (empty($data['id']) || empty(trim($data['athlete_name'])) || empty(trim($data['title_won'])) || empty(trim($data['event_name']))) {
    sendResponse(false, null, "ID, Athlete name, Title won, and Event name are required.", 422);
}

$id = (int)$data['id'];
$athlete_name = sanitizeInput($data['athlete_name']);
$title_won = sanitizeInput($data['title_won']);
$event_name = sanitizeInput($data['event_name']);
$category = isset($data['category']) ? sanitizeInput($data['category']) : '';
$year = isset($data['year']) ? sanitizeInput($data['year']) : '';
$sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;

$photoUpdate = "";
$params = [$athlete_name, $title_won, $event_name, $event_id, $category, $year, $sort_order];

if (isset($data['photo'])) {
    $photoUpdate = ", photo = ?";
    $params[] = sanitizeInput($data['photo']);
}

$params[] = $id;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $check = $db->prepare("SELECT id, athlete_name FROM champions WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(false, null, "Champion not found.", 404);
    }
    
    $stmt = $db->prepare("UPDATE champions SET athlete_name = ?, title_won = ?, event_name = ?, event_id = ?, category = ?, year = ?, sort_order = ? {$photoUpdate} WHERE id = ?");
    $stmt->execute($params);
    
    logAdminAction($db, $admin['id'], 'CHAMPION_UPDATED', "Updated champion {$athlete_name}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Champion updated successfully."]);
} catch (Exception $e) {
    error_log("Champion Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update champion.", 500);
}
