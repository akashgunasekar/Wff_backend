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
if (empty($data['section_key'])) {
    sendResponse(false, null, "Section key is required.", 422);
}

$key = sanitizeInput($data['section_key']);
$title = sanitizeInput($data['title'] ?? '');
$subtitle = sanitizeInput($data['subtitle'] ?? '');

$content = null;
if (isset($data['content'])) {
    if (is_array($data['content'])) {
        $content = json_encode($data['content'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $content = (string)$data['content'];
    }
}

$status = isset($data['status']) ? (int)$data['status'] : 1;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if row already exists
    $stmtCheck = $db->prepare("SELECT id, content FROM homepage_sections WHERE section_key = ?");
    $stmtCheck->execute([$key]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $finalContent = $content !== null ? $content : $existing['content'];
        $stmt = $db->prepare("UPDATE homepage_sections SET title = ?, subtitle = ?, content = ?, status = ? WHERE section_key = ?");
        $stmt->execute([$title, $subtitle, $finalContent, $status, $key]);
    } else {
        $finalContent = $content !== null ? $content : '';
        $stmt = $db->prepare("INSERT INTO homepage_sections (section_key, title, subtitle, content, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$key, $title, $subtitle, $finalContent, $status]);
    }
    
    logAdminAction($db, $admin['id'], 'HOMEPAGE_SECTION_UPDATED', "Updated homepage section: {$key}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Section updated successfully."]);
} catch (Exception $e) {
    error_log("Section Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update section: " . $e->getMessage(), 500);
}
