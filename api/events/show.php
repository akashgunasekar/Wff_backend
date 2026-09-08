<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

try {
    if (!isset($_GET['id']) && !isset($_GET['slug'])) {
        sendResponse(false, null, "Event ID or slug is required.", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM events WHERE ";
    $params = [];

    if (isset($_GET['id'])) {
        $query .= "id = :id";
        $params[':id'] = $_GET['id'];
    } else {
        $query .= "slug = :slug";
        $params[':slug'] = $_GET['slug'];
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $event = $stmt->fetch();

    if ($event) {
        if ($event['status'] === 'ongoing') $event['status'] = 'open';
        else if ($event['status'] === 'completed' || $event['status'] === 'cancelled') $event['status'] = 'closed';
        else if ($event['status'] === 'draft') $event['status'] = 'upcoming';
        else if ($event['status'] !== 'upcoming') $event['status'] = 'upcoming'; // Fallback
    }

    if (!$event) {
        sendResponse(false, null, "Event not found.", 404);
    }

    // Fetch categories
    $stmt = $db->prepare("SELECT * FROM event_categories WHERE event_id = ? AND availability != 'disabled' ORDER BY id ASC");
    $stmt->execute([$event['id']]);
    $categories = $stmt->fetchAll();
    
    // Polyfill extended category fields
    if (count($categories) > 0) {
        $catIds = array_column($categories, 'id');
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $keys = array_map(function($id) { return 'category_ext_' . $id; }, $catIds);
        
        $stmt_ext = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
        $stmt_ext->execute($keys);
        $extRows = $stmt_ext->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($categories as &$cat) {
            $key = 'category_ext_' . $cat['id'];
            $cat['short_description'] = '';
            $cat['structure_type'] = 'single';
            $cat['weight_divisions'] = [];
            $cat['height_divisions'] = [];
            $cat['display_order'] = 0;
            
            if (isset($extRows[$key])) {
                $extData = json_decode($extRows[$key], true);
                if (is_array($extData)) {
                    $cat['short_description'] = $extData['short_description'] ?? '';
                    $cat['structure_type'] = $extData['structure_type'] ?? 'single';
                    $cat['weight_divisions'] = $extData['weight_divisions'] ?? [];
                    $cat['height_divisions'] = $extData['height_divisions'] ?? [];
                    $cat['display_order'] = $extData['display_order'] ?? 0;
                }
            }
        }
        
        // Sort by display_order, then id
        usort($categories, function($a, $b) {
            if ($a['display_order'] == $b['display_order']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['display_order'] <=> $b['display_order'];
        });
    }
    $event['categories'] = $categories;

    // Fetch Event Officials from site_settings
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(['event_' . $event['id'] . '_officials']);
    $officialsJson = $stmt->fetchColumn();
    
    $event['officials'] = [];
    if ($officialsJson) {
        $eventOfficialsData = json_decode($officialsJson, true);
        if (is_array($eventOfficialsData) && count($eventOfficialsData) > 0) {
            $officialIds = array_column($eventOfficialsData, 'official_id');
            if (count($officialIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($officialIds), '?'));
                $stmt = $db->prepare("SELECT id, name, photo, designation, bio FROM officials WHERE id IN ($placeholders) AND status = 1");
                $stmt->execute($officialIds);
                $masterOfficials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch social settings
                $keys = array_map(function($id) { return 'official_' . $id . '_socials'; }, $officialIds);
                $placeholders2 = implode(',', array_fill(0, count($keys), '?'));
                $stmt2 = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders2)");
                $stmt2->execute($keys);
                $socialsRows = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $masterDict = [];
                foreach ($masterOfficials as $mo) {
                    $key = 'official_' . $mo['id'] . '_socials';
                    if (isset($socialsRows[$key])) {
                        $soc = json_decode($socialsRows[$key], true);
                        if (is_array($soc)) {
                            $mo['instagram_url'] = $soc['instagram_url'] ?? '';
                            $mo['facebook_url'] = $soc['facebook_url'] ?? '';
                            $mo['youtube_url'] = $soc['youtube_url'] ?? '';
                            $mo['website_url'] = $soc['website_url'] ?? '';
                        }
                    }
                    $masterDict[$mo['id']] = $mo;
                }
                
                foreach ($eventOfficialsData as $eo) {
                    if (isset($masterDict[$eo['official_id']])) {
                        $m = $masterDict[$eo['official_id']];
                        $event['officials'][] = [
                            'official_id' => $m['id'],
                            'name' => $m['name'],
                            'photo' => $m['photo'],
                            'designation' => $m['designation'],
                            'bio' => $m['bio'],
                            'instagram_url' => $m['instagram_url'] ?? '',
                            'facebook_url' => $m['facebook_url'] ?? '',
                            'youtube_url' => $m['youtube_url'] ?? '',
                            'website_url' => $m['website_url'] ?? '',
                            'role' => $eo['role'],
                            'display_order' => $eo['display_order']
                        ];
                    }
                }
                
                usort($event['officials'], function($a, $b) {
                    return $a['display_order'] <=> $b['display_order'];
                });
            }
        }
    }

    // Fetch Content Meta
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["event_{$event['id']}_meta"]);
    $metaJson = $stmt->fetchColumn();
    $event['content_meta'] = $metaJson ? json_decode($metaJson, true) : null;

    // Fetch Cash Enabled
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["event_{$event['id']}_cash_enabled"]);
    $cashEnabled = $stmt->fetchColumn();
    $event['cash_enabled'] = $cashEnabled === '1';

    sendResponse(true, $event);
} catch (Exception $e) {
    error_log("Event Show API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch event.", 500);
}
