<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Only set if not already present in the environment
        if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            if ($value !== '') {
                putenv("$name=$value");
            }
        }
    }
}

// Fallback chain: $_ENV -> getenv -> default
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'wff_tn');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');
$dbPass = $_ENV['DB_PASS'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : null);
if ($dbPass === null) {
    $dbPass = $_ENV['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
}
define('DB_PASS', $dbPass);
