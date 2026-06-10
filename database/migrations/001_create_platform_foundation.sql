CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(50) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_otps_user_purpose (user_id, purpose),
    INDEX idx_user_otps_expires_at (expires_at),
    CONSTRAINT fk_user_otps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_logins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    login_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    INDEX idx_user_logins_user_login_at (user_id, login_at),
    CONSTRAINT fk_user_logins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE businesses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255) NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(30) NOT NULL,
    country VARCHAR(100) NOT NULL,
    is_public_physical_location TINYINT(1) NOT NULL DEFAULT 1,
    legal_structure_id BIGINT UNSIGNED NULL,
    primary_category_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_businesses_owner_user (owner_user_id),
    CONSTRAINT fk_businesses_owner_user FOREIGN KEY (owner_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    scope VARCHAR(50) NOT NULL,
    description TEXT NULL,
    is_system_role TINYINT(1) NOT NULL DEFAULT 1,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    business_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_roles_scope (scope),
    INDEX idx_roles_business (business_id),
    CONSTRAINT fk_roles_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    is_owner TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_users_business_user (business_id, user_id),
    INDEX idx_business_users_user (user_id),
    INDEX idx_business_users_role (role_id),
    CONSTRAINT fk_business_users_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_users_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    employee_type VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employees_business (business_id),
    INDEX idx_employees_user (user_id),
    CONSTRAINT fk_employees_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(150) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    module_key VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_permissions_module_key (module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permissions_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_standalone TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    activated_at DATETIME NULL,
    deactivated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_modules_business_module (business_id, module_id),
    CONSTRAINT fk_business_modules_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_modules_module FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_payment_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    payment_provider_id BIGINT UNSIGNED NOT NULL,
    provider_account_id VARCHAR(255) NULL,
    provider_customer_id VARCHAR(255) NULL,
    account_type VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    charges_enabled TINYINT(1) NOT NULL DEFAULT 0,
    payouts_enabled TINYINT(1) NOT NULL DEFAULT 0,
    requirements_due_json JSON NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_business_payment_accounts_business (business_id),
    INDEX idx_business_payment_accounts_provider (payment_provider_id),
    CONSTRAINT fk_business_payment_accounts_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_payment_accounts_provider FOREIGN KEY (payment_provider_id) REFERENCES payment_providers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    status_key VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact_statuses_business (business_id),
    INDEX idx_contact_statuses_status_key (status_key),
    CONSTRAINT fk_contact_statuses_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    portal_user_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    contact_type VARCHAR(100) NULL,
    status_id BIGINT UNSIGNED NULL,
    source_module_key VARCHAR(100) NULL,
    source_detail VARCHAR(255) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contacts_business (business_id),
    INDEX idx_contacts_status (status_id),
    INDEX idx_contacts_created_by (created_by_user_id),
    CONSTRAINT fk_contacts_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_contacts_status FOREIGN KEY (status_id) REFERENCES contact_statuses (id) ON DELETE SET NULL,
    CONSTRAINT fk_contacts_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    note_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_business (business_id),
    INDEX idx_notes_contact (contact_id),
    CONSTRAINT fk_notes_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    priority VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tasks_business (business_id),
    INDEX idx_tasks_contact (contact_id),
    INDEX idx_tasks_assigned_to (assigned_to_user_id),
    CONSTRAINT fk_tasks_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL,
    enterprise_account_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    module_key VARCHAR(100) NULL,
    activity_type VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NULL,
    description TEXT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_logs_business (business_id),
    INDEX idx_activity_logs_user (user_id),
    INDEX idx_activity_logs_contact (contact_id),
    INDEX idx_activity_logs_activity_type (activity_type),
    CONSTRAINT fk_activity_logs_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_logs_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO modules (module_key, name, description, is_standalone, is_active, created_at, updated_at) VALUES
('lead_hub', 'Lead Hub', 'Central dashboard and CRM layer included with every module.', 0, 1, NOW(), NOW()),
('247sp', '24/7 Sales Partner', 'Website, hosting, domain, and lead capture product.', 1, 1, NOW(), NOW()),
('emd', 'EMD Network', 'Exclusive manual pay-per-lead network.', 1, 1, NOW(), NOW()),
('ssp', 'Super Simple Payments', 'Estimates, invoices, and payment foundation.', 1, 1, NOW(), NOW()),
('tuhwd', 'Tell Us How We Did', 'Review request and feedback product.', 1, 1, NOW(), NOW()),
('kyn', 'Know Your Numbers', 'Manual expenses and basic P&L module requiring SSP.', 1, 1, NOW(), NOW()),
('full_os', 'Full OS', 'Complete business operating system tier.', 0, 1, NOW(), NOW()),
('enterprise', 'Enterprise', 'Parent account support for managing multiple businesses.', 0, 1, NOW(), NOW());

INSERT INTO payment_providers (provider_key, name, description, is_active, created_at, updated_at) VALUES
('stripe', 'Stripe', 'Initial billing and payment provider.', 1, NOW(), NOW());

INSERT INTO roles (name, scope, description, is_system_role, is_custom, business_id, created_at, updated_at) VALUES
('Owner', 'business', 'Business owner with full business access.', 1, 0, NULL, NOW(), NOW()),
('Admin', 'business', 'Business admin access.', 1, 0, NULL, NOW(), NOW()),
('Sales', 'business', 'Sales and lead management access.', 1, 0, NULL, NOW(), NOW()),
('Office', 'business', 'Office workflow access.', 1, 0, NULL, NOW(), NOW()),
('Bookkeeper', 'business', 'Bookkeeping access.', 1, 0, NULL, NOW(), NOW()),
('Technician', 'business', 'Technician access for future field workflows.', 1, 0, NULL, NOW(), NOW()),
('Super Admin', 'internal', 'Internal super admin access.', 1, 0, NULL, NOW(), NOW()),
('Support', 'internal', 'Internal support access.', 1, 0, NULL, NOW(), NOW()),
('Bookkeeping Staff', 'internal', 'Internal bookkeeping staff access.', 1, 0, NULL, NOW(), NOW()),
('Marketing Staff', 'internal', 'Internal marketing staff access.', 1, 0, NULL, NOW(), NOW()),
('Sales Staff', 'internal', 'Internal sales staff access.', 1, 0, NULL, NOW(), NOW()),
('Domain/Email Admin', 'internal', 'Internal domain and email setup access.', 1, 0, NULL, NOW(), NOW()),
('Account Manager', 'internal', 'Internal account management access.', 1, 0, NULL, NOW(), NOW());

INSERT INTO contact_statuses (business_id, name, status_key, sort_order, is_default, is_active, created_at, updated_at) VALUES
(NULL, 'New Lead', 'new_lead', 10, 1, 1, NOW(), NOW()),
(NULL, 'Contacted', 'contacted', 20, 0, 1, NOW(), NOW()),
(NULL, 'Qualified', 'qualified', 30, 0, 1, NOW(), NOW()),
(NULL, 'Estimate Sent', 'estimate_sent', 40, 0, 1, NOW(), NOW()),
(NULL, 'Customer', 'customer', 50, 0, 1, NOW(), NOW()),
(NULL, 'Inactive', 'inactive', 60, 0, 1, NOW(), NOW()),
(NULL, 'Lost', 'lost', 70, 0, 1, NOW(), NOW()),
(NULL, 'Spam', 'spam', 80, 0, 1, NOW(), NOW());
