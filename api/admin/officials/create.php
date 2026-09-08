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

if (empty(trim($data['name']))) {
    sendResponse(false, null, "Name is required.", 422);
}

$name = sanitizeInput($data['name']);
$role = isset($data['role']) ? sanitizeInput($data['role']) : '';
$designation = isset($data['designation']) ? sanitizeInput($data['designation']) : '';
$photo = isset($data['photo']) ? sanitizeInput($data['photo']) : '';
$bio = isset($data['bio']) ? sanitizeInput($data['bio']) : '';
$sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$status = isset($data['status']) ? (int)$data['status'] : 1;

$socials = [
    'instagram_url' => isset($data['instagram_url']) ? sanitizeInput($data['instagram_url']) : '',
    'facebook_url' => isset($data['facebook_url']) ? sanitizeInput($data['facebook_url']) : '',
    'youtube_url' => isset($data['youtube_url']) ? sanitizeInput($data['youtube_url']) : '',
    'website_url' => isset($data['website_url']) ? sanitizeInput($data['website_url']) : ''
];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("INSERT INTO officials (name, role, designation, photo, bio, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $role, $designation, $photo, $bio, $sort_order, $status]);
    $official_id = $db->lastInsertId();
    
    // Save social links
    $key = 'official_' . $official_id . '_socials';
    $json = json_encode($socials);
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $json]);
    
    logAdminAction($db, $admin['id'], 'OFFICIAL_CREATED', "Created official {$name}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["id" => $official_id, "message" => "Official created successfully."]);
} catch (Exception $e) {
    error_log("Official Create Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to create official.", 500);
}
