SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'request_type'
        ),
        'ALTER TABLE domain_requests ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT ''purchase'' AFTER requested_domain',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'registrar_domain_id'
        ),
        'ALTER TABLE domain_requests ADD COLUMN registrar_domain_id VARCHAR(100) NULL AFTER registrar',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'registrar_order_id'
        ),
        'ALTER TABLE domain_requests ADD COLUMN registrar_order_id VARCHAR(100) NULL AFTER registrar_domain_id',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'registrar_transaction_id'
        ),
        'ALTER TABLE domain_requests ADD COLUMN registrar_transaction_id VARCHAR(100) NULL AFTER registrar_order_id',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'registrar_response_json'
        ),
        'ALTER TABLE domain_requests ADD COLUMN registrar_response_json MEDIUMTEXT NULL AFTER registrar_transaction_id',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'dns_status'
        ),
        'ALTER TABLE domain_requests ADD COLUMN dns_status VARCHAR(50) NOT NULL DEFAULT ''not_started'' AFTER expiration_date',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'dns_verified_at'
        ),
        'ALTER TABLE domain_requests ADD COLUMN dns_verified_at DATETIME NULL AFTER dns_status',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'ssl_status'
        ),
        'ALTER TABLE domain_requests ADD COLUMN ssl_status VARCHAR(50) NOT NULL DEFAULT ''pending'' AFTER dns_verified_at',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'ssl_updated_at'
        ),
        'ALTER TABLE domain_requests ADD COLUMN ssl_updated_at DATETIME NULL AFTER ssl_status',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'next_action'
        ),
        'ALTER TABLE domain_requests ADD COLUMN next_action VARCHAR(255) NULL AFTER ssl_updated_at',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'last_error'
        ),
        'ALTER TABLE domain_requests ADD COLUMN last_error TEXT NULL AFTER next_action',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND column_name = 'last_checked_at'
        ),
        'ALTER TABLE domain_requests ADD COLUMN last_checked_at DATETIME NULL AFTER last_error',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND index_name = 'idx_domain_requests_request_type'
        ),
        'ALTER TABLE domain_requests ADD INDEX idx_domain_requests_request_type (request_type)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND index_name = 'idx_domain_requests_dns_status'
        ),
        'ALTER TABLE domain_requests ADD INDEX idx_domain_requests_dns_status (dns_status)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_requests'
              AND index_name = 'idx_domain_requests_ssl_status'
        ),
        'ALTER TABLE domain_requests ADD INDEX idx_domain_requests_ssl_status (ssl_status)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'registrar'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN registrar VARCHAR(100) NULL AFTER domain_name',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'registrar_domain_id'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN registrar_domain_id VARCHAR(100) NULL AFTER registrar',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'ownership_type'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN ownership_type VARCHAR(50) NOT NULL DEFAULT ''fdv_owned'' AFTER registrar_domain_id',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'auto_renew'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 1 AFTER ownership_type',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'expiration_date'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN expiration_date DATE NULL AFTER auto_renew',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND column_name = 'ssl_status'
        ),
        'ALTER TABLE domain_assignments ADD COLUMN ssl_status VARCHAR(50) NOT NULL DEFAULT ''pending'' AFTER expiration_date',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND index_name = 'idx_domain_assignments_registrar'
        ),
        'ALTER TABLE domain_assignments ADD INDEX idx_domain_assignments_registrar (registrar)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND index_name = 'idx_domain_assignments_ownership'
        ),
        'ALTER TABLE domain_assignments ADD INDEX idx_domain_assignments_ownership (ownership_type)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_assignments'
              AND index_name = 'idx_domain_assignments_ssl_status'
        ),
        'ALTER TABLE domain_assignments ADD INDEX idx_domain_assignments_ssl_status (ssl_status)',
        'SELECT 1'
    )
);
PREPARE domain_repair_stmt FROM @sql;
EXECUTE domain_repair_stmt;
DEALLOCATE PREPARE domain_repair_stmt;

CREATE TABLE IF NOT EXISTS domain_dns_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    domain_request_id BIGINT UNSIGNED NULL,
    domain_assignment_id BIGINT UNSIGNED NULL,
    domain_name VARCHAR(255) NOT NULL,
    record_type VARCHAR(20) NOT NULL,
    host VARCHAR(255) NOT NULL,
    value VARCHAR(500) NOT NULL,
    priority INT NULL,
    ttl INT NOT NULL DEFAULT 1800,
    provider VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'planned',
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain_dns_record (domain_name, record_type, host, value),
    INDEX idx_domain_dns_records_business (business_id),
    INDEX idx_domain_dns_records_request (domain_request_id),
    INDEX idx_domain_dns_records_assignment (domain_assignment_id),
    INDEX idx_domain_dns_records_status (status),
    CONSTRAINT fk_domain_dns_records_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_domain_dns_records_request FOREIGN KEY (domain_request_id) REFERENCES domain_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_domain_dns_records_assignment FOREIGN KEY (domain_assignment_id) REFERENCES domain_assignments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domain_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    domain_request_id BIGINT UNSIGNED NULL,
    domain_assignment_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    registrar VARCHAR(100) NULL,
    event_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'recorded',
    message VARCHAR(500) NULL,
    request_json MEDIUMTEXT NULL,
    response_json MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_domain_events_business (business_id),
    INDEX idx_domain_events_request (domain_request_id),
    INDEX idx_domain_events_assignment (domain_assignment_id),
    INDEX idx_domain_events_type (event_type),
    INDEX idx_domain_events_status (status),
    CONSTRAINT fk_domain_events_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_domain_events_request FOREIGN KEY (domain_request_id) REFERENCES domain_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_domain_events_assignment FOREIGN KEY (domain_assignment_id) REFERENCES domain_assignments (id) ON DELETE SET NULL,
    CONSTRAINT fk_domain_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE domain_requests
SET request_type = 'purchase'
WHERE request_type IS NULL
   OR request_type = '';

UPDATE domain_requests dr
LEFT JOIN `247sp_domain_selections` ds ON ds.business_id = dr.business_id
    AND ds.domain_name = dr.requested_domain
SET dr.request_type = CASE
        WHEN ds.selection_type = 'existing' THEN 'existing'
        ELSE dr.request_type
    END,
    dr.dns_status = CASE
        WHEN dr.domain_status IN ('active', 'transferred') THEN 'pending_verification'
        ELSE dr.dns_status
    END,
    dr.next_action = CASE
        WHEN dr.next_action IS NOT NULL AND dr.next_action <> '' THEN dr.next_action
        WHEN ds.selection_type = 'existing' THEN 'Connect DNS so the website can go live.'
        WHEN dr.domain_status = 'requested' THEN 'Domain request received. Availability and purchase are next.'
        ELSE dr.next_action
    END
WHERE dr.request_type = 'purchase'
   OR ds.selection_type = 'existing'
   OR dr.next_action IS NULL
   OR dr.next_action = '';
