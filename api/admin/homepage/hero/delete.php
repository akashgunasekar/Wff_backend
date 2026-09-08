<?php
require_once __DIR__ . '/../../../../helpers/cors.php';
require_once __DIR__ . '/../../../../helpers/response.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';
require_once __DIR__ . '/../../../../config/audit.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['id'])) {
    sendResponse(false, null, "ID required.", 422);
}

$id = (int)$data['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT image FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    $slide = $stmt->fetch();
    
    $stmt = $db->prepare("DELETE FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    
    // Cleanup physical file if it's a local upload
    if ($slide && !empty($slide['image']) && strpos($slide['image'], '/uploads/hero/') === 0) {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM hero_slides WHERE image = ?");
        $stmtCheck->execute([$slide['image']]);
        if ($stmtCheck->fetchColumn() == 0) {
            $physicalPath = realpath(__DIR__ . '/../../../../' . ltrim($slide['image'], '/'));
            if ($physicalPath && file_exists($physicalPath)) {
                unlink($physicalPath);
            }
        }
    }
    
    logAdminAction($db, $admin['id'], 'HOMEPAGE_HERO_DELETED', "Deleted hero slide ID: {$id}", null, null, $_SERVER['REMOTE_ADDR']);
    
    sendResponse(true, ["message" => "Hero slide deleted."]);
} catch (Exception $e) {
    sendResponse(false, null, "Failed to delete slide.", 500);
}
