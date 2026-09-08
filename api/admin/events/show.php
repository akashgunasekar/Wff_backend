<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

if (empty($_GET['id'])) {
    sendResponse(false, null, "Event ID is required.", 400);
}

$event_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        sendResponse(false, null, "Event not found.", 404);
    }
    if ($event) {
        if ($event['status'] === 'ongoing') $event['status'] = 'open';
        else if ($event['status'] === 'completed' || $event['status'] === 'cancelled') $event['status'] = 'closed';
        else if ($event['status'] === 'draft') $event['status'] = 'upcoming';
        else if ($event['status'] !== 'upcoming') $event['status'] = 'upcoming'; // Fallback
    }

    $stmt = $db->prepare("SELECT * FROM event_categories WHERE event_id = ? ORDER BY id ASC");
    $stmt->execute([$event_id]);
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
                // Include inactive officials in admin view if they are attached
                $stmt = $db->prepare("SELECT id, name, photo, designation FROM officials WHERE id IN ($placeholders)");
                $stmt->execute($officialIds);
                $masterOfficials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $masterDict = [];
                foreach ($masterOfficials as $mo) {
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
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event['registration_count'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments p JOIN registrations r ON p.registration_id = r.id WHERE r.event_id = ? AND p.status = 'captured'");
    $stmt->execute([$event_id]);
    $event['payment_count'] = $stmt->fetchColumn();

    // Fetch Tan Spray Price
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["event_{$event_id}_tan_spray_price"]);
    $tsPrice = $stmt->fetchColumn();
    $event['tan_spray_price'] = $tsPrice !== false ? $tsPrice : '1000';
    
    // Fetch Cash Enabled
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["event_{$event_id}_cash_enabled"]);
    $cashEnabled = $stmt->fetchColumn();
    $event['cash_enabled'] = $cashEnabled === '1';

    // Fetch Content Meta
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(["event_{$event_id}_meta"]);
    $metaJson = $stmt->fetchColumn();
    $event['content_meta'] = $metaJson ? json_decode($metaJson, true) : [
        'about_title' => 'About This Event',
        'about_content' => '',
        'why_title' => 'Why This Event Matters',
        'why_intro' => '',
        'why_highlights' => [],
        'winner_slides' => [],
        'faqs' => [],
        'terms_content' => ''
    ];

    sendResponse(true, $event);
} catch (Exception $e) {
    error_log("Admin Event Show Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch event.", 500);
}
