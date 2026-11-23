<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $dateCase = "CASE 
                     WHEN h.date IS NOT NULL AND TRIM(h.date) != '' THEN
                         h.date
                     WHEN e.id IS NOT NULL AND e.date_type = 'multi' AND e.event_end_date IS NOT NULL THEN
                         CONCAT(DATE_FORMAT(e.event_date, '%d %b'), ' – ', DATE_FORMAT(e.event_end_date, '%d %b %Y'))
                     WHEN e.id IS NOT NULL AND e.event_date IS NOT NULL THEN
                         DATE_FORMAT(e.event_date, '%d %M %Y')
                     ELSE
                         h.date
                 END";

    $query = "SELECT h.id, h.title, h.subtitle, 
                     COALESCE(NULLIF(TRIM(h.image), ''), e.banner_image) AS image, 
                     {$dateCase} AS date, 
                     COALESCE(NULLIF(TRIM(h.location), ''), e.venue) AS location, 
                     h.event_id, h.sort_order, h.status,
                     e.slug, e.date_type, e.event_date, e.event_end_date,
                     CASE WHEN e.status = 'ongoing' THEN 'open' ELSE e.status END AS event_status
              FROM hero_slides h 
              LEFT JOIN events e ON h.event_id = e.id 
              WHERE h.status = 1 
              ORDER BY h.sort_order ASC";
    
    // Attempt to query with display_fee column
    try {
        $queryWithFee = "SELECT h.id, h.title, h.subtitle, 
                         COALESCE(NULLIF(TRIM(h.image), ''), e.banner_image) AS image, 
                         h.display_fee,
                         {$dateCase} AS date, 
                         COALESCE(NULLIF(TRIM(h.location), ''), e.venue) AS location, 
                         h.event_id, h.sort_order, h.status,
                         e.slug, e.date_type, e.event_date, e.event_end_date,
                         CASE WHEN e.status = 'ongoing' THEN 'open' ELSE e.status END AS event_status
                  FROM hero_slides h 
                  LEFT JOIN events e ON h.event_id = e.id 
                  WHERE h.status = 1 
                  ORDER BY h.sort_order ASC";
        $stmt = $db->prepare($queryWithFee);
        $stmt->execute();
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch from site_settings fallback
        foreach ($slides as &$slide) {
            $stmtSettings = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $stmtSettings->execute(["hero_fee_{$slide['id']}"]);
            $slide['display_fee'] = $stmtSettings->fetchColumn() ?: null;
        }
    }
    
    // Process price display
    foreach ($slides as &$slide) {
        $slide['price'] = null;
        if (!empty($slide['display_fee'])) {
            $slide['price'] = "ENTRY FEE " . (is_numeric(preg_replace('/[^0-9.]/', '', $slide['display_fee'])) ? "₹" . number_format((float)preg_replace('/[^0-9.]/', '', $slide['display_fee'])) : $slide['display_fee']);
        }
    }

    sendResponse(true, $slides);
} catch (Exception $e) {
    error_log("Hero API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch hero slides.", 500);
}

