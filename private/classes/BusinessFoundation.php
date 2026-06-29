<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BillingFoundation.php';

final class BusinessFoundation
{
    private const MODULE_NAMES = [
        '247sp' => '24/7 Sales Partner',
        'emd' => 'EMD Network',
        'ssp' => 'Super Simple Payments',
        'tuhwd' => 'Tell Us How We Did',
        'kyn' => 'Know Your Numbers',
        'full_os' => 'Full OS',
        'enterprise' => 'Enterprise',
    ];

    private const FULL_OS_INCLUDED_MODULE_KEYS = ['lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn'];
    private const CUSTOMER_VISIBLE_MODULE_KEYS = ['lead_hub', '247sp'];
    private const CUSTOMER_SELECTABLE_MODULE_KEYS = ['247sp'];

    public static function legalStructures(): array
    {
        return self::fetchActiveRows('legal_structures');
    }

    public static function categories(): array
    {
        return self::fetchActiveRows('categories');
    }

    public static function subServices(?int $categoryId = null): array
    {
        $sql = 'SELECT id, category_id, name FROM sub_services WHERE is_active = 1';
        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $sql .= ' ORDER BY name ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public static function businessForUser(int $businessId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.*
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE b.id = :business_id
               AND bu.user_id = :user_id
               AND bu.status = :link_status
             LIMIT 1'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'link_status' => 'active',
        ]);

        $business = $statement->fetch();

        return $business ?: null;
    }

    public static function firstBusinessForUser(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.*
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.created_at ASC, b.business_name ASC
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        $business = $statement->fetch();

        return $business ?: null;
    }

    public static function businessesForDashboard(int $userId, bool $normalizeEnterprise = true): array
    {
        if ($normalizeEnterprise) {
            self::ensureEnterpriseUserBusinessesUseFullOs($userId);
        }

        $statement = Database::connection()->prepare(
            'SELECT b.id,
                    b.business_name,
                    b.slug,
                    b.status,
                    b.setup_status,
                    b.setup_step,
                    b.city,
                    b.state,
                    bu.is_owner,
                    r.name AS role_name
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             LEFT JOIN roles r ON r.id = bu.role_id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
             ORDER BY b.business_name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        $businesses = $statement->fetchAll();

        foreach ($businesses as &$business) {
            $business['active_modules'] = self::customerActiveModules((int) $business['id']);
            $business['has_enterprise'] = self::businessHasActiveModule((int) $business['id'], 'enterprise');
            $business['profile_completion'] = self::profileCompletion($business);
        }

        return $businesses;
    }

    public static function selectedSubServiceIds(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT sub_service_id FROM business_sub_services WHERE business_id = :business_id'
        );
        $statement->execute(['business_id' => $businessId]);

        return array_map('intval', array_column($statement->fetchAll(), 'sub_service_id'));
    }

    public static function selectedServices(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ss.id, ss.name, c.name AS category_name
             FROM business_sub_services bss
             INNER JOIN sub_services ss ON ss.id = bss.sub_service_id
             INNER JOIN categories c ON c.id = ss.category_id
             WHERE bss.business_id = :business_id
             ORDER BY c.name ASC, ss.name ASC'
        );
        $statement->execute(['business_id' => $businessId]);

        return $statement->fetchAll();
    }

    public static function selectedCustomServices(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT bcs.id, bcs.service_name AS name, c.name AS category_name, bcs.category_id
             FROM business_custom_services bcs
             INNER JOIN categories c ON c.id = bcs.category_id
             WHERE bcs.business_id = :business_id
             ORDER BY c.name ASC, bcs.service_name ASC'
        );
        $statement->execute(['business_id' => $businessId]);

        return $statement->fetchAll();
    }

    public static function activeModules(int $businessId): array
    {
        if (self::businessHasActiveModule($businessId, 'enterprise')) {
            self::activateEnterpriseFullOsModules($businessId, null);
        }

        $statement = Database::connection()->prepare(
            "SELECT m.module_key, m.name, bm.activation_source
             FROM business_modules bm
             INNER JOIN modules m ON m.id = bm.module_id
             WHERE bm.business_id = :business_id
               AND bm.status = :status
               AND m.module_key IN ('lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn', 'full_os')
             ORDER BY FIELD(m.module_key, 'lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn', 'full_os', 'enterprise'), m.name"
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function customerActiveModules(int $businessId): array
    {
        $placeholders = implode(',', array_fill(0, count(self::CUSTOMER_VISIBLE_MODULE_KEYS), '?'));
        $statement = Database::connection()->prepare(
            "SELECT m.module_key, m.name, bm.activation_source
             FROM business_modules bm
             INNER JOIN modules m ON m.id = bm.module_id
             WHERE bm.business_id = ?
               AND bm.status = 'active'
               AND m.module_key IN ({$placeholders})
             ORDER BY FIELD(m.module_key, 'lead_hub', '247sp'), m.name"
        );
        $statement->execute(array_merge([$businessId], self::CUSTOMER_VISIBLE_MODULE_KEYS));

        return $statement->fetchAll();
    }

    public static function availableModules(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::CUSTOMER_SELECTABLE_MODULE_KEYS), '?'));
        $statement = Database::connection()->prepare(
            "SELECT module_key, name
             FROM modules
             WHERE is_active = 1
               AND module_key IN ({$placeholders})
             ORDER BY FIELD(module_key, '247sp')"
        );
        $statement->execute(self::CUSTOMER_SELECTABLE_MODULE_KEYS);

        return $statement->fetchAll();
    }

    public static function fullOsIncludedModules(): array
    {
        $statement = Database::connection()->query(
            "SELECT module_key, name
             FROM modules
             WHERE is_active = 1
               AND module_key IN ('lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn')
             ORDER BY FIELD(module_key, 'lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn')"
        );

        return $statement->fetchAll();
    }

    public static function userHasEnterpriseAccess(int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM business_users bu
             INNER JOIN business_modules bm ON bm.business_id = bu.business_id
             INNER JOIN modules m ON m.id = bm.module_id
             INNER JOIN businesses b ON b.id = bu.business_id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status
               AND bm.status = :module_status
               AND m.module_key = :module_key'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
            'module_status' => 'active',
            'module_key' => 'enterprise',
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function saveBusinessInfo(int $userId, array $input, ?int $businessId = null): int
    {
        $data = [
            'business_name' => trim((string) ($input['business_name'] ?? '')),
            'legal_name' => trim((string) ($input['legal_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address_line_1' => trim((string) ($input['address_line_1'] ?? '')),
            'address_line_2' => trim((string) ($input['address_line_2'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'country' => trim((string) ($input['country'] ?? 'US')),
            'is_public_physical_location' => isset($input['is_public_physical_location']) ? 1 : 0,
            'legal_structure_id' => self::nullableInt($input['legal_structure_id'] ?? null),
            'legal_structure_other' => self::normalizeOptionalText($input['legal_structure_other'] ?? ''),
            'business_started_on' => self::normalizeOptionalDate($input['business_started_on'] ?? ''),
        ];

        $slug = self::uniqueSlug($data['business_name'], $businessId);

        if ($businessId === null) {
            $statement = Database::connection()->prepare(
                'INSERT INTO businesses (
                    business_name, slug, legal_name, owner_user_id, phone, email,
                    address_line_1, address_line_2, city, state, postal_code, country,
                    is_public_physical_location, legal_structure_id, legal_structure_other, business_started_on, status, setup_status, setup_step,
                    created_at, updated_at
                 ) VALUES (
                    :business_name, :slug, :legal_name, :owner_user_id, :phone, :email,
                    :address_line_1, :address_line_2, :city, :state, :postal_code, :country,
                    :is_public_physical_location, :legal_structure_id, :legal_structure_other, :business_started_on, :status, :setup_status, :setup_step,
                    NOW(), NOW()
                 )'
            );
            $statement->execute($data + [
                'slug' => $slug,
                'owner_user_id' => $userId,
                'status' => 'active',
                'setup_status' => 'incomplete',
                'setup_step' => 'services',
            ]);

            $businessId = (int) Database::connection()->lastInsertId();
            self::linkOwner($businessId, $userId);

            if (self::userHasEnterpriseAccess($userId)) {
                self::activateEnterpriseFullOsModules($businessId, $userId);
            }

            self::logActivity($businessId, $userId, 'business_created', 'Business created');

            return $businessId;
        }

        $statement = Database::connection()->prepare(
            'UPDATE businesses
             SET business_name = :business_name,
                 slug = :slug,
                 legal_name = :legal_name,
                 phone = :phone,
                 email = :email,
                 address_line_1 = :address_line_1,
                 address_line_2 = :address_line_2,
                 city = :city,
                 state = :state,
                 postal_code = :postal_code,
                 country = :country,
                 is_public_physical_location = :is_public_physical_location,
                 legal_structure_id = :legal_structure_id,
                 legal_structure_other = :legal_structure_other,
                 business_started_on = :business_started_on,
                 setup_status = IF(setup_status = :complete_status, setup_status, :incomplete_status),
                 setup_step = IF(setup_step = :completed_step, setup_step, :services_step),
                 updated_at = NOW()
             WHERE id = :business_id'
        );
        $statement->execute($data + [
            'slug' => $slug,
            'business_id' => $businessId,
            'complete_status' => 'complete',
            'incomplete_status' => 'incomplete',
            'completed_step' => 'completed',
            'services_step' => 'services',
        ]);

        self::logActivity($businessId, $userId, 'business_updated', 'Business information updated');

        if (self::userHasEnterpriseAccess($userId)) {
            self::activateEnterpriseFullOsModules($businessId, $userId);
        }

        return $businessId;
    }

    public static function saveServices(int $businessId, int $userId, int $categoryId, array $subServiceIds, string $customService = ''): void
    {
        $validIds = self::validSubServiceIds($categoryId, $subServiceIds);
        $customService = self::normalizeOptionalText($customService);

        Database::connection()->beginTransaction();

        try {
            $statement = Database::connection()->prepare(
                'UPDATE businesses
                 SET primary_category_id = :category_id,
                     setup_status = IF(setup_status = :complete_status, setup_status, :incomplete_status),
                     setup_step = IF(setup_step = :completed_step, setup_step, :modules_step),
                     updated_at = NOW()
                 WHERE id = :business_id'
            );
            $statement->execute([
                'category_id' => $categoryId,
                'business_id' => $businessId,
                'complete_status' => 'complete',
                'incomplete_status' => 'incomplete',
                'completed_step' => 'completed',
                'modules_step' => 'modules',
            ]);

            $delete = Database::connection()->prepare('DELETE FROM business_sub_services WHERE business_id = :business_id');
            $delete->execute(['business_id' => $businessId]);

            $insert = Database::connection()->prepare(
                'INSERT INTO business_sub_services (business_id, sub_service_id, created_at)
                 VALUES (:business_id, :sub_service_id, NOW())'
            );

            foreach ($validIds as $subServiceId) {
                $insert->execute([
                    'business_id' => $businessId,
                    'sub_service_id' => $subServiceId,
                ]);
            }

            $deleteCustom = Database::connection()->prepare('DELETE FROM business_custom_services WHERE business_id = :business_id');
            $deleteCustom->execute(['business_id' => $businessId]);

            if ($customService !== '') {
                $customInsert = Database::connection()->prepare(
                    'INSERT INTO business_custom_services (business_id, category_id, service_name, created_at, updated_at)
                     VALUES (:business_id, :category_id, :service_name, NOW(), NOW())'
                );
                $customInsert->execute([
                    'business_id' => $businessId,
                    'category_id' => $categoryId,
                    'service_name' => $customService,
                ]);
            }

            self::logActivity($businessId, $userId, 'business_services_updated', 'Business services updated');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function saveModules(int $businessId, int $userId, array $selectedKeys, string $packageType = 'modular', bool $forceFullOs = false): void
    {
        $enterpriseForced = $forceFullOs || self::businessHasActiveModule($businessId, 'enterprise');

        if ($enterpriseForced) {
            $moduleSources = self::enterpriseFullOsModuleSources();
        } elseif ($packageType === 'full_os') {
            $moduleSources = self::moduleSources(['full_os']);
        } else {
            $moduleSources = self::moduleSources($selectedKeys);
        }

        Database::connection()->beginTransaction();

        try {
            $deactivate = Database::connection()->prepare(
                'UPDATE business_modules
                 SET status = :inactive_status, deactivated_at = NOW(), updated_at = NOW()
                 WHERE business_id = :business_id
                   AND module_id NOT IN (
                       SELECT id FROM modules WHERE module_key = :enterprise_module_key
                   )'
            );
            $deactivate->execute([
                'inactive_status' => 'inactive',
                'business_id' => $businessId,
                'enterprise_module_key' => 'enterprise',
            ]);

            foreach ($moduleSources as $moduleKey => $source) {
                self::activateModule($businessId, $userId, $moduleKey, $source);
            }

            $statement = Database::connection()->prepare(
                'UPDATE businesses
                 SET setup_status = IF(setup_status = :complete_status, setup_status, :incomplete_status),
                     setup_step = IF(setup_step = :completed_step_check, setup_step, :completed_step_value),
                     updated_at = NOW()
                 WHERE id = :business_id'
            );
            $statement->execute([
                'complete_status' => 'complete',
                'incomplete_status' => 'incomplete',
                'completed_step_check' => 'completed',
                'completed_step_value' => 'completed',
                'business_id' => $businessId,
            ]);

            self::logActivity($businessId, $userId, 'business_modules_updated', 'Business modules updated');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function setEnterpriseAccessForUser(int $userId, int $businessId, bool $enabled): bool
    {
        if (self::businessForUser($businessId, $userId) === null) {
            return false;
        }

        if ($enabled) {
            self::activateModule($businessId, $userId, 'enterprise', 'enterprise');
            self::ensureEnterpriseUserBusinessesUseFullOs($userId);
            return true;
        }

        $statement = Database::connection()->prepare(
            'UPDATE business_modules bm
             INNER JOIN modules m ON m.id = bm.module_id
             INNER JOIN business_users bu ON bu.business_id = bm.business_id
             SET bm.status = :inactive_status,
                 bm.deactivated_at = NOW(),
                 bm.updated_at = NOW()
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND m.module_key = :module_key'
        );
        $statement->execute([
            'inactive_status' => 'inactive',
            'user_id' => $userId,
            'link_status' => 'active',
            'module_key' => 'enterprise',
        ]);

        return true;
    }

    public static function completeOnboarding(int $businessId, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE businesses
             SET setup_status = :setup_status,
                 setup_step = :setup_step,
                 updated_at = NOW()
             WHERE id = :business_id'
        );
        $statement->execute([
            'setup_status' => 'complete',
            'setup_step' => 'completed',
            'business_id' => $businessId,
        ]);

        self::logActivity($businessId, $userId, 'business_onboarding_completed', 'Business onboarding completed');
    }

    public static function resetOnboardingForTesting(int $businessId, int $userId): bool
    {
        if (self::businessForUser($businessId, $userId) === null) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'UPDATE businesses
             SET setup_status = :setup_status,
                 setup_step = :setup_step,
                 updated_at = NOW()
             WHERE id = :business_id'
        );
        $statement->execute([
            'setup_status' => 'incomplete',
            'setup_step' => 'business_info',
            'business_id' => $businessId,
        ]);

        return true;
    }

    public static function removeModuleAssignmentsForTesting(int $businessId, int $userId): bool
    {
        if (self::businessForUser($businessId, $userId) === null) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'DELETE FROM business_modules WHERE business_id = :business_id'
        );
        $statement->execute(['business_id' => $businessId]);

        return true;
    }

    public static function leadHubSummary(int $businessId): array
    {
        $connection = Database::connection();

        $contacts = $connection->prepare('SELECT COUNT(*) FROM contacts WHERE business_id = :business_id');
        $contacts->execute(['business_id' => $businessId]);

        $tasks = $connection->prepare('SELECT COUNT(*) FROM tasks WHERE business_id = :business_id');
        $tasks->execute(['business_id' => $businessId]);

        $activity = $connection->prepare(
            'SELECT activity_type, subject, description, created_at
             FROM activity_logs
             WHERE business_id = :business_id
             ORDER BY created_at DESC, id DESC
             LIMIT 5'
        );
        $activity->execute(['business_id' => $businessId]);

        return [
            'contact_count' => (int) $contacts->fetchColumn(),
            'task_count' => (int) $tasks->fetchColumn(),
            'recent_activity' => $activity->fetchAll(),
        ];
    }

    public static function profileCompletion(array $business): int
    {
        if (($business['setup_status'] ?? '') === 'complete') {
            return 100;
        }

        switch ($business['setup_step'] ?? '') {
            case 'services':
                return 33;
            case 'modules':
                return 66;
            case 'completed':
                return 90;
            default:
                return 10;
        }
    }

    public static function moduleSources(array $selectedKeys): array
    {
        $selected = array_values(array_unique(array_filter(array_map('strval', $selectedKeys))));
        $sources = [];

        foreach ($selected as $moduleKey) {
            if (!array_key_exists($moduleKey, self::MODULE_NAMES)) {
                continue;
            }

            if ($moduleKey === 'enterprise') {
                continue;
            }

            $sources[$moduleKey] = 'manual';
        }

        if (isset($sources['full_os'])) {
            foreach (['lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn'] as $moduleKey) {
                $sources[$moduleKey] = 'full_os';
            }
        } elseif (count($sources) > 0) {
            if (isset($sources['kyn']) && !isset($sources['ssp'])) {
                $sources['ssp'] = 'manual';
            }

            $sources['lead_hub'] = 'manual';
        }

        return $sources;
    }

    public static function packageTypeFromActiveModules(array $activeModules): string
    {
        foreach ($activeModules as $module) {
            if (($module['module_key'] ?? '') === 'full_os') {
                return 'full_os';
            }
        }

        return 'modular';
    }

    public static function selectedModuleKeysFromActiveModules(array $activeModules): array
    {
        return array_values(array_filter(array_map(
            static fn (array $module): string => (string) $module['module_key'],
            $activeModules
        ), static fn (string $moduleKey): bool => $moduleKey !== 'lead_hub'));
    }

    private static function fetchActiveRows(string $table): array
    {
        $statement = Database::connection()->query(
            "SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
        );

        return $statement->fetchAll();
    }

    private static function nullableInt($value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private static function normalizeOptionalText($value): string
    {
        return substr(trim((string) $value), 0, 150);
    }

    private static function normalizeOptionalDate($value): ?string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }

    private static function linkOwner(int $businessId, int $userId): void
    {
        $role = Database::connection()->prepare(
            'SELECT id FROM roles WHERE name = :role_name AND scope = :role_scope LIMIT 1'
        );
        $role->execute([
            'role_name' => 'Owner',
            'role_scope' => 'business',
        ]);
        $roleId = $role->fetchColumn() ?: null;

        $statement = Database::connection()->prepare(
            'INSERT INTO business_users (business_id, user_id, role_id, status, is_owner, created_at, updated_at)
             VALUES (:business_id, :user_id, :role_id, :status, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), is_owner = 1, updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'status' => 'active',
        ]);
    }

    public static function ensureEnterpriseUserBusinessesUseFullOs(int $userId): void
    {
        if (!self::userHasEnterpriseAccess($userId)) {
            return;
        }

        $statement = Database::connection()->prepare(
            'SELECT b.id
             FROM businesses b
             INNER JOIN business_users bu ON bu.business_id = b.id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND b.status = :business_status'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'business_status' => 'active',
        ]);

        foreach ($statement->fetchAll() as $business) {
            self::activateEnterpriseFullOsModules((int) $business['id'], $userId);
        }
    }

    private static function activateEnterpriseFullOsModules(int $businessId, ?int $userId): void
    {
        foreach (self::enterpriseFullOsModuleSources() as $moduleKey => $source) {
            self::activateModule($businessId, $userId, $moduleKey, $source);
        }
    }

    private static function enterpriseFullOsModuleSources(): array
    {
        $sources = ['full_os' => 'enterprise'];

        foreach (self::FULL_OS_INCLUDED_MODULE_KEYS as $moduleKey) {
            $sources[$moduleKey] = 'full_os';
        }

        return $sources;
    }

    private static function activateModule(int $businessId, ?int $userId, string $moduleKey, string $activationSource): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO business_modules (
                business_id, module_id, status, activated_at, deactivated_at,
                activated_by_user_id, activation_source, created_at, updated_at
             )
             SELECT :business_id, id, :active_status, NOW(), NULL,
                    :activated_by_user_id, :activation_source, NOW(), NOW()
             FROM modules
             WHERE module_key = :module_key
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                activated_at = VALUES(activated_at),
                deactivated_at = NULL,
                activated_by_user_id = COALESCE(VALUES(activated_by_user_id), activated_by_user_id),
                activation_source = VALUES(activation_source),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'active_status' => 'active',
            'activated_by_user_id' => $userId,
            'activation_source' => $activationSource,
            'module_key' => $moduleKey,
        ]);

        if ($moduleKey === '247sp') {
            BillingFoundation::ensureSubscriptionForBusiness($businessId);
        }
    }

    private static function businessHasActiveModule(int $businessId, string $moduleKey): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM business_modules bm
             INNER JOIN modules m ON m.id = bm.module_id
             WHERE bm.business_id = :business_id
               AND bm.status = :status
               AND m.module_key = :module_key'
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'active',
            'module_key' => $moduleKey,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : 'business';
    }

    private static function uniqueSlug(string $businessName, ?int $ignoreBusinessId = null): string
    {
        $base = self::slugify($businessName);
        $slug = $base;
        $suffix = 2;

        while (self::slugExists($slug, $ignoreBusinessId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreBusinessId): bool
    {
        $sql = 'SELECT COUNT(*) FROM businesses WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreBusinessId !== null) {
            $sql .= ' AND id <> :business_id';
            $params['business_id'] = $ignoreBusinessId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function validSubServiceIds(int $categoryId, array $subServiceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $subServiceIds))));

        if (count($ids) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT id FROM sub_services WHERE category_id = ? AND is_active = 1 AND id IN ({$placeholders})"
        );
        $statement->execute(array_merge([$categoryId], $ids));

        return array_map('intval', array_column($statement->fetchAll(), 'id'));
    }

    private static function logActivity(int $businessId, int $userId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }
}
