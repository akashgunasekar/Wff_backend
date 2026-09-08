<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    if (!isset($_GET['id'])) {
        sendResponse(false, null, "Album ID is required.", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    $albumQuery = "SELECT a.id, a.title, a.event_id, a.date, a.description, a.status, a.created_at, a.updated_at,
                   COALESCE(NULLIF(a.cover_image, ''), (SELECT image FROM gallery_images i WHERE i.album_id = a.id ORDER BY i.sort_order ASC LIMIT 1)) as cover_image,
                   e.event_name as event_title, e.event_date as event_date, e.venue as location 
                   FROM gallery_albums a 
                   LEFT JOIN events e ON a.event_id = e.id 
                   WHERE a.id = :id AND a.status = 1";
    $albumStmt = $db->prepare($albumQuery);
    $albumStmt->execute([':id' => $_GET['id']]);
    $album = $albumStmt->fetch(PDO::FETCH_ASSOC);

    if ($album) {
        if (!empty($album['event_id'])) {
            $album['title'] = $album['event_title'] ?? $album['title'];
            $album['date'] = $album['event_date'] ?? $album['date'];
            $album['location'] = $album['location'] ?? ''; // location comes from e.venue_name
        } else {
            $album['location'] = '';
        }
        unset($album['event_title'], $album['event_date']);
    }

    if (!$album) {
        sendResponse(false, null, "Album not found.", 404);
    }

    $imgQuery = "SELECT * FROM gallery_images WHERE album_id = :album_id ORDER BY sort_order ASC";
    $imgStmt = $db->prepare($imgQuery);
    $imgStmt->execute([':album_id' => $album['id']]);
    $album['images'] = $imgStmt->fetchAll();

    sendResponse(true, $album);
} catch (Exception $e) {
    error_log("Gallery Show API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch gallery images.", 500);
}
