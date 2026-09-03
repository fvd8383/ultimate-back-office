<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/private/classes/SiteAdminWorkspace.php';
require_once dirname(__DIR__, 2) . '/private/classes/SiteGenerationBriefManager.php';

final class WebsitePlatformM4AServiceStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private WebsitePlatformM4AServiceDatabase $database, private string $sql)
    {
    }

    public function execute(?array $params = null): bool
    {
        [$this->rows, $this->affected] = $this->database->executeSql($this->sql, $params ?? []);
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->rows;
        $this->rows = [];
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class WebsitePlatformM4AServiceDatabase extends PDO
{
    public array $users = [];
    public array $sites = [];
    public array $associations = [];
    public array $businesses = [];
    public array $modules = [];
    public array $businessModules = [];
    public array $profiles = [];
    public array $selectedServices = [];
    public array $customServices = [];
    public array $serviceAreas = [];
    public array $hours = [];
    public array $exceptions = [];
    public array $faqs = [];
    public array $pricingGuidance = [];
    public array $appointmentRules = [];
    public array $transferRules = [];
    public array $escalationRules = [];
    public array $notificationPreferences = [];
    public array $briefs = [];
    public array $revisions = [];
    public array $approvals = [];
    public array $events = [];
    public bool $failEventInsert = false;
    public bool $failRevisionInsert = false;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    public $beforeTransactionHook = null;

    private bool $transaction = false;
    private array $backup = [];
    private int $nextId = 1000;
    private int $lastId = 0;

    public function __construct()
    {
    }

    public static function fixture(): self
    {
        $database = new self();
        $database->users = [
            1 => ['id' => 1, 'status' => 'active', 'roles' => ['Admin']],
            2 => ['id' => 2, 'status' => 'active', 'roles' => ['Super Admin']],
            3 => ['id' => 3, 'status' => 'active', 'roles' => []],
        ];
        $database->sites = [
            10 => self::site(10, 'site-247sp', '247sp'),
            20 => self::site(20, 'site-emd', 'emd'),
            30 => self::site(30, 'site-demo', 'internal_demo'),
            40 => self::site(40, 'site-other', '247sp'),
            50 => self::site(50, 'site-archived', '247sp', 'archived'),
        ];
        $database->businesses = [
            100 => self::business(100, 'Acme Plumbing'),
            101 => self::business(101, 'Other Plumbing'),
            102 => self::business(102, 'Archived Plumbing'),
        ];
        $database->associations = [
            1 => self::association(1, 10, 100),
            2 => self::association(2, 40, 101),
            3 => self::association(3, 50, 102),
        ];
        $database->modules = [1 => ['id' => 1, 'module_key' => '247sp', 'is_active' => 1]];
        $database->businessModules = [
            1 => ['id' => 1, 'business_id' => 100, 'module_id' => 1, 'status' => 'active'],
            2 => ['id' => 2, 'business_id' => 101, 'module_id' => 1, 'status' => 'active'],
            3 => ['id' => 3, 'business_id' => 102, 'module_id' => 1, 'status' => 'active'],
        ];
        $database->profiles[100] = self::profile(500, 100);
        $database->selectedServices[100] = [[
            'sub_service_id' => 701, 'name' => 'Leak Repair', 'category_id' => 801, 'category_name' => 'Plumbing',
        ]];
        $database->customServices[100] = [[
            'business_custom_service_id' => 702, 'name' => 'Historic Fixture Repair',
            'category_id' => 801, 'category_name' => 'Plumbing',
        ]];
        $database->serviceAreas[100] = [
            'service_area_address' => '9 Private Depot Road', 'service_area_city' => 'Richmond',
            'service_area_state' => 'VA', 'service_area_postal_code' => '23220',
            'service_area_business' => 1, 'service_area_radius_miles' => 25,
            'service_area_radius_is_custom' => 0, 'updated_at' => '2026-09-02 10:05:00',
        ];
        $database->hours[100] = [[
            'id' => 901, 'day_of_week' => 1, 'time_range_order' => 1, 'is_closed' => 0,
            'is_24_hours' => 0, 'opens_at' => '08:00:00', 'closes_at' => '17:00:00',
            'updated_at' => '2026-09-02 10:06:00',
        ]];
        $database->exceptions[100] = [[
            'id' => 902, 'exception_date' => '2026-12-25', 'time_range_order' => 1,
            'label' => 'Christmas', 'is_closed' => 1, 'is_24_hours' => 0,
            'opens_at' => null, 'closes_at' => null, 'updated_at' => '2026-09-02 10:07:00',
        ]];
        $database->faqs[100] = [
            ['id' => 903, 'question' => 'Do you offer estimates?', 'answer' => 'Yes.', 'channel_scope' => 'all', 'is_active' => 1, 'sort_order' => 10, 'updated_at' => '2026-09-02 10:08:00'],
            ['id' => 904, 'question' => 'Can I request online?', 'answer' => 'Yes.', 'channel_scope' => 'website', 'is_active' => 1, 'sort_order' => 20, 'updated_at' => '2026-09-02 10:09:00'],
            ['id' => 905, 'question' => 'Voice only?', 'answer' => 'Private voice guidance.', 'channel_scope' => 'voice', 'is_active' => 1, 'sort_order' => 30, 'updated_at' => '2026-09-02 10:10:00'],
        ];
        $database->pricingGuidance[100] = [
            ['id' => 906, 'guidance_type' => 'starting_price', 'title' => 'Service calls', 'guidance_text' => 'Starting at $99', 'amount_min' => '99.00', 'amount_max' => null, 'currency_code' => 'USD', 'is_active' => 1, 'sort_order' => 10, 'updated_at' => '2026-09-02 10:11:00'],
            ['id' => 907, 'guidance_type' => 'disclaimer', 'title' => 'Old', 'guidance_text' => 'Inactive private guidance', 'amount_min' => null, 'amount_max' => null, 'currency_code' => null, 'is_active' => 0, 'sort_order' => 20, 'updated_at' => '2026-09-02 10:12:00'],
        ];
        $database->transferRules[100] = [[
            'id' => 908, 'name' => 'Owner', 'transfer_number' => '+15555550100',
            'backup_transfer_number' => '+15555550101', 'applies_during_business_hours' => 1,
            'applies_after_hours' => 1, 'priority' => 10, 'maximum_attempts' => 1,
            'fallback_behavior' => 'create_leadhub_task', 'sub_service_id' => null,
            'business_custom_service_id' => null, 'condition_text' => 'private condition',
            'is_active' => 1, 'updated_at' => '2026-09-02 10:13:00',
            'sub_service_name' => null, 'custom_service_name' => null,
        ]];
        $database->notificationPreferences[100] = [[
            'id' => 909, 'notification_type' => 'new_lead', 'email_enabled' => 1,
            'sms_enabled' => 1, 'in_app_enabled' => 1, 'destination_email' => 'private-alerts@example.test',
            'destination_phone' => '+15555550102', 'daily_summary_enabled' => 0,
            'is_active' => 1, 'updated_at' => '2026-09-02 10:14:00',
        ]];
        return $database;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        if (count($matches[1]) !== count(array_unique($matches[1]))) {
            throw new RuntimeException('Duplicate native PDO placeholder in M4A service SQL.');
        }
        return new WebsitePlatformM4AServiceStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('Nested transaction.');
        }
        if (is_callable($this->beforeTransactionHook)) {
            $hook = $this->beforeTransactionHook;
            $this->beforeTransactionHook = null;
            $hook($this);
        }
        $this->backup = [];
        foreach ($this->stateProperties() as $property) {
            $this->backup[$property] = $this->{$property};
        }
        $this->backup['nextId'] = $this->nextId;
        $this->backup['lastId'] = $this->lastId;
        $this->transaction = true;
        $this->beginCount++;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        $this->backup = [];
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        foreach ($this->stateProperties() as $property) {
            $this->{$property} = $this->backup[$property];
        }
        $this->nextId = $this->backup['nextId'];
        $this->lastId = $this->backup['lastId'];
        $this->transaction = false;
        $this->backup = [];
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function executeSql(string $sql, array $parameters): array
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql);

        if (str_contains($normalized, 'from users u') && str_contains($normalized, 'left join user_roles')) {
            $user = $this->users[(int) $parameters['user_id']] ?? null;
            if ($user === null) {
                return [[], 0];
            }
            $roles = $user['roles'] === [] ? [null] : $user['roles'];
            return [[...array_map(static fn ($role): array => [
                'id' => $user['id'], 'status' => $user['status'], 'role_name' => $role,
            ], $roles)], 0];
        }
        if (str_contains($normalized, 'select status from users where id')) {
            $user = $this->users[(int) $parameters['user_id']] ?? null;
            return [$user === null ? [] : [['status' => $user['status']]], 0];
        }
        if (str_contains($normalized, 'select count(*) from user_roles ur')) {
            $userId = (int) (array_values($parameters)[0] ?? 0);
            $roles = $this->users[$userId]['roles'] ?? [];
            return [[[ 'count' => count(array_intersect($roles, ['Admin', 'Super Admin'])) > 0 ? 1 : 0 ]], 0];
        }
        if (str_contains($normalized, 'select * from businesses where id')) {
            $row = $this->businesses[(int) $parameters['business_id']] ?? null;
            return [$row === null ? [] : [$row], 0];
        }
        if (str_contains($normalized, 'select * from business_profiles where business_id')) {
            $row = $this->profiles[(int) $parameters['business_id']] ?? null;
            return [$row === null ? [] : [$row], 0];
        }
        if (str_contains($normalized, 'from business_sub_services bss')) {
            return [$this->selectedServices[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_custom_services bcs')) {
            return [$this->customServices[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from `247sp_website_configurations`')) {
            $row = $this->serviceAreas[(int) $parameters['business_id']] ?? null;
            return [$row === null ? [] : [$row], 0];
        }
        if (str_contains($normalized, 'from business_profile_hours')) {
            return [$this->hours[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_profile_hour_exceptions')) {
            return [$this->exceptions[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_profile_faqs')) {
            return [$this->faqs[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_profile_pricing_guidance')) {
            return [$this->pricingGuidance[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_appointment_rules r')) {
            return [$this->appointmentRules[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_transfer_rules r')) {
            return [$this->transferRules[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_escalation_rules r')) {
            return [$this->escalationRules[(int) $parameters['business_id']] ?? [], 0];
        }
        if (str_contains($normalized, 'from business_notification_preferences')) {
            return [$this->notificationPreferences[(int) $parameters['business_id']] ?? [], 0];
        }

        if (str_contains($normalized, 'select s.id, s.site_key, s.purpose, s.lifecycle_status')
            && str_contains($normalized, 'as has_247sp_module')) {
            $site = $this->sites[(int) $parameters['site_id']] ?? null;
            if ($site === null) {
                return [[], 0];
            }
            $association = $this->activeAssociation((int) $site['id']);
            $business = $association === null ? null : ($this->businesses[(int) $association['business_id']] ?? null);
            return [[array_merge($site, [
                'business_id' => $association['business_id'] ?? null,
                'business_status' => $business['status'] ?? null,
                'is_suspended' => $business['is_suspended'] ?? null,
                'has_247sp_module' => $association === null ? 0 : ($this->hasActive247spModule((int) $association['business_id']) ? 1 : 0),
            ])], 0];
        }
        if (str_contains($normalized, 'from sites where id = :site_id for update')) {
            $site = $this->sites[(int) $parameters['site_id']] ?? null;
            return [$site === null ? [] : [$site], 0];
        }
        if (str_contains($normalized, 'select id from sites where id = :site_id limit 1')) {
            $site = $this->sites[(int) $parameters['site_id']] ?? null;
            return [$site === null ? [] : [['id' => $site['id']]], 0];
        }

        if (str_contains($normalized, 'select id from site_generation_briefs') && str_contains($normalized, 'content_hash')) {
            foreach ($this->briefs as $brief) {
                if ((int) $brief['site_id'] === (int) $parameters['site_id']
                    && (string) $brief['content_hash'] === (string) $parameters['content_hash']) {
                    return [[[ 'id' => $brief['id'] ]], 0];
                }
            }
            return [[], 0];
        }
        if (str_contains($normalized, 'coalesce(max(brief_version), 0) + 1')) {
            $versions = array_map('intval', array_column(array_filter(
                $this->briefs,
                static fn (array $row): bool => (int) $row['site_id'] === (int) $parameters['site_id']
            ), 'brief_version'));
            return [[[ 'next_version' => ($versions === [] ? 0 : max($versions)) + 1 ]], 0];
        }
        if (str_starts_with($normalized, 'insert into site_generation_briefs')) {
            $id = $this->newId();
            $this->briefs[$id] = [
                'id' => $id, 'site_id' => (int) $parameters['site_id'],
                'brief_version' => (int) $parameters['brief_version'], 'state' => (string) $parameters['state'],
                'brief_json' => (string) $parameters['brief_json'], 'source_type' => (string) $parameters['source_type'],
                'source_reference' => null, 'created_by_user_id' => (int) $parameters['actor_id'],
                'superseded_at' => null, 'content_hash' => (string) $parameters['content_hash'],
                'created_at' => '2026-09-02 12:00:00',
            ];
            return [[], 1];
        }
        if (str_contains($normalized, 'from site_generation_briefs where id = :brief_id limit 1')) {
            $brief = $this->briefs[(int) $parameters['brief_id']] ?? null;
            return [$brief === null ? [] : [$brief], 0];
        }
        if (str_contains($normalized, 'from site_generation_briefs') && str_contains($normalized, 'where site_id = :site_id and source_type')) {
            $rows = $this->briefRows((int) $parameters['site_id'], (string) $parameters['source_type']);
            return [$rows === [] ? [] : [$rows[0]], 0];
        }
        if (str_contains($normalized, 'from site_generation_briefs') && str_contains($normalized, 'where site_id = :site_id order by brief_version')) {
            return [$this->briefRows((int) $parameters['site_id']), 0];
        }

        if (str_contains($normalized, 'from site_revisions') && str_contains($normalized, 'lifecycle_status in (:draft_status')) {
            $rows = array_values(array_filter($this->revisions, static fn (array $row): bool =>
                (int) $row['site_id'] === (int) $parameters['site_id']
                && in_array((string) $row['lifecycle_status'], ['draft', 'validation_failed'], true)
            ));
            usort($rows, static fn (array $a, array $b): int => (int) $b['revision_number'] <=> (int) $a['revision_number']);
            return [$rows === [] ? [] : [['id' => $rows[0]['id']]], 0];
        }
        if (str_contains($normalized, 'from site_generation_briefs') && str_contains($normalized, 'where id = :brief_id and site_id')) {
            $brief = $this->briefs[(int) $parameters['brief_id']] ?? null;
            if ($brief === null || (int) $brief['site_id'] !== (int) $parameters['site_id']) {
                return [[], 0];
            }
            return [[[
                'id' => $brief['id'], 'site_id' => $brief['site_id'], 'brief_version' => $brief['brief_version'],
                'source_type' => $brief['source_type'], 'content_hash' => $brief['content_hash'],
            ]], 0];
        }
        if (str_contains($normalized, 'from site_revisions') && str_contains($normalized, 'where id = :revision_id and site_id')) {
            $revision = $this->revisions[(int) $parameters['revision_id']] ?? null;
            if ($revision === null || (int) $revision['site_id'] !== (int) $parameters['site_id']) {
                return [[], 0];
            }
            return [[$revision], 0];
        }
        if (str_contains($normalized, 'from site_business_associations sba') && str_contains($normalized, 'inner join businesses b')) {
            $association = $this->activeAssociation((int) $parameters['site_id']);
            if ($association === null) {
                return [[], 0];
            }
            $business = $this->businesses[(int) $association['business_id']] ?? null;
            if ($business === null) {
                return [[], 0];
            }
            return [[[
                'business_id' => $association['business_id'],
                'business_status' => $business['status'],
                'is_suspended' => $business['is_suspended'],
            ]], 0];
        }
        if (str_contains($normalized, 'select bm.id as business_module_id')) {
            foreach ($this->businessModules as $assignment) {
                $module = $this->modules[(int) $assignment['module_id']] ?? null;
                if ((int) $assignment['business_id'] === (int) $parameters['business_id']
                    && (string) $assignment['status'] === 'active'
                    && $module !== null && (string) $module['module_key'] === '247sp'
                    && (int) $module['is_active'] === 1) {
                    return [[[
                        'business_module_id' => $assignment['id'], 'module_id' => $module['id'],
                    ]], 0];
                }
            }
            return [[], 0];
        }
        if (str_contains($normalized, 'coalesce(max(revision_number), 0) + 1')) {
            $numbers = array_map('intval', array_column(array_filter(
                $this->revisions,
                static fn (array $row): bool => (int) $row['site_id'] === (int) $parameters['site_id']
            ), 'revision_number'));
            return [[[ 'next_revision' => ($numbers === [] ? 0 : max($numbers)) + 1 ]], 0];
        }
        if (str_starts_with($normalized, 'insert into site_revisions')) {
            if ($this->failRevisionInsert) {
                throw new RuntimeException('Injected revision insert failure.');
            }
            $id = $this->newId();
            $this->revisions[$id] = [
                'id' => $id, 'site_id' => (int) $parameters['site_id'],
                'revision_number' => (int) $parameters['revision_number'],
                'lifecycle_status' => (string) $parameters['status'],
                'based_on_revision_id' => $parameters['based_on_revision_id'] === null ? null : (int) $parameters['based_on_revision_id'],
                'restored_from_revision_id' => null,
                'generation_brief_id' => (int) $parameters['generation_brief_id'],
                'materiality' => (string) $parameters['materiality'],
                'snapshot_schema_version' => (int) $parameters['snapshot_schema_version'],
                'facts_snapshot_json' => (string) $parameters['facts_snapshot_json'],
                'source_references_json' => (string) $parameters['source_references_json'],
                'snapshot_hash' => (string) $parameters['snapshot_hash'],
                'created_by_user_id' => (int) $parameters['actor_id'],
                'correlation_id' => (string) $parameters['correlation_id'],
                'review_ready_at' => null, 'published_at' => null, 'superseded_at' => null,
                'created_at' => '2026-09-02 12:05:00', 'updated_at' => '2026-09-02 12:05:00',
            ];
            return [[], 1];
        }

        if (str_contains($normalized, 'as revision_count') && str_contains($normalized, 'from sites s')) {
            $rows = [];
            foreach ($this->sites as $site) {
                if (isset($parameters['purpose']) && (string) $site['purpose'] !== (string) $parameters['purpose']) {
                    continue;
                }
                if (isset($parameters['lifecycle_status']) && (string) $site['lifecycle_status'] !== (string) $parameters['lifecycle_status']) {
                    continue;
                }
                $association = $this->activeAssociation((int) $site['id']);
                $business = $association === null ? null : ($this->businesses[(int) $association['business_id']] ?? null);
                $siteRevisions = array_values(array_filter($this->revisions, static fn (array $r): bool => (int) $r['site_id'] === (int) $site['id']));
                $mutable = array_values(array_filter($siteRevisions, static fn (array $r): bool => in_array($r['lifecycle_status'], ['draft', 'validation_failed'], true)));
                usort($mutable, static fn (array $a, array $b): int => (int) $b['revision_number'] <=> (int) $a['revision_number']);
                $rows[] = array_merge($site, [
                    'business_id' => $association['business_id'] ?? null,
                    'business_name' => $business['business_name'] ?? null,
                    'revision_count' => count($siteRevisions),
                    'brief_count' => count($this->briefRows((int) $site['id'])),
                    'mutable_revision_id' => $mutable[0]['id'] ?? null,
                ]);
            }
            usort($rows, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);
            return [$rows, 0];
        }
        if (str_contains($normalized, 'sba.id as association_id') && str_contains($normalized, 'from sites s')) {
            $site = $this->sites[(int) $parameters['site_id']] ?? null;
            if ($site === null) {
                return [[], 0];
            }
            $association = $this->activeAssociation((int) $site['id']);
            $business = $association === null ? null : ($this->businesses[(int) $association['business_id']] ?? null);
            return [[array_merge($site, [
                'association_id' => $association['id'] ?? null,
                'business_id' => $association['business_id'] ?? null,
                'association_role' => $association['association_role'] ?? null,
                'association_status' => $association['status'] ?? null,
                'effective_at' => $association['effective_at'] ?? null,
                'business_name' => $business['business_name'] ?? null,
            ])], 0];
        }
        if (str_contains($normalized, 'case when lifecycle_status in (:mutable_draft')) {
            $rows = array_values(array_filter($this->revisions, static fn (array $row): bool => (int) $row['site_id'] === (int) $parameters['site_id']));
            usort($rows, static fn (array $a, array $b): int => (int) $b['revision_number'] <=> (int) $a['revision_number']);
            foreach ($rows as &$row) {
                $row['composition_access'] = in_array($row['lifecycle_status'], ['draft', 'validation_failed'], true) ? 'mutable' : 'immutable';
            }
            unset($row);
            return [$rows, 0];
        }
        if (str_contains($normalized, 'from site_approvals sa') && str_contains($normalized, 'inner join site_revisions sr')) {
            $rows = [];
            foreach ($this->approvals as $approval) {
                if ((int) $approval['site_id'] !== (int) $parameters['site_id']) {
                    continue;
                }
                $revision = $this->revisions[(int) $approval['revision_id']] ?? null;
                if ($revision === null) {
                    continue;
                }
                $rows[] = [
                    'id' => $approval['id'], 'site_id' => $approval['site_id'],
                    'revision_id' => $approval['revision_id'], 'approval_type' => $approval['approval_type'],
                    'state' => $approval['state'], 'requested_at' => $approval['requested_at'],
                    'decided_at' => $approval['decided_at'], 'revoked_at' => $approval['revoked_at'],
                    'revision_number' => $revision['revision_number'],
                ];
            }
            return [$rows, 0];
        }

        if (str_starts_with($normalized, 'insert into site_events')) {
            if ($this->failEventInsert) {
                throw new RuntimeException('Injected event insert failure.');
            }
            $id = $this->newId();
            $this->events[$id] = [
                'id' => $id, 'site_id' => (int) $parameters['site_id'],
                'revision_id' => $parameters['revision_id'] === null ? null : (int) $parameters['revision_id'],
                'actor_user_id' => $parameters['actor_user_id'] === null ? null : (int) $parameters['actor_user_id'],
                'actor_type' => (string) $parameters['actor_type'], 'event_type' => (string) $parameters['event_type'],
                'result' => (string) $parameters['result'], 'reason' => $parameters['reason'],
                'correlation_id' => (string) $parameters['correlation_id'],
                'metadata_json' => (string) $parameters['metadata_json'], 'created_at' => '2026-09-02 12:10:00',
            ];
            return [[], 1];
        }

        throw new RuntimeException('Unsupported M4A service SQL: ' . $normalized);
    }

    public function addBrief(int $siteId, int $version = 1, string $summary = 'Fixture brief'): int
    {
        $id = $this->newId();
        $content = [
            'summary' => $summary, 'target_audience' => '', 'tone_notes' => '',
            'design_notes' => '', 'conversion_notes' => '', 'page_notes' => '',
        ];
        $this->briefs[$id] = [
            'id' => $id, 'site_id' => $siteId, 'brief_version' => $version, 'state' => 'authored',
            'brief_json' => CanonicalJson::encode($content), 'source_type' => 'admin_manual',
            'source_reference' => null, 'created_by_user_id' => 1, 'superseded_at' => null,
            'content_hash' => CanonicalJson::hash($content), 'created_at' => '2026-09-02 11:00:00',
        ];
        return $id;
    }

    public function addRevision(int $siteId, string $status, ?int $briefId = null): int
    {
        $id = $this->newId();
        $numbers = array_map('intval', array_column(array_filter(
            $this->revisions,
            static fn (array $row): bool => (int) $row['site_id'] === $siteId
        ), 'revision_number'));
        $this->revisions[$id] = [
            'id' => $id, 'site_id' => $siteId, 'revision_number' => ($numbers === [] ? 0 : max($numbers)) + 1,
            'lifecycle_status' => $status, 'based_on_revision_id' => null,
            'restored_from_revision_id' => null, 'generation_brief_id' => $briefId,
            'materiality' => 'undetermined', 'snapshot_schema_version' => 1,
            'facts_snapshot_json' => '{}', 'source_references_json' => '{}',
            'snapshot_hash' => str_repeat(dechex(($id % 15) + 1), 64),
            'created_by_user_id' => 1, 'correlation_id' => 'fixture:' . $id,
            'review_ready_at' => null, 'published_at' => null, 'superseded_at' => null,
            'created_at' => '2026-09-02 11:05:00', 'updated_at' => '2026-09-02 11:05:00',
        ];
        return $id;
    }

    private static function site(int $id, string $key, string $purpose, string $status = 'draft'): array
    {
        return [
            'id' => $id, 'site_key' => $key, 'purpose' => $purpose, 'lifecycle_status' => $status,
            'current_published_revision_id' => null, 'created_by_user_id' => 1,
            'suspended_at' => null, 'archived_at' => $status === 'archived' ? '2026-09-01 00:00:00' : null,
            'lock_version' => 0, 'created_at' => '2026-09-02 09:00:00', 'updated_at' => '2026-09-02 09:00:00',
        ];
    }

    private static function association(int $id, int $siteId, int $businessId): array
    {
        return [
            'id' => $id, 'site_id' => $siteId, 'business_id' => $businessId,
            'association_role' => 'customer', 'status' => 'active',
            'effective_at' => '2026-09-02 09:05:00', 'ended_at' => null,
        ];
    }

    private static function business(int $id, string $name): array
    {
        return [
            'id' => $id, 'business_name' => $name, 'legal_name' => $name . ' LLC',
            'phone' => '+15555550123', 'email' => 'hello@example.test', 'status' => 'active',
            'is_suspended' => 0, 'address_line_1' => '123 Public Street', 'address_line_2' => null,
            'city' => 'Richmond', 'state' => 'VA', 'postal_code' => '23220', 'country' => 'US',
            'is_public_physical_location' => 0, 'updated_at' => '2026-09-02 10:00:00',
            'stripe_customer_id' => 'cus_sensitive', 'password_hash' => 'sensitive-auth-value',
            'internal_notes' => 'private internal note', 'provider_credentials' => 'private provider value',
            'legacy_generated_content' => 'must never enter snapshot',
        ];
    }

    private static function profile(int $id, int $businessId): array
    {
        return [
            'id' => $id, 'business_id' => $businessId, 'lifecycle_status' => 'active',
            'public_display_name' => 'Acme Home Services', 'website_url' => 'https://example.test',
            'timezone' => 'America/New_York', 'default_language' => 'en',
            'short_description' => 'Trusted plumbing help.', 'long_description' => 'Careful local plumbing service.',
            'primary_greeting' => 'How can we help?', 'value_proposition' => 'Reliable help from local professionals.',
            'tone' => 'calm', 'personality' => 'helpful', 'prohibited_claims' => 'Do not promise instant arrival.',
            'appointment_requests_enabled' => 0, 'automatic_booking_enabled' => 0,
            'minimum_notice_minutes' => null, 'default_appointment_duration_minutes' => null,
            'emergency_service_enabled' => 0, 'profile_completed_at' => '2026-09-01 00:00:00',
            'activated_at' => '2026-09-01 01:00:00', 'updated_at' => '2026-09-02 10:01:00',
        ];
    }

    private function activeAssociation(int $siteId): ?array
    {
        foreach ($this->associations as $association) {
            if ((int) $association['site_id'] === $siteId
                && (string) $association['association_role'] === 'customer'
                && (string) $association['status'] === 'active') {
                return $association;
            }
        }
        return null;
    }

    private function hasActive247spModule(int $businessId): bool
    {
        foreach ($this->businessModules as $assignment) {
            $module = $this->modules[(int) $assignment['module_id']] ?? null;
            if ((int) $assignment['business_id'] === $businessId
                && (string) $assignment['status'] === 'active'
                && $module !== null && (string) $module['module_key'] === '247sp'
                && (int) $module['is_active'] === 1) {
                return true;
            }
        }
        return false;
    }

    private function briefRows(int $siteId, ?string $sourceType = null): array
    {
        $rows = array_values(array_filter($this->briefs, static fn (array $row): bool =>
            (int) $row['site_id'] === $siteId
            && ($sourceType === null || (string) $row['source_type'] === $sourceType)
        ));
        usort($rows, static fn (array $a, array $b): int =>
            ((int) $b['brief_version'] <=> (int) $a['brief_version']) ?: ((int) $b['id'] <=> (int) $a['id'])
        );
        return $rows;
    }

    private function newId(): int
    {
        $this->lastId = ++$this->nextId;
        return $this->lastId;
    }

    private function stateProperties(): array
    {
        return [
            'sites', 'associations', 'businesses', 'modules', 'businessModules', 'profiles',
            'selectedServices', 'customServices', 'serviceAreas', 'hours', 'exceptions',
            'faqs', 'pricingGuidance', 'appointmentRules', 'transferRules', 'escalationRules',
            'notificationPreferences', 'briefs', 'revisions', 'approvals', 'events',
        ];
    }
}

function useWebsitePlatformM4AServiceDatabase(WebsitePlatformM4AServiceDatabase $database): void
{
    (new ReflectionProperty(Database::class, 'connection'))->setValue(null, $database);
}
