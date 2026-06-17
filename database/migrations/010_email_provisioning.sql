CREATE TABLE mailbox_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    requested_email VARCHAR(255) NOT NULL,
    display_name VARCHAR(150) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'requested',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mailbox_requests_business_email (business_id, requested_email),
    INDEX idx_mailbox_requests_business (business_id),
    INDEX idx_mailbox_requests_status (status),
    CONSTRAINT fk_mailbox_requests_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mailbox_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    mailbox_request_id BIGINT UNSIGNED NULL,
    email_address VARCHAR(255) NOT NULL,
    display_name VARCHAR(150) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending_setup',
    mailbox_type VARCHAR(50) NOT NULL DEFAULT 'additional',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mailbox_assignments_business_email (business_id, email_address),
    INDEX idx_mailbox_assignments_business (business_id),
    INDEX idx_mailbox_assignments_request (mailbox_request_id),
    INDEX idx_mailbox_assignments_status (status),
    INDEX idx_mailbox_assignments_type (mailbox_type),
    CONSTRAINT fk_mailbox_assignments_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_mailbox_assignments_request FOREIGN KEY (mailbox_request_id) REFERENCES mailbox_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mailbox_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mailbox_assignment_id BIGINT UNSIGNED NULL,
    mailbox_request_id BIGINT UNSIGNED NULL,
    activity_type VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mailbox_activity_assignment (mailbox_assignment_id),
    INDEX idx_mailbox_activity_request (mailbox_request_id),
    INDEX idx_mailbox_activity_type (activity_type),
    CONSTRAINT fk_mailbox_activity_assignment FOREIGN KEY (mailbox_assignment_id) REFERENCES mailbox_assignments (id) ON DELETE SET NULL,
    CONSTRAINT fk_mailbox_activity_request FOREIGN KEY (mailbox_request_id) REFERENCES mailbox_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_mailbox_counts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    included_mailbox_count INT UNSIGNED NOT NULL DEFAULT 1,
    additional_mailbox_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_mailbox_counts_business (business_id),
    CONSTRAINT fk_business_mailbox_counts_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mailbox_requests (business_id, requested_email, display_name, status, created_at, updated_at)
SELECT er.business_id,
       CONCAT(LOWER(er.primary_mailbox_name), '@', LOWER(ds.domain_name)),
       er.primary_mailbox_name,
       CASE
           WHEN er.status = 'active' THEN 'active'
           WHEN er.status = 'cancelled' THEN 'cancelled'
           ELSE 'requested'
       END,
       er.created_at,
       er.updated_at
FROM `247sp_email_requests` er
INNER JOIN `247sp_domain_selections` ds ON ds.business_id = er.business_id
WHERE er.primary_mailbox_name <> ''
  AND ds.domain_name <> ''
  AND CHAR_LENGTH(CONCAT(LOWER(er.primary_mailbox_name), '@', LOWER(ds.domain_name))) <= 255
  AND NOT EXISTS (
      SELECT 1
      FROM mailbox_requests mr
      WHERE mr.business_id = er.business_id
        AND mr.requested_email = CONCAT(LOWER(er.primary_mailbox_name), '@', LOWER(ds.domain_name))
  );

INSERT INTO mailbox_assignments (business_id, mailbox_request_id, email_address, display_name, status, mailbox_type, created_at, updated_at)
SELECT mr.business_id,
       mr.id,
       mr.requested_email,
       mr.display_name,
       'active',
       'included',
       NOW(),
       NOW()
FROM mailbox_requests mr
WHERE mr.status = 'active'
  AND NOT EXISTS (
      SELECT 1
      FROM mailbox_assignments ma
      WHERE ma.business_id = mr.business_id
        AND ma.email_address = mr.requested_email
  );

INSERT INTO business_mailbox_counts (business_id, included_mailbox_count, additional_mailbox_count, created_at, updated_at)
SELECT DISTINCT b.id,
       1,
       0,
       NOW(),
       NOW()
FROM businesses b
INNER JOIN business_modules bm ON bm.business_id = b.id AND bm.status = 'active'
INNER JOIN modules m ON m.id = bm.module_id AND m.module_key = '247sp'
WHERE NOT EXISTS (
    SELECT 1
    FROM business_mailbox_counts bmc
    WHERE bmc.business_id = b.id
);
