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

if (empty($data['event_id']) || empty($data['pricing']) || !is_array($data['pricing'])) {
    sendResponse(false, null, "Event ID and pricing array are required.", 422);
}

$event_id = (int)$data['event_id'];
$pricing = $data['pricing']; // Format: [{ id: 1, entry_fee: '2000', availability: 'open' }]

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("UPDATE event_categories SET entry_fee = ?, availability = ? WHERE id = ? AND event_id = ?");
    
    $updated = 0;
    foreach ($pricing as $p) {
        if (empty($p['id'])) continue;
        
        $fee = isset($p['entry_fee']) && trim($p['entry_fee']) !== '' ? $p['entry_fee'] : null;
        $avail = in_array($p['availability'] ?? '', ['open', 'closed', 'waitlist']) ? $p['availability'] : 'open';
        
        $stmt->execute([$fee, $avail, (int)$p['id'], $event_id]);
        $updated += $stmt->rowCount();
    }
    
    logAdminAction($db, $admin['id'], 'EVENT_CATEGORIES_PRICING_UPDATED', "Bulk updated pricing for Event {$event_id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Pricing updated successfully."]);
} catch (Exception $e) {
    error_log("Update Pricing Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to update pricing.", 500);
}
