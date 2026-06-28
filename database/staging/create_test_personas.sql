-- Staging-only test personas for QA.
-- Safe to rerun against staging after all migrations have been applied.
-- This script intentionally uses existing roles, modules, plans, and tables.

START TRANSACTION;

SET @internal_admin_role_id := (
    SELECT id
    FROM roles
    WHERE scope = 'internal'
      AND name IN ('Super Admin', 'Admin')
    ORDER BY FIELD(name, 'Super Admin', 'Admin'), id
    LIMIT 1
);

SET @business_owner_role_id := (
    SELECT id
    FROM roles
    WHERE scope = 'business'
      AND name = 'Owner'
    ORDER BY id
    LIMIT 1
);

SET @category_id := (
    SELECT id
    FROM categories
    WHERE name = 'Other'
    ORDER BY id
    LIMIT 1
);

SET @legal_structure_id := (
    SELECT id
    FROM legal_structures
    WHERE name = 'Other'
    ORDER BY id
    LIMIT 1
);

-- Persona: admin@test.com
-- Purpose: Internal platform administrator for /app/admin/* QA.
-- This user receives internal admin access only and is kept out of business_users.
INSERT INTO users (first_name, last_name, email, phone, status, created_at, updated_at)
VALUES ('Internal', 'Admin', 'admin@test.com', NULL, 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    status = VALUES(status),
    updated_at = NOW();

SET @admin_user_id := (SELECT id FROM users WHERE email = 'admin@test.com' LIMIT 1);

DELETE FROM business_users
WHERE user_id = @admin_user_id;

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE ur.user_id = @admin_user_id
  AND r.scope = 'internal';

INSERT IGNORE INTO user_roles (user_id, role_id, created_at)
SELECT @admin_user_id, @internal_admin_role_id, NOW()
WHERE @admin_user_id IS NOT NULL
  AND @internal_admin_role_id IS NOT NULL;

-- Persona: customer@test.com
-- Purpose: Standard customer workflow QA with completed business setup,
-- active 247SP and Lead Hub access, and an active 247SP subscription.
INSERT INTO users (first_name, last_name, email, phone, status, created_at, updated_at)
VALUES ('Standard', 'Customer', 'customer@test.com', '555-0101', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    status = VALUES(status),
    updated_at = NOW();

SET @customer_user_id := (SELECT id FROM users WHERE email = 'customer@test.com' LIMIT 1);

INSERT INTO businesses (
    business_name, slug, legal_name, owner_user_id, phone, email,
    address_line_1, address_line_2, city, state, postal_code, country,
    is_public_physical_location, legal_structure_id, primary_category_id,
    status, is_suspended, is_test_account, internal_status, setup_status, setup_step,
    created_at, updated_at
)
SELECT
    'Customer Test Services', 'customer-test-services', 'Customer Test Services',
    @customer_user_id, '555-1101', 'customer@test.com',
    '100 Customer Test Way', NULL, 'Raleigh', 'NC', '27601', 'US',
    1, @legal_structure_id, @category_id,
    'active', 0, 1, 'active', 'complete', 'completed',
    NOW(), NOW()
WHERE @customer_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM businesses WHERE slug = 'customer-test-services'
  );

UPDATE businesses
SET business_name = 'Customer Test Services',
    legal_name = 'Customer Test Services',
    owner_user_id = @customer_user_id,
    phone = '555-1101',
    email = 'customer@test.com',
    address_line_1 = '100 Customer Test Way',
    address_line_2 = NULL,
    city = 'Raleigh',
    state = 'NC',
    postal_code = '27601',
    country = 'US',
    is_public_physical_location = 1,
    legal_structure_id = @legal_structure_id,
    primary_category_id = @category_id,
    status = 'active',
    is_suspended = 0,
    is_test_account = 1,
    internal_status = 'active',
    setup_status = 'complete',
    setup_step = 'completed',
    updated_at = NOW()
WHERE slug = 'customer-test-services';

SET @customer_business_id := (SELECT id FROM businesses WHERE slug = 'customer-test-services' LIMIT 1);

INSERT INTO business_users (business_id, user_id, role_id, status, is_owner, created_at, updated_at)
SELECT @customer_business_id, @customer_user_id, @business_owner_role_id, 'active', 1, NOW(), NOW()
WHERE @customer_business_id IS NOT NULL
  AND @customer_user_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    status = VALUES(status),
    is_owner = VALUES(is_owner),
    updated_at = NOW();

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE ur.user_id = @customer_user_id
  AND r.scope = 'internal';

-- Persona: trial@test.com
-- Purpose: Mid-onboarding and trial-state QA. This customer is linked to a
-- business with active 247SP access, a trial subscription, and incomplete 247SP onboarding.
INSERT INTO users (first_name, last_name, email, phone, status, created_at, updated_at)
VALUES ('Trial', 'Customer', 'trial@test.com', '555-0102', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    status = VALUES(status),
    updated_at = NOW();

SET @trial_user_id := (SELECT id FROM users WHERE email = 'trial@test.com' LIMIT 1);

INSERT INTO businesses (
    business_name, slug, legal_name, owner_user_id, phone, email,
    address_line_1, address_line_2, city, state, postal_code, country,
    is_public_physical_location, legal_structure_id, primary_category_id,
    status, is_suspended, is_test_account, internal_status, setup_status, setup_step,
    created_at, updated_at
)
SELECT
    'Trial Test Services', 'trial-test-services', 'Trial Test Services',
    @trial_user_id, '555-1102', 'trial@test.com',
    '200 Trial Test Way', NULL, 'Raleigh', 'NC', '27601', 'US',
    1, @legal_structure_id, @category_id,
    'active', 0, 1, 'active', 'incomplete', 'modules',
    NOW(), NOW()
WHERE @trial_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM businesses WHERE slug = 'trial-test-services'
  );

UPDATE businesses
SET business_name = 'Trial Test Services',
    legal_name = 'Trial Test Services',
    owner_user_id = @trial_user_id,
    phone = '555-1102',
    email = 'trial@test.com',
    address_line_1 = '200 Trial Test Way',
    address_line_2 = NULL,
    city = 'Raleigh',
    state = 'NC',
    postal_code = '27601',
    country = 'US',
    is_public_physical_location = 1,
    legal_structure_id = @legal_structure_id,
    primary_category_id = @category_id,
    status = 'active',
    is_suspended = 0,
    is_test_account = 1,
    internal_status = 'active',
    setup_status = 'incomplete',
    setup_step = 'modules',
    updated_at = NOW()
WHERE slug = 'trial-test-services';

SET @trial_business_id := (SELECT id FROM businesses WHERE slug = 'trial-test-services' LIMIT 1);

INSERT INTO business_users (business_id, user_id, role_id, status, is_owner, created_at, updated_at)
SELECT @trial_business_id, @trial_user_id, @business_owner_role_id, 'active', 1, NOW(), NOW()
WHERE @trial_business_id IS NOT NULL
  AND @trial_user_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    status = VALUES(status),
    is_owner = VALUES(is_owner),
    updated_at = NOW();

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE ur.user_id = @trial_user_id
  AND r.scope = 'internal';

-- Persona: suspended@test.com
-- Purpose: Past-due and suspended-account QA. Public business status stays
-- active so testers can reach the customer UI and verify warning behavior.
INSERT INTO users (first_name, last_name, email, phone, status, created_at, updated_at)
VALUES ('Suspended', 'Customer', 'suspended@test.com', '555-0103', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    status = VALUES(status),
    updated_at = NOW();

SET @suspended_user_id := (SELECT id FROM users WHERE email = 'suspended@test.com' LIMIT 1);

INSERT INTO businesses (
    business_name, slug, legal_name, owner_user_id, phone, email,
    address_line_1, address_line_2, city, state, postal_code, country,
    is_public_physical_location, legal_structure_id, primary_category_id,
    status, is_suspended, is_test_account, internal_status, setup_status, setup_step,
    created_at, updated_at
)
SELECT
    'Suspended Test Services', 'suspended-test-services', 'Suspended Test Services',
    @suspended_user_id, '555-1103', 'suspended@test.com',
    '300 Suspended Test Way', NULL, 'Raleigh', 'NC', '27601', 'US',
    1, @legal_structure_id, @category_id,
    'active', 1, 1, 'suspended', 'complete', 'completed',
    NOW(), NOW()
WHERE @suspended_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM businesses WHERE slug = 'suspended-test-services'
  );

UPDATE businesses
SET business_name = 'Suspended Test Services',
    legal_name = 'Suspended Test Services',
    owner_user_id = @suspended_user_id,
    phone = '555-1103',
    email = 'suspended@test.com',
    address_line_1 = '300 Suspended Test Way',
    address_line_2 = NULL,
    city = 'Raleigh',
    state = 'NC',
    postal_code = '27601',
    country = 'US',
    is_public_physical_location = 1,
    legal_structure_id = @legal_structure_id,
    primary_category_id = @category_id,
    status = 'active',
    is_suspended = 1,
    is_test_account = 1,
    internal_status = 'suspended',
    setup_status = 'complete',
    setup_step = 'completed',
    updated_at = NOW()
WHERE slug = 'suspended-test-services';

SET @suspended_business_id := (SELECT id FROM businesses WHERE slug = 'suspended-test-services' LIMIT 1);

INSERT INTO business_users (business_id, user_id, role_id, status, is_owner, created_at, updated_at)
SELECT @suspended_business_id, @suspended_user_id, @business_owner_role_id, 'active', 1, NOW(), NOW()
WHERE @suspended_business_id IS NOT NULL
  AND @suspended_user_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    status = VALUES(status),
    is_owner = VALUES(is_owner),
    updated_at = NOW();

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE ur.user_id = @suspended_user_id
  AND r.scope = 'internal';

-- Select representative services for customer businesses.
INSERT IGNORE INTO business_sub_services (business_id, sub_service_id, created_at)
SELECT business_ids.business_id, ss.id, NOW()
FROM (
    SELECT @customer_business_id AS business_id
    UNION ALL SELECT @suspended_business_id
) business_ids
INNER JOIN sub_services ss ON ss.name IN ('General Service', 'Consultation', 'Other')
INNER JOIN categories c ON c.id = ss.category_id AND c.name = 'Other'
WHERE business_ids.business_id IS NOT NULL;

-- Active customer modules: 247SP plus included Lead Hub.
INSERT INTO business_modules (
    business_id, module_id, status, activated_at, deactivated_at,
    activated_by_user_id, activation_source, created_at, updated_at
)
SELECT business_ids.business_id, m.id, 'active', NOW(), NULL,
       business_ids.user_id, 'manual', NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_user_id AS user_id
    UNION ALL SELECT @trial_business_id, @trial_user_id
    UNION ALL SELECT @suspended_business_id, @suspended_user_id
) business_ids
INNER JOIN modules m ON m.module_key IN ('247sp', 'lead_hub')
WHERE business_ids.business_id IS NOT NULL
  AND business_ids.user_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    activated_at = VALUES(activated_at),
    deactivated_at = NULL,
    activated_by_user_id = VALUES(activated_by_user_id),
    activation_source = VALUES(activation_source),
    updated_at = NOW();

-- 247SP subscriptions use the existing 247sp plan.
INSERT INTO subscriptions (business_id, plan_id, status, started_at, cancelled_at, created_at)
SELECT subscription_seed.business_id, p.id, subscription_seed.status, NOW(), NULL, NOW()
FROM (
    SELECT @customer_business_id AS business_id, 'active' AS status
    UNION ALL SELECT @trial_business_id, 'trial'
    UNION ALL SELECT @suspended_business_id, 'past_due'
) subscription_seed
INNER JOIN plans p ON p.product_key = '247sp'
WHERE subscription_seed.business_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    started_at = COALESCE(started_at, VALUES(started_at)),
    cancelled_at = NULL;

-- Completed 247SP onboarding for the standard customer and suspended/past-due customer.
INSERT INTO `247sp_onboarding` (
    business_id, contact_name, setup_status, current_step, completed_at, created_at, updated_at
)
SELECT completed.business_id, completed.contact_name, 'complete', 'complete', NOW(), NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, 'Standard Customer' AS contact_name
    UNION ALL SELECT @suspended_business_id, 'Suspended Customer'
) completed
WHERE completed.business_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    contact_name = VALUES(contact_name),
    setup_status = VALUES(setup_status),
    current_step = VALUES(current_step),
    completed_at = COALESCE(completed_at, VALUES(completed_at)),
    updated_at = NOW();

SET @customer_onboarding_id := (
    SELECT id FROM `247sp_onboarding` WHERE business_id = @customer_business_id LIMIT 1
);
SET @suspended_onboarding_id := (
    SELECT id FROM `247sp_onboarding` WHERE business_id = @suspended_business_id LIMIT 1
);

INSERT INTO `247sp_website_configurations` (
    business_id, onboarding_id, primary_category_id, service_area_address,
    service_area_city, service_area_state, service_area_postal_code,
    service_area_business, website_status, created_at, updated_at
)
SELECT completed.business_id, completed.onboarding_id, @category_id, completed.address_line_1,
       'Raleigh', 'NC', '27601', 0, 'ready_for_build', NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_onboarding_id AS onboarding_id, '100 Customer Test Way' AS address_line_1
    UNION ALL SELECT @suspended_business_id, @suspended_onboarding_id, '300 Suspended Test Way'
) completed
WHERE completed.business_id IS NOT NULL
  AND completed.onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    primary_category_id = VALUES(primary_category_id),
    service_area_address = VALUES(service_area_address),
    service_area_city = VALUES(service_area_city),
    service_area_state = VALUES(service_area_state),
    service_area_postal_code = VALUES(service_area_postal_code),
    service_area_business = VALUES(service_area_business),
    website_status = VALUES(website_status),
    updated_at = NOW();

INSERT INTO `247sp_business_content` (
    business_id, onboarding_id, business_description, about_company,
    years_in_business, financing_available, special_offer, created_at, updated_at
)
SELECT completed.business_id, completed.onboarding_id,
       CONCAT(completed.business_name, ' is a staging test service business for customer workflow QA.'),
       CONCAT(completed.business_name, ' exists only for internal staging validation.'),
       5, 0, NULL, NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_onboarding_id AS onboarding_id, 'Customer Test Services' AS business_name
    UNION ALL SELECT @suspended_business_id, @suspended_onboarding_id, 'Suspended Test Services'
) completed
WHERE completed.business_id IS NOT NULL
  AND completed.onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    business_description = VALUES(business_description),
    about_company = VALUES(about_company),
    years_in_business = VALUES(years_in_business),
    financing_available = VALUES(financing_available),
    special_offer = VALUES(special_offer),
    updated_at = NOW();

INSERT INTO `247sp_service_pages` (
    business_id, onboarding_id, service_number, service_name, short_description, created_at, updated_at
)
SELECT completed.business_id, completed.onboarding_id, service_seed.service_number,
       service_seed.service_name, service_seed.short_description, NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_onboarding_id AS onboarding_id
    UNION ALL SELECT @suspended_business_id, @suspended_onboarding_id
) completed
CROSS JOIN (
    SELECT 1 AS service_number, 'General Service' AS service_name, 'Staging service page for validating standard service content.' AS short_description
    UNION ALL SELECT 2, 'Consultation', 'Staging service page for validating consultation workflow content.'
    UNION ALL SELECT 3, 'Priority Support', 'Staging service page for validating support-oriented website content.'
) service_seed
WHERE completed.business_id IS NOT NULL
  AND completed.onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    service_name = VALUES(service_name),
    short_description = VALUES(short_description),
    updated_at = NOW();

INSERT INTO `247sp_domain_selections` (
    business_id, onboarding_id, selection_type, domain_name, status, created_at, updated_at
)
SELECT completed.business_id, completed.onboarding_id, 'existing', completed.domain_name, 'pending', NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_onboarding_id AS onboarding_id, 'customer-test-services.example.com' AS domain_name
    UNION ALL SELECT @suspended_business_id, @suspended_onboarding_id, 'suspended-test-services.example.com'
) completed
WHERE completed.business_id IS NOT NULL
  AND completed.onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    selection_type = VALUES(selection_type),
    domain_name = VALUES(domain_name),
    status = VALUES(status),
    updated_at = NOW();

INSERT INTO `247sp_email_requests` (
    business_id, onboarding_id, primary_mailbox_name, status, created_at, updated_at
)
SELECT completed.business_id, completed.onboarding_id, 'info', 'pending', NOW(), NOW()
FROM (
    SELECT @customer_business_id AS business_id, @customer_onboarding_id AS onboarding_id
    UNION ALL SELECT @suspended_business_id, @suspended_onboarding_id
) completed
WHERE completed.business_id IS NOT NULL
  AND completed.onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    primary_mailbox_name = VALUES(primary_mailbox_name),
    status = VALUES(status),
    updated_at = NOW();

-- Incomplete 247SP onboarding state for trial@test.com.
INSERT INTO `247sp_onboarding` (
    business_id, contact_name, setup_status, current_step, completed_at, created_at, updated_at
)
SELECT @trial_business_id, 'Trial Customer', 'in_progress', 'services', NULL, NOW(), NOW()
WHERE @trial_business_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    contact_name = VALUES(contact_name),
    setup_status = VALUES(setup_status),
    current_step = VALUES(current_step),
    completed_at = NULL,
    updated_at = NOW();

SET @trial_onboarding_id := (
    SELECT id FROM `247sp_onboarding` WHERE business_id = @trial_business_id LIMIT 1
);

INSERT INTO `247sp_website_configurations` (
    business_id, onboarding_id, primary_category_id, service_area_address,
    service_area_city, service_area_state, service_area_postal_code,
    service_area_business, website_status, created_at, updated_at
)
SELECT @trial_business_id, @trial_onboarding_id, NULL, '200 Trial Test Way',
       'Raleigh', 'NC', '27601', 0, 'in_progress', NOW(), NOW()
WHERE @trial_business_id IS NOT NULL
  AND @trial_onboarding_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    onboarding_id = VALUES(onboarding_id),
    primary_category_id = VALUES(primary_category_id),
    service_area_address = VALUES(service_area_address),
    service_area_city = VALUES(service_area_city),
    service_area_state = VALUES(service_area_state),
    service_area_postal_code = VALUES(service_area_postal_code),
    service_area_business = VALUES(service_area_business),
    website_status = VALUES(website_status),
    updated_at = NOW();

DELETE FROM `247sp_service_pages`
WHERE business_id = @trial_business_id;

DELETE FROM `247sp_business_content`
WHERE business_id = @trial_business_id;

DELETE FROM `247sp_domain_selections`
WHERE business_id = @trial_business_id;

DELETE FROM `247sp_email_requests`
WHERE business_id = @trial_business_id;

COMMIT;

SELECT
    u.email,
    CONCAT_WS(' ', u.first_name, u.last_name) AS name,
    b.business_name,
    b.setup_status AS business_setup_status,
    onboarding.setup_status AS onboarding_status,
    onboarding.current_step AS onboarding_step,
    subscription.status AS subscription_status,
    CASE WHEN admin_roles.user_id IS NULL THEN 'no' ELSE 'yes' END AS internal_admin_access
FROM users u
LEFT JOIN business_users bu ON bu.user_id = u.id
LEFT JOIN businesses b ON b.id = bu.business_id
LEFT JOIN `247sp_onboarding` onboarding ON onboarding.business_id = b.id
LEFT JOIN subscriptions subscription ON subscription.business_id = b.id
LEFT JOIN plans p ON p.id = subscription.plan_id AND p.product_key = '247sp'
LEFT JOIN (
    SELECT DISTINCT ur.user_id
    FROM user_roles ur
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE r.scope = 'internal'
      AND r.name IN ('Super Admin', 'Admin')
) admin_roles ON admin_roles.user_id = u.id
WHERE u.email IN ('admin@test.com', 'customer@test.com', 'trial@test.com', 'suspended@test.com')
ORDER BY FIELD(u.email, 'admin@test.com', 'customer@test.com', 'trial@test.com', 'suspended@test.com');
