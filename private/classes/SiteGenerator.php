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
        $cta = 'Call ' . $businessName . ' today to request service.';
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
                    'call_to_action' => self::override($overrides, 'home', 'call_to_action', $cta),
                    'hero_image_path' => (string) ($branding['hero_image_path'] ?? ''),
                ],
            ],
        ];

        $sortOrder = 20;
        foreach ($services as $service) {
            $serviceNumber = (int) $service['service_number'];
            $serviceKey = 'service_' . $serviceNumber;
            $serviceName = self::override($overrides, $serviceKey, 'title', (string) $service['service_name']);
            $serviceDescription = self::override($overrides, $serviceKey, 'description', (string) $service['short_description']);
            $serviceSlug = self::uniqueSlug(self::slugify($serviceName), $usedSlugs);
            $usedSlugs[] = $serviceSlug;

            $pages[] = [
                'page_type' => 'service',
                'title' => $serviceName,
                'slug' => $serviceSlug,
                'sort_order' => $sortOrder,
                'content' => [
                    'service_number' => $serviceNumber,
                    'service_name' => $serviceName,
                    'service_description' => $serviceDescription,
                    'call_to_action' => $cta,
                    'hero_image_path' => (string) ($branding['hero_image_path'] ?? ''),
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
                'years_in_business' => (int) $content['years_in_business'],
                'service_area' => $serviceArea,
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
                'contact_form_placeholder' => 'Contact form placeholder. Form sending and lead processing are not active in Sprint 4.',
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
