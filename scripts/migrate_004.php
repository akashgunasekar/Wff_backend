<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    $sql = file_get_contents(__DIR__ . '/../database/migrations/004_admin_audit.sql');
    $db->exec($sql);
    echo "Migration 004 applied successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
