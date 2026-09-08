<?php
// backend/config/audit.php

/**
 * Logs admin actions into the admin_audit_logs table.
 */
function logAdminAction($db, $admin_id, $action, $description = null, $previous_status = null, $new_status = null, $ip_address = null) {
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_logs (admin_id, action, previous_status, new_status, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admin_id, $action, $previous_status, $new_status, $ip_address]);
    } catch (PDOException $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}
