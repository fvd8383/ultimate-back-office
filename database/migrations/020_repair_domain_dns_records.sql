CREATE TABLE IF NOT EXISTS domain_dns_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    domain_request_id BIGINT UNSIGNED NULL,
    domain_assignment_id BIGINT UNSIGNED NULL,
    domain_name VARCHAR(255) NOT NULL,
    record_type VARCHAR(20) NOT NULL,
    host VARCHAR(255) NOT NULL,
    value VARCHAR(500) NOT NULL,
    record_hash CHAR(64) GENERATED ALWAYS AS (
        SHA2(CONCAT_WS('|', COALESCE(LOWER(domain_name), ''), COALESCE(UPPER(record_type), ''), COALESCE(LOWER(host), ''), COALESCE(value, '')), 256)
    ) STORED,
    priority INT NULL,
    ttl INT NOT NULL DEFAULT 1800,
    provider VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'planned',
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain_dns_record_hash (record_hash),
    INDEX idx_domain_dns_records_business (business_id),
    INDEX idx_domain_dns_records_request (domain_request_id),
    INDEX idx_domain_dns_records_assignment (domain_assignment_id),
    INDEX idx_domain_dns_records_status (status),
    CONSTRAINT fk_domain_dns_records_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_domain_dns_records_request FOREIGN KEY (domain_request_id) REFERENCES domain_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_domain_dns_records_assignment FOREIGN KEY (domain_assignment_id) REFERENCES domain_assignments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'business_id'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN business_id BIGINT UNSIGNED NOT NULL AFTER id',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'domain_request_id'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN domain_request_id BIGINT UNSIGNED NULL AFTER business_id',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'domain_assignment_id'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN domain_assignment_id BIGINT UNSIGNED NULL AFTER domain_request_id',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'domain_name'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN domain_name VARCHAR(255) NOT NULL AFTER domain_assignment_id',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'record_type'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN record_type VARCHAR(20) NOT NULL AFTER domain_name',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'host'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN host VARCHAR(255) NOT NULL AFTER record_type',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'value'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN value VARCHAR(500) NOT NULL AFTER host',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'record_hash'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN record_hash CHAR(64) GENERATED ALWAYS AS (SHA2(CONCAT_WS(''|'', COALESCE(LOWER(domain_name), ''''), COALESCE(UPPER(record_type), ''''), COALESCE(LOWER(host), ''''), COALESCE(value, '''')), 256)) STORED AFTER value',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'priority'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN priority INT NULL AFTER record_hash',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'ttl'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN ttl INT NOT NULL DEFAULT 1800 AFTER priority',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'provider'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN provider VARCHAR(100) NULL AFTER ttl',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'status'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT ''planned'' AFTER provider',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'last_synced_at'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN last_synced_at DATETIME NULL AFTER status',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'created_at'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_synced_at',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND column_name = 'updated_at'
        ),
        'ALTER TABLE domain_dns_records ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND index_name = 'uq_domain_dns_record_hash'
        ),
        'ALTER TABLE domain_dns_records ADD UNIQUE KEY uq_domain_dns_record_hash (record_hash)',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND index_name = 'idx_domain_dns_records_business'
        ),
        'ALTER TABLE domain_dns_records ADD INDEX idx_domain_dns_records_business (business_id)',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND index_name = 'idx_domain_dns_records_request'
        ),
        'ALTER TABLE domain_dns_records ADD INDEX idx_domain_dns_records_request (domain_request_id)',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND index_name = 'idx_domain_dns_records_assignment'
        ),
        'ALTER TABLE domain_dns_records ADD INDEX idx_domain_dns_records_assignment (domain_assignment_id)',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

SET @sql = (
    SELECT IF(
        NOT EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'domain_dns_records'
              AND index_name = 'idx_domain_dns_records_status'
        ),
        'ALTER TABLE domain_dns_records ADD INDEX idx_domain_dns_records_status (status)',
        'SELECT 1'
    )
);
PREPARE domain_dns_repair_stmt FROM @sql;
EXECUTE domain_dns_repair_stmt;
DEALLOCATE PREPARE domain_dns_repair_stmt;

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
