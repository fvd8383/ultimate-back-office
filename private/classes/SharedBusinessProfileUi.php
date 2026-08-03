<?php

require_once __DIR__ . '/SharedBusinessProfile.php';

final class SharedBusinessProfileUi
{
    private const CUSTOMER_ACTIONS = [
        'save_shared_facts' => 'shared_facts',
        'save_hours' => 'hours',
        'save_exceptions' => 'exceptions',
        'save_faqs' => 'faqs',
        'save_pricing_guidance' => 'pricing_guidance',
        'save_appointment_settings' => 'appointment_settings',
        'save_appointment_rules' => 'appointment_rules',
        'save_transfer_rules' => 'transfer_rules',
        'save_escalation_rules' => 'escalation_rules',
        'save_notification_preferences' => 'notification_preferences',
        'submit_for_review' => 'overview',
    ];

    private const ADMIN_ACTIONS = [
        'transition_lifecycle' => 'overview',
    ];

    private const COLLECTION_ACTIONS = [
        'save_hours' => 'replaceHours',
        'save_exceptions' => 'replaceHourExceptions',
        'save_faqs' => 'saveFaqs',
        'save_pricing_guidance' => 'savePricingGuidance',
        'save_appointment_rules' => 'saveAppointmentRules',
        'save_transfer_rules' => 'saveTransferRules',
        'save_escalation_rules' => 'saveEscalationRules',
        'save_notification_preferences' => 'saveNotificationPreferences',
    ];

    public static function action(array $post, bool $admin = false): string
    {
        $action = strtolower(trim((string) ($post['profile_action'] ?? '')));
        $allowed = $admin ? self::ADMIN_ACTIONS : self::CUSTOMER_ACTIONS;

        if (!isset($allowed[$action])) {
            throw new InvalidArgumentException('The requested profile action is not supported.');
        }

        return $action;
    }

    public static function sectionForAction(string $action, bool $admin = false): string
    {
        $allowed = $admin ? self::ADMIN_ACTIONS : self::CUSTOMER_ACTIONS;

        return $allowed[$action] ?? 'overview';
    }

    public static function payload(string $action, array $post): array
    {
        if ($action === 'save_shared_facts') {
            return self::pick($post, [
                'public_display_name', 'website_url', 'timezone', 'default_language', 'short_description',
                'long_description', 'primary_greeting', 'value_proposition', 'tone', 'personality',
                'prohibited_claims',
            ]);
        }

        if ($action === 'save_appointment_settings') {
            $payload = self::pick($post, [
                'minimum_notice_minutes', 'default_appointment_duration_minutes',
            ]);
            foreach (['appointment_requests_enabled', 'automatic_booking_enabled', 'emergency_service_enabled'] as $field) {
                $payload[$field] = isset($post[$field]) ? '1' : '0';
            }

            return $payload;
        }

        if (isset(self::COLLECTION_ACTIONS[$action])) {
            return self::collectionPayload($action, $post);
        }

        if ($action === 'submit_for_review') {
            return ['target_status' => 'in_review'];
        }

        if ($action === 'transition_lifecycle') {
            $target = strtolower(trim((string) ($post['target_status'] ?? '')));
            if (!in_array($target, ['draft', 'incomplete', 'in_review', 'ready', 'active'], true)) {
                throw new InvalidArgumentException('Choose a supported lifecycle status.');
            }

            return ['target_status' => $target];
        }

        throw new InvalidArgumentException('The requested profile action is not supported.');
    }

    public static function dispatch(
        string $action,
        int $businessId,
        int $actingUserId,
        array $payload,
        ?callable $invoker = null
    ): array {
        if ($action === 'save_shared_facts' || $action === 'save_appointment_settings') {
            return self::invoke('updateProfile', [$businessId, $actingUserId, $payload], $invoker);
        }

        if (isset(self::COLLECTION_ACTIONS[$action])) {
            return self::invoke(self::COLLECTION_ACTIONS[$action], [$businessId, $actingUserId, $payload], $invoker);
        }

        if ($action === 'submit_for_review' || $action === 'transition_lifecycle') {
            return self::invoke(
                'transitionLifecycleStatus',
                [$businessId, $actingUserId, (string) $payload['target_status']],
                $invoker
            );
        }

        throw new InvalidArgumentException('The requested profile action is not supported.');
    }

    public static function fieldErrorsForSection(string $section, array $fieldErrors): array
    {
        $prefixes = [
            'shared_facts' => ['profile', 'public_display_name', 'website_url', 'timezone', 'default_language', 'short_description', 'long_description', 'primary_greeting', 'value_proposition', 'tone', 'personality', 'prohibited_claims'],
            'hours' => ['hours'],
            'exceptions' => ['exceptions'],
            'faqs' => ['faqs'],
            'pricing_guidance' => ['pricing_guidance'],
            'appointment_settings' => ['profile', 'appointment_requests_enabled', 'automatic_booking_enabled', 'minimum_notice_minutes', 'default_appointment_duration_minutes', 'emergency_service_enabled'],
            'appointment_rules' => ['appointment_rules'],
            'transfer_rules' => ['transfer_rules'],
            'escalation_rules' => ['escalation_rules'],
            'notification_preferences' => ['notification_preferences'],
            'overview' => ['lifecycle_status'],
        ];
        $allowed = $prefixes[$section] ?? [];

        return array_filter(
            $fieldErrors,
            static function ($message, $field) use ($allowed): bool {
                foreach ($allowed as $prefix) {
                    if ($field === $prefix || str_starts_with((string) $field, $prefix . '.')) {
                        return true;
                    }
                }

                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function allowedTransitions(string $status): array
    {
        return SharedBusinessProfile::allowedLifecycleTransitions($status);
    }

    public static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function statusLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private static function collectionPayload(string $action, array $post): array
    {
        if (!array_key_exists('rows', $post) || !is_array($post['rows'])) {
            throw new InvalidArgumentException('This section was incomplete. No profile records were changed.');
        }

        $rows = [];
        $explicitRemovalCount = 0;
        foreach ($post['rows'] as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('This section contained an invalid row. No profile records were changed.');
            }

            $id = (int) ($row['id'] ?? 0);
            $remove = self::truthy($row['_remove'] ?? false);
            $include = self::truthy($row['_include'] ?? false);
            if ($remove) {
                if ($id > 0) {
                    $explicitRemovalCount++;
                }
                continue;
            }
            if ($id <= 0 && !$include) {
                continue;
            }

            unset($row['_remove'], $row['_include']);
            if ($id <= 0) {
                unset($row['id']);
            }
            self::normalizeCollectionBooleans($action, $row);
            self::normalizeHoursState($action, $row);
            self::normalizeServiceReference($action, $row);
            $rows[] = $row;
        }

        if (count($rows) === 0
            && $explicitRemovalCount === 0
            && !self::truthy($post['confirm_empty'] ?? false)
        ) {
            throw new InvalidArgumentException(
                'No profile rows were submitted. Existing records were left unchanged.'
            );
        }

        return array_values($rows);
    }

    private static function normalizeCollectionBooleans(string $action, array &$row): void
    {
        $fields = [
            'save_faqs' => ['is_active'],
            'save_pricing_guidance' => ['is_active'],
            'save_appointment_rules' => ['is_active'],
            'save_transfer_rules' => ['applies_during_business_hours', 'applies_after_hours', 'is_active'],
            'save_escalation_rules' => ['requires_immediate_transfer', 'requires_owner_alert', 'is_active'],
            'save_notification_preferences' => ['email_enabled', 'sms_enabled', 'in_app_enabled', 'daily_summary_enabled', 'is_active'],
        ];
        foreach ($fields[$action] ?? [] as $field) {
            $row[$field] = isset($row[$field]) && self::truthy($row[$field]) ? '1' : '0';
        }
    }

    private static function normalizeHoursState(string $action, array &$row): void
    {
        if (!in_array($action, ['save_hours', 'save_exceptions'], true)) {
            return;
        }

        $state = strtolower(trim((string) ($row['state'] ?? 'open')));
        unset($row['state']);
        $row['is_closed'] = $state === 'closed' ? '1' : '0';
        $row['is_24_hours'] = $state === '24_hours' ? '1' : '0';
        if ($state !== 'open') {
            $row['opens_at'] = '';
            $row['closes_at'] = '';
        }
    }

    private static function normalizeServiceReference(string $action, array &$row): void
    {
        if (!in_array($action, [
            'save_appointment_rules', 'save_transfer_rules', 'save_escalation_rules',
        ], true)) {
            return;
        }

        $reference = trim((string) ($row['service_reference'] ?? ''));
        unset($row['service_reference']);
        $row['sub_service_id'] = '';
        $row['business_custom_service_id'] = '';
        if (str_starts_with($reference, 'sub:')) {
            $row['sub_service_id'] = substr($reference, 4);
        } elseif (str_starts_with($reference, 'custom:')) {
            $row['business_custom_service_id'] = substr($reference, 7);
        }
    }

    private static function pick(array $source, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $source)) {
                $result[$field] = $source[$field];
            }
        }

        if (count($result) === 0) {
            throw new InvalidArgumentException('No supported profile fields were submitted.');
        }

        return $result;
    }

    private static function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private static function invoke(string $method, array $arguments, ?callable $invoker): array
    {
        if ($invoker !== null) {
            return $invoker($method, $arguments);
        }

        return SharedBusinessProfile::{$method}(...$arguments);
    }
}
