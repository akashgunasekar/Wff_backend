<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../config/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $admin = getAuthenticatedAdmin();

    // Never return registration metadata through the settings endpoint
    $query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key NOT LIKE 'registration_%' AND setting_key NOT LIKE '%_meta'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $raw = $stmt->fetchAll();
    $settings = [];
    foreach($raw as $row) {
        $k = $row['setting_key'];
        $val = $row['setting_value'];

        // If public visitor (not logged-in admin), never reveal credentials
        if (!$admin) {
            if (in_array($k, ['smtp_pass', 'smtp_user', 'razorpay_key_secret'])) {
                continue;
            }
        } else {
            // Mask password for admin display
            if ($k === 'smtp_pass' && !empty($val)) {
                $val = '••••••••';
            }
        }

        $settings[$k] = $val;
    }

    sendResponse(true, $settings);
} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch settings.", 500);
}
