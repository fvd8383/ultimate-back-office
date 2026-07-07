<?php

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BusinessFoundation.php';
require_once __DIR__ . '/BillingFoundation.php';
require_once __DIR__ . '/SiteGenerator.php';
require_once __DIR__ . '/WebsiteManager.php';
require_once __DIR__ . '/LeadHub.php';

final class AdminPortal
{
    private const ADMIN_ROLE_NAMES = ['Super Admin', 'Admin'];
    private const MODULE_ORDER = ['247sp', 'lead_hub', 'emd', 'ssp', 'tuhwd', 'enterprise', 'full_os', 'kyn'];
    private const FULL_OS_INCLUDED_MODULE_KEYS = ['lead_hub', '247sp', 'emd', 'ssp', 'tuhwd', 'kyn'];

    public static function currentUserIsAdmin(int $userId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::ADMIN_ROLE_NAMES), '?'));
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM user_roles ur
             INNER JOIN users u ON u.id = ur.user_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ?
               AND u.status = 'active'
               AND r.scope = 'internal'
               AND r.name IN ({$placeholders})"
        );
        $statement->execute(array_merge([$userId], self::ADMIN_ROLE_NAMES));

        return (int) $statement->fetchColumn() > 0;
    }

    public static function dashboardMetrics(): array
    {
        $connection = Database::connection();

        return [
            'total_users' => (int) $connection->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'total_businesses' => (int) $connection->query('SELECT COUNT(*) FROM businesses')->fetchColumn(),
            'total_websites' => (int) $connection->query('SELECT COUNT(*) FROM `247sp_generated_websites`')->fetchColumn(),
            'ready_for_build' => (int) $connection->query("SELECT COUNT(*) FROM `247sp_website_configurations` WHERE website_status = 'ready_for_build'")->fetchColumn(),
            'generated_websites' => (int) $connection->query("SELECT COUNT(*) FROM `247sp_generated_websites` WHERE status = 'generated'")->fetchColumn(),
        ];
    }

    public static function recentSignups(int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, status, created_at
             FROM users
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function recentBusinesses(int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id, b.business_name, b.email, b.phone, b.status, b.internal_status, b.created_at,
                    u.first_name AS owner_first_name, u.last_name AS owner_last_name
             FROM businesses b
             LEFT JOIN users u ON u.id = b.owner_user_id
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function recentWebsiteGenerations(int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            'SELECT gw.id, gw.business_id, gw.status, gw.generated_at,
                    b.business_name, t.name AS template_name
             FROM `247sp_generated_websites` gw
             INNER JOIN businesses b ON b.id = gw.business_id
             INNER JOIN `247sp_templates` t ON t.id = gw.template_id
             ORDER BY gw.generated_at DESC, gw.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function users(): array
    {
        return Database::connection()->query(
            'SELECT id, first_name, last_name, email, status, created_at
             FROM users
             ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    public static function user(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, phone, status, created_at, updated_at
             FROM users
             WHERE id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public static function linkedBusinessesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT b.id, b.business_name, b.status, b.internal_status, bu.is_owner, bu.status AS link_status, r.name AS role_name
             FROM business_users bu
             INNER JOIN businesses b ON b.id = bu.business_id
             LEFT JOIN roles r ON r.id = bu.role_id
             WHERE bu.user_id = :user_id
             ORDER BY b.business_name ASC'
        );
        $statement->execute(['user_id' => $userId]);

        $businesses = $statement->fetchAll();
        foreach ($businesses as &$business) {
            $business['active_modules'] = self::activeModulesForBusiness((int) $business['id']);
        }

        return $businesses;
    }

    public static function activeModulesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT m.module_key, m.name
             FROM business_users bu
             INNER JOIN business_modules bm ON bm.business_id = bu.business_id
             INNER JOIN modules m ON m.id = bm.module_id
             WHERE bu.user_id = :user_id
               AND bu.status = :link_status
               AND bm.status = :module_status
             ORDER BY m.name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'link_status' => 'active',
            'module_status' => 'active',
        ]);

        return $statement->fetchAll();
    }

    public static function websiteCountForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(DISTINCT gw.id)
             FROM business_users bu
             INNER JOIN `247sp_generated_websites` gw ON gw.business_id = bu.business_id
             WHERE bu.user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public static function businesses(): array
    {
        $statement = Database::connection()->query(
            "SELECT b.id, b.business_name, b.email, b.phone, b.status, b.internal_status,
                    b.is_suspended, b.is_test_account, b.created_at,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS owner_name,
                    onboarding.setup_status AS onboarding_status,
                    config.website_status,
                    GROUP_CONCAT(DISTINCT m.name ORDER BY FIELD(m.module_key, '247sp', 'lead_hub', 'emd', 'ssp', 'tuhwd', 'enterprise', 'full_os', 'kyn') SEPARATOR ', ') AS active_modules
             FROM businesses b
             LEFT JOIN users u ON u.id = b.owner_user_id
             LEFT JOIN `247sp_onboarding` onboarding ON onboarding.business_id = b.id
             LEFT JOIN `247sp_website_configurations` config ON config.business_id = b.id
             LEFT JOIN business_modules bm ON bm.business_id = b.id AND bm.status = 'active'
             LEFT JOIN modules m ON m.id = bm.module_id
             GROUP BY b.id, b.business_name, b.email, b.phone, b.status, b.internal_status,
                      b.is_suspended, b.is_test_account, b.created_at, owner_name,
                      onboarding.setup_status, config.website_status
             ORDER BY b.created_at DESC, b.id DESC"
        );

        return $statement->fetchAll();
    }

    public static function business(int $businessId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT b.*,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS owner_name,
                    onboarding.setup_status AS onboarding_status,
                    onboarding.current_step AS onboarding_step,
                    onboarding.completed_at AS onboarding_completed_at,
                    config.website_status,
                    config.service_area_city,
                    config.service_area_state,
                    config.service_area_postal_code,
                    config.service_area_business,
                    config.service_area_radius_miles,
                    config.service_area_radius_is_custom
             FROM businesses b
             LEFT JOIN users u ON u.id = b.owner_user_id
             LEFT JOIN `247sp_onboarding` onboarding ON onboarding.business_id = b.id
             LEFT JOIN `247sp_website_configurations` config ON config.business_id = b.id
             WHERE b.id = :business_id
             LIMIT 1"
        );
        $statement->execute(['business_id' => $businessId]);
        $business = $statement->fetch();

        return $business ?: null;
    }

    public static function activeModulesForBusiness(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT m.id, m.module_key, m.name, bm.status, bm.activation_source, bm.activated_at
             FROM modules m
             INNER JOIN business_modules bm ON bm.module_id = m.id
             WHERE bm.business_id = :business_id
               AND bm.status = 'active'
             ORDER BY FIELD(m.module_key, '247sp', 'lead_hub', 'emd', 'ssp', 'tuhwd', 'enterprise', 'full_os', 'kyn'), m.name"
        );
        $statement->execute(['business_id' => $businessId]);

        return $statement->fetchAll();
    }

    public static function allManagedModules(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::MODULE_ORDER), '?'));
        $statement = Database::connection()->prepare(
            "SELECT id, module_key, name
             FROM modules
             WHERE is_active = 1
               AND module_key IN ({$placeholders})
             ORDER BY FIELD(module_key, '247sp', 'lead_hub', 'emd', 'ssp', 'tuhwd', 'enterprise', 'full_os', 'kyn')"
        );
        $statement->execute(self::MODULE_ORDER);

        return $statement->fetchAll();
    }

    public static function setModuleStatus(int $businessId, int $adminUserId, string $moduleKey, bool $enabled): void
    {
        $moduleKey = trim($moduleKey);
        $validKeys = array_column(self::allManagedModules(), 'module_key');

        if (!in_array($moduleKey, $validKeys, true)) {
            throw new InvalidArgumentException('Unsupported module selection.');
        }

        if (!$enabled) {
            self::deactivateModule($businessId, $moduleKey);

            if ($moduleKey === 'ssp') {
                self::deactivateModule($businessId, 'kyn');
            }

            self::logActivity($businessId, $adminUserId, 'admin_module_disabled', 'Admin disabled ' . $moduleKey);
            return;
        }

        $keysToEnable = [$moduleKey];

        if ($moduleKey === 'full_os' || $moduleKey === 'enterprise') {
            $keysToEnable = array_merge($keysToEnable, self::FULL_OS_INCLUDED_MODULE_KEYS);
            if ($moduleKey === 'enterprise') {
                $keysToEnable[] = 'full_os';
            }
        } elseif ($moduleKey === 'kyn') {
            $keysToEnable = ['lead_hub', 'ssp', 'kyn'];
        } elseif ($moduleKey !== 'lead_hub') {
            $keysToEnable[] = 'lead_hub';
        }

        foreach (array_values(array_unique($keysToEnable)) as $key) {
            self::activateModule($businessId, $adminUserId, $key, $moduleKey === $key ? 'admin' : $moduleKey);
        }

        self::logActivity($businessId, $adminUserId, 'admin_module_enabled', 'Admin enabled ' . $moduleKey);
    }

    public static function setBusinessFlags(int $businessId, int $adminUserId, array $flags): void
    {
        $isSuspended = isset($flags['is_suspended']) ? (int) (bool) $flags['is_suspended'] : 0;
        $isTestAccount = isset($flags['is_test_account']) ? (int) (bool) $flags['is_test_account'] : 0;
        $internalStatus = $isSuspended ? 'suspended' : 'active';
        $publicStatus = $isSuspended ? 'suspended' : 'active';

        $statement = Database::connection()->prepare(
            'UPDATE businesses
             SET is_suspended = :is_suspended,
                 is_test_account = :is_test_account,
                 internal_status = :internal_status,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :business_id'
        );
        $statement->execute([
            'is_suspended' => $isSuspended,
            'is_test_account' => $isTestAccount,
            'internal_status' => $internalStatus,
            'status' => $publicStatus,
            'business_id' => $businessId,
        ]);

        self::logActivity($businessId, $adminUserId, 'admin_business_flags_updated', 'Admin updated business flags');
    }

    public static function addNote(int $businessId, ?int $userId, int $adminUserId, string $note): void
    {
        $note = trim($note);

        if ($note === '') {
            throw new InvalidArgumentException('Admin note cannot be empty.');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO admin_notes (business_id, user_id, admin_user_id, note, created_at)
             VALUES (:business_id, :user_id, :admin_user_id, :note, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'admin_user_id' => $adminUserId,
            'note' => $note,
        ]);

        self::logActivity($businessId, $adminUserId, 'admin_note_added', 'Admin note added');
    }

    public static function notesForBusiness(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT n.*, au.first_name AS admin_first_name, au.last_name AS admin_last_name
             FROM admin_notes n
             INNER JOIN users au ON au.id = n.admin_user_id
             WHERE n.business_id = :business_id
             ORDER BY n.created_at DESC, n.id DESC'
        );
        $statement->execute(['business_id' => $businessId]);

        return $statement->fetchAll();
    }

    public static function recent247spWebsiteLeadsForBusiness(int $businessId, int $limit = 10): array
    {
        return LeadHub::recent247spWebsiteLeads($businessId, $limit);
    }

    public static function websites(): array
    {
        return Database::connection()->query(
            'SELECT gw.id, gw.business_id, gw.status, gw.generated_at,
                    b.business_name, t.name AS template_name
             FROM `247sp_generated_websites` gw
             INNER JOIN businesses b ON b.id = gw.business_id
             INNER JOIN `247sp_templates` t ON t.id = gw.template_id
             ORDER BY gw.generated_at DESC, gw.id DESC'
        )->fetchAll();
    }

    public static function website(int $websiteId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT gw.*, b.business_name, b.email, b.phone, t.name AS template_name, t.template_key
             FROM `247sp_generated_websites` gw
             INNER JOIN businesses b ON b.id = gw.business_id
             INNER JOIN `247sp_templates` t ON t.id = gw.template_id
             WHERE gw.id = :website_id
             LIMIT 1'
        );
        $statement->execute(['website_id' => $websiteId]);
        $website = $statement->fetch();

        return $website ?: null;
    }

    public static function websiteForBusiness(int $businessId): ?array
    {
        return SiteGenerator::websiteForBusiness($businessId);
    }

    public static function websiteAssetsSummary(int $websiteId): array
    {
        $website = self::website($websiteId);
        $businessId = (int) ($website['business_id'] ?? 0);
        $branding = $businessId > 0 ? WebsiteManager::brandingForBusiness($businessId) : [];
        $serviceImages = $businessId > 0 ? WebsiteManager::serviceImagesForBusiness($businessId) : [];
        $imageCount = count(array_filter([
            $branding['hero_image_path'] ?? '',
            $branding['about_image_path'] ?? '',
        ])) + count(array_filter($serviceImages));

        return [
            'logo_assigned' => ($branding['logo_path'] ?? '') !== '',
            'primary_color_assigned' => ($branding['primary_color'] ?? '') !== '',
            'secondary_color_assigned' => ($branding['secondary_color'] ?? '') !== '',
            'image_count' => $imageCount,
        ];
    }

    public static function websiteBrandingForBusiness(int $businessId): array
    {
        return [
            'branding' => WebsiteManager::brandingForBusiness($businessId),
            'service_images' => WebsiteManager::serviceImagesForBusiness($businessId),
        ];
    }

    public static function generateWebsiteForBusiness(int $businessId, int $adminUserId, bool $regenerate): array
    {
        if (!TwentyFourSevenSalesPartner::businessHasAccess($businessId)) {
            throw new InvalidArgumentException('24/7 Sales Partner must be active before a website can be generated.');
        }

        return $regenerate
            ? SiteGenerator::regenerateWebsite($businessId, $adminUserId)
            : SiteGenerator::generateWebsite($businessId, $adminUserId);
    }

    public static function statusLabel(?string $status): string
    {
        $status = (string) $status;

        if ($status === '') {
            return 'Not Started';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    private static function activateModule(int $businessId, int $adminUserId, string $moduleKey, string $activationSource): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO business_modules (
                business_id, module_id, status, activated_at, deactivated_at,
                activated_by_user_id, activation_source, created_at, updated_at
             )
             SELECT :business_id, id, :status, NOW(), NULL,
                    :activated_by_user_id, :activation_source, NOW(), NOW()
             FROM modules
             WHERE module_key = :module_key
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                activated_at = VALUES(activated_at),
                deactivated_at = NULL,
                activated_by_user_id = VALUES(activated_by_user_id),
                activation_source = VALUES(activation_source),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'status' => 'active',
            'activated_by_user_id' => $adminUserId,
            'activation_source' => $activationSource,
            'module_key' => $moduleKey,
        ]);

        if ($moduleKey === '247sp') {
            BillingFoundation::ensureSubscriptionForBusiness($businessId);
        }
    }

    private static function deactivateModule(int $businessId, string $moduleKey): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE business_modules bm
             INNER JOIN modules m ON m.id = bm.module_id
             SET bm.status = :status,
                 bm.deactivated_at = NOW(),
                 bm.updated_at = NOW()
             WHERE bm.business_id = :business_id
               AND m.module_key = :module_key'
        );
        $statement->execute([
            'status' => 'inactive',
            'business_id' => $businessId,
            'module_key' => $moduleKey,
        ]);
    }

    private static function logActivity(int $businessId, int $adminUserId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, module_key, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :module_key, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $adminUserId,
            'module_key' => 'admin',
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }
}
