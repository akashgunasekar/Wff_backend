<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendResponse(false, null, "Invalid JSON data.", 400);
    }

    if (empty($data['email']) || empty($data['password'])) {
        sendResponse(false, null, "Email and password are required.", 400);
    }

    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, is_active FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if (!$admin || !password_verify($password, $admin['password_hash']) || !(bool)$admin['is_active']) {
        sendResponse(false, null, "Invalid email or password.", 401);
        exit;
    }
    
    // Prevent Session Fixation
    session_regenerate_id(true);
    
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Update last login
    $update = $db->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?");
    $update->execute([$admin['id']]);
    
    sendResponse(true, [
        "id" => $admin['id'],
        "name" => $admin['name'],
        "email" => $admin['email'],
        "role" => $admin['role'],
        "csrf_token" => $_SESSION['csrf_token']
    ], "Logged in successfully.");

} catch (Exception $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    sendResponse(false, null, "Authentication service unavailable.", 500);
}
