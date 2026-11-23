<?php
/**
 * Local Development Server Router for PHP built-in server
 * Usage: php -S 127.0.0.1:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// 1. Serve existing static file (uploads, assets, etc.)
if ($uri !== '/' && is_file($file)) {
    return false;
}

// 2. Serve directory index.php
if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

// 3. Serve file with .php extension if requested without extension
if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}

// Fallback: 404
http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found.'
]);
return true;
