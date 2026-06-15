CREATE TABLE `247sp_onboarding` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    contact_name VARCHAR(255) NULL,
    setup_status VARCHAR(50) NOT NULL DEFAULT 'not_started',
    current_step VARCHAR(50) NOT NULL DEFAULT 'business_information',
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_onboarding_business (business_id),
    INDEX idx_247sp_onboarding_status (setup_status),
    CONSTRAINT fk_247sp_onboarding_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_website_configurations` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    primary_category_id BIGINT UNSIGNED NULL,
    service_area_address VARCHAR(255) NULL,
    service_area_city VARCHAR(100) NULL,
    service_area_state VARCHAR(100) NULL,
    service_area_postal_code VARCHAR(30) NULL,
    service_area_business TINYINT(1) NOT NULL DEFAULT 0,
    website_status VARCHAR(50) NOT NULL DEFAULT 'not_started',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_website_configurations_business (business_id),
    INDEX idx_247sp_website_configurations_onboarding (onboarding_id),
    INDEX idx_247sp_website_configurations_category (primary_category_id),
    CONSTRAINT fk_247sp_website_configurations_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_website_configurations_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_website_configurations_category FOREIGN KEY (primary_category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_business_content` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    business_description TEXT NULL,
    about_company TEXT NULL,
    years_in_business INT UNSIGNED NULL,
    financing_available TINYINT(1) NOT NULL DEFAULT 0,
    special_offer VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_business_content_business (business_id),
    INDEX idx_247sp_business_content_onboarding (onboarding_id),
    CONSTRAINT fk_247sp_business_content_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_business_content_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_service_pages` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    service_number TINYINT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    short_description TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_service_pages_business_number (business_id, service_number),
    INDEX idx_247sp_service_pages_onboarding (onboarding_id),
    CONSTRAINT fk_247sp_service_pages_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_service_pages_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_domain_selections` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    selection_type VARCHAR(50) NOT NULL,
    domain_name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_domain_selections_business (business_id),
    INDEX idx_247sp_domain_selections_onboarding (onboarding_id),
    INDEX idx_247sp_domain_selections_status (status),
    CONSTRAINT fk_247sp_domain_selections_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_domain_selections_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_email_requests` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    primary_mailbox_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_email_requests_business (business_id),
    INDEX idx_247sp_email_requests_onboarding (onboarding_id),
    INDEX idx_247sp_email_requests_status (status),
    CONSTRAINT fk_247sp_email_requests_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_email_requests_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
