CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plans_product_key (product_key),
    INDEX idx_plans_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'trial',
    started_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscriptions_business_plan (business_id, plan_id),
    INDEX idx_subscriptions_business (business_id),
    INDEX idx_subscriptions_plan (plan_id),
    INDEX idx_subscriptions_status (status),
    CONSTRAINT fk_subscriptions_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    transaction_reference VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_subscription (subscription_id),
    INDEX idx_payments_status (status),
    CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (product_key, name, setup_fee, monthly_fee, active, created_at)
SELECT '247sp', '24/7 Sales Partner', 100.00, 47.00, 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM plans WHERE product_key = '247sp'
);

INSERT INTO subscriptions (business_id, plan_id, status, started_at, created_at)
SELECT b.id, p.id, 'trial', NOW(), NOW()
FROM businesses b
INNER JOIN business_modules bm ON bm.business_id = b.id AND bm.status = 'active'
INNER JOIN modules m ON m.id = bm.module_id AND m.module_key = '247sp'
INNER JOIN plans p ON p.product_key = '247sp'
WHERE NOT EXISTS (
    SELECT 1
    FROM subscriptions s
    WHERE s.business_id = b.id
      AND s.plan_id = p.id
);
