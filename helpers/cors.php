<?php
$allowedOriginsString = getenv('CORS_ALLOWED_ORIGINS');
if (!$allowedOriginsString) {
    $allowedOriginsString = 'http://localhost:3000,http://127.0.0.1:3000';
}
$allowedOrigins = array_map('trim', explode(',', $allowedOriginsString));

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$isAllowed = in_array($origin, $allowedOrigins);

if ($isAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
