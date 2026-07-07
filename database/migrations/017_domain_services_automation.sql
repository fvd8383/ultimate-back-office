ALTER TABLE domain_requests
    ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT 'purchase' AFTER requested_domain,
    ADD COLUMN registrar_domain_id VARCHAR(100) NULL AFTER registrar,
    ADD COLUMN registrar_order_id VARCHAR(100) NULL AFTER registrar_domain_id,
    ADD COLUMN registrar_transaction_id VARCHAR(100) NULL AFTER registrar_order_id,
    ADD COLUMN registrar_response_json MEDIUMTEXT NULL AFTER registrar_transaction_id,
    ADD COLUMN dns_status VARCHAR(50) NOT NULL DEFAULT 'not_started' AFTER expiration_date,
    ADD COLUMN dns_verified_at DATETIME NULL AFTER dns_status,
    ADD COLUMN ssl_status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER dns_verified_at,
    ADD COLUMN ssl_updated_at DATETIME NULL AFTER ssl_status,
    ADD COLUMN next_action VARCHAR(255) NULL AFTER ssl_updated_at,
    ADD COLUMN last_error TEXT NULL AFTER next_action,
    ADD COLUMN last_checked_at DATETIME NULL AFTER last_error,
    ADD INDEX idx_domain_requests_request_type (request_type),
    ADD INDEX idx_domain_requests_dns_status (dns_status),
    ADD INDEX idx_domain_requests_ssl_status (ssl_status);

ALTER TABLE domain_assignments
    ADD COLUMN registrar VARCHAR(100) NULL AFTER domain_name,
    ADD COLUMN registrar_domain_id VARCHAR(100) NULL AFTER registrar,
    ADD COLUMN ownership_type VARCHAR(50) NOT NULL DEFAULT 'fdv_owned' AFTER registrar_domain_id,
    ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 1 AFTER ownership_type,
    ADD COLUMN expiration_date DATE NULL AFTER auto_renew,
    ADD COLUMN ssl_status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER expiration_date,
    ADD INDEX idx_domain_assignments_registrar (registrar),
    ADD INDEX idx_domain_assignments_ownership (ownership_type),
    ADD INDEX idx_domain_assignments_ssl_status (ssl_status);

CREATE TABLE domain_dns_records (
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

CREATE TABLE domain_events (
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

UPDATE domain_requests dr
LEFT JOIN `247sp_domain_selections` ds ON ds.business_id = dr.business_id
    AND ds.domain_name = dr.requested_domain
SET dr.request_type = CASE
        WHEN ds.selection_type = 'existing' THEN 'existing'
        ELSE 'purchase'
    END,
    dr.dns_status = CASE
        WHEN dr.domain_status IN ('active', 'transferred') THEN 'pending_verification'
        ELSE dr.dns_status
    END,
    dr.next_action = CASE
        WHEN ds.selection_type = 'existing' THEN 'Connect DNS so the website can go live.'
        WHEN dr.domain_status = 'requested' THEN 'Domain request received. Availability and purchase are next.'
        ELSE dr.next_action
    END
WHERE dr.request_type = 'purchase';
