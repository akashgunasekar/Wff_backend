<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT a.id, a.title, a.event_id, a.date, a.description, a.status, a.created_at, a.updated_at,
              COALESCE(NULLIF(a.cover_image, ''), (SELECT image FROM gallery_images i WHERE i.album_id = a.id ORDER BY i.sort_order ASC LIMIT 1)) as cover_image,
              e.event_name as event_title, e.event_date as event_date, e.venue as location 
              FROM gallery_albums a 
              LEFT JOIN events e ON a.event_id = e.id 
              WHERE a.status = 1 
              ORDER BY COALESCE(e.event_date, a.date) DESC, a.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($albums as &$a) {
        if (!empty($a['event_id'])) {
            $a['title'] = $a['event_title'] ?? $a['title'];
            $a['date'] = $a['event_date'] ?? $a['date'];
            $a['location'] = $a['location'] ?? ''; // location comes from e.venue_name
        } else {
            $a['location'] = '';
        }
        unset($a['event_title'], $a['event_date']);
    }

    sendResponse(true, $albums);
} catch (Exception $e) {
    error_log("Gallery API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch gallery albums.", 500);
}
