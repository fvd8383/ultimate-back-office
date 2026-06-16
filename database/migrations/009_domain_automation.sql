CREATE TABLE domain_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    requested_domain VARCHAR(255) NOT NULL,
    domain_status VARCHAR(50) NOT NULL DEFAULT 'requested',
    registrar VARCHAR(100) NULL,
    annual_cost DECIMAL(10,2) NULL,
    purchase_date DATE NULL,
    expiration_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain_requests_business_domain (business_id, requested_domain),
    INDEX idx_domain_requests_business (business_id),
    INDEX idx_domain_requests_status (domain_status),
    INDEX idx_domain_requests_expiration (expiration_date),
    CONSTRAINT fk_domain_requests_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE domain_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    domain_request_id BIGINT UNSIGNED NULL,
    domain_name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    assigned_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain_assignments_business (business_id),
    INDEX idx_domain_assignments_request (domain_request_id),
    INDEX idx_domain_assignments_status (status),
    CONSTRAINT fk_domain_assignments_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_domain_assignments_request FOREIGN KEY (domain_request_id) REFERENCES domain_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    domain_assignment_id BIGINT UNSIGNED NULL,
    domain_name VARCHAR(255) NOT NULL,
    publish_status VARCHAR(50) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_domains_website (website_id),
    INDEX idx_website_domains_business (business_id),
    INDEX idx_website_domains_assignment (domain_assignment_id),
    INDEX idx_website_domains_publish_status (publish_status),
    CONSTRAINT fk_website_domains_website FOREIGN KEY (website_id) REFERENCES `247sp_generated_websites` (id) ON DELETE CASCADE,
    CONSTRAINT fk_website_domains_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_website_domains_assignment FOREIGN KEY (domain_assignment_id) REFERENCES domain_assignments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO domain_requests (business_id, requested_domain, domain_status, created_at, updated_at)
SELECT ds.business_id,
       ds.domain_name,
       CASE
           WHEN ds.status = 'registered' THEN 'active'
           WHEN ds.status = 'pending' THEN 'requested'
           ELSE ds.status
       END,
       ds.created_at,
       ds.updated_at
FROM `247sp_domain_selections` ds
WHERE NOT EXISTS (
    SELECT 1
    FROM domain_requests dr
    WHERE dr.business_id = ds.business_id
      AND dr.requested_domain = ds.domain_name
);

INSERT INTO domain_assignments (business_id, domain_request_id, domain_name, status, assigned_at, created_at, updated_at)
SELECT dr.business_id,
       dr.id,
       dr.requested_domain,
       dr.domain_status,
       NOW(),
       NOW(),
       NOW()
FROM domain_requests dr
WHERE dr.domain_status IN ('active', 'transferred')
  AND NOT EXISTS (
      SELECT 1
      FROM domain_assignments da
      WHERE da.business_id = dr.business_id
  );

INSERT INTO website_domains (website_id, business_id, domain_assignment_id, domain_name, publish_status, created_at, updated_at)
SELECT gw.id,
       da.business_id,
       da.id,
       da.domain_name,
       'ready',
       NOW(),
       NOW()
FROM domain_assignments da
INNER JOIN `247sp_generated_websites` gw ON gw.business_id = da.business_id
WHERE da.status IN ('active', 'transferred')
  AND NOT EXISTS (
      SELECT 1
      FROM website_domains wd
      WHERE wd.website_id = gw.id
  );
