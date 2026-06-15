CREATE TABLE `247sp_templates` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_template_assignments` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    assigned_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_template_assignments_business (business_id),
    INDEX idx_247sp_template_assignments_template (template_id),
    CONSTRAINT fk_247sp_template_assignments_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_template_assignments_template FOREIGN KEY (template_id) REFERENCES `247sp_templates` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_generated_websites` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    onboarding_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'generated',
    generated_at DATETIME NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_generated_websites_business (business_id),
    INDEX idx_247sp_generated_websites_onboarding (onboarding_id),
    INDEX idx_247sp_generated_websites_template (template_id),
    INDEX idx_247sp_generated_websites_status (status),
    CONSTRAINT fk_247sp_generated_websites_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_generated_websites_onboarding FOREIGN KEY (onboarding_id) REFERENCES `247sp_onboarding` (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_generated_websites_template FOREIGN KEY (template_id) REFERENCES `247sp_templates` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `247sp_generated_pages` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    page_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content_json JSON NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'generated',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_247sp_generated_pages_website_slug (website_id, slug),
    INDEX idx_247sp_generated_pages_business (business_id),
    INDEX idx_247sp_generated_pages_type (page_type),
    CONSTRAINT fk_247sp_generated_pages_website FOREIGN KEY (website_id) REFERENCES `247sp_generated_websites` (id) ON DELETE CASCADE,
    CONSTRAINT fk_247sp_generated_pages_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `247sp_templates` (template_key, name, description, is_active, created_at, updated_at) VALUES
('starter_local_service', 'Starter Local Service', 'Single 247SP starter template for local service businesses.', 1, NOW(), NOW());
