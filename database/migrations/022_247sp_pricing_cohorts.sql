CREATE TABLE pricing_cohorts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    cohort_key VARCHAR(32) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    position_start BIGINT UNSIGNED NOT NULL,
    position_end BIGINT UNSIGNED NULL,
    setup_fee DECIMAL(10,2) NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    free_introductory_months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    effective_from DATETIME NOT NULL,
    effective_until DATETIME NULL,
    version INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    stripe_recurring_price_ref VARCHAR(255) NULL,
    stripe_setup_price_ref VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pricing_cohorts_plan_key_version (plan_id, cohort_key, version),
    UNIQUE KEY uq_pricing_cohorts_plan_start_version (plan_id, position_start, version),
    INDEX idx_pricing_cohorts_active_effective_range (
        plan_id, is_active, effective_from, effective_until, position_start, position_end
    ),
    CONSTRAINT chk_pricing_cohorts_position_start CHECK (position_start > 0),
    CONSTRAINT chk_pricing_cohorts_position_end CHECK (position_end IS NULL OR position_end >= position_start),
    CONSTRAINT chk_pricing_cohorts_setup_fee CHECK (setup_fee >= 0),
    CONSTRAINT chk_pricing_cohorts_monthly_fee CHECK (monthly_fee >= 0),
    CONSTRAINT chk_pricing_cohorts_intro_months CHECK (free_introductory_months >= 0),
    CONSTRAINT chk_pricing_cohorts_currency CHECK (CHAR_LENGTH(currency) = 3 AND currency = UPPER(currency)),
    CONSTRAINT chk_pricing_cohorts_effective_dates CHECK (effective_until IS NULL OR effective_until > effective_from),
    CONSTRAINT chk_pricing_cohorts_version CHECK (version > 0),
    CONSTRAINT chk_pricing_cohorts_active CHECK (is_active IN (0, 1)),
    CONSTRAINT fk_pricing_cohorts_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_customer_sequence_counters (
    plan_id BIGINT UNSIGNED PRIMARY KEY,
    next_sequence_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
    lock_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_product_sequence_next CHECK (next_sequence_number > 0),
    CONSTRAINT fk_product_sequence_counter_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_customer_sequence_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    pricing_cohort_id BIGINT UNSIGNED NOT NULL,
    customer_sequence_number BIGINT UNSIGNED NOT NULL,
    completed_signup_idempotency_key VARCHAR(191) NOT NULL,
    assigned_at DATETIME NOT NULL,
    actor_type VARCHAR(20) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    system_actor_key VARCHAR(100) NULL,
    correlation_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_sequence_allocation (plan_id, customer_sequence_number),
    UNIQUE KEY uq_product_sequence_subscription (subscription_id),
    UNIQUE KEY uq_product_sequence_signup_key (plan_id, completed_signup_idempotency_key),
    UNIQUE KEY uq_product_sequence_snapshot_relationship (
        id, subscription_id, pricing_cohort_id, customer_sequence_number
    ),
    INDEX idx_product_sequence_business (business_id, assigned_at),
    INDEX idx_product_sequence_cohort (pricing_cohort_id),
    CONSTRAINT chk_product_sequence_number CHECK (customer_sequence_number > 0),
    CONSTRAINT chk_product_sequence_actor CHECK (
        (actor_type = 'user' AND actor_user_id IS NOT NULL AND system_actor_key IS NULL)
        OR
        (actor_type = 'system' AND actor_user_id IS NULL AND system_actor_key IS NOT NULL)
    ),
    CONSTRAINT fk_product_sequence_allocation_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_sequence_allocation_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_sequence_allocation_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_sequence_allocation_cohort FOREIGN KEY (pricing_cohort_id) REFERENCES pricing_cohorts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_sequence_allocation_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_commercial_terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    allocation_id BIGINT UNSIGNED NOT NULL,
    pricing_cohort_id BIGINT UNSIGNED NOT NULL,
    customer_sequence_number BIGINT UNSIGNED NOT NULL,
    locked_setup_fee DECIMAL(10,2) NOT NULL,
    locked_monthly_fee DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    locked_free_introductory_months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    pricing_assigned_at DATETIME NOT NULL,
    business_signup_completed_at DATETIME NOT NULL,
    introductory_period_starts_at DATETIME NULL,
    introductory_period_expires_at DATETIME NULL,
    recurring_billing_starts_at DATETIME NOT NULL,
    locked_stripe_recurring_price_ref VARCHAR(255) NULL,
    locked_stripe_setup_price_ref VARCHAR(255) NULL,
    configuration_version INT UNSIGNED NOT NULL,
    correlation_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_commercial_terms_subscription (subscription_id),
    UNIQUE KEY uq_subscription_commercial_terms_allocation (allocation_id),
    INDEX idx_subscription_commercial_terms_cohort (pricing_cohort_id),
    INDEX idx_subscription_commercial_terms_sequence (customer_sequence_number),
    CONSTRAINT chk_subscription_terms_sequence CHECK (customer_sequence_number > 0),
    CONSTRAINT chk_subscription_terms_setup_fee CHECK (locked_setup_fee >= 0),
    CONSTRAINT chk_subscription_terms_monthly_fee CHECK (locked_monthly_fee >= 0),
    CONSTRAINT chk_subscription_terms_intro_months CHECK (locked_free_introductory_months >= 0),
    CONSTRAINT chk_subscription_terms_currency CHECK (CHAR_LENGTH(currency) = 3 AND currency = UPPER(currency)),
    CONSTRAINT chk_subscription_terms_version CHECK (configuration_version > 0),
    CONSTRAINT chk_subscription_terms_intro_dates CHECK (
        (introductory_period_starts_at IS NULL
            AND introductory_period_expires_at IS NULL
            AND recurring_billing_starts_at = business_signup_completed_at)
        OR
        (introductory_period_starts_at IS NOT NULL
            AND introductory_period_expires_at IS NOT NULL
            AND introductory_period_expires_at >= introductory_period_starts_at
            AND recurring_billing_starts_at = introductory_period_expires_at)
    ),
    CONSTRAINT fk_subscription_terms_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_terms_allocation FOREIGN KEY (allocation_id) REFERENCES product_customer_sequence_allocations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_terms_cohort FOREIGN KEY (pricing_cohort_id) REFERENCES pricing_cohorts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_terms_allocation_relationship FOREIGN KEY (
        allocation_id, subscription_id, pricing_cohort_id, customer_sequence_number
    ) REFERENCES product_customer_sequence_allocations (
        id, subscription_id, pricing_cohort_id, customer_sequence_number
    ) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pricing_cohorts (
    plan_id, cohort_key, display_name, position_start, position_end,
    setup_fee, monthly_fee, currency, free_introductory_months,
    effective_from, effective_until, version, is_active,
    stripe_recurring_price_ref, stripe_setup_price_ref, created_at, updated_at
)
SELECT
    p.id, seeded.cohort_key, seeded.display_name, seeded.position_start, seeded.position_end,
    seeded.setup_fee, seeded.monthly_fee, 'USD', seeded.free_introductory_months,
    '2026-08-11 00:00:00', NULL, 1, 1,
    NULL, NULL, NOW(), NOW()
FROM plans p
INNER JOIN (
    SELECT 'alpha' AS cohort_key, 'Alpha' AS display_name, 1 AS position_start, 5 AS position_end,
           0.00 AS setup_fee, 79.00 AS monthly_fee, 6 AS free_introductory_months
    UNION ALL
    SELECT 'beta', 'Beta', 6, 10, 0.00, 97.00, 0
    UNION ALL
    SELECT 'founding', 'Founding', 11, 25, 100.00, 147.00, 0
    UNION ALL
    SELECT 'standard', 'Standard', 26, NULL, 250.00, 197.00, 0
) seeded ON 1 = 1
WHERE p.product_key = '247sp';

INSERT INTO product_customer_sequence_counters (
    plan_id, next_sequence_number, lock_version, created_at, updated_at
)
SELECT id, 1, 0, NOW(), NOW()
FROM plans
WHERE product_key = '247sp';
