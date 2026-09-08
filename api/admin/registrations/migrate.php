<?php
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = (new Database())->getConnection();
    $sql = file_get_contents(__DIR__ . '/../../../database/migrations/004_admin_audit.sql');
    $db->exec($sql);
    echo json_encode(['success' => true, 'message' => 'Migration 004 applied via HTTP.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
}
