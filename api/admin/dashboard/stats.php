<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $db = (new Database())->getConnection();

    // Total events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'published'");
    $active_events = $stmt->fetchColumn();

    // Registrations stats
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'payment_pending' THEN 1 ELSE 0 END) as payment_pending,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
        FROM registrations
    ");
    $regStats = $stmt->fetch(PDO::FETCH_ASSOC);

    sendResponse(true, [
        'active_events' => (int)$active_events,
        'registrations' => [
            'total' => (int)$regStats['total'],
            'payment_pending' => (int)$regStats['payment_pending'],
            'paid' => (int)$regStats['paid'],
            'confirmed' => (int)$regStats['confirmed'],
        ]
    ]);

} catch (Exception $e) {
    error_log("Admin Dashboard Stats Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to load dashboard statistics.", 500);
}
