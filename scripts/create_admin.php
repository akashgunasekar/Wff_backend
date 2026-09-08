<?php
/**
 * CLI Script to create the initial administrator.
 * Run from terminal: php backend/scripts/create_admin.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "WFF Tamil Nadu - Admin Setup\n";
echo "============================\n\n";

$name = readline("Enter Admin Name: ");
if (empty(trim($name))) die("Name is required.\n");

$email = readline("Enter Admin Email: ");
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) die("Invalid email format.\n");

// Hidden password input (unix/mac only)
echo "Enter Password: ";
system('stty -echo');
$password = trim(fgets(STDIN));
system('stty echo');
echo "\n";

if (strlen($password) < 8) die("Password must be at least 8 characters.\n");

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die("Error: Admin with this email already exists.\n");
    }
    
    // Insert admin
    $insert = $db->prepare("INSERT INTO admins (name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
    $insert->execute([$name, $email, $hash]);
    
    echo "Success: Administrator account created securely.\n";
    
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
