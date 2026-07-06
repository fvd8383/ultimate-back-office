ALTER TABLE subscriptions
    ADD COLUMN stripe_customer_id VARCHAR(255) NULL AFTER status,
    ADD COLUMN stripe_subscription_id VARCHAR(255) NULL AFTER stripe_customer_id,
    ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL AFTER stripe_subscription_id,
    ADD COLUMN stripe_latest_invoice_id VARCHAR(255) NULL AFTER stripe_checkout_session_id,
    ADD COLUMN payment_method_status VARCHAR(50) NOT NULL DEFAULT 'not_on_file' AFTER stripe_latest_invoice_id,
    ADD COLUMN current_period_start DATETIME NULL AFTER payment_method_status,
    ADD COLUMN current_period_end DATETIME NULL AFTER current_period_start,
    ADD COLUMN cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0 AFTER current_period_end,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at,
    ADD UNIQUE KEY uq_subscriptions_stripe_subscription (stripe_subscription_id),
    ADD INDEX idx_subscriptions_stripe_customer (stripe_customer_id),
    ADD INDEX idx_subscriptions_stripe_checkout_session (stripe_checkout_session_id),
    ADD INDEX idx_subscriptions_payment_method_status (payment_method_status);

ALTER TABLE payments
    ADD COLUMN stripe_invoice_id VARCHAR(255) NULL AFTER transaction_reference,
    ADD COLUMN stripe_payment_intent_id VARCHAR(255) NULL AFTER stripe_invoice_id,
    ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL AFTER stripe_payment_intent_id,
    ADD COLUMN stripe_event_id VARCHAR(255) NULL AFTER stripe_checkout_session_id,
    ADD COLUMN invoice_url VARCHAR(500) NULL AFTER stripe_event_id,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at,
    ADD UNIQUE KEY uq_payments_stripe_invoice (stripe_invoice_id),
    ADD INDEX idx_payments_stripe_payment_intent (stripe_payment_intent_id),
    ADD INDEX idx_payments_stripe_event (stripe_event_id);

CREATE TABLE stripe_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'processing',
    payload_json MEDIUMTEXT NULL,
    error_message TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_stripe_webhook_events_event (event_id),
    INDEX idx_stripe_webhook_events_type (event_type),
    INDEX idx_stripe_webhook_events_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
