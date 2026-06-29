<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/DomainAutomation.php';
require_once __DIR__ . '/WebsiteManager.php';

final class SiteGenerator
{
    private const TEMPLATE_KEY = 'starter_local_service';

    public static function generateWebsite(int $businessId, int $userId): array
    {
        if (self::websiteForBusiness($businessId) !== null) {
            throw new InvalidArgumentException('Website has already been generated. Use Regenerate Website to replace generated pages.');
        }

        return self::buildWebsite($businessId, $userId, false);
    }

    public static function regenerateWebsite(int $businessId, int $userId): array
    {
        return self::buildWebsite($businessId, $userId, true);
    }

    public static function websiteForBusiness(int $businessId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT gw.*, t.template_key, t.name AS template_name
             FROM `247sp_generated_websites` gw
             INNER JOIN `247sp_templates` t ON t.id = gw.template_id
             WHERE gw.business_id = :business_id
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $website = $statement->fetch();

        return $website ?: null;
    }

    public static function pagesForWebsite(int $websiteId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM `247sp_generated_pages`
             WHERE website_id = :website_id
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute(['website_id' => $websiteId]);

        return $statement->fetchAll();
    }

    public static function pageBySlug(int $websiteId, string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM `247sp_generated_pages`
             WHERE website_id = :website_id AND slug = :slug
             LIMIT 1'
        );
        $statement->execute([
            'website_id' => $websiteId,
            'slug' => $slug,
        ]);
        $page = $statement->fetch();

        return $page ?: null;
    }

    private static function buildWebsite(int $businessId, int $userId, bool $replaceExisting): array
    {
        $source = self::sourceData($businessId);
        $existingWebsite = self::websiteForBusiness($businessId);

        if (!$replaceExisting && $existingWebsite !== null) {
            throw new InvalidArgumentException('Website has already been generated.');
        }

        Database::connection()->beginTransaction();

        try {
            $template = self::starterTemplate();
            self::assignTemplate($businessId, (int) $template['id']);

            if ($existingWebsite === null) {
                $websiteId = self::insertWebsite($businessId, (int) $source['onboarding']['id'], (int) $template['id']);
            } else {
                $websiteId = (int) $existingWebsite['id'];
                self::refreshWebsite($websiteId, (int) $source['onboarding']['id'], (int) $template['id']);
                self::deletePages($websiteId);
            }

            foreach (self::buildPages($source) as $page) {
                self::insertPage($websiteId, $businessId, $page);
            }

            self::markConfigurationGenerated($businessId);
            DomainAutomation::syncWebsiteDomainForBusiness($businessId);
            self::logActivity(
                $businessId,
                $userId,
                $existingWebsite === null ? '247sp_website_generated' : '247sp_website_regenerated',
                $existingWebsite === null ? '247SP website generated' : '247SP website regenerated'
            );

            Database::connection()->commit();

            return self::websiteForBusiness($businessId) ?? [];
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    private static function sourceData(int $businessId): array
    {
        $bundle = TwentyFourSevenSalesPartner::bundle($businessId);
        $onboarding = $bundle['onboarding'];

        if (($onboarding['setup_status'] ?? '') !== 'complete') {
            throw new InvalidArgumentException('Onboarding must be complete before generating a website.');
        }

        $readinessErrors = TwentyFourSevenSalesPartner::readinessErrors($businessId);
        if (count($readinessErrors) > 0) {
            throw new InvalidArgumentException(implode(' ', $readinessErrors));
        }

        $business = self::business($businessId);
        if ($business === null) {
            throw new InvalidArgumentException('Business record could not be found.');
        }

        return [
            'business' => $business,
            'onboarding' => $onboarding,
            'configuration' => $bundle['configuration'],
            'content' => $bundle['content'],
            'service_pages' => $bundle['service_pages'],
            'domain' => $bundle['domain'],
            'email' => $bundle['email'],
            'branding' => WebsiteManager::brandingForBusiness($businessId),
            'content_overrides' => WebsiteManager::contentOverridesForBusiness($businessId),
            'service_images' => WebsiteManager::serviceImagesForBusiness($businessId),
        ];
    }

    private static function buildPages(array $source): array
    {
        $business = $source['business'];
        $configuration = $source['configuration'];
        $content = $source['content'];
        $services = $source['service_pages'];
        $branding = $source['branding'];
        $overrides = $source['content_overrides'];
        $serviceImages = $source['service_images'];

        $businessName = (string) $business['business_name'];
        $serviceArea = self::serviceArea($configuration);
        $yearsInBusiness = self::yearsInBusiness($business['business_started_on'] ?? null, $content['years_in_business'] ?? null);
        $defaultPrimaryCtaLabel = self::override($overrides, 'home', 'call_to_action', 'Call Now');
        $primaryCta = self::cta($overrides, 'primary', 'call_now', $defaultPrimaryCtaLabel);
        $secondaryCta = self::cta($overrides, 'secondary', 'contact_form', 'Contact Us');
        $usedSlugs = ['home', 'about', 'contact'];
        $pages = [
            [
                'page_type' => 'home',
                'title' => 'Home',
                'slug' => 'home',
                'sort_order' => 10,
                'content' => [
                    'headline' => self::override($overrides, 'home', 'headline', $businessName),
                    'subheadline' => self::override($overrides, 'home', 'subheadline', (string) $content['business_description']),
                    'business_description' => self::override($overrides, 'home', 'subheadline', (string) $content['business_description']),
                    'service_area' => $serviceArea,
                    'service_highlights' => array_map(static function (array $service): array {
                        return [
                            'name' => (string) $service['service_name'],
                            'description' => (string) $service['short_description'],
                        ];
                    }, $services),
                    'special_offer' => (string) ($content['special_offer'] ?? ''),
                    'financing_available' => (int) ($content['financing_available'] ?? 0) === 1,
                    'call_to_action' => self::override($overrides, 'home', 'call_to_action', $primaryCta['label']),
                    'primary_cta' => $primaryCta,
                    'secondary_cta' => $secondaryCta,
                    'pricing_list_path' => self::override($overrides, 'home', 'pricing_list_path', ''),
                    'stats' => self::homepageStats($overrides, $yearsInBusiness, count($services), (int) ($content['financing_available'] ?? 0) === 1),
                    'hero_image_path' => self::override($overrides, 'home', 'hero_image_path', (string) ($branding['hero_image_path'] ?? '')),
                ],
            ],
        ];

        $sortOrder = 20;
        foreach ($services as $service) {
            $serviceNumber = (int) $service['service_number'];
            $serviceKey = 'service_' . $serviceNumber;
            $serviceName = self::override($overrides, $serviceKey, 'title', (string) $service['service_name']);
            $serviceDescription = self::override($overrides, $serviceKey, 'description', (string) $service['short_description']);
            $serviceIncludedItems = self::serviceIncludedItems($overrides, $serviceKey, $serviceName);
            $storedServiceSlug = trim((string) ($service['slug'] ?? ''));
            $serviceSlug = self::uniqueSlug($storedServiceSlug !== '' ? $storedServiceSlug : self::slugify($serviceName), $usedSlugs);
            $usedSlugs[] = $serviceSlug;

            $pages[] = [
                'page_type' => 'service',
                'title' => $serviceName,
                'slug' => $serviceSlug,
                'sort_order' => $sortOrder,
                'content' => [
                    'service_page_id' => (int) $service['id'],
                    'parent_service_page_id' => (int) ($service['parent_service_page_id'] ?? 0),
                    'service_number' => $serviceNumber,
                    'sort_order' => (int) ($service['sort_order'] ?? $sortOrder),
                    'service_name' => $serviceName,
                    'service_description' => $serviceDescription,
                    'included_heading' => self::override($overrides, $serviceKey, 'included_heading', $serviceName . ' made straightforward'),
                    'included_description' => self::override($overrides, $serviceKey, 'included_description', (string) $business['business_name'] . ' helps customers understand the issue, choose a practical next step, and get service scheduled without confusion.'),
                    'included_items' => $serviceIncludedItems,
                    'trust_heading' => self::override($overrides, $serviceKey, 'trust_heading', 'Why choose ' . (string) $business['business_name'] . ' for ' . $serviceName),
                    'trust_cards' => self::serviceTrustCards($overrides, $serviceKey, $serviceArea),
                    'call_to_action' => $primaryCta['label'],
                    'hero_image_path' => self::override($overrides, $serviceKey, 'hero_image_path', (string) ($branding['hero_image_path'] ?? '')),
                    'service_image_path' => (string) ($serviceImages[$serviceNumber] ?? ''),
                ],
            ];
            $sortOrder += 10;
        }

        $pages[] = [
            'page_type' => 'about',
            'title' => 'About',
            'slug' => 'about',
            'sort_order' => 50,
            'content' => [
                'about_heading' => self::override($overrides, 'about', 'heading', 'About ' . $businessName),
                'company_description' => self::override($overrides, 'about', 'description', (string) $content['about_company']),
                'years_in_business' => $yearsInBusiness,
                'service_area' => $serviceArea,
                'hero_image_path' => self::override($overrides, 'about', 'hero_image_path', (string) ($branding['about_image_path'] ?? '')),
                'about_image_path' => (string) ($branding['about_image_path'] ?? ''),
            ],
        ];

        $pages[] = [
            'page_type' => 'contact',
            'title' => 'Contact',
            'slug' => 'contact',
            'sort_order' => 60,
            'content' => [
                'contact_heading' => self::override($overrides, 'contact', 'heading', 'Contact ' . $businessName),
                'contact_description' => self::override($overrides, 'contact', 'description', 'Tell us what you need and we will help you take the next step.'),
                'phone' => (string) $business['phone'],
                'email' => (string) $business['email'],
                'service_area' => $serviceArea,
                'hero_image_path' => self::override($overrides, 'contact', 'hero_image_path', ''),
                'contact_form_prompt' => 'Tell us what you need and we will help with the next step.',
            ],
        ];

        return $pages;
    }

    private static function business(int $businessId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM businesses WHERE id = :business_id LIMIT 1');
        $statement->execute(['business_id' => $businessId]);
        $business = $statement->fetch();

        return $business ?: null;
    }

    private static function starterTemplate(): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM `247sp_templates` WHERE template_key = :template_key AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['template_key' => self::TEMPLATE_KEY]);
        $template = $statement->fetch();

        if (!$template) {
            throw new RuntimeException('Starter Local Service template is missing.');
        }

        return $template;
    }

    private static function assignTemplate(int $businessId, int $templateId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_template_assignments` (
                business_id, template_id, status, assigned_at, created_at, updated_at
             ) VALUES (
                :business_id, :template_id, :status, NOW(), NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                template_id = VALUES(template_id),
                status = VALUES(status),
                assigned_at = NOW(),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'template_id' => $templateId,
            'status' => 'active',
        ]);
    }

    private static function insertWebsite(int $businessId, int $onboardingId, int $templateId): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_generated_websites` (
                business_id, onboarding_id, template_id, status, generated_at, created_at, updated_at
             ) VALUES (
                :business_id, :onboarding_id, :template_id, :status, NOW(), NOW(), NOW()
             )'
        );
        $statement->execute([
            'business_id' => $businessId,
            'onboarding_id' => $onboardingId,
            'template_id' => $templateId,
            'status' => 'generated',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private static function refreshWebsite(int $websiteId, int $onboardingId, int $templateId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `247sp_generated_websites`
             SET onboarding_id = :onboarding_id,
                 template_id = :template_id,
                 status = :status,
                 generated_at = NOW(),
                 updated_at = NOW()
             WHERE id = :website_id'
        );
        $statement->execute([
            'onboarding_id' => $onboardingId,
            'template_id' => $templateId,
            'status' => 'generated',
            'website_id' => $websiteId,
        ]);
    }

    private static function deletePages(int $websiteId): void
    {
        $statement = Database::connection()->prepare('DELETE FROM `247sp_generated_pages` WHERE website_id = :website_id');
        $statement->execute(['website_id' => $websiteId]);
    }

    private static function insertPage(int $websiteId, int $businessId, array $page): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_generated_pages` (
                website_id, business_id, page_type, title, slug, content_json, status, sort_order, created_at, updated_at
             ) VALUES (
                :website_id, :business_id, :page_type, :title, :slug, :content_json, :status, :sort_order, NOW(), NOW()
             )'
        );
        $statement->execute([
            'website_id' => $websiteId,
            'business_id' => $businessId,
            'page_type' => $page['page_type'],
            'title' => $page['title'],
            'slug' => $page['slug'],
            'content_json' => json_encode($page['content'], JSON_THROW_ON_ERROR),
            'status' => 'generated',
            'sort_order' => $page['sort_order'],
        ]);
    }

    private static function markConfigurationGenerated(int $businessId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `247sp_website_configurations`
             SET website_status = :website_status,
                 updated_at = NOW()
             WHERE business_id = :business_id'
        );
        $statement->execute([
            'website_status' => 'generated',
            'business_id' => $businessId,
        ]);
    }

    private static function serviceArea(?array $configuration): string
    {
        if ($configuration === null) {
            return '';
        }

        return trim(implode(', ', array_filter([
            $configuration['service_area_city'] ?? '',
            $configuration['service_area_state'] ?? '',
            $configuration['service_area_postal_code'] ?? '',
        ])));
    }

    private static function override(array $overrides, string $pageKey, string $fieldKey, string $fallback): string
    {
        $value = trim((string) ($overrides[$pageKey][$fieldKey] ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    private static function cta(array $overrides, string $slot, string $defaultType, string $defaultLabel): array
    {
        $type = self::normalizeCtaType(self::override($overrides, 'home', $slot . '_cta_type', $defaultType), $defaultType);

        $label = self::override($overrides, 'home', $slot . '_cta_label', $defaultLabel);
        if (strcasecmp($label, 'View Pricing') === 0) {
            $type = 'view_pricing';
        }

        return [
            'type' => $type,
            'label' => $label,
        ];
    }

    private static function normalizeCtaType(string $type, string $defaultType): string
    {
        if (in_array($type, ['call_now', 'contact_form', 'view_pricing'], true)) {
            return $type;
        }

        if (in_array($type, ['schedule_service', 'request_service', 'instant_quote'], true)) {
            return 'contact_form';
        }

        return in_array($defaultType, ['call_now', 'contact_form', 'view_pricing'], true) ? $defaultType : 'contact_form';
    }

    private static function homepageStats(array $overrides, ?int $yearsInBusiness, int $serviceCount, bool $financingAvailable): array
    {
        $stats = [
            [
                'value' => $yearsInBusiness !== null && $yearsInBusiness > 0 ? (string) $yearsInBusiness : 'Local',
                'label' => $yearsInBusiness !== null && $yearsInBusiness > 0 ? 'Years in business' : 'Service',
            ],
            [
                'value' => (string) $serviceCount,
                'label' => 'Core services available',
            ],
            [
                'value' => $financingAvailable ? 'Yes' : 'Clear',
                'label' => $financingAvailable ? 'Financing available' : 'Communication from request to service',
            ],
        ];

        foreach ($stats as $index => &$stat) {
            $statNumber = $index + 1;
            $stat['value'] = self::override($overrides, 'home', 'stat_' . $statNumber . '_value', $stat['value']);
            $stat['label'] = self::override($overrides, 'home', 'stat_' . $statNumber . '_label', $stat['label']);
        }
        unset($stat);

        return $stats;
    }

    private static function yearsInBusiness($businessStartedOn, $legacyYears): ?int
    {
        $startedOn = trim((string) $businessStartedOn);
        if ($startedOn !== '') {
            $started = DateTimeImmutable::createFromFormat('!Y-m-d', $startedOn);
            $today = new DateTimeImmutable('today');
            if ($started !== false && $started <= $today) {
                return max(0, (int) $started->diff($today)->y);
            }
        }

        $legacy = (int) $legacyYears;
        return $legacy > 0 ? $legacy : null;
    }

    private static function serviceIncludedItems(array $overrides, string $serviceKey, string $serviceName): array
    {
        $serviceLabel = strtolower($serviceName !== '' ? $serviceName : 'service');

        return [
            self::override($overrides, $serviceKey, 'included_item_1', 'You need a clear assessment before a small ' . $serviceLabel . ' issue becomes a bigger problem.'),
            self::override($overrides, $serviceKey, 'included_item_2', 'You want reliable help from a local business that explains the next step clearly.'),
            self::override($overrides, $serviceKey, 'included_item_3', 'You are ready to schedule ' . $serviceLabel . ' and want the job handled professionally.'),
        ];
    }

    private static function serviceTrustCards(array $overrides, string $serviceKey, string $serviceArea): array
    {
        return [
            [
                'title' => self::override($overrides, $serviceKey, 'trust_1_title', 'Local'),
                'text' => self::override($overrides, $serviceKey, 'trust_1_text', $serviceArea !== '' ? 'Serving ' . $serviceArea : 'Service near you'),
            ],
            [
                'title' => self::override($overrides, $serviceKey, 'trust_2_title', 'Clear'),
                'text' => self::override($overrides, $serviceKey, 'trust_2_text', 'Simple communication before work begins'),
            ],
            [
                'title' => self::override($overrides, $serviceKey, 'trust_3_title', 'Ready'),
                'text' => self::override($overrides, $serviceKey, 'trust_3_text', 'Call or request service when you need help'),
            ],
        ];
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : 'service';
    }

    private static function uniqueSlug(string $slug, array $usedSlugs): string
    {
        $candidate = $slug;
        $suffix = 2;

        while (in_array($candidate, $usedSlugs, true)) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
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
