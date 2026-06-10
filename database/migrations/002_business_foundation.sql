ALTER TABLE businesses
    ADD COLUMN slug VARCHAR(255) NULL AFTER business_name,
    ADD COLUMN setup_status VARCHAR(50) NOT NULL DEFAULT 'draft' AFTER status,
    ADD COLUMN setup_step VARCHAR(50) NOT NULL DEFAULT 'business_info' AFTER setup_status,
    ADD UNIQUE KEY uq_businesses_slug (slug);

ALTER TABLE business_modules
    ADD COLUMN activated_by_user_id BIGINT UNSIGNED NULL AFTER deactivated_at,
    ADD COLUMN activation_source VARCHAR(50) NOT NULL DEFAULT 'manual' AFTER activated_by_user_id,
    ADD INDEX idx_business_modules_activated_by (activated_by_user_id),
    ADD CONSTRAINT fk_business_modules_activated_by FOREIGN KEY (activated_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE legal_structures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sub_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sub_services_category_name (category_id, name),
    INDEX idx_sub_services_category (category_id),
    CONSTRAINT fk_sub_services_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_sub_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    sub_service_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_sub_services_business_service (business_id, sub_service_id),
    INDEX idx_business_sub_services_business (business_id),
    INDEX idx_business_sub_services_service (sub_service_id),
    CONSTRAINT fk_business_sub_services_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_sub_services_service FOREIGN KEY (sub_service_id) REFERENCES sub_services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE businesses
    ADD INDEX idx_businesses_legal_structure (legal_structure_id),
    ADD INDEX idx_businesses_primary_category (primary_category_id),
    ADD CONSTRAINT fk_businesses_legal_structure FOREIGN KEY (legal_structure_id) REFERENCES legal_structures (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_businesses_primary_category FOREIGN KEY (primary_category_id) REFERENCES categories (id) ON DELETE SET NULL;

INSERT INTO legal_structures (name, is_active, created_at, updated_at) VALUES
('Sole Proprietorship', 1, NOW(), NOW()),
('Single Member LLC', 1, NOW(), NOW()),
('Multi Member LLC', 1, NOW(), NOW()),
('Corporation', 1, NOW(), NOW()),
('S Corporation', 1, NOW(), NOW()),
('Partnership', 1, NOW(), NOW()),
('Nonprofit', 1, NOW(), NOW()),
('Other', 1, NOW(), NOW());

INSERT INTO categories (name, is_active, created_at, updated_at) VALUES
('Plumbing', 1, NOW(), NOW()),
('Electrical', 1, NOW(), NOW()),
('HVAC', 1, NOW(), NOW()),
('Landscaping', 1, NOW(), NOW()),
('Cleaning', 1, NOW(), NOW()),
('Roofing', 1, NOW(), NOW()),
('Painting', 1, NOW(), NOW()),
('Handyman', 1, NOW(), NOW()),
('Pest Control', 1, NOW(), NOW()),
('Pool Service', 1, NOW(), NOW()),
('Pressure Washing', 1, NOW(), NOW()),
('Auto Detailing', 1, NOW(), NOW()),
('General Contractor', 1, NOW(), NOW()),
('Other', 1, NOW(), NOW());

INSERT INTO sub_services (category_id, name, is_active, created_at, updated_at)
SELECT id, 'Leak Repair', 1, NOW(), NOW() FROM categories WHERE name = 'Plumbing'
UNION ALL SELECT id, 'Drain Cleaning', 1, NOW(), NOW() FROM categories WHERE name = 'Plumbing'
UNION ALL SELECT id, 'Water Heaters', 1, NOW(), NOW() FROM categories WHERE name = 'Plumbing'
UNION ALL SELECT id, 'Panel Upgrades', 1, NOW(), NOW() FROM categories WHERE name = 'Electrical'
UNION ALL SELECT id, 'Wiring', 1, NOW(), NOW() FROM categories WHERE name = 'Electrical'
UNION ALL SELECT id, 'Generator Installation', 1, NOW(), NOW() FROM categories WHERE name = 'Electrical'
UNION ALL SELECT id, 'Repairs', 1, NOW(), NOW() FROM categories WHERE name = 'HVAC'
UNION ALL SELECT id, 'Maintenance', 1, NOW(), NOW() FROM categories WHERE name = 'HVAC'
UNION ALL SELECT id, 'Installations', 1, NOW(), NOW() FROM categories WHERE name = 'HVAC'
UNION ALL SELECT id, 'Lawn Mowing', 1, NOW(), NOW() FROM categories WHERE name = 'Landscaping'
UNION ALL SELECT id, 'Snow Removal', 1, NOW(), NOW() FROM categories WHERE name = 'Landscaping'
UNION ALL SELECT id, 'Landscape Design', 1, NOW(), NOW() FROM categories WHERE name = 'Landscaping'
UNION ALL SELECT id, 'Residential Cleaning', 1, NOW(), NOW() FROM categories WHERE name = 'Cleaning'
UNION ALL SELECT id, 'Commercial Cleaning', 1, NOW(), NOW() FROM categories WHERE name = 'Cleaning'
UNION ALL SELECT id, 'Deep Cleaning', 1, NOW(), NOW() FROM categories WHERE name = 'Cleaning'
UNION ALL SELECT id, 'Roof Repair', 1, NOW(), NOW() FROM categories WHERE name = 'Roofing'
UNION ALL SELECT id, 'Roof Replacement', 1, NOW(), NOW() FROM categories WHERE name = 'Roofing'
UNION ALL SELECT id, 'Gutter Services', 1, NOW(), NOW() FROM categories WHERE name = 'Roofing'
UNION ALL SELECT id, 'Interior Painting', 1, NOW(), NOW() FROM categories WHERE name = 'Painting'
UNION ALL SELECT id, 'Exterior Painting', 1, NOW(), NOW() FROM categories WHERE name = 'Painting'
UNION ALL SELECT id, 'Cabinet Painting', 1, NOW(), NOW() FROM categories WHERE name = 'Painting'
UNION ALL SELECT id, 'Home Repairs', 1, NOW(), NOW() FROM categories WHERE name = 'Handyman'
UNION ALL SELECT id, 'Furniture Assembly', 1, NOW(), NOW() FROM categories WHERE name = 'Handyman'
UNION ALL SELECT id, 'Fixture Installation', 1, NOW(), NOW() FROM categories WHERE name = 'Handyman'
UNION ALL SELECT id, 'General Pest Control', 1, NOW(), NOW() FROM categories WHERE name = 'Pest Control'
UNION ALL SELECT id, 'Termite Treatment', 1, NOW(), NOW() FROM categories WHERE name = 'Pest Control'
UNION ALL SELECT id, 'Rodent Control', 1, NOW(), NOW() FROM categories WHERE name = 'Pest Control'
UNION ALL SELECT id, 'Pool Cleaning', 1, NOW(), NOW() FROM categories WHERE name = 'Pool Service'
UNION ALL SELECT id, 'Pool Opening', 1, NOW(), NOW() FROM categories WHERE name = 'Pool Service'
UNION ALL SELECT id, 'Equipment Repair', 1, NOW(), NOW() FROM categories WHERE name = 'Pool Service'
UNION ALL SELECT id, 'House Washing', 1, NOW(), NOW() FROM categories WHERE name = 'Pressure Washing'
UNION ALL SELECT id, 'Driveways', 1, NOW(), NOW() FROM categories WHERE name = 'Pressure Washing'
UNION ALL SELECT id, 'Decks and Patios', 1, NOW(), NOW() FROM categories WHERE name = 'Pressure Washing'
UNION ALL SELECT id, 'Interior Detailing', 1, NOW(), NOW() FROM categories WHERE name = 'Auto Detailing'
UNION ALL SELECT id, 'Exterior Detailing', 1, NOW(), NOW() FROM categories WHERE name = 'Auto Detailing'
UNION ALL SELECT id, 'Ceramic Coating', 1, NOW(), NOW() FROM categories WHERE name = 'Auto Detailing'
UNION ALL SELECT id, 'Remodeling', 1, NOW(), NOW() FROM categories WHERE name = 'General Contractor'
UNION ALL SELECT id, 'Additions', 1, NOW(), NOW() FROM categories WHERE name = 'General Contractor'
UNION ALL SELECT id, 'Project Management', 1, NOW(), NOW() FROM categories WHERE name = 'General Contractor'
UNION ALL SELECT id, 'General Service', 1, NOW(), NOW() FROM categories WHERE name = 'Other'
UNION ALL SELECT id, 'Consultation', 1, NOW(), NOW() FROM categories WHERE name = 'Other'
UNION ALL SELECT id, 'Custom Work', 1, NOW(), NOW() FROM categories WHERE name = 'Other';
