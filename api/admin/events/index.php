<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

requireAdmin();

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_categories WHERE event_id = e.id) as category_count,
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registration_count
              FROM events e
              ORDER BY e.event_date DESC";
              
    $stmt = $db->query($query);
    $events = $stmt->fetchAll();

    foreach ($events as &$event) {
        if ($event['status'] === 'ongoing') $event['status'] = 'open';
        else if ($event['status'] === 'completed' || $event['status'] === 'cancelled') $event['status'] = 'closed';
        else if ($event['status'] === 'draft') $event['status'] = 'upcoming';
        else if ($event['status'] !== 'upcoming') $event['status'] = 'upcoming'; // Fallback
    }

    sendResponse(true, $events);
} catch (Exception $e) {
    error_log("Admin Events API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch events.", 500);
}
