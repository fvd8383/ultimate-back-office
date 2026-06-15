ALTER TABLE businesses
    ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN is_test_account TINYINT(1) NOT NULL DEFAULT 0 AFTER is_suspended,
    ADD COLUMN internal_status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER is_test_account,
    ADD INDEX idx_businesses_internal_status (internal_status),
    ADD INDEX idx_businesses_is_suspended (is_suspended),
    ADD INDEX idx_businesses_is_test_account (is_test_account);

CREATE TABLE admin_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notes_business (business_id),
    INDEX idx_admin_notes_user (user_id),
    INDEX idx_admin_notes_admin_user (admin_user_id),
    CONSTRAINT fk_admin_notes_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_admin_notes_admin_user FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, scope, description, is_system_role, is_custom, business_id, created_at, updated_at)
SELECT 'Admin', 'internal', 'Internal administrator access.', 1, 0, NULL, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE name = 'Admin' AND scope = 'internal'
);
