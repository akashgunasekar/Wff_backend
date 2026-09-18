<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';

// Setup / Reset Admin Password Endpoint
// Pass email, new_password, and a secret key or setup request
$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$email = $data['email'] ?? 'admin@wfftn.com';
$password = $data['password'] ?? 'Admin@2k26';

if (empty($email) || empty($password)) {
    sendResponse(false, null, "Email and password are required.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin) {
        $update = $db->prepare("UPDATE admins SET password_hash = ?, is_active = 1 WHERE id = ?");
        $update->execute([$hash, $admin['id']]);
        sendResponse(true, ["email" => $email, "password_set" => $password], "Admin password updated successfully.");
    } else {
        $insert = $db->prepare("INSERT INTO admins (name, email, password_hash, role, is_active) VALUES ('Admin', ?, ?, 'admin', 1)");
        $insert->execute([$email, $hash]);
        sendResponse(true, ["email" => $email, "password_set" => $password], "Admin created and password set successfully.");
    }
} catch (Exception $e) {
    sendResponse(false, null, $e->getMessage(), 500);
}
