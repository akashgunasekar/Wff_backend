<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, null, "Invalid JSON data.", 400);
}

if (empty($data['event_id']) || empty($data['master_ids']) || !is_array($data['master_ids'])) {
    sendResponse(false, null, "Event ID and Master Category IDs are required.", 422);
}

$event_id = (int)$data['event_id'];
$master_ids = $data['master_ids'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check event exists
    $stmt = $db->prepare("SELECT id FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    if (!$stmt->fetch()) {
        sendResponse(false, null, "Event not found.", 404);
    }
    
    // Check currently attached categories to avoid duplicating names
    $stmt = $db->prepare("SELECT name FROM event_categories WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $existingNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $existingNamesLower = array_map('strtolower', $existingNames);
    
    $added = 0;
    
    foreach ($master_ids as $m_id) {
        $key = 'master_category_' . $m_id;
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetchColumn();
        
        if ($row) {
            $master = json_decode($row, true);
            if (!$master || in_array(strtolower($master['name']), $existingNamesLower)) {
                continue; // Skip if already exists by name
            }
            
            // Insert into event_categories
            $stmt = $db->prepare("INSERT INTO event_categories (event_id, name, age_group, gender, entry_fee, availability) VALUES (?, ?, ?, ?, ?, ?)");
            // default fee empty string/null
            $stmt->execute([$event_id, $master['name'], $master['age_group'] ?? '', $master['gender'] ?? '', null, 'open']);
            $category_id = $db->lastInsertId();
            
            // Polyfill extended fields
            $ext_data = [
                'short_description' => $master['short_description'] ?? '',
                'structure_type' => $master['structure_type'] ?? 'single',
                'weight_divisions' => $master['weight_divisions'] ?? [],
                'height_divisions' => $master['height_divisions'] ?? [],
                'display_order' => $master['display_order'] ?? 0
            ];
            
            $catKey = 'category_ext_' . $category_id;
            $json = json_encode($ext_data);
            $stmt_ext = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt_ext->execute([$catKey, $json]);
            
            $added++;
        }
    }
    
    logAdminAction($db, $admin['id'], 'EVENT_CATEGORIES_ATTACHED', "Attached {$added} master categories to Event {$event_id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Successfully added {$added} categories.", "added" => $added]);
} catch (Exception $e) {
    error_log("Attach Master Categories Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to attach categories.", 500);
}
