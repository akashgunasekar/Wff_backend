<?php
// backend/config/auth.php

$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Configure secure session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns the currently authenticated admin data if session exists
 */
function getAuthenticatedAdmin() {
    if (isset($_SESSION['admin_id'])) {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return [
            'id' => $_SESSION['admin_id'],
            'name' => $_SESSION['admin_name'],
            'email' => $_SESSION['admin_email'],
            'role' => $_SESSION['admin_role'],
            'csrf_token' => $_SESSION['csrf_token']
        ];
    }
    return null;
}

/**
 * Enforces admin authentication and exits with 401 if unauthorized.
 * Verifies CSRF token for state-changing requests.
 */
function requireAdmin() {
    $admin = getAuthenticatedAdmin();
    
    if (!$admin) {
        require_once __DIR__ . '/../helpers/response.php';
        sendResponse(false, null, "Unauthorized. Please log in.", 401);
        exit;
    }
    
    // CSRF Protection
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($csrfHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $csrfHeader = $headers['X-CSRF-Token'] ?? ($headers['X-Csrf-Token'] ?? '');
        }
        
        if (empty($csrfHeader) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
            require_once __DIR__ . '/../helpers/response.php';
            sendResponse(false, null, "CSRF token validation failed.", 403);
            exit;
        }
    }
    
    return $admin;
}
