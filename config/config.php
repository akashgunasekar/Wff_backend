<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'A fatal error occurred on the server.',
            'debug' => $error['message']
        ]);
    }
});

$envFile = file_exists(__DIR__ . '/../.env') ? (__DIR__ . '/../.env') : (__DIR__ . '/../env');
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
        if (strpos($trimmed, '=') !== false) {
            list($name, $value) = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $envVars[$name] = $value;
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            @putenv("$name=$value");
        }
    }
}

// Robust fallback: parsed file array -> $_ENV -> getenv -> default
define('DB_HOST', $envVars['DB_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', $envVars['DB_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
define('DB_NAME', $envVars['DB_NAME'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'u841181565_wff_tn');
$dbUser = $envVars['DB_USER'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'u841181565_wff';
if ($dbUser === 'wff') {
    $dbUser = 'u841181565_wff';
}
define('DB_USER', $dbUser);
define('DB_PASS', $envVars['DB_PASS'] ?? $envVars['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? $_ENV['DB_PASSWORD'] ?? (getenv('DB_PASS') ?: 'Wff@#2k26'));

define('RAZORPAY_KEY_ID', $envVars['RAZORPAY_KEY_ID'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_KEY_ID') ?: '');
define('RAZORPAY_KEY_SECRET', $envVars['RAZORPAY_KEY_SECRET'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_KEY_SECRET') ?: '');
define('RAZORPAY_WEBHOOK_SECRET', $envVars['RAZORPAY_WEBHOOK_SECRET'] ?? $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? getenv('RAZORPAY_WEBHOOK_SECRET') ?: '');

