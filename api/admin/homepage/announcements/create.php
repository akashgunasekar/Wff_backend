<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../helpers/validation.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';
require_once __DIR__ . '/../../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['message'])) {
    sendResponse(false, null, "Message is required.", 422);
}

$message = sanitizeInput($data['message']);
$link_text = sanitizeInput($data['link_text'] ?? '');
$link_url = sanitizeInput($data['link_url'] ?? '');
$status = isset($data['status']) ? (int)$data['status'] : 1;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO announcements (message, link_text, link_url, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$message, $link_text, $link_url, $status]);
    
    logAdminAction($db, $admin['id'], 'ANNOUNCEMENT_CREATED', "Created announcement", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Announcement created."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to create announcement.", 500);
}
