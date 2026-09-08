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

if (empty($data['id']) || empty(trim($data['name']))) {
    sendResponse(false, null, "ID and Name are required.", 422);
}

$id = (int)$data['id'];
$name = sanitizeInput($data['name']);
$role = isset($data['role']) ? sanitizeInput($data['role']) : '';
$designation = isset($data['designation']) ? sanitizeInput($data['designation']) : '';
$bio = isset($data['bio']) ? sanitizeInput($data['bio']) : '';
$sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$status = isset($data['status']) ? (int)$data['status'] : 1;

// Only update photo if provided
$photoUpdate = "";
$params = [$name, $role, $designation, $bio, $sort_order, $status];

if (isset($data['photo'])) {
    $photoUpdate = ", photo = ?";
    $params[] = sanitizeInput($data['photo']);
}

$params[] = $id;

$socials = [
    'instagram_url' => isset($data['instagram_url']) ? sanitizeInput($data['instagram_url']) : '',
    'facebook_url' => isset($data['facebook_url']) ? sanitizeInput($data['facebook_url']) : '',
    'youtube_url' => isset($data['youtube_url']) ? sanitizeInput($data['youtube_url']) : '',
    'website_url' => isset($data['website_url']) ? sanitizeInput($data['website_url']) : ''
];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $check = $db->prepare("SELECT id, name FROM officials WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(false, null, "Official not found.", 404);
    }
    
    $stmt = $db->prepare("UPDATE officials SET name = ?, role = ?, designation = ?, bio = ?, sort_order = ?, status = ? {$photoUpdate} WHERE id = ?");
    $stmt->execute($params);
    
    // Save social links
    $key = 'official_' . $id . '_socials';
    $json = json_encode($socials);
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($stmt->fetchColumn() !== false) {
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$json, $key]);
    } else {
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $json]);
    }
    
    logAdminAction($db, $admin['id'], 'OFFICIAL_UPDATED', "Updated official {$name}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Official updated successfully."]);
} catch (Exception $e) {
    error_log("Official Update Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update official.", 500);
}
