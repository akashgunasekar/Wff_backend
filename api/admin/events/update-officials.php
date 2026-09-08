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

if (empty($data['event_id']) || !isset($data['officials']) || !is_array($data['officials'])) {
    sendResponse(false, null, "Event ID and officials array are required.", 422);
}

$event_id = (int)$data['event_id'];
$officials = $data['officials']; // Format: [{ official_id: 1, role: 'Head Judge', display_order: 0 }]

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check event exists
    $stmt = $db->prepare("SELECT id FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    if (!$stmt->fetch()) {
        sendResponse(false, null, "Event not found.", 404);
    }
    
    $cleanOfficials = [];
    foreach ($officials as $idx => $off) {
        if (!empty($off['official_id'])) {
            $cleanOfficials[] = [
                'official_id' => (int)$off['official_id'],
                'role' => sanitizeInput($off['role'] ?? 'Judge'),
                'display_order' => isset($off['display_order']) ? (int)$off['display_order'] : $idx
            ];
        }
    }
    
    $key = 'event_' . $event_id . '_officials';
    $json = json_encode($cleanOfficials);
    
    // Upsert site_settings
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($stmt->fetchColumn() !== false) {
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$json, $key]);
    } else {
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $json]);
    }
    
    logAdminAction($db, $admin['id'], 'EVENT_OFFICIALS_UPDATED', "Updated officials for Event {$event_id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Event officials updated successfully."]);
} catch (Exception $e) {
    error_log("Update Event Officials Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update event officials.", 500);
}
