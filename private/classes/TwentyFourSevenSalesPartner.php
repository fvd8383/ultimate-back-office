<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BusinessFoundation.php';
require_once __DIR__ . '/DomainAutomation.php';
require_once __DIR__ . '/EmailProvisioningFoundation.php';

final class TwentyFourSevenSalesPartner
{
    public const STEPS = [
        'business_information',
        'service_area',
        'services',
        'website_content',
        'domain_selection',
        'email_selection',
    ];

    private const NEXT_STEP = [
        'business_information' => 'service_area',
        'service_area' => 'services',
        'services' => 'website_content',
        'website_content' => 'domain_selection',
        'domain_selection' => 'email_selection',
        'email_selection' => 'review',
    ];

    public static function businessForUser(?int $businessId, int $userId): ?array
    {
        if ($businessId !== null && $businessId > 0) {
            return BusinessFoundation::businessForUser($businessId, $userId);
        }

        return BusinessFoundation::firstBusinessForUser($userId);
    }

    public static function businessHasAccess(int $businessId): bool
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
            'module_key' => '247sp',
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function bundle(int $businessId): array
    {
        $onboarding = self::onboarding($businessId);

        return [
            'onboarding' => $onboarding,
            'configuration' => self::oneByBusiness('247sp_website_configurations', $businessId),
            'content' => self::oneByBusiness('247sp_business_content', $businessId),
            'service_pages' => self::servicePages($businessId),
            'domain' => self::oneByBusiness('247sp_domain_selections', $businessId),
            'email' => self::oneByBusiness('247sp_email_requests', $businessId),
        ];
    }

    public static function dashboardSummary(int $businessId): array
    {
        $bundle = self::bundle($businessId);
        $onboarding = $bundle['onboarding'];
        $domain = $bundle['domain'];
        $email = $bundle['email'];
        $configuration = $bundle['configuration'];
        $domainWorkflow = DomainAutomation::currentDomainForBusiness($businessId);

        return [
            'website_status' => self::websiteStatus($onboarding, $configuration),
            'domain_status' => $domainWorkflow['domain_status'] ?? ($domain['status'] ?? 'not_selected'),
            'email_status' => EmailProvisioningFoundation::currentEmailStatusForBusiness($businessId) ?: ($email['status'] ?? 'not_selected'),
            'current_step' => $onboarding['current_step'] ?? 'business_information',
            'setup_status' => $onboarding['setup_status'] ?? 'not_started',
            'completed_at' => $onboarding['completed_at'] ?? null,
        ];
    }

    public static function saveBusinessInformation(int $businessId, int $userId, array $input): void
    {
        $businessName = trim((string) ($input['business_name'] ?? ''));
        $contactName = trim((string) ($input['contact_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $businessStartedOn = self::normalizeOptionalDate($input['business_started_on'] ?? '');

        if ($businessName === '' || $contactName === '' || $email === '' || $phone === '') {
            throw new InvalidArgumentException('Business name, contact name, email, and phone are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $onboarding = self::ensureOnboarding($businessId);

        Database::connection()->beginTransaction();

        try {
            $business = Database::connection()->prepare(
                'UPDATE businesses
                 SET business_name = :business_name,
                     email = :email,
                     phone = :phone,
                     business_started_on = :business_started_on,
                     updated_at = NOW()
                 WHERE id = :business_id'
            );
            $business->execute([
                'business_name' => $businessName,
                'email' => $email,
                'phone' => $phone,
                'business_started_on' => $businessStartedOn,
                'business_id' => $businessId,
            ]);

            $statement = Database::connection()->prepare(
                'UPDATE `247sp_onboarding`
                 SET contact_name = :contact_name,
                     setup_status = :setup_status,
                     current_step = :current_step,
                     updated_at = NOW()
                 WHERE id = :onboarding_id'
            );
            $statement->execute([
                'contact_name' => $contactName,
                'setup_status' => 'in_progress',
                'current_step' => self::NEXT_STEP['business_information'],
                'onboarding_id' => (int) $onboarding['id'],
            ]);

            self::logActivity($businessId, $userId, '247sp_business_information_saved', '247SP business information saved');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function saveServiceArea(int $businessId, int $userId, array $input): void
    {
        $address = trim((string) ($input['address_line_1'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $state = trim((string) ($input['state'] ?? ''));
        $postalCode = trim((string) ($input['postal_code'] ?? ''));
        $isServiceAreaBusiness = isset($input['service_area_business']) ? 1 : 0;

        if ($address === '' || $city === '' || $state === '' || $postalCode === '') {
            throw new InvalidArgumentException('Address, city, state, and ZIP are required.');
        }

        $onboarding = self::ensureOnboarding($businessId);

        Database::connection()->beginTransaction();

        try {
            $business = Database::connection()->prepare(
                'UPDATE businesses
                 SET address_line_1 = :address,
                     city = :city,
                     state = :state,
                     postal_code = :postal_code,
                     is_public_physical_location = :is_public_physical_location,
                     updated_at = NOW()
                 WHERE id = :business_id'
            );
            $business->execute([
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode,
                'is_public_physical_location' => $isServiceAreaBusiness ? 0 : 1,
                'business_id' => $businessId,
            ]);

            self::upsertWebsiteConfiguration($businessId, (int) $onboarding['id'], [
                'service_area_address' => $address,
                'service_area_city' => $city,
                'service_area_state' => $state,
                'service_area_postal_code' => $postalCode,
                'service_area_business' => $isServiceAreaBusiness,
                'website_status' => 'in_progress',
            ]);
            self::advance($businessId, self::NEXT_STEP['service_area']);
            self::logActivity($businessId, $userId, '247sp_service_area_saved', '247SP service area saved');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function saveServices(int $businessId, int $userId, array $input): void
    {
        $categoryId = (int) ($input['primary_category_id'] ?? 0);

        if ($categoryId <= 0 || !self::categoryExists($categoryId)) {
            throw new InvalidArgumentException('Select one primary service category.');
        }

        $services = [];
        for ($i = 1; $i <= 3; $i++) {
            $name = trim((string) ($input["service_{$i}_name"] ?? ''));
            $description = trim((string) ($input["service_{$i}_description"] ?? ''));

            if ($name === '' || $description === '') {
                throw new InvalidArgumentException("Service {$i} requires a name and short description.");
            }

            $services[] = [
                'service_number' => $i,
                'service_name' => $name,
                'short_description' => $description,
            ];
        }

        $onboarding = self::ensureOnboarding($businessId);

        Database::connection()->beginTransaction();

        try {
            $business = Database::connection()->prepare(
                'UPDATE businesses
                 SET primary_category_id = :primary_category_id,
                     updated_at = NOW()
                 WHERE id = :business_id'
            );
            $business->execute([
                'primary_category_id' => $categoryId,
                'business_id' => $businessId,
            ]);

            self::upsertWebsiteConfiguration($businessId, (int) $onboarding['id'], [
                'primary_category_id' => $categoryId,
                'website_status' => 'in_progress',
            ]);

            $statement = Database::connection()->prepare(
                'INSERT INTO `247sp_service_pages` (
                    business_id, onboarding_id, service_number, service_name, short_description, created_at, updated_at
                 ) VALUES (
                    :business_id, :onboarding_id, :service_number, :service_name, :short_description, NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    service_name = VALUES(service_name),
                    short_description = VALUES(short_description),
                    updated_at = NOW()'
            );

            foreach ($services as $service) {
                $statement->execute([
                    'business_id' => $businessId,
                    'onboarding_id' => (int) $onboarding['id'],
                    'service_number' => $service['service_number'],
                    'service_name' => $service['service_name'],
                    'short_description' => $service['short_description'],
                ]);
            }

            self::advance($businessId, self::NEXT_STEP['services']);
            self::logActivity($businessId, $userId, '247sp_services_saved', '247SP service pages saved');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function saveWebsiteContent(int $businessId, int $userId, array $input): void
    {
        $businessDescription = trim((string) ($input['business_description'] ?? ''));
        $aboutCompany = trim((string) ($input['about_company'] ?? ''));
        $specialOffer = trim((string) ($input['special_offer'] ?? ''));

        if ($businessDescription === '' || $aboutCompany === '') {
            throw new InvalidArgumentException('Business description and about company are required.');
        }

        $onboarding = self::ensureOnboarding($businessId);
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_business_content` (
                business_id, onboarding_id, business_description, about_company,
                years_in_business, financing_available, special_offer, created_at, updated_at
             ) VALUES (
                :business_id, :onboarding_id, :business_description, :about_company,
                :years_in_business, :financing_available, :special_offer, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                business_description = VALUES(business_description),
                about_company = VALUES(about_company),
                years_in_business = VALUES(years_in_business),
                financing_available = VALUES(financing_available),
                special_offer = VALUES(special_offer),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'onboarding_id' => (int) $onboarding['id'],
            'business_description' => $businessDescription,
            'about_company' => $aboutCompany,
            'years_in_business' => null,
            'financing_available' => isset($input['financing_available']) ? 1 : 0,
            'special_offer' => $specialOffer,
        ]);

        self::advance($businessId, self::NEXT_STEP['website_content']);
        self::logActivity($businessId, $userId, '247sp_website_content_saved', '247SP website content saved');
    }

    public static function saveDomainSelection(int $businessId, int $userId, array $input): void
    {
        $selectionType = (string) ($input['domain_selection_type'] ?? '');

        if (!in_array($selectionType, ['existing', 'purchase'], true)) {
            throw new InvalidArgumentException('Choose whether to bring an existing domain or purchase through 247SP.');
        }

        $field = $selectionType === 'existing' ? 'existing_domain_name' : 'desired_domain_name';
        $domainName = self::normalizeDomain((string) ($input[$field] ?? ''));

        if ($domainName === '') {
            throw new InvalidArgumentException('Domain name is required.');
        }

        $onboarding = self::ensureOnboarding($businessId);
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_domain_selections` (
                business_id, onboarding_id, selection_type, domain_name, status, created_at, updated_at
             ) VALUES (
                :business_id, :onboarding_id, :selection_type, :domain_name, :status, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                selection_type = VALUES(selection_type),
                domain_name = VALUES(domain_name),
                status = VALUES(status),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'onboarding_id' => (int) $onboarding['id'],
            'selection_type' => $selectionType,
            'domain_name' => $domainName,
            'status' => 'pending',
        ]);

        DomainAutomation::ensureRequestForBusiness($businessId, $domainName);
        self::advance($businessId, self::NEXT_STEP['domain_selection']);
        self::logActivity($businessId, $userId, '247sp_domain_selection_saved', '247SP domain selection saved');
    }

    public static function saveEmailSelection(int $businessId, int $userId, array $input): void
    {
        $mailbox = strtolower(trim((string) ($input['primary_mailbox_name'] ?? '')));

        if ($mailbox === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $mailbox)) {
            throw new InvalidArgumentException('Enter a valid primary mailbox name, such as info, support, or office.');
        }

        $onboarding = self::ensureOnboarding($businessId);
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_email_requests` (
                business_id, onboarding_id, primary_mailbox_name, status, created_at, updated_at
             ) VALUES (
                :business_id, :onboarding_id, :primary_mailbox_name, :status, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                primary_mailbox_name = VALUES(primary_mailbox_name),
                status = VALUES(status),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'onboarding_id' => (int) $onboarding['id'],
            'primary_mailbox_name' => $mailbox,
            'status' => 'pending',
        ]);

        EmailProvisioningFoundation::ensureRequestForBusiness($businessId);
        self::advance($businessId, self::NEXT_STEP['email_selection']);
        self::logActivity($businessId, $userId, '247sp_email_selection_saved', '247SP email request saved');
    }

    public static function completeOnboarding(int $businessId, int $userId): void
    {
        $readinessErrors = self::readinessErrors($businessId);

        if (count($readinessErrors) > 0) {
            throw new InvalidArgumentException(implode(' ', $readinessErrors));
        }

        Database::connection()->beginTransaction();

        try {
            $statement = Database::connection()->prepare(
                'UPDATE `247sp_onboarding`
                 SET setup_status = :setup_status,
                     current_step = :current_step,
                     completed_at = NOW(),
                     updated_at = NOW()
                 WHERE business_id = :business_id'
            );
            $statement->execute([
                'setup_status' => 'complete',
                'current_step' => 'complete',
                'business_id' => $businessId,
            ]);

            $website = Database::connection()->prepare(
                'UPDATE `247sp_website_configurations`
                 SET website_status = :website_status,
                     updated_at = NOW()
                 WHERE business_id = :business_id'
            );
            $website->execute([
                'website_status' => 'ready_for_build',
                'business_id' => $businessId,
            ]);

            self::logActivity($businessId, $userId, '247sp_onboarding_completed', '247SP onboarding completed');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function readinessErrors(int $businessId): array
    {
        $bundle = self::bundle($businessId);
        $errors = [];

        if (($bundle['onboarding']['contact_name'] ?? '') === '') {
            $errors[] = 'Business information is incomplete.';
        }

        if ($bundle['configuration'] === null || ($bundle['configuration']['primary_category_id'] ?? null) === null) {
            $errors[] = 'Service area or primary category is incomplete.';
        }

        if (count($bundle['service_pages']) !== 3) {
            $errors[] = 'Three service pages are required.';
        }

        if ($bundle['content'] === null) {
            $errors[] = 'Website content is incomplete.';
        }

        if ($bundle['domain'] === null) {
            $errors[] = 'Domain selection is incomplete.';
        }

        if ($bundle['email'] === null) {
            $errors[] = 'Email selection is incomplete.';
        }

        return $errors;
    }

    private static function onboarding(int $businessId): ?array
    {
        return self::oneByBusiness('247sp_onboarding', $businessId);
    }

    private static function ensureOnboarding(int $businessId): array
    {
        $existing = self::onboarding($businessId);

        if ($existing !== null) {
            return $existing;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_onboarding` (business_id, setup_status, current_step, created_at, updated_at)
             VALUES (:business_id, :setup_status, :current_step, NOW(), NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'setup_status' => 'in_progress',
            'current_step' => 'business_information',
        ]);

        return self::onboarding($businessId) ?? [];
    }

    private static function oneByBusiness(string $table, int $businessId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM ' . self::tableName($table) . ' WHERE business_id = :business_id LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private static function servicePages(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM `247sp_service_pages`
             WHERE business_id = :business_id
             ORDER BY service_number ASC'
        );
        $statement->execute(['business_id' => $businessId]);

        return $statement->fetchAll();
    }

    private static function upsertWebsiteConfiguration(int $businessId, int $onboardingId, array $values): void
    {
        $existing = self::oneByBusiness('247sp_website_configurations', $businessId) ?? [];
        $data = [
            'business_id' => $businessId,
            'onboarding_id' => $onboardingId,
            'primary_category_id' => $values['primary_category_id'] ?? ($existing['primary_category_id'] ?? null),
            'service_area_address' => $values['service_area_address'] ?? ($existing['service_area_address'] ?? null),
            'service_area_city' => $values['service_area_city'] ?? ($existing['service_area_city'] ?? null),
            'service_area_state' => $values['service_area_state'] ?? ($existing['service_area_state'] ?? null),
            'service_area_postal_code' => $values['service_area_postal_code'] ?? ($existing['service_area_postal_code'] ?? null),
            'service_area_business' => $values['service_area_business'] ?? ($existing['service_area_business'] ?? 0),
            'website_status' => $values['website_status'] ?? ($existing['website_status'] ?? 'in_progress'),
        ];

        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_website_configurations` (
                business_id, onboarding_id, primary_category_id, service_area_address,
                service_area_city, service_area_state, service_area_postal_code,
                service_area_business, website_status, created_at, updated_at
             ) VALUES (
                :business_id, :onboarding_id, :primary_category_id, :service_area_address,
                :service_area_city, :service_area_state, :service_area_postal_code,
                :service_area_business, :website_status, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                primary_category_id = VALUES(primary_category_id),
                service_area_address = VALUES(service_area_address),
                service_area_city = VALUES(service_area_city),
                service_area_state = VALUES(service_area_state),
                service_area_postal_code = VALUES(service_area_postal_code),
                service_area_business = VALUES(service_area_business),
                website_status = VALUES(website_status),
                updated_at = NOW()'
        );
        $statement->execute($data);
    }

    private static function advance(int $businessId, string $step): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `247sp_onboarding`
             SET setup_status = IF(setup_status = :complete_status, setup_status, :in_progress_status),
                 current_step = IF(setup_status = :complete_status_check, current_step, :current_step),
                 updated_at = NOW()
             WHERE business_id = :business_id'
        );
        $statement->execute([
            'complete_status' => 'complete',
            'in_progress_status' => 'in_progress',
            'complete_status_check' => 'complete',
            'current_step' => $step,
            'business_id' => $businessId,
        ]);
    }

    private static function categoryExists(int $categoryId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM categories WHERE id = :id AND is_active = 1'
        );
        $statement->execute(['id' => $categoryId]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string) $domain);

        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return '';
        }

        return $domain;
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

    private static function tableName(string $table): string
    {
        $allowed = [
            '247sp_onboarding',
            '247sp_website_configurations',
            '247sp_business_content',
            '247sp_domain_selections',
            '247sp_email_requests',
        ];

        if (!in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported 247SP table.');
        }

        return '`' . $table . '`';
    }

    private static function websiteStatus(?array $onboarding, ?array $configuration): string
    {
        if (($configuration['website_status'] ?? '') === 'published') {
            return 'published';
        }

        if (($onboarding['setup_status'] ?? '') === 'complete') {
            return 'ready_for_build';
        }

        if ($onboarding !== null || $configuration !== null) {
            return 'in_progress';
        }

        return 'not_started';
    }

    private static function logActivity(int $businessId, int $userId, string $activityType, string $subject): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_logs (business_id, user_id, module_key, activity_type, subject, created_at)
             VALUES (:business_id, :user_id, :module_key, :activity_type, :subject, NOW())'
        );
        $statement->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'module_key' => '247sp',
            'activity_type' => $activityType,
            'subject' => $subject,
        ]);
    }
}
