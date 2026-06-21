ALTER TABLE businesses
    ADD COLUMN legal_structure_other VARCHAR(150) NULL AFTER legal_structure_id;

CREATE TABLE business_custom_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_custom_services_business_category_name (business_id, category_id, service_name),
    INDEX idx_business_custom_services_business (business_id),
    INDEX idx_business_custom_services_category (category_id),
    CONSTRAINT fk_business_custom_services_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_custom_services_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sub_services (category_id, name, is_active, created_at, updated_at)
SELECT c.id, s.name, 1, NOW(), NOW()
FROM categories c
INNER JOIN (
    SELECT 'Plumbing' AS category_name, 'Emergency Plumbing' AS name
    UNION ALL SELECT 'Plumbing', 'Toilet Repair'
    UNION ALL SELECT 'Plumbing', 'Sewer Line Service'
    UNION ALL SELECT 'Plumbing', 'Other'
    UNION ALL SELECT 'Electrical', 'Lighting Installation'
    UNION ALL SELECT 'Electrical', 'Outlet and Switch Repair'
    UNION ALL SELECT 'Electrical', 'EV Charger Installation'
    UNION ALL SELECT 'Electrical', 'Other'
    UNION ALL SELECT 'HVAC', 'AC Repair'
    UNION ALL SELECT 'HVAC', 'Furnace Repair'
    UNION ALL SELECT 'HVAC', 'Ductwork'
    UNION ALL SELECT 'HVAC', 'Other'
    UNION ALL SELECT 'Landscaping', 'Mulching'
    UNION ALL SELECT 'Landscaping', 'Tree and Shrub Care'
    UNION ALL SELECT 'Landscaping', 'Seasonal Cleanup'
    UNION ALL SELECT 'Landscaping', 'Other'
    UNION ALL SELECT 'Cleaning', 'Move-In/Move-Out Cleaning'
    UNION ALL SELECT 'Cleaning', 'Recurring Cleaning'
    UNION ALL SELECT 'Cleaning', 'Post-Construction Cleaning'
    UNION ALL SELECT 'Cleaning', 'Other'
    UNION ALL SELECT 'Roofing', 'Roof Inspection'
    UNION ALL SELECT 'Roofing', 'Storm Damage Repair'
    UNION ALL SELECT 'Roofing', 'Skylight Repair'
    UNION ALL SELECT 'Roofing', 'Other'
    UNION ALL SELECT 'Painting', 'Drywall Repair'
    UNION ALL SELECT 'Painting', 'Deck Staining'
    UNION ALL SELECT 'Painting', 'Trim Painting'
    UNION ALL SELECT 'Painting', 'Other'
    UNION ALL SELECT 'Handyman', 'Door Repair'
    UNION ALL SELECT 'Handyman', 'Drywall Patching'
    UNION ALL SELECT 'Handyman', 'TV Mounting'
    UNION ALL SELECT 'Handyman', 'Other'
    UNION ALL SELECT 'Pest Control', 'Ant Control'
    UNION ALL SELECT 'Pest Control', 'Mosquito Control'
    UNION ALL SELECT 'Pest Control', 'Wildlife Removal'
    UNION ALL SELECT 'Pest Control', 'Other'
    UNION ALL SELECT 'Pool Service', 'Pool Closing'
    UNION ALL SELECT 'Pool Service', 'Chemical Balancing'
    UNION ALL SELECT 'Pool Service', 'Leak Detection'
    UNION ALL SELECT 'Pool Service', 'Other'
    UNION ALL SELECT 'Pressure Washing', 'Concrete Cleaning'
    UNION ALL SELECT 'Pressure Washing', 'Fence Washing'
    UNION ALL SELECT 'Pressure Washing', 'Commercial Washing'
    UNION ALL SELECT 'Pressure Washing', 'Other'
    UNION ALL SELECT 'Auto Detailing', 'Mobile Detailing'
    UNION ALL SELECT 'Auto Detailing', 'Paint Correction'
    UNION ALL SELECT 'Auto Detailing', 'Fleet Washing'
    UNION ALL SELECT 'Auto Detailing', 'Other'
    UNION ALL SELECT 'General Contractor', 'Kitchen Remodeling'
    UNION ALL SELECT 'General Contractor', 'Bathroom Remodeling'
    UNION ALL SELECT 'General Contractor', 'Basement Finishing'
    UNION ALL SELECT 'General Contractor', 'Other'
    UNION ALL SELECT 'Other', 'Other'
) s ON s.category_name = c.name
LEFT JOIN sub_services existing ON existing.category_id = c.id AND existing.name = s.name
WHERE existing.id IS NULL;
