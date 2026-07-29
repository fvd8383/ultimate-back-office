CREATE TABLE business_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    lifecycle_status VARCHAR(50) NOT NULL DEFAULT 'draft',
    public_display_name VARCHAR(255) NULL,
    website_url VARCHAR(255) NULL,
    timezone VARCHAR(100) NULL,
    default_language VARCHAR(20) NOT NULL DEFAULT 'en',
    short_description TEXT NULL,
    long_description TEXT NULL,
    primary_greeting TEXT NULL,
    value_proposition TEXT NULL,
    tone VARCHAR(100) NULL,
    personality VARCHAR(255) NULL,
    prohibited_claims TEXT NULL,
    appointment_requests_enabled TINYINT(1) NOT NULL DEFAULT 0,
    automatic_booking_enabled TINYINT(1) NOT NULL DEFAULT 0,
    minimum_notice_minutes INT UNSIGNED NULL,
    default_appointment_duration_minutes INT UNSIGNED NULL,
    emergency_service_enabled TINYINT(1) NOT NULL DEFAULT 0,
    readiness_snapshot_json JSON NULL,
    profile_completed_at DATETIME NULL,
    activated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_profiles_business (business_id),
    UNIQUE KEY uq_business_profiles_id_business (id, business_id),
    INDEX idx_business_profiles_lifecycle (lifecycle_status),
    INDEX idx_business_profiles_timezone (timezone),
    CONSTRAINT fk_business_profiles_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_profile_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    time_range_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    is_24_hours TINYINT(1) NOT NULL DEFAULT 0,
    opens_at TIME NULL,
    closes_at TIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_profile_hours_profile_day_order (business_profile_id, day_of_week, time_range_order),
    INDEX idx_business_profile_hours_business_day (business_id, day_of_week),
    INDEX idx_business_profile_hours_profile_business (business_profile_id, business_id),
    CONSTRAINT fk_business_profile_hours_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_profile_hours_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_profile_hour_exceptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    exception_date DATE NOT NULL,
    time_range_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    label VARCHAR(150) NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    is_24_hours TINYINT(1) NOT NULL DEFAULT 0,
    opens_at TIME NULL,
    closes_at TIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_profile_hour_exceptions_profile_date_order (business_profile_id, exception_date, time_range_order),
    INDEX idx_business_profile_hour_exceptions_business_date (business_id, exception_date),
    INDEX idx_business_profile_hour_exceptions_profile_business (business_profile_id, business_id),
    CONSTRAINT fk_business_profile_hour_exceptions_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_profile_hour_exceptions_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_profile_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    channel_scope VARCHAR(50) NOT NULL DEFAULT 'all',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_profile_faqs_business_active_sort (business_id, is_active, sort_order),
    INDEX idx_business_profile_faqs_profile_active_sort (business_profile_id, is_active, sort_order),
    INDEX idx_business_profile_faqs_profile_business (business_profile_id, business_id),
    CONSTRAINT fk_business_profile_faqs_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_profile_faqs_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_profile_pricing_guidance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    guidance_type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NULL,
    guidance_text TEXT NOT NULL,
    amount_min DECIMAL(10,2) NULL,
    amount_max DECIMAL(10,2) NULL,
    currency_code CHAR(3) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_profile_pricing_business_active_sort (business_id, is_active, sort_order),
    INDEX idx_business_profile_pricing_profile_type_active (business_profile_id, guidance_type, is_active),
    INDEX idx_business_profile_pricing_profile_business (business_profile_id, business_id),
    CONSTRAINT fk_business_profile_pricing_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_profile_pricing_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_appointment_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    sub_service_id BIGINT UNSIGNED NULL,
    business_custom_service_id BIGINT UNSIGNED NULL,
    rule_text TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_appointment_rules_business_active_sort (business_id, is_active, sort_order),
    INDEX idx_business_appointment_rules_profile_active_sort (business_profile_id, is_active, sort_order),
    INDEX idx_business_appointment_rules_profile_business (business_profile_id, business_id),
    INDEX idx_business_appointment_rules_sub_service (sub_service_id),
    INDEX idx_business_appointment_rules_custom_service (business_custom_service_id),
    CONSTRAINT fk_business_appointment_rules_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_appointment_rules_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE,
    CONSTRAINT fk_business_appointment_rules_sub_service FOREIGN KEY (sub_service_id) REFERENCES sub_services (id) ON DELETE SET NULL,
    CONSTRAINT fk_business_appointment_rules_custom_service FOREIGN KEY (business_custom_service_id) REFERENCES business_custom_services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_transfer_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    transfer_number VARCHAR(50) NOT NULL,
    backup_transfer_number VARCHAR(50) NULL,
    applies_during_business_hours TINYINT(1) NOT NULL DEFAULT 1,
    applies_after_hours TINYINT(1) NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 100,
    maximum_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
    fallback_behavior VARCHAR(50) NOT NULL DEFAULT 'create_leadhub_task',
    sub_service_id BIGINT UNSIGNED NULL,
    business_custom_service_id BIGINT UNSIGNED NULL,
    condition_text TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_transfer_rules_business_active_priority (business_id, is_active, priority),
    INDEX idx_business_transfer_rules_profile_active_priority (business_profile_id, is_active, priority),
    INDEX idx_business_transfer_rules_profile_business (business_profile_id, business_id),
    INDEX idx_business_transfer_rules_sub_service (sub_service_id),
    INDEX idx_business_transfer_rules_custom_service (business_custom_service_id),
    CONSTRAINT fk_business_transfer_rules_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_transfer_rules_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE,
    CONSTRAINT fk_business_transfer_rules_sub_service FOREIGN KEY (sub_service_id) REFERENCES sub_services (id) ON DELETE SET NULL,
    CONSTRAINT fk_business_transfer_rules_custom_service FOREIGN KEY (business_custom_service_id) REFERENCES business_custom_services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_escalation_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    condition_text TEXT NOT NULL,
    instruction_text TEXT NULL,
    sub_service_id BIGINT UNSIGNED NULL,
    business_custom_service_id BIGINT UNSIGNED NULL,
    urgency_level VARCHAR(50) NOT NULL DEFAULT 'normal',
    requires_immediate_transfer TINYINT(1) NOT NULL DEFAULT 0,
    requires_owner_alert TINYINT(1) NOT NULL DEFAULT 0,
    disclaimer_text TEXT NULL,
    priority INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_escalation_rules_business_active_priority (business_id, is_active, priority),
    INDEX idx_business_escalation_rules_profile_active_priority (business_profile_id, is_active, priority),
    INDEX idx_business_escalation_rules_profile_business (business_profile_id, business_id),
    INDEX idx_business_escalation_rules_urgency (urgency_level),
    INDEX idx_business_escalation_rules_sub_service (sub_service_id),
    INDEX idx_business_escalation_rules_custom_service (business_custom_service_id),
    CONSTRAINT fk_business_escalation_rules_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_escalation_rules_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE,
    CONSTRAINT fk_business_escalation_rules_sub_service FOREIGN KEY (sub_service_id) REFERENCES sub_services (id) ON DELETE SET NULL,
    CONSTRAINT fk_business_escalation_rules_custom_service FOREIGN KEY (business_custom_service_id) REFERENCES business_custom_services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_notification_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    business_profile_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
    in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
    destination_email VARCHAR(255) NULL,
    destination_phone VARCHAR(50) NULL,
    daily_summary_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_notification_preferences_profile_type (business_profile_id, notification_type),
    INDEX idx_business_notification_preferences_business_type (business_id, notification_type),
    INDEX idx_business_notification_preferences_active (is_active),
    INDEX idx_business_notification_preferences_profile_business (business_profile_id, business_id),
    CONSTRAINT fk_business_notification_preferences_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_notification_preferences_profile_business FOREIGN KEY (business_profile_id, business_id) REFERENCES business_profiles (id, business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO business_profiles (
    business_id,
    lifecycle_status,
    public_display_name,
    default_language,
    short_description,
    long_description,
    created_at,
    updated_at
)
SELECT
    b.id,
    'draft',
    NULLIF(b.business_name, ''),
    'en',
    bc.business_description,
    bc.about_company,
    NOW(),
    NOW()
FROM businesses b
LEFT JOIN (
    SELECT business_id, MIN(id) AS content_id
    FROM `247sp_business_content`
    GROUP BY business_id
) bc_pick ON bc_pick.business_id = b.id
LEFT JOIN `247sp_business_content` bc ON bc.id = bc_pick.content_id
WHERE NOT EXISTS (
    SELECT 1
    FROM business_profiles existing
    WHERE existing.business_id = b.id
);
