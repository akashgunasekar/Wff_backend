CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    registration_id INT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL
);
