<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BusinessFoundation.php';
require_once __DIR__ . '/AdminPortal.php';

final class SharedBusinessProfileException extends RuntimeException
{
    private string $errorType;
    private array $fieldErrors;

    public function __construct(
        string $errorType,
        string $message,
        array $fieldErrors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorType = $errorType;
        $this->fieldErrors = $fieldErrors;
    }

    public function errorType(): string
    {
        return $this->errorType;
    }

    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}

final class SharedBusinessProfile
{
    private const MODULE_KEY = 'shared_business_profile';
    private const READINESS_VERSION = 1;

    private const LIFECYCLE_STATUSES = ['draft', 'incomplete', 'in_review', 'ready', 'active'];
    private const LIFECYCLE_TRANSITIONS = [
        'draft' => ['incomplete', 'in_review'],
        'incomplete' => ['draft', 'in_review'],
        'in_review' => ['draft', 'incomplete', 'ready'],
        'ready' => ['in_review', 'incomplete', 'active'],
        'active' => ['in_review', 'incomplete', 'ready'],
    ];

    private const PROFILE_FIELDS = [
        'public_display_name',
        'website_url',
        'timezone',
        'default_language',
        'short_description',
        'long_description',
        'primary_greeting',
        'value_proposition',
        'tone',
        'personality',
        'prohibited_claims',
        'appointment_requests_enabled',
        'automatic_booking_enabled',
        'minimum_notice_minutes',
        'default_appointment_duration_minutes',
        'emergency_service_enabled',
    ];

    private const FAQ_CHANNEL_SCOPES = ['all', 'website', 'voice', 'sms', 'chat'];
    private const PRICING_GUIDANCE_TYPES = [
        'starting_price',
        'service_call_fee',
        'estimate_policy',
        'deposit_policy',
        'financing',
        'disclaimer',
        'prohibited_statement',
        'general_guidance',
    ];
    private const APPOINTMENT_RULE_TYPES = [
        'general',
        'request_only',
        'automatic_booking',
        'minimum_notice',
        'preparation',
        'service_eligibility',
    ];
    private const TRANSFER_FALLBACK_BEHAVIORS = [
        'create_leadhub_task',
        'collect_message',
        'owner_notification',
        'voicemail',
        'end_conversation',
    ];
    private const ESCALATION_RULE_TYPES = [
        'immediate_transfer',
        'owner_alert',
        'prohibited_ai_handling',
        'disclaimer_language',
    ];
    private const URGENCY_LEVELS = ['low', 'normal', 'high', 'urgent', 'emergency'];
    private const NOTIFICATION_TYPES = [
        'new_lead',
        'missed_call',
        'transfer_failed',
        'urgent_lead',
        'new_message',
        'appointment_request',
        'unresolved_lead_summary',
    ];

    private const CHILD_TABLE_COLUMNS = [
        'business_profile_hours' => [
            'day_of_week', 'time_range_order', 'is_closed', 'is_24_hours', 'opens_at', 'closes_at',
        ],
        'business_profile_hour_exceptions' => [
            'exception_date', 'time_range_order', 'label', 'is_closed', 'is_24_hours', 'opens_at', 'closes_at',
        ],
        'business_profile_faqs' => [
            'question', 'answer', 'channel_scope', 'is_active', 'sort_order',
        ],
        'business_profile_pricing_guidance' => [
            'guidance_type', 'title', 'guidance_text', 'amount_min', 'amount_max', 'currency_code', 'is_active', 'sort_order',
        ],
        'business_appointment_rules' => [
            'rule_type', 'sub_service_id', 'business_custom_service_id', 'rule_text', 'is_active', 'sort_order',
        ],
        'business_transfer_rules' => [
            'name', 'transfer_number', 'backup_transfer_number', 'applies_during_business_hours',
            'applies_after_hours', 'priority', 'maximum_attempts', 'fallback_behavior', 'sub_service_id',
            'business_custom_service_id', 'condition_text', 'is_active',
        ],
        'business_escalation_rules' => [
            'rule_type', 'name', 'condition_text', 'instruction_text', 'sub_service_id',
            'business_custom_service_id', 'urgency_level', 'requires_immediate_transfer',
            'requires_owner_alert', 'disclaimer_text', 'priority', 'is_active',
        ],
        'business_notification_preferences' => [
            'notification_type', 'email_enabled', 'sms_enabled', 'in_app_enabled', 'destination_email',
            'destination_phone', 'daily_summary_enabled', 'is_active',
        ],
    ];

    public static function getProfileForBusiness(int $businessId, int $actingUserId): array
    {
        return self::readOperation('get_profile', function () use ($businessId, $actingUserId): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);

            return self::normalizedProfile($business, $profile);
        });
    }

    public static function updateProfile(int $businessId, int $actingUserId, array $input): array
    {
        return self::readOperation('update_profile', function () use ($businessId, $actingUserId, $input): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $values = self::validateProfileInput($input, $profile);

            return self::runMutation(
                $business,
                $profile,
                $actingUserId,
                'profile_updated',
                'business_profile',
                ['fields' => array_keys($values)],
                function (PDO $connection) use ($profile, $values): void {
                    if (count($values) === 0) {
                        return;
                    }

                    $assignments = [];
                    foreach (array_keys($values) as $column) {
                        $assignments[] = $column . ' = :' . $column;
                    }

                    $statement = $connection->prepare(
                        'UPDATE business_profiles SET ' . implode(', ', $assignments) . ', updated_at = NOW() WHERE id = :profile_id'
                    );
                    $statement->execute($values + ['profile_id' => (int) $profile['id']]);
                }
            );
        });
    }

    public static function getHours(int $businessId, int $actingUserId): array
    {
        return self::readOperation('get_hours', function () use ($businessId, $actingUserId): array {
            self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);

            return self::fetchHours((int) $profile['id'], $businessId);
        });
    }

    public static function replaceHours(int $businessId, int $actingUserId, array $hours): array
    {
        return self::replaceChildCollection(
            $businessId,
            $actingUserId,
            'business_profile_hours',
            'hours_replaced',
            $hours,
            function (array $rows): array {
                return self::validateHours($rows, false);
            }
        );
    }

    public static function getHourExceptions(int $businessId, int $actingUserId): array
    {
        return self::readOperation('get_hour_exceptions', function () use ($businessId, $actingUserId): array {
            self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);

            return self::fetchHourExceptions((int) $profile['id'], $businessId);
        });
    }

    public static function replaceHourExceptions(int $businessId, int $actingUserId, array $exceptions): array
    {
        return self::replaceChildCollection(
            $businessId,
            $actingUserId,
            'business_profile_hour_exceptions',
            'hour_exceptions_replaced',
            $exceptions,
            function (array $rows): array {
                return self::validateHours($rows, true);
            }
        );
    }

    public static function getFaqs(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead($businessId, $actingUserId, 'fetchFaqs', 'get_faqs');
    }

    public static function saveFaqs(int $businessId, int $actingUserId, array $faqs): array
    {
        return self::replaceChildCollection(
            $businessId,
            $actingUserId,
            'business_profile_faqs',
            'faqs_saved',
            $faqs,
            function (array $rows): array {
                return self::validateFaqs($rows);
            }
        );
    }

    public static function getPricingGuidance(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead($businessId, $actingUserId, 'fetchPricingGuidance', 'get_pricing_guidance');
    }

    public static function savePricingGuidance(int $businessId, int $actingUserId, array $guidance): array
    {
        return self::replaceChildCollection(
            $businessId,
            $actingUserId,
            'business_profile_pricing_guidance',
            'pricing_guidance_saved',
            $guidance,
            function (array $rows): array {
                return self::validatePricingGuidance($rows);
            }
        );
    }

    public static function getAppointmentRules(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead($businessId, $actingUserId, 'fetchAppointmentRules', 'get_appointment_rules');
    }

    public static function saveAppointmentRules(int $businessId, int $actingUserId, array $rules): array
    {
        return self::replaceRules(
            $businessId,
            $actingUserId,
            'business_appointment_rules',
            'appointment_rules_saved',
            $rules,
            function (array $rows, array $business): array {
                return self::validateAppointmentRules($rows, $business);
            }
        );
    }

    public static function getTransferRules(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead($businessId, $actingUserId, 'fetchTransferRules', 'get_transfer_rules');
    }

    public static function saveTransferRules(int $businessId, int $actingUserId, array $rules): array
    {
        return self::replaceRules(
            $businessId,
            $actingUserId,
            'business_transfer_rules',
            'transfer_rules_saved',
            $rules,
            function (array $rows, array $business): array {
                return self::validateTransferRules($rows, $business);
            }
        );
    }

    public static function getEscalationRules(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead($businessId, $actingUserId, 'fetchEscalationRules', 'get_escalation_rules');
    }

    public static function saveEscalationRules(int $businessId, int $actingUserId, array $rules): array
    {
        return self::replaceRules(
            $businessId,
            $actingUserId,
            'business_escalation_rules',
            'escalation_rules_saved',
            $rules,
            function (array $rows, array $business): array {
                return self::validateEscalationRules($rows, $business);
            }
        );
    }

    public static function getNotificationPreferences(int $businessId, int $actingUserId): array
    {
        return self::authorizedChildRead(
            $businessId,
            $actingUserId,
            'fetchNotificationPreferences',
            'get_notification_preferences'
        );
    }

    public static function saveNotificationPreferences(int $businessId, int $actingUserId, array $preferences): array
    {
        return self::readOperation('save_notification_preferences', function () use ($businessId, $actingUserId, $preferences): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $rows = self::validateNotificationPreferences($preferences, $business);
            self::assertOwnedChildIds('business_notification_preferences', $rows, $businessId, (int) $profile['id']);

            return self::runMutation(
                $business,
                $profile,
                $actingUserId,
                'notification_preferences_saved',
                'business_notification_preferences',
                ['row_count' => count($rows), 'notification_types' => array_column($rows, 'notification_type')],
                function (PDO $connection) use ($businessId, $profile, $rows): void {
                    self::replaceRows(
                        $connection,
                        'business_notification_preferences',
                        $businessId,
                        (int) $profile['id'],
                        $rows
                    );
                }
            );
        });
    }

    public static function transitionLifecycleStatus(int $businessId, int $actingUserId, string $targetStatus): array
    {
        return self::readOperation('transition_lifecycle', function () use ($businessId, $actingUserId, $targetStatus): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $targetStatus = strtolower(trim($targetStatus));
            $currentStatus = (string) $profile['lifecycle_status'];

            if (!in_array($targetStatus, self::LIFECYCLE_STATUSES, true)) {
                throw new SharedBusinessProfileException(
                    'validation_failed',
                    'The requested lifecycle status is not supported.',
                    ['lifecycle_status' => 'Choose a supported lifecycle status.']
                );
            }

            if ($targetStatus === $currentStatus) {
                return self::normalizedProfile($business, $profile);
            }

            if (!in_array($targetStatus, self::LIFECYCLE_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw new SharedBusinessProfileException(
                    'invalid_lifecycle_transition',
                    'The requested lifecycle transition is not allowed.'
                );
            }

            $readiness = self::calculateReadinessData($business, $profile);
            if (in_array($targetStatus, ['ready', 'active'], true) && !$readiness['is_complete']) {
                throw new SharedBusinessProfileException(
                    'invalid_lifecycle_transition',
                    'The profile must pass readiness validation before it can become ' . $targetStatus . '.',
                    ['lifecycle_status' => 'Complete all required profile sections first.']
                );
            }

            if ($targetStatus === 'active' && !AdminPortal::currentUserIsAdmin($actingUserId)) {
                throw new SharedBusinessProfileException(
                    'unauthorized',
                    'Only an authorized internal administrator may activate a Shared Business Profile.'
                );
            }

            return self::runMutation(
                $business,
                $profile,
                $actingUserId,
                'lifecycle_transitioned',
                'business_profile',
                ['from' => $currentStatus, 'to' => $targetStatus],
                function (PDO $connection) use ($profile, $targetStatus): void {
                    $completionSql = in_array($targetStatus, ['ready', 'active'], true)
                        ? 'profile_completed_at = COALESCE(profile_completed_at, NOW()),'
                        : '';
                    $activationSql = $targetStatus === 'active'
                        ? 'activated_at = COALESCE(activated_at, NOW()),'
                        : '';
                    $statement = $connection->prepare(
                        'UPDATE business_profiles SET lifecycle_status = :status, '
                        . $completionSql . ' ' . $activationSql . ' updated_at = NOW() WHERE id = :profile_id'
                    );
                    $statement->execute([
                        'status' => $targetStatus,
                        'profile_id' => (int) $profile['id'],
                    ]);
                }
            );
        });
    }

    public static function calculateReadiness(int $businessId, int $actingUserId): array
    {
        return self::readOperation('calculate_readiness', function () use ($businessId, $actingUserId): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);

            return self::calculateReadinessData($business, $profile);
        });
    }

    public static function allowedLifecycleTransitions(string $status): array
    {
        return self::LIFECYCLE_TRANSITIONS[strtolower(trim($status))] ?? [];
    }

    private static function authorizedBusiness(int $businessId, int $actingUserId): array
    {
        if ($businessId <= 0) {
            throw new SharedBusinessProfileException('business_not_found', 'The requested business was not found.');
        }

        $statement = Database::connection()->prepare('SELECT * FROM businesses WHERE id = :business_id LIMIT 1');
        $statement->execute(['business_id' => $businessId]);
        $business = $statement->fetch();

        if (!$business) {
            throw new SharedBusinessProfileException('business_not_found', 'The requested business was not found.');
        }

        $actor = Database::connection()->prepare('SELECT status FROM users WHERE id = :user_id LIMIT 1');
        $actor->execute(['user_id' => $actingUserId]);
        if ($actingUserId <= 0 || $actor->fetchColumn() !== 'active') {
            throw new SharedBusinessProfileException('unauthorized', 'You are not authorized to access this business.');
        }

        if (AdminPortal::currentUserIsAdmin($actingUserId)) {
            return $business;
        }

        $authorized = BusinessFoundation::businessForUser($businessId, $actingUserId);
        $isSuspended = (int) ($business['is_suspended'] ?? 0) === 1;
        if ($authorized === null || (string) ($business['status'] ?? '') !== 'active' || $isSuspended) {
            throw new SharedBusinessProfileException('unauthorized', 'You are not authorized to access this business.');
        }

        return $business;
    }

    private static function requireProfile(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM business_profiles WHERE business_id = :business_id LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $profile = $statement->fetch();

        if (!$profile) {
            throw new SharedBusinessProfileException(
                'profile_not_found',
                'The Shared Business Profile for this business was not found.'
            );
        }

        return $profile;
    }

    private static function readOperation(string $operation, callable $callback)
    {
        try {
            return $callback();
        } catch (SharedBusinessProfileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::reportFailure($operation, $exception);
            throw new SharedBusinessProfileException(
                'database_failure',
                'The Shared Business Profile request could not be completed.',
                [],
                $exception
            );
        }
    }

    private static function runMutation(
        array $business,
        array $profile,
        int $actingUserId,
        string $action,
        string $targetType,
        array $summary,
        callable $callback
    ): array {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $lock = $connection->prepare(
                'SELECT id FROM business_profiles WHERE id = :profile_id AND business_id = :business_id FOR UPDATE'
            );
            $lock->execute([
                'profile_id' => (int) $profile['id'],
                'business_id' => (int) $business['id'],
            ]);
            if ($lock->fetchColumn() === false) {
                throw new SharedBusinessProfileException(
                    'profile_not_found',
                    'The Shared Business Profile for this business was not found.'
                );
            }

            $callback($connection);
            $profile = self::requireProfile((int) $business['id']);
            $readiness = self::calculateReadinessData($business, $profile);

            if (!$readiness['is_complete'] && in_array((string) $profile['lifecycle_status'], ['ready', 'active'], true)) {
                $statement = $connection->prepare(
                    "UPDATE business_profiles SET lifecycle_status = 'incomplete', updated_at = NOW() WHERE id = :profile_id"
                );
                $statement->execute(['profile_id' => (int) $profile['id']]);
                $profile['lifecycle_status'] = 'incomplete';
                $readiness['lifecycle_status'] = 'incomplete';
            }

            $snapshot = $connection->prepare(
                'UPDATE business_profiles SET readiness_snapshot_json = :snapshot, updated_at = NOW() WHERE id = :profile_id'
            );
            $snapshot->execute([
                'snapshot' => self::jsonForStorage($readiness),
                'profile_id' => (int) $profile['id'],
            ]);

            self::logMutation(
                (int) $business['id'],
                $actingUserId,
                $action,
                $targetType,
                (int) $profile['id'],
                $summary
            );
            $profile = self::requireProfile((int) $business['id']);
            $normalized = self::normalizedProfile($business, $profile);
            $connection->commit();

            return $normalized;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            if ($exception instanceof SharedBusinessProfileException) {
                throw $exception;
            }

            self::reportFailure($action, $exception);
            throw new SharedBusinessProfileException(
                'database_failure',
                'The Shared Business Profile change could not be saved.',
                [],
                $exception
            );
        }
    }

    private static function replaceChildCollection(
        int $businessId,
        int $actingUserId,
        string $table,
        string $action,
        array $input,
        callable $validator
    ): array {
        return self::readOperation($action, function () use ($businessId, $actingUserId, $table, $action, $input, $validator): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $rows = $validator($input);
            self::assertOwnedChildIds($table, $rows, $businessId, (int) $profile['id']);

            return self::runMutation(
                $business,
                $profile,
                $actingUserId,
                $action,
                $table,
                ['row_count' => count($rows)],
                function (PDO $connection) use ($table, $businessId, $profile, $rows): void {
                    self::replaceRows($connection, $table, $businessId, (int) $profile['id'], $rows);
                }
            );
        });
    }

    private static function replaceRules(
        int $businessId,
        int $actingUserId,
        string $table,
        string $action,
        array $input,
        callable $validator
    ): array {
        return self::readOperation($action, function () use ($businessId, $actingUserId, $table, $action, $input, $validator): array {
            $business = self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $rows = $validator($input, $business);
            self::assertOwnedChildIds($table, $rows, $businessId, (int) $profile['id']);

            return self::runMutation(
                $business,
                $profile,
                $actingUserId,
                $action,
                $table,
                ['row_count' => count($rows)],
                function (PDO $connection) use ($table, $businessId, $profile, $rows): void {
                    self::replaceRows($connection, $table, $businessId, (int) $profile['id'], $rows);
                }
            );
        });
    }

    private static function authorizedChildRead(
        int $businessId,
        int $actingUserId,
        string $fetchMethod,
        string $operation
    ): array {
        return self::readOperation($operation, function () use ($businessId, $actingUserId, $fetchMethod): array {
            self::authorizedBusiness($businessId, $actingUserId);
            $profile = self::requireProfile($businessId);
            $profileId = (int) $profile['id'];

            if ($fetchMethod === 'fetchFaqs') {
                return self::fetchFaqs($profileId, $businessId);
            }
            if ($fetchMethod === 'fetchPricingGuidance') {
                return self::fetchPricingGuidance($profileId, $businessId);
            }
            if ($fetchMethod === 'fetchAppointmentRules') {
                return self::fetchAppointmentRules($profileId, $businessId);
            }
            if ($fetchMethod === 'fetchTransferRules') {
                return self::fetchTransferRules($profileId, $businessId);
            }
            if ($fetchMethod === 'fetchEscalationRules') {
                return self::fetchEscalationRules($profileId, $businessId);
            }
            if ($fetchMethod === 'fetchNotificationPreferences') {
                return self::fetchNotificationPreferences($profileId, $businessId);
            }

            throw new LogicException('Unsupported Shared Business Profile collection reader.');
        });
    }

    private static function replaceRows(
        PDO $connection,
        string $table,
        int $businessId,
        int $profileId,
        array $rows
    ): void {
        $columns = self::CHILD_TABLE_COLUMNS[$table] ?? null;
        if ($columns === null) {
            throw new LogicException('Unsupported Shared Business Profile child table.');
        }

        $delete = $connection->prepare(
            'DELETE FROM `' . $table . '` WHERE business_id = :business_id AND business_profile_id = :profile_id'
        );
        $existingStatement = $connection->prepare(
            'SELECT id, created_at FROM `' . $table . '` WHERE business_id = :business_id AND business_profile_id = :profile_id'
        );
        $existingStatement->execute(['business_id' => $businessId, 'profile_id' => $profileId]);
        $createdAtById = [];
        foreach ($existingStatement->fetchAll() as $existingRow) {
            $createdAtById[(int) $existingRow['id']] = $existingRow['created_at'];
        }
        $delete->execute(['business_id' => $businessId, 'profile_id' => $profileId]);

        if (count($rows) === 0) {
            return;
        }

        $columnSql = implode(', ', array_map(function (string $column): string {
            return '`' . $column . '`';
        }, $columns));
        $placeholderSql = implode(', ', array_map(function (string $column): string {
            return ':' . $column;
        }, $columns));

        foreach ($rows as $row) {
            $hasId = isset($row['id']) && (int) $row['id'] > 0;
            $sql = 'INSERT INTO `' . $table . '` ('
                . ($hasId ? 'id, ' : '')
                . 'business_id, business_profile_id, ' . $columnSql . ', created_at, updated_at) VALUES ('
                . ($hasId ? ':id, ' : '')
                . ':business_id, :profile_id, ' . $placeholderSql . ', COALESCE(:created_at, NOW()), NOW())';
            $statement = $connection->prepare($sql);
            $params = [
                'business_id' => $businessId,
                'profile_id' => $profileId,
                'created_at' => $hasId && isset($createdAtById[(int) $row['id']])
                    ? $createdAtById[(int) $row['id']]
                    : null,
            ];
            if ($hasId) {
                $params['id'] = (int) $row['id'];
            }
            foreach ($columns as $column) {
                $params[$column] = $row[$column] ?? null;
            }
            $statement->execute($params);
        }
    }

    private static function assertOwnedChildIds(
        string $table,
        array $rows,
        int $businessId,
        int $profileId
    ): void {
        if (!isset(self::CHILD_TABLE_COLUMNS[$table])) {
            throw new LogicException('Unsupported Shared Business Profile child table.');
        }

        $seen = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (isset($seen[$id])) {
                throw new SharedBusinessProfileException(
                    'validation_failed',
                    'A child record ID may only appear once.',
                    ['id' => 'Duplicate child record ID.']
                );
            }
            $seen[$id] = true;

            $statement = Database::connection()->prepare(
                'SELECT business_id, business_profile_id FROM `' . $table . '` WHERE id = :id LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $existing = $statement->fetch();

            if (!$existing) {
                throw new SharedBusinessProfileException('child_record_not_found', 'A referenced profile child record was not found.');
            }
            if ((int) $existing['business_id'] !== $businessId || (int) $existing['business_profile_id'] !== $profileId) {
                throw new SharedBusinessProfileException(
                    'cross_business_reference',
                    'A referenced profile child record belongs to another business.'
                );
            }
        }
    }

    private static function validateProfileInput(array $input, array $currentProfile): array
    {
        $errors = [];
        self::assertKnownFields($input, self::PROFILE_FIELDS, 'profile', $errors);
        $values = [];

        $textFields = [
            'public_display_name' => 255,
            'short_description' => 10000,
            'long_description' => 30000,
            'primary_greeting' => 2000,
            'value_proposition' => 5000,
            'tone' => 100,
            'personality' => 255,
            'prohibited_claims' => 10000,
        ];
        foreach ($textFields as $field => $maxLength) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $values[$field] = self::plainNullableText($input[$field], $maxLength, $field, $errors);
        }

        if (array_key_exists('website_url', $input)) {
            $url = self::nullableText($input['website_url'], 255, 'website_url', $errors);
            if ($url !== null) {
                $validUrl = filter_var($url, FILTER_VALIDATE_URL) !== false;
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                if (!$validUrl || !in_array($scheme, ['http', 'https'], true)) {
                    $errors['website_url'] = 'Enter a valid HTTP or HTTPS URL.';
                }
            }
            $values['website_url'] = $url;
        }

        if (array_key_exists('timezone', $input)) {
            $timezone = self::nullableText($input['timezone'], 100, 'timezone', $errors);
            if ($timezone !== null && !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                $errors['timezone'] = 'Choose a valid IANA timezone.';
            }
            $values['timezone'] = $timezone;
        }

        if (array_key_exists('default_language', $input)) {
            $language = strtolower(trim((string) $input['default_language']));
            if ($language === '' || strlen($language) > 20 || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $language) !== 1) {
                $errors['default_language'] = 'Use a language code such as en or en-US.';
            }
            $values['default_language'] = $language;
        }

        foreach (['appointment_requests_enabled', 'automatic_booking_enabled', 'emergency_service_enabled'] as $field) {
            if (array_key_exists($field, $input)) {
                $values[$field] = self::booleanValue($input[$field], $field, $errors);
            }
        }

        if (array_key_exists('minimum_notice_minutes', $input)) {
            $values['minimum_notice_minutes'] = self::nullableInteger(
                $input['minimum_notice_minutes'],
                0,
                525600,
                'minimum_notice_minutes',
                $errors
            );
        }
        if (array_key_exists('default_appointment_duration_minutes', $input)) {
            $values['default_appointment_duration_minutes'] = self::nullableInteger(
                $input['default_appointment_duration_minutes'],
                1,
                1440,
                'default_appointment_duration_minutes',
                $errors
            );
        }

        $appointmentsEnabled = (int) ($values['appointment_requests_enabled'] ?? $currentProfile['appointment_requests_enabled'] ?? 0);
        $automaticBookingEnabled = (int) ($values['automatic_booking_enabled'] ?? $currentProfile['automatic_booking_enabled'] ?? 0);
        if ($automaticBookingEnabled === 1 && $appointmentsEnabled !== 1) {
            $errors['automatic_booking_enabled'] = 'Automatic booking requires appointment requests to be enabled.';
        }
        if (count($values) === 0 && count($errors) === 0) {
            $errors['profile'] = 'Provide at least one supported profile field to update.';
        }

        self::throwValidationErrors($errors);

        return $values;
    }

    private static function validateHours(array $rows, bool $exceptions): array
    {
        $normalized = [];
        $errors = [];
        $groups = [];
        $orders = [];

        foreach ($rows as $index => $row) {
            $prefix = ($exceptions ? 'exceptions.' : 'hours.') . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each hours entry must be an object or associative array.';
                continue;
            }

            $allowed = $exceptions
                ? ['id', 'exception_date', 'time_range_order', 'label', 'is_closed', 'is_24_hours', 'opens_at', 'closes_at']
                : ['id', 'day_of_week', 'time_range_order', 'is_closed', 'is_24_hours', 'opens_at', 'closes_at'];
            self::assertKnownFields($row, $allowed, $prefix, $errors);

            if ($exceptions) {
                $groupKey = self::isoDate($row['exception_date'] ?? null, $prefix . '.exception_date', $errors);
            } else {
                $groupKey = self::requiredInteger($row['day_of_week'] ?? null, 0, 6, $prefix . '.day_of_week', $errors);
            }

            $groupIndex = (string) $groupKey;
            $defaultOrder = count($groups[$groupIndex] ?? []) + 1;
            $order = array_key_exists('time_range_order', $row)
                ? self::requiredInteger($row['time_range_order'], 1, 65535, $prefix . '.time_range_order', $errors)
                : $defaultOrder;
            $isClosed = self::booleanValue($row['is_closed'] ?? false, $prefix . '.is_closed', $errors);
            $is24Hours = self::booleanValue($row['is_24_hours'] ?? false, $prefix . '.is_24_hours', $errors);
            $opensAt = self::timeValue($row['opens_at'] ?? null, $prefix . '.opens_at', $errors);
            $closesAt = self::timeValue($row['closes_at'] ?? null, $prefix . '.closes_at', $errors);

            if ($isClosed === 1 && ($is24Hours === 1 || $opensAt !== null || $closesAt !== null)) {
                $errors[$prefix] = 'A closed entry cannot also be 24 hours or contain opening times.';
            } elseif ($is24Hours === 1 && ($opensAt !== null || $closesAt !== null)) {
                $errors[$prefix] = 'A 24-hour entry cannot contain opening or closing times.';
            } elseif ($isClosed === 0 && $is24Hours === 0 && ($opensAt === null || $closesAt === null)) {
                $errors[$prefix] = 'Opening and closing times are required for an open time range.';
            } elseif ($opensAt !== null && $closesAt !== null && $opensAt === $closesAt) {
                $errors[$prefix] = 'Opening and closing times cannot be the same; use the 24-hour setting instead.';
            }

            $orderKey = $groupIndex . ':' . $order;
            if (isset($orders[$orderKey])) {
                $errors[$prefix . '.time_range_order'] = 'Time range order must be unique within the day or date.';
            }
            $orders[$orderKey] = true;

            $normalizedRow = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'time_range_order' => $order,
                'is_closed' => $isClosed,
                'is_24_hours' => $is24Hours,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ];
            if ($exceptions) {
                $normalizedRow['exception_date'] = $groupKey;
                $normalizedRow['label'] = self::plainNullableText($row['label'] ?? null, 150, $prefix . '.label', $errors);
            } else {
                $normalizedRow['day_of_week'] = $groupKey;
            }

            $normalized[] = $normalizedRow;
            $groups[$groupIndex][] = ['row' => $normalizedRow, 'prefix' => $prefix];
        }

        foreach ($groups as $entries) {
            $stateEntries = array_filter($entries, function (array $entry): bool {
                return $entry['row']['is_closed'] === 1 || $entry['row']['is_24_hours'] === 1;
            });
            if (count($stateEntries) > 0 && count($entries) > 1) {
                foreach ($entries as $entry) {
                    $errors[$entry['prefix']] = 'Closed and 24-hour days or dates must use exactly one entry.';
                }
                continue;
            }

            $ranges = [];
            foreach ($entries as $entry) {
                $row = $entry['row'];
                if ($row['opens_at'] === null || $row['closes_at'] === null) {
                    continue;
                }
                $start = self::minutesFromTime($row['opens_at']);
                $end = self::minutesFromTime($row['closes_at']);
                if ($end <= $start) {
                    $end += 1440;
                }
                $ranges[] = ['start' => $start, 'end' => $end, 'prefix' => $entry['prefix']];
            }
            usort($ranges, function (array $left, array $right): int {
                return $left['start'] <=> $right['start'];
            });
            for ($rangeIndex = 1; $rangeIndex < count($ranges); $rangeIndex++) {
                if ($ranges[$rangeIndex]['start'] < $ranges[$rangeIndex - 1]['end']) {
                    $errors[$ranges[$rangeIndex]['prefix']] = 'Time ranges on the same day or date cannot overlap.';
                }
            }
        }

        self::throwValidationErrors($errors);
        usort($normalized, function (array $left, array $right) use ($exceptions): int {
            $groupField = $exceptions ? 'exception_date' : 'day_of_week';
            return [$left[$groupField], $left['time_range_order']] <=> [$right[$groupField], $right['time_range_order']];
        });

        return $normalized;
    }

    private static function validateFaqs(array $rows): array
    {
        $normalized = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $prefix = 'faqs.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each FAQ must be an object or associative array.';
                continue;
            }
            self::assertKnownFields($row, ['id', 'question', 'answer', 'channel_scope', 'is_active', 'sort_order'], $prefix, $errors);
            $scope = strtolower(trim((string) ($row['channel_scope'] ?? 'all')));
            if (!in_array($scope, self::FAQ_CHANNEL_SCOPES, true)) {
                $errors[$prefix . '.channel_scope'] = 'Choose a supported FAQ channel scope.';
            }
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'question' => self::requiredPlainText($row['question'] ?? null, 500, $prefix . '.question', $errors),
                'answer' => self::requiredPlainText($row['answer'] ?? null, 5000, $prefix . '.answer', $errors),
                'channel_scope' => $scope,
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
                'sort_order' => self::requiredInteger($row['sort_order'] ?? $index, 0, 100000, $prefix . '.sort_order', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validatePricingGuidance(array $rows): array
    {
        $normalized = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $prefix = 'pricing_guidance.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each pricing guidance entry must be an object or associative array.';
                continue;
            }
            self::assertKnownFields(
                $row,
                ['id', 'guidance_type', 'title', 'guidance_text', 'amount_min', 'amount_max', 'currency_code', 'is_active', 'sort_order'],
                $prefix,
                $errors
            );
            $type = strtolower(trim((string) ($row['guidance_type'] ?? '')));
            if (!in_array($type, self::PRICING_GUIDANCE_TYPES, true)) {
                $errors[$prefix . '.guidance_type'] = 'Choose a supported pricing guidance type.';
            }
            $amountMin = self::nullableDecimal($row['amount_min'] ?? null, $prefix . '.amount_min', $errors);
            $amountMax = self::nullableDecimal($row['amount_max'] ?? null, $prefix . '.amount_max', $errors);
            if ($amountMin !== null && $amountMax !== null && (float) $amountMin > (float) $amountMax) {
                $errors[$prefix . '.amount_max'] = 'Maximum amount must be greater than or equal to minimum amount.';
            }
            if (in_array($type, ['starting_price', 'service_call_fee'], true) && $amountMin === null && $amountMax === null) {
                $errors[$prefix . '.amount_min'] = 'A monetary guidance entry requires at least one approved amount.';
            }
            $currency = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $errors[$prefix . '.currency_code'] = 'Use a three-letter currency code.';
            }
            if (($amountMin !== null || $amountMax !== null) && $currency === '') {
                $errors[$prefix . '.currency_code'] = 'Currency is required when an amount is provided.';
            }
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'guidance_type' => $type,
                'title' => self::plainNullableText($row['title'] ?? null, 150, $prefix . '.title', $errors),
                'guidance_text' => self::requiredPlainText($row['guidance_text'] ?? null, 5000, $prefix . '.guidance_text', $errors),
                'amount_min' => $amountMin,
                'amount_max' => $amountMax,
                'currency_code' => $currency !== '' ? $currency : null,
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
                'sort_order' => self::requiredInteger($row['sort_order'] ?? $index, 0, 100000, $prefix . '.sort_order', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validateAppointmentRules(array $rows, array $business): array
    {
        $normalized = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $prefix = 'appointment_rules.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each appointment rule must be an object or associative array.';
                continue;
            }
            self::assertKnownFields(
                $row,
                ['id', 'rule_type', 'sub_service_id', 'business_custom_service_id', 'rule_text', 'is_active', 'sort_order'],
                $prefix,
                $errors
            );
            $type = strtolower(trim((string) ($row['rule_type'] ?? '')));
            if (!in_array($type, self::APPOINTMENT_RULE_TYPES, true)) {
                $errors[$prefix . '.rule_type'] = 'Choose a supported appointment rule type.';
            }
            $references = self::validateServiceReferences($row, $business, $prefix, $errors);
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'rule_type' => $type,
                'sub_service_id' => $references['sub_service_id'],
                'business_custom_service_id' => $references['business_custom_service_id'],
                'rule_text' => self::requiredPlainText($row['rule_text'] ?? null, 5000, $prefix . '.rule_text', $errors),
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
                'sort_order' => self::requiredInteger($row['sort_order'] ?? $index, 0, 100000, $prefix . '.sort_order', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validateTransferRules(array $rows, array $business): array
    {
        $normalized = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $prefix = 'transfer_rules.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each transfer rule must be an object or associative array.';
                continue;
            }
            self::assertKnownFields(
                $row,
                [
                    'id', 'name', 'transfer_number', 'backup_transfer_number', 'applies_during_business_hours',
                    'applies_after_hours', 'priority', 'maximum_attempts', 'fallback_behavior', 'sub_service_id',
                    'business_custom_service_id', 'condition_text', 'is_active',
                ],
                $prefix,
                $errors
            );
            $duringHours = self::booleanValue(
                $row['applies_during_business_hours'] ?? true,
                $prefix . '.applies_during_business_hours',
                $errors
            );
            $afterHours = self::booleanValue(
                $row['applies_after_hours'] ?? true,
                $prefix . '.applies_after_hours',
                $errors
            );
            if ($duringHours === 0 && $afterHours === 0) {
                $errors[$prefix] = 'A transfer rule must apply during business hours, after hours, or both.';
            }
            $fallback = strtolower(trim((string) ($row['fallback_behavior'] ?? 'create_leadhub_task')));
            if (!in_array($fallback, self::TRANSFER_FALLBACK_BEHAVIORS, true)) {
                $errors[$prefix . '.fallback_behavior'] = 'Choose a supported fallback behavior.';
            }
            $references = self::validateServiceReferences($row, $business, $prefix, $errors);
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'name' => self::requiredPlainText($row['name'] ?? null, 150, $prefix . '.name', $errors),
                'transfer_number' => self::phoneValue($row['transfer_number'] ?? null, $business, false, $prefix . '.transfer_number', $errors),
                'backup_transfer_number' => self::phoneValue(
                    $row['backup_transfer_number'] ?? null,
                    $business,
                    true,
                    $prefix . '.backup_transfer_number',
                    $errors
                ),
                'applies_during_business_hours' => $duringHours,
                'applies_after_hours' => $afterHours,
                'priority' => self::requiredInteger($row['priority'] ?? 100, 1, 100000, $prefix . '.priority', $errors),
                'maximum_attempts' => self::requiredInteger(
                    $row['maximum_attempts'] ?? 1,
                    1,
                    10,
                    $prefix . '.maximum_attempts',
                    $errors
                ),
                'fallback_behavior' => $fallback,
                'sub_service_id' => $references['sub_service_id'],
                'business_custom_service_id' => $references['business_custom_service_id'],
                'condition_text' => self::plainNullableText($row['condition_text'] ?? null, 5000, $prefix . '.condition_text', $errors),
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validateEscalationRules(array $rows, array $business): array
    {
        $normalized = [];
        $errors = [];
        $profile = self::requireProfile((int) $business['id']);
        foreach ($rows as $index => $row) {
            $prefix = 'escalation_rules.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each escalation rule must be an object or associative array.';
                continue;
            }
            self::assertKnownFields(
                $row,
                [
                    'id', 'rule_type', 'name', 'condition_text', 'instruction_text', 'sub_service_id',
                    'business_custom_service_id', 'urgency_level', 'requires_immediate_transfer',
                    'requires_owner_alert', 'disclaimer_text', 'priority', 'is_active',
                ],
                $prefix,
                $errors
            );
            $type = strtolower(trim((string) ($row['rule_type'] ?? '')));
            if (!in_array($type, self::ESCALATION_RULE_TYPES, true)) {
                $errors[$prefix . '.rule_type'] = 'Choose a supported escalation rule type.';
            }
            $urgency = strtolower(trim((string) ($row['urgency_level'] ?? 'normal')));
            if (!in_array($urgency, self::URGENCY_LEVELS, true)) {
                $errors[$prefix . '.urgency_level'] = 'Choose a supported urgency level.';
            }
            if ($urgency === 'emergency' && (int) $profile['emergency_service_enabled'] !== 1) {
                $errors[$prefix . '.urgency_level'] = 'Emergency rules require emergency service to be explicitly enabled.';
            }
            $instruction = self::plainNullableText(
                $row['instruction_text'] ?? null,
                5000,
                $prefix . '.instruction_text',
                $errors
            );
            $disclaimer = self::plainNullableText(
                $row['disclaimer_text'] ?? null,
                5000,
                $prefix . '.disclaimer_text',
                $errors
            );
            if ($instruction === null && $disclaimer === null) {
                $errors[$prefix . '.instruction_text'] = 'Provide approved instruction or disclaimer text.';
            }
            $references = self::validateServiceReferences($row, $business, $prefix, $errors);
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'rule_type' => $type,
                'name' => self::requiredPlainText($row['name'] ?? null, 150, $prefix . '.name', $errors),
                'condition_text' => self::requiredPlainText(
                    $row['condition_text'] ?? null,
                    5000,
                    $prefix . '.condition_text',
                    $errors
                ),
                'instruction_text' => $instruction,
                'sub_service_id' => $references['sub_service_id'],
                'business_custom_service_id' => $references['business_custom_service_id'],
                'urgency_level' => $urgency,
                'requires_immediate_transfer' => self::booleanValue(
                    $row['requires_immediate_transfer'] ?? ($type === 'immediate_transfer'),
                    $prefix . '.requires_immediate_transfer',
                    $errors
                ),
                'requires_owner_alert' => self::booleanValue(
                    $row['requires_owner_alert'] ?? ($type === 'owner_alert'),
                    $prefix . '.requires_owner_alert',
                    $errors
                ),
                'disclaimer_text' => $disclaimer,
                'priority' => self::requiredInteger($row['priority'] ?? 100, 1, 100000, $prefix . '.priority', $errors),
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validateNotificationPreferences(array $rows, array $business): array
    {
        $normalized = [];
        $errors = [];
        $seenTypes = [];
        foreach ($rows as $index => $row) {
            $prefix = 'notification_preferences.' . $index;
            if (!is_array($row)) {
                $errors[$prefix] = 'Each notification preference must be an object or associative array.';
                continue;
            }
            self::assertKnownFields(
                $row,
                [
                    'id', 'notification_type', 'email_enabled', 'sms_enabled', 'in_app_enabled',
                    'destination_email', 'destination_phone', 'daily_summary_enabled', 'is_active',
                ],
                $prefix,
                $errors
            );
            $type = strtolower(trim((string) ($row['notification_type'] ?? '')));
            if (!in_array($type, self::NOTIFICATION_TYPES, true)) {
                $errors[$prefix . '.notification_type'] = 'Choose a supported notification type.';
            } elseif (isset($seenTypes[$type])) {
                $errors[$prefix . '.notification_type'] = 'Each notification type may appear only once.';
            }
            $seenTypes[$type] = true;

            $email = strtolower(trim((string) ($row['destination_email'] ?? '')));
            if ($email !== '' && (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                $errors[$prefix . '.destination_email'] = 'Enter a valid destination email address.';
            }
            $normalized[] = [
                'id' => self::optionalId($row['id'] ?? null, $prefix . '.id', $errors),
                'notification_type' => $type,
                'email_enabled' => self::booleanValue($row['email_enabled'] ?? true, $prefix . '.email_enabled', $errors),
                'sms_enabled' => self::booleanValue($row['sms_enabled'] ?? false, $prefix . '.sms_enabled', $errors),
                'in_app_enabled' => self::booleanValue($row['in_app_enabled'] ?? true, $prefix . '.in_app_enabled', $errors),
                'destination_email' => $email !== '' ? $email : null,
                'destination_phone' => self::phoneValue(
                    $row['destination_phone'] ?? null,
                    $business,
                    true,
                    $prefix . '.destination_phone',
                    $errors
                ),
                'daily_summary_enabled' => self::booleanValue(
                    $row['daily_summary_enabled'] ?? false,
                    $prefix . '.daily_summary_enabled',
                    $errors
                ),
                'is_active' => self::booleanValue($row['is_active'] ?? true, $prefix . '.is_active', $errors),
            ];
        }
        self::throwValidationErrors($errors);

        return $normalized;
    }

    private static function validateServiceReferences(
        array $row,
        array $business,
        string $prefix,
        array &$errors
    ): array {
        $subServiceId = self::optionalId($row['sub_service_id'] ?? null, $prefix . '.sub_service_id', $errors);
        $customServiceId = self::optionalId(
            $row['business_custom_service_id'] ?? null,
            $prefix . '.business_custom_service_id',
            $errors
        );

        if ($subServiceId !== null && $customServiceId !== null) {
            $errors[$prefix] = 'A rule may reference either a selected sub-service or a custom service, not both.';
            return ['sub_service_id' => $subServiceId, 'business_custom_service_id' => $customServiceId];
        }

        if ($subServiceId !== null) {
            $statement = Database::connection()->prepare(
                'SELECT ss.id, bss.business_id
                 FROM sub_services ss
                 LEFT JOIN business_sub_services bss
                   ON bss.sub_service_id = ss.id AND bss.business_id = :business_id
                 WHERE ss.id = :service_id AND ss.is_active = 1
                 LIMIT 1'
            );
            $statement->execute(['business_id' => (int) $business['id'], 'service_id' => $subServiceId]);
            $service = $statement->fetch();
            if (!$service) {
                throw new SharedBusinessProfileException('child_record_not_found', 'A referenced sub-service was not found.');
            }
            if ($service['business_id'] === null) {
                $errors[$prefix . '.sub_service_id'] = 'The referenced sub-service must be selected for this business.';
            }
        }

        if ($customServiceId !== null) {
            $statement = Database::connection()->prepare(
                'SELECT business_id FROM business_custom_services WHERE id = :service_id LIMIT 1'
            );
            $statement->execute(['service_id' => $customServiceId]);
            $ownerBusinessId = $statement->fetchColumn();
            if ($ownerBusinessId === false) {
                throw new SharedBusinessProfileException('child_record_not_found', 'A referenced custom service was not found.');
            }
            if ((int) $ownerBusinessId !== (int) $business['id']) {
                throw new SharedBusinessProfileException(
                    'cross_business_reference',
                    'A referenced custom service belongs to another business.'
                );
            }
        }

        return ['sub_service_id' => $subServiceId, 'business_custom_service_id' => $customServiceId];
    }

    private static function normalizedProfile(array $business, array $profile): array
    {
        $businessId = (int) $business['id'];
        $profileId = (int) $profile['id'];
        $readiness = self::calculateReadinessData($business, $profile);

        return [
            'shared_business_facts' => [
                'business_id' => $businessId,
                'business_profile_id' => $profileId,
                'business_name' => (string) ($business['business_name'] ?? ''),
                'legal_name' => self::nullIfEmpty($business['legal_name'] ?? null),
                'public_display_name' => self::nullIfEmpty($profile['public_display_name'] ?? null),
                'phone' => self::nullIfEmpty($business['phone'] ?? null),
                'email' => self::nullIfEmpty($business['email'] ?? null),
                'website_url' => self::nullIfEmpty($profile['website_url'] ?? null),
                'address' => [
                    'line_1' => self::nullIfEmpty($business['address_line_1'] ?? null),
                    'line_2' => self::nullIfEmpty($business['address_line_2'] ?? null),
                    'city' => self::nullIfEmpty($business['city'] ?? null),
                    'state' => self::nullIfEmpty($business['state'] ?? null),
                    'postal_code' => self::nullIfEmpty($business['postal_code'] ?? null),
                    'country' => self::nullIfEmpty($business['country'] ?? null),
                    'is_public_physical_location' => (int) ($business['is_public_physical_location'] ?? 0) === 1,
                ],
                'timezone' => self::nullIfEmpty($profile['timezone'] ?? null),
                'default_language' => (string) ($profile['default_language'] ?? 'en'),
                'short_description' => self::nullIfEmpty($profile['short_description'] ?? null),
                'long_description' => self::nullIfEmpty($profile['long_description'] ?? null),
                'primary_greeting' => self::nullIfEmpty($profile['primary_greeting'] ?? null),
                'value_proposition' => self::nullIfEmpty($profile['value_proposition'] ?? null),
                'tone' => self::nullIfEmpty($profile['tone'] ?? null),
                'personality' => self::nullIfEmpty($profile['personality'] ?? null),
                'prohibited_claims' => self::nullIfEmpty($profile['prohibited_claims'] ?? null),
                'appointment_requests_enabled' => (int) $profile['appointment_requests_enabled'] === 1,
                'automatic_booking_enabled' => (int) $profile['automatic_booking_enabled'] === 1,
                'minimum_notice_minutes' => self::nullableIntFromDatabase($profile['minimum_notice_minutes'] ?? null),
                'default_appointment_duration_minutes' => self::nullableIntFromDatabase(
                    $profile['default_appointment_duration_minutes'] ?? null
                ),
                'emergency_service_enabled' => (int) $profile['emergency_service_enabled'] === 1,
                'updated_at' => $profile['updated_at'] ?? null,
            ],
            'services' => self::fetchServices($businessId),
            'service_area' => self::fetchServiceArea($business),
            'hours' => self::fetchHours($profileId, $businessId),
            'exceptions' => self::fetchHourExceptions($profileId, $businessId),
            'faqs' => self::fetchFaqs($profileId, $businessId),
            'pricing_guidance' => self::fetchPricingGuidance($profileId, $businessId),
            'appointment_rules' => self::fetchAppointmentRules($profileId, $businessId),
            'transfer_rules' => self::fetchTransferRules($profileId, $businessId),
            'escalation_rules' => self::fetchEscalationRules($profileId, $businessId),
            'notification_preferences' => self::fetchNotificationPreferences($profileId, $businessId),
            'readiness' => $readiness,
            'lifecycle' => [
                'status' => (string) $profile['lifecycle_status'],
                'profile_completed_at' => $profile['profile_completed_at'] ?? null,
                'activated_at' => $profile['activated_at'] ?? null,
                'updated_at' => $profile['updated_at'] ?? null,
            ],
        ];
    }

    private static function fetchServices(int $businessId): array
    {
        $selected = Database::connection()->prepare(
            'SELECT ss.id AS sub_service_id, ss.name, ss.category_id, c.name AS category_name
             FROM business_sub_services bss
             INNER JOIN sub_services ss ON ss.id = bss.sub_service_id
             INNER JOIN categories c ON c.id = ss.category_id
             WHERE bss.business_id = :business_id
             ORDER BY c.name ASC, ss.name ASC, ss.id ASC'
        );
        $selected->execute(['business_id' => $businessId]);

        $custom = Database::connection()->prepare(
            'SELECT bcs.id AS business_custom_service_id, bcs.service_name AS name,
                    bcs.category_id, c.name AS category_name
             FROM business_custom_services bcs
             INNER JOIN categories c ON c.id = bcs.category_id
             WHERE bcs.business_id = :business_id
             ORDER BY c.name ASC, bcs.service_name ASC, bcs.id ASC'
        );
        $custom->execute(['business_id' => $businessId]);

        return [
            'selected_sub_services' => array_map(function (array $row): array {
                return [
                    'sub_service_id' => (int) $row['sub_service_id'],
                    'name' => (string) $row['name'],
                    'category_id' => (int) $row['category_id'],
                    'category_name' => (string) $row['category_name'],
                ];
            }, $selected->fetchAll()),
            'custom_services' => array_map(function (array $row): array {
                return [
                    'business_custom_service_id' => (int) $row['business_custom_service_id'],
                    'name' => (string) $row['name'],
                    'category_id' => (int) $row['category_id'],
                    'category_name' => (string) $row['category_name'],
                ];
            }, $custom->fetchAll()),
        ];
    }

    private static function fetchServiceArea(array $business): array
    {
        $statement = Database::connection()->prepare(
            'SELECT service_area_address, service_area_city, service_area_state, service_area_postal_code,
                    service_area_business, service_area_radius_miles, service_area_radius_is_custom, updated_at
             FROM `247sp_website_configurations`
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $statement->execute(['business_id' => (int) $business['id']]);
        $configuration = $statement->fetch() ?: [];

        $customersVisit = (int) ($business['is_public_physical_location'] ?? 0) === 1;
        $businessTravels = (int) ($configuration['service_area_business'] ?? 0) === 1;
        $mode = 'unconfigured';
        if ($customersVisit && $businessTravels) {
            $mode = 'hybrid';
        } elseif ($businessTravels) {
            $mode = 'business_travels';
        } elseif ($customersVisit) {
            $mode = 'customers_visit';
        }

        return [
            'mode' => $mode,
            'customers_visit_business' => $customersVisit,
            'business_travels_to_customers' => $businessTravels,
            'base_address' => [
                'line_1' => self::nullIfEmpty($configuration['service_area_address'] ?? $business['address_line_1'] ?? null),
                'city' => self::nullIfEmpty($configuration['service_area_city'] ?? $business['city'] ?? null),
                'state' => self::nullIfEmpty($configuration['service_area_state'] ?? $business['state'] ?? null),
                'postal_code' => self::nullIfEmpty(
                    $configuration['service_area_postal_code'] ?? $business['postal_code'] ?? null
                ),
                'country' => self::nullIfEmpty($business['country'] ?? null),
            ],
            'radius_miles' => self::nullableIntFromDatabase($configuration['service_area_radius_miles'] ?? null),
            'radius_is_custom' => (int) ($configuration['service_area_radius_is_custom'] ?? 0) === 1,
            'updated_at' => $configuration['updated_at'] ?? $business['updated_at'] ?? null,
        ];
    }

    private static function fetchHours(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, day_of_week, time_range_order, is_closed, is_24_hours, opens_at, closes_at, updated_at
             FROM business_profile_hours
             WHERE business_profile_id = :profile_id AND business_id = :business_id
             ORDER BY day_of_week ASC, time_range_order ASC, id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'day_of_week' => (int) $row['day_of_week'],
                'time_range_order' => (int) $row['time_range_order'],
                'is_closed' => (int) $row['is_closed'] === 1,
                'is_24_hours' => (int) $row['is_24_hours'] === 1,
                'opens_at' => $row['opens_at'],
                'closes_at' => $row['closes_at'],
                'is_overnight' => $row['opens_at'] !== null && $row['closes_at'] !== null
                    && strcmp((string) $row['closes_at'], (string) $row['opens_at']) < 0,
                'updated_at' => $row['updated_at'],
            ];
        }, $statement->fetchAll());
    }

    private static function fetchHourExceptions(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, exception_date, time_range_order, label, is_closed, is_24_hours, opens_at, closes_at, updated_at
             FROM business_profile_hour_exceptions
             WHERE business_profile_id = :profile_id AND business_id = :business_id
             ORDER BY exception_date ASC, time_range_order ASC, id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'exception_date' => (string) $row['exception_date'],
                'time_range_order' => (int) $row['time_range_order'],
                'label' => self::nullIfEmpty($row['label']),
                'is_closed' => (int) $row['is_closed'] === 1,
                'is_24_hours' => (int) $row['is_24_hours'] === 1,
                'opens_at' => $row['opens_at'],
                'closes_at' => $row['closes_at'],
                'is_overnight' => $row['opens_at'] !== null && $row['closes_at'] !== null
                    && strcmp((string) $row['closes_at'], (string) $row['opens_at']) < 0,
                'updated_at' => $row['updated_at'],
            ];
        }, $statement->fetchAll());
    }

    private static function fetchFaqs(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, question, answer, channel_scope, is_active, sort_order, updated_at
             FROM business_profile_faqs
             WHERE business_profile_id = :profile_id AND business_id = :business_id
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows($statement->fetchAll(), ['is_active'], ['id', 'sort_order']);
    }

    private static function fetchPricingGuidance(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, guidance_type, title, guidance_text, amount_min, amount_max, currency_code,
                    is_active, sort_order, updated_at
             FROM business_profile_pricing_guidance
             WHERE business_profile_id = :profile_id AND business_id = :business_id
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows($statement->fetchAll(), ['is_active'], ['id', 'sort_order']);
    }

    private static function fetchAppointmentRules(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.rule_type, r.sub_service_id, r.business_custom_service_id, r.rule_text,
                    r.is_active, r.sort_order, r.updated_at, ss.name AS sub_service_name,
                    bcs.service_name AS custom_service_name
             FROM business_appointment_rules r
             LEFT JOIN sub_services ss ON ss.id = r.sub_service_id
             LEFT JOIN business_custom_services bcs
               ON bcs.id = r.business_custom_service_id AND bcs.business_id = r.business_id
             WHERE r.business_profile_id = :profile_id AND r.business_id = :business_id
             ORDER BY r.sort_order ASC, r.id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows(
            $statement->fetchAll(),
            ['is_active'],
            ['id', 'sort_order', 'sub_service_id', 'business_custom_service_id']
        );
    }

    private static function fetchTransferRules(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.name, r.transfer_number, r.backup_transfer_number,
                    r.applies_during_business_hours, r.applies_after_hours, r.priority,
                    r.maximum_attempts, r.fallback_behavior, r.sub_service_id,
                    r.business_custom_service_id, r.condition_text, r.is_active, r.updated_at,
                    ss.name AS sub_service_name, bcs.service_name AS custom_service_name
             FROM business_transfer_rules r
             LEFT JOIN sub_services ss ON ss.id = r.sub_service_id
             LEFT JOIN business_custom_services bcs
               ON bcs.id = r.business_custom_service_id AND bcs.business_id = r.business_id
             WHERE r.business_profile_id = :profile_id AND r.business_id = :business_id
             ORDER BY r.priority ASC, r.id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows(
            $statement->fetchAll(),
            ['applies_during_business_hours', 'applies_after_hours', 'is_active'],
            ['id', 'priority', 'maximum_attempts', 'sub_service_id', 'business_custom_service_id']
        );
    }

    private static function fetchEscalationRules(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.rule_type, r.name, r.condition_text, r.instruction_text,
                    r.sub_service_id, r.business_custom_service_id, r.urgency_level,
                    r.requires_immediate_transfer, r.requires_owner_alert, r.disclaimer_text,
                    r.priority, r.is_active, r.updated_at, ss.name AS sub_service_name,
                    bcs.service_name AS custom_service_name
             FROM business_escalation_rules r
             LEFT JOIN sub_services ss ON ss.id = r.sub_service_id
             LEFT JOIN business_custom_services bcs
               ON bcs.id = r.business_custom_service_id AND bcs.business_id = r.business_id
             WHERE r.business_profile_id = :profile_id AND r.business_id = :business_id
             ORDER BY r.priority ASC, r.id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows(
            $statement->fetchAll(),
            ['requires_immediate_transfer', 'requires_owner_alert', 'is_active'],
            ['id', 'priority', 'sub_service_id', 'business_custom_service_id']
        );
    }

    private static function fetchNotificationPreferences(int $profileId, int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, notification_type, email_enabled, sms_enabled, in_app_enabled,
                    destination_email, destination_phone, daily_summary_enabled, is_active, updated_at
             FROM business_notification_preferences
             WHERE business_profile_id = :profile_id AND business_id = :business_id
             ORDER BY notification_type ASC, id ASC'
        );
        $statement->execute(['profile_id' => $profileId, 'business_id' => $businessId]);

        return self::booleanizeRows(
            $statement->fetchAll(),
            ['email_enabled', 'sms_enabled', 'in_app_enabled', 'daily_summary_enabled', 'is_active'],
            ['id']
        );
    }

    private static function calculateReadinessData(array $business, array $profile): array
    {
        $businessId = (int) $business['id'];
        $profileId = (int) $profile['id'];
        $completed = [];
        $incomplete = [];
        $missing = [];
        $warnings = [];

        $identityMissing = [];
        foreach (['business_name', 'phone', 'email'] as $field) {
            if (trim((string) ($business[$field] ?? '')) === '') {
                $identityMissing[] = 'business.' . $field;
            }
        }
        self::recordSection('business_identity', $identityMissing, $completed, $incomplete, $missing);

        $timezone = trim((string) ($profile['timezone'] ?? ''));
        $timezoneMissing = [];
        if ($timezone === '' || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezoneMissing[] = 'shared_business_facts.timezone';
        }
        self::recordSection('timezone', $timezoneMissing, $completed, $incomplete, $missing);

        $services = self::fetchServices($businessId);
        $serviceMissing = count($services['selected_sub_services']) + count($services['custom_services']) > 0
            ? []
            : ['services'];
        self::recordSection('services', $serviceMissing, $completed, $incomplete, $missing);

        $serviceArea = self::fetchServiceArea($business);
        $serviceAreaMissing = [];
        if ($serviceArea['mode'] === 'unconfigured') {
            $serviceAreaMissing[] = 'service_area.mode';
        }
        if ($serviceArea['business_travels_to_customers'] && (int) ($serviceArea['radius_miles'] ?? 0) <= 0) {
            $serviceAreaMissing[] = 'service_area.radius_miles';
        }
        foreach (['city', 'state', 'postal_code'] as $field) {
            if (trim((string) ($serviceArea['base_address'][$field] ?? '')) === '') {
                $serviceAreaMissing[] = 'service_area.base_address.' . $field;
            }
        }
        self::recordSection('service_area', $serviceAreaMissing, $completed, $incomplete, $missing);

        $hours = self::fetchHours($profileId, $businessId);
        $hoursMissing = self::hoursReadinessMissing($hours);
        self::recordSection('hours', $hoursMissing, $completed, $incomplete, $missing);

        $greetingMissing = trim((string) ($profile['primary_greeting'] ?? '')) === ''
            ? ['shared_business_facts.primary_greeting']
            : [];
        self::recordSection('greeting', $greetingMissing, $completed, $incomplete, $missing);

        $faqs = self::fetchFaqs($profileId, $businessId);
        $activeFaqs = array_filter($faqs, function (array $faq): bool {
            return $faq['is_active'];
        });
        self::recordSection(
            'faqs',
            count($activeFaqs) > 0 ? [] : ['faqs.active'],
            $completed,
            $incomplete,
            $missing
        );

        $transferRules = self::fetchTransferRules($profileId, $businessId);
        $activeTransfers = array_filter($transferRules, function (array $rule): bool {
            return $rule['is_active'];
        });
        $transferMissing = count($activeTransfers) > 0 ? [] : ['transfer_rules.active'];
        foreach ($activeTransfers as $rule) {
            if (preg_match('/^\+[1-9]\d{7,14}$/', (string) $rule['transfer_number']) !== 1) {
                $transferMissing[] = 'transfer_rules.' . $rule['id'] . '.transfer_number';
            }
        }
        self::recordSection('transfer_behavior', $transferMissing, $completed, $incomplete, $missing);

        $escalationRules = self::fetchEscalationRules($profileId, $businessId);
        $activeEscalations = array_filter($escalationRules, function (array $rule): bool {
            return $rule['is_active'];
        });
        $escalationMissing = count($activeEscalations) > 0 ? [] : ['escalation_rules.active'];
        if ((int) $profile['emergency_service_enabled'] === 1) {
            $hasEmergencyBehavior = count(array_filter($activeEscalations, function (array $rule): bool {
                return $rule['urgency_level'] === 'emergency' || $rule['requires_immediate_transfer'];
            })) > 0;
            if (!$hasEmergencyBehavior) {
                $escalationMissing[] = 'escalation_rules.emergency_behavior';
            }
        }
        self::recordSection('escalation_behavior', $escalationMissing, $completed, $incomplete, $missing);

        $preferences = self::fetchNotificationPreferences($profileId, $businessId);
        $activePreferences = array_filter($preferences, function (array $preference): bool {
            return $preference['is_active'];
        });
        $notificationMissing = count($activePreferences) > 0 ? [] : ['notification_preferences.active'];
        foreach ($activePreferences as $preference) {
            $prefix = 'notification_preferences.' . $preference['notification_type'];
            if ($preference['email_enabled'] && trim((string) $preference['destination_email']) === '') {
                $notificationMissing[] = $prefix . '.destination_email';
            }
            if ($preference['sms_enabled'] && preg_match('/^\+[1-9]\d{7,14}$/', (string) $preference['destination_phone']) !== 1) {
                $notificationMissing[] = $prefix . '.destination_phone';
            }
        }
        self::recordSection('notification_destinations', $notificationMissing, $completed, $incomplete, $missing);

        $appointmentRules = self::fetchAppointmentRules($profileId, $businessId);
        $activeAppointmentRules = array_filter($appointmentRules, function (array $rule): bool {
            return $rule['is_active'];
        });
        if ((int) $profile['appointment_requests_enabled'] === 1) {
            self::recordSection(
                'appointment_rules',
                count($activeAppointmentRules) > 0 ? [] : ['appointment_rules.active'],
                $completed,
                $incomplete,
                $missing
            );
        } elseif (count($activeAppointmentRules) === 0) {
            $warnings[] = 'Appointment rules are optional while appointment requests are disabled.';
        }

        if (count(array_filter(self::fetchPricingGuidance($profileId, $businessId), function (array $row): bool {
            return $row['is_active'];
        })) === 0) {
            $warnings[] = 'Approved pricing guidance has not been added; it is optional for current profile completion.';
        }
        if ((int) $profile['automatic_booking_enabled'] === 1) {
            $warnings[] = 'Automatic booking is configured, but this service does not provide scheduling or calendar availability.';
        }

        return [
            'is_complete' => count($incomplete) === 0,
            'lifecycle_status' => (string) $profile['lifecycle_status'],
            'completed_sections' => $completed,
            'incomplete_sections' => $incomplete,
            'missing_fields' => $missing,
            'warnings' => array_values(array_unique($warnings)),
            'calculated_at' => gmdate('c'),
            'readiness_version' => self::READINESS_VERSION,
        ];
    }

    private static function recordSection(
        string $section,
        array $sectionMissing,
        array &$completed,
        array &$incomplete,
        array &$missing
    ): void {
        $sectionMissing = array_values(array_unique($sectionMissing));
        if (count($sectionMissing) === 0) {
            $completed[] = $section;
            return;
        }
        $incomplete[] = $section;
        $missing[$section] = $sectionMissing;
    }

    private static function hoursReadinessMissing(array $hours): array
    {
        $missing = [];
        $byDay = [];
        foreach ($hours as $row) {
            $day = (int) $row['day_of_week'];
            $byDay[$day][] = $row;
            $contradictory = ($row['is_closed'] && ($row['is_24_hours'] || $row['opens_at'] !== null || $row['closes_at'] !== null))
                || ($row['is_24_hours'] && ($row['opens_at'] !== null || $row['closes_at'] !== null))
                || (!$row['is_closed'] && !$row['is_24_hours'] && ($row['opens_at'] === null || $row['closes_at'] === null));
            if ($contradictory) {
                $missing[] = 'hours.' . $day . '.valid_state';
            }
        }
        for ($day = 0; $day <= 6; $day++) {
            if (!isset($byDay[$day])) {
                $missing[] = 'hours.' . $day;
                continue;
            }
            $seenOrders = [];
            $ranges = [];
            foreach ($byDay[$day] as $row) {
                $order = (int) $row['time_range_order'];
                if (isset($seenOrders[$order])) {
                    $missing[] = 'hours.' . $day . '.unique_range_order';
                }
                $seenOrders[$order] = true;
                if ($row['opens_at'] !== null && $row['closes_at'] !== null) {
                    $start = self::minutesFromTime((string) $row['opens_at']);
                    $end = self::minutesFromTime((string) $row['closes_at']);
                    if ($end <= $start) {
                        $end += 1440;
                    }
                    $ranges[] = ['start' => $start, 'end' => $end];
                }
            }
            usort($ranges, function (array $left, array $right): int {
                return $left['start'] <=> $right['start'];
            });
            for ($rangeIndex = 1; $rangeIndex < count($ranges); $rangeIndex++) {
                if ($ranges[$rangeIndex]['start'] < $ranges[$rangeIndex - 1]['end']) {
                    $missing[] = 'hours.' . $day . '.non_overlapping_ranges';
                }
            }
            $stateRows = array_filter($byDay[$day], function (array $row): bool {
                return $row['is_closed'] || $row['is_24_hours'];
            });
            if (count($stateRows) > 0 && count($byDay[$day]) > 1) {
                $missing[] = 'hours.' . $day . '.single_state';
            }
        }

        return array_values(array_unique($missing));
    }

    private static function booleanizeRows(array $rows, array $booleanFields, array $integerFields): array
    {
        foreach ($rows as &$row) {
            foreach ($booleanFields as $field) {
                $row[$field] = (int) ($row[$field] ?? 0) === 1;
            }
            foreach ($integerFields as $field) {
                $row[$field] = self::nullableIntFromDatabase($row[$field] ?? null);
            }
        }
        unset($row);

        return $rows;
    }

    private static function assertKnownFields(array $input, array $allowed, string $prefix, array &$errors): void
    {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                $name = is_string($field) ? $field : (string) $field;
                $errors[$prefix . '.' . $name] = 'This field is not supported by the Shared Business Profile service.';
            }
        }
    }

    private static function throwValidationErrors(array $errors): void
    {
        if (count($errors) === 0) {
            return;
        }

        throw new SharedBusinessProfileException(
            'validation_failed',
            'One or more Shared Business Profile fields are invalid.',
            $errors
        );
    }

    private static function requiredPlainText($value, int $maxLength, string $field, array &$errors): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            $errors[$field] = 'This field is required.';
            return '';
        }
        if (self::stringLength($text) > $maxLength) {
            $errors[$field] = 'This field may not exceed ' . $maxLength . ' characters.';
        }
        if (self::containsMarkup($text)) {
            $errors[$field] = 'HTML or markup is not allowed.';
        }

        return $text;
    }

    private static function plainNullableText($value, int $maxLength, string $field, array &$errors): ?string
    {
        $text = self::nullableText($value, $maxLength, $field, $errors);
        if ($text !== null && self::containsMarkup($text)) {
            $errors[$field] = 'HTML or markup is not allowed.';
        }

        return $text;
    }

    private static function nullableText($value, int $maxLength, string $field, array &$errors): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (self::stringLength($text) > $maxLength) {
            $errors[$field] = 'This field may not exceed ' . $maxLength . ' characters.';
        }

        return $text;
    }

    private static function containsMarkup(string $value): bool
    {
        return preg_match('/<\/?[a-z][^>]*>/i', $value) === 1;
    }

    private static function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function booleanValue($value, string $field, array &$errors): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ($value === 1 || $value === 0 || $value === '1' || $value === '0') {
            return (int) $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['true', 'yes', 'on'], true)) {
            return 1;
        }
        if (in_array($normalized, ['false', 'no', 'off'], true)) {
            return 0;
        }
        $errors[$field] = 'Use a boolean value.';

        return 0;
    }

    private static function requiredInteger($value, int $minimum, int $maximum, string $field, array &$errors): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < $minimum || $validated > $maximum) {
            $errors[$field] = 'Enter a whole number from ' . $minimum . ' through ' . $maximum . '.';
            return $minimum;
        }

        return (int) $validated;
    }

    private static function nullableInteger($value, int $minimum, int $maximum, string $field, array &$errors): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return self::requiredInteger($value, $minimum, $maximum, $field, $errors);
    }

    private static function optionalId($value, string $field, array &$errors): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return self::requiredInteger($value, 1, PHP_INT_MAX, $field, $errors);
    }

    private static function nullableDecimal($value, string $field, array &$errors): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $string = trim((string) $value);
        if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $string) !== 1 || (float) $string > 99999999.99) {
            $errors[$field] = 'Enter a non-negative amount with no more than two decimal places.';
            return null;
        }

        return number_format((float) $string, 2, '.', '');
    }

    private static function isoDate($value, string $field, array &$errors): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $errors[$field] = 'Use an ISO date in YYYY-MM-DD format.';
            return '';
        }

        return $date;
    }

    private static function timeValue($value, string $field, array &$errors): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $time = trim((string) $value);
        foreach (['!H:i', '!H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $time);
            if ($parsed !== false) {
                $expected = $format === '!H:i' ? 'H:i' : 'H:i:s';
                if ($parsed->format($expected) === $time) {
                    return $parsed->format('H:i:s');
                }
            }
        }
        $errors[$field] = 'Use a 24-hour time in HH:MM format.';

        return null;
    }

    private static function minutesFromTime(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    private static function phoneValue(
        $value,
        array $business,
        bool $nullable,
        string $field,
        array &$errors
    ): ?string {
        $phone = trim((string) $value);
        if ($phone === '') {
            if (!$nullable) {
                $errors[$field] = 'A phone number is required.';
            }
            return null;
        }

        $hasPlus = substr($phone, 0, 1) === '+';
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits)) {
            $digits = '';
        }
        if (!$hasPlus && strlen($digits) === 10 && self::isNorthAmericanBusiness($business)) {
            $digits = '1' . $digits;
        }
        $normalized = '+' . $digits;
        if (preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            $errors[$field] = 'Enter an E.164-compatible phone number, including country code when needed.';
            return null;
        }

        return $normalized;
    }

    private static function isNorthAmericanBusiness(array $business): bool
    {
        $country = strtolower(trim((string) ($business['country'] ?? '')));

        return in_array($country, ['us', 'usa', 'united states', 'united states of america', 'canada', 'ca'], true);
    }

    private static function nullableIntFromDatabase($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullIfEmpty($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function jsonForStorage(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Shared Business Profile data could not be encoded.');
        }

        return $json;
    }

    private static function logMutation(
        int $businessId,
        int $actingUserId,
        string $action,
        string $targetType,
        int $targetId,
        array $summary
    ): void {
        $metadata = [
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'change_summary' => $summary,
        ];
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (
                business_id, user_id, module_key, activity_type, subject, description, metadata_json, created_at
             ) VALUES (
                :business_id, :user_id, :module_key, :activity_type, :subject, :description, :metadata_json, NOW()
             )'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $actingUserId,
            'module_key' => self::MODULE_KEY,
            'activity_type' => 'shared_business_profile_' . $action,
            'subject' => 'Shared Business Profile updated',
            'description' => 'Updated ' . str_replace('_', ' ', $targetType) . '.',
            'metadata_json' => self::jsonForStorage($metadata),
        ]);
    }

    private static function reportFailure(string $operation, Throwable $exception): void
    {
        error_log(
            '[SharedBusinessProfile] ' . $operation . ' failed: '
            . get_class($exception) . ': ' . $exception->getMessage()
        );
    }
}
