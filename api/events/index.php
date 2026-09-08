<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $status = isset($_GET['status']) ? $_GET['status'] : null;

    $query = "SELECT * FROM events";
    $params = [];

    if ($status) {
        $statusMapToDb = ['open' => 'ongoing', 'upcoming' => 'upcoming', 'closed' => 'completed'];
        $dbStatus = isset($statusMapToDb[$status]) ? $statusMapToDb[$status] : $status;
        $query .= " WHERE status = :status";
        $params[':status'] = $dbStatus;
    }
    
    $query .= " ORDER BY event_date ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    foreach ($events as &$event) {
        if ($event['status'] === 'ongoing') $event['status'] = 'open';
        else if ($event['status'] === 'completed' || $event['status'] === 'cancelled') $event['status'] = 'closed';
        else if ($event['status'] === 'draft') $event['status'] = 'upcoming';
        else if ($event['status'] !== 'upcoming') $event['status'] = 'upcoming'; // Fallback
    }

    // Fetch categories for all events to avoid N+1 query problem, or just loop if there are few events.
    if (count($events) > 0) {
        $eventIds = array_column($events, 'id');
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        
        $catQuery = "SELECT * FROM event_categories WHERE event_id IN ($placeholders) ORDER BY id ASC";
        $catStmt = $db->prepare($catQuery);
        $catStmt->execute($eventIds);
        $categories = $catStmt->fetchAll();
        
        // Group categories by event_id
        $groupedCats = [];
        foreach ($categories as $cat) {
            // Decode HTML entities stored in DB (from htmlspecialchars on input)
            if (isset($cat['name'])) {
                $cat['name'] = html_entity_decode($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $groupedCats[$cat['event_id']][] = $cat;
        }
        
        // Fetch cash settings for events
        $settingQuery = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'event_%_cash_enabled'";
        $settingStmt = $db->query($settingQuery);
        $settingsRaw = $settingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($settingsRaw as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Attach categories and settings to events
        foreach ($events as &$event) {
            $event['categories'] = $groupedCats[$event['id']] ?? [];
            $key = "event_{$event['id']}_cash_enabled";
            $event['cash_enabled'] = isset($settings[$key]) ? (bool)($settings[$key] === '1') : false;
        }
    }

    sendResponse(true, $events);
} catch (Exception $e) {
    error_log("Events API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch events.", 500);
}
