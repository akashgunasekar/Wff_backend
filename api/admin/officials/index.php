<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $db = (new Database())->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM officials ORDER BY sort_order ASC, id DESC");
    $stmt->execute();
    $officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch social links from site_settings
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'official_%_socials'");
    $stmt->execute();
    $socialSettings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $socialSettings[$row['setting_key']] = json_decode($row['setting_value'], true);
    }
    
    foreach ($officials as &$official) {
        $key = 'official_' . $official['id'] . '_socials';
        if (isset($socialSettings[$key])) {
            $official['instagram_url'] = $socialSettings[$key]['instagram_url'] ?? '';
            $official['facebook_url'] = $socialSettings[$key]['facebook_url'] ?? '';
            $official['youtube_url'] = $socialSettings[$key]['youtube_url'] ?? '';
            $official['website_url'] = $socialSettings[$key]['website_url'] ?? '';
        } else {
            $official['instagram_url'] = '';
            $official['facebook_url'] = '';
            $official['youtube_url'] = '';
            $official['website_url'] = '';
        }
    }
    
    sendResponse(true, $officials);
} catch (Exception $e) {
    error_log("Admin Officials List Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to fetch officials.", 500);
}
