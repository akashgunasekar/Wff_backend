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
if (empty($data['section_key']) || !isset($data['content'])) {
    sendResponse(false, null, "Section key and content are required.", 422);
}

$key = sanitizeInput($data['section_key']);
$title = sanitizeInput($data['title'] ?? '');
$subtitle = sanitizeInput($data['subtitle'] ?? '');
$content = $data['content']; // Expect JSON or text
$status = isset($data['status']) ? (int)$data['status'] : 1;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO homepage_sections (section_key, title, subtitle, content, status) 
                          VALUES (?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE title = ?, subtitle = ?, content = ?, status = ?");
    $stmt->execute([$key, $title, $subtitle, $content, $status, $title, $subtitle, $content, $status]);
    
    logAdminAction($db, $admin['id'], 'HOMEPAGE_SECTION_UPDATED', "Updated homepage section: {$key}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Section updated successfully."]);
} catch (Exception $e) {
    error_log("Section Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update section.", 500);
}
