CREATE TABLE website_integrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    ga_measurement_id VARCHAR(32) NULL,
    google_search_console_property VARCHAR(255) NULL,
    google_tag_manager_id VARCHAR(32) NULL,
    microsoft_clarity_id VARCHAR(64) NULL,
    meta_pixel_id VARCHAR(64) NULL,
    google_business_profile_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_integrations_business (business_id),
    CONSTRAINT fk_website_integrations_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE plans
SET monthly_fee = 47.00
WHERE product_key = '247sp';
