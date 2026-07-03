<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TwentyFourSevenSalesPartner.php';

final class WebsiteManager
{
    public const DEFAULT_PRIMARY_COLOR = '#3144D3';

    private const CTA_TYPES = ['call_now', 'contact_form', 'view_pricing'];
    private const MAX_UPLOAD_BYTES = 5242880;
    private const BRANDING_UPLOADS = [
        'logo' => [
            'column' => 'logo_path',
            'directory' => 'logos',
            'extensions' => ['png', 'jpg', 'jpeg', 'svg'],
            'mimes' => ['image/png', 'image/jpeg', 'image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
        ],
        'hero_image' => [
            'column' => 'hero_image_path',
            'directory' => 'hero-images',
            'extensions' => ['png', 'jpg', 'jpeg'],
            'mimes' => ['image/png', 'image/jpeg'],
        ],
        'about_image' => [
            'column' => 'about_image_path',
            'directory' => 'about-images',
            'extensions' => ['png', 'jpg', 'jpeg'],
            'mimes' => ['image/png', 'image/jpeg'],
        ],
    ];

    public static function brandingForBusiness(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM `247sp_website_branding`
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $branding = $statement->fetch();

        if (!$branding) {
            return [
                'business_id' => $businessId,
                'logo_path' => null,
                'primary_color' => self::DEFAULT_PRIMARY_COLOR,
                'secondary_color' => null,
                'hero_image_path' => null,
                'about_image_path' => null,
            ];
        }

        $branding['primary_color'] = self::normalizeHexColor((string) ($branding['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR)) ?: self::DEFAULT_PRIMARY_COLOR;
        $branding['secondary_color'] = self::normalizeHexColor((string) ($branding['secondary_color'] ?? ''));

        return $branding;
    }

    public static function integrationsForBusiness(int $businessId): array
    {
        $defaults = [
            'business_id' => $businessId,
            'ga_measurement_id' => null,
            'google_search_console_property' => null,
            'google_tag_manager_id' => null,
            'microsoft_clarity_id' => null,
            'meta_pixel_id' => null,
            'google_business_profile_url' => null,
        ];

        $statement = Database::connection()->prepare(
            'SELECT *
             FROM `247sp_website_integrations`
             WHERE business_id = :business_id
             LIMIT 1'
        );
        $statement->execute(['business_id' => $businessId]);
        $integrations = $statement->fetch();

        if (!$integrations) {
            return $defaults;
        }

        return array_merge($defaults, [
            'ga_measurement_id' => self::normalizeGaMeasurementId((string) ($integrations['ga_measurement_id'] ?? '')),
            'google_search_console_property' => self::optionalNullableText($integrations['google_search_console_property'] ?? null),
            'google_tag_manager_id' => self::optionalNullableText($integrations['google_tag_manager_id'] ?? null),
            'microsoft_clarity_id' => self::optionalNullableText($integrations['microsoft_clarity_id'] ?? null),
            'meta_pixel_id' => self::optionalNullableText($integrations['meta_pixel_id'] ?? null),
            'google_business_profile_url' => self::optionalNullableText($integrations['google_business_profile_url'] ?? null),
        ]);
    }

    public static function serviceImagesForBusiness(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT service_number, image_path
             FROM `247sp_website_service_images`
             WHERE business_id = :business_id
             ORDER BY service_number ASC'
        );
        $statement->execute(['business_id' => $businessId]);

        $images = [];
        foreach ($statement->fetchAll() as $row) {
            $images[(int) $row['service_number']] = (string) $row['image_path'];
        }

        return $images;
    }

    public static function contentOverridesForBusiness(int $businessId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT page_key, field_key, field_value
             FROM `247sp_website_content_overrides`
             WHERE business_id = :business_id
             ORDER BY page_key ASC, field_key ASC'
        );
        $statement->execute(['business_id' => $businessId]);

        $overrides = [];
        foreach ($statement->fetchAll() as $row) {
            $pageKey = (string) $row['page_key'];
            $fieldKey = (string) $row['field_key'];
            $overrides[$pageKey][$fieldKey] = (string) $row['field_value'];
        }

        return $overrides;
    }

    public static function saveWebsiteManager(int $businessId, int $userId, array $input, array $files): void
    {
        $primaryColor = self::normalizeHexColor((string) ($input['primary_color'] ?? ''));
        $secondaryColor = self::normalizeHexColor((string) ($input['secondary_color'] ?? ''));

        if ($primaryColor === null) {
            throw new InvalidArgumentException('Primary brand color must be a valid hex color.');
        }

        if (trim((string) ($input['secondary_color'] ?? '')) !== '' && $secondaryColor === null) {
            throw new InvalidArgumentException('Secondary brand color must be a valid hex color.');
        }

        $existingBranding = self::brandingForBusiness($businessId);
        $integrations = self::integrationsFromInput($businessId, $input);
        $branding = [
            'logo_path' => $existingBranding['logo_path'] ?? null,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'hero_image_path' => $existingBranding['hero_image_path'] ?? null,
            'about_image_path' => $existingBranding['about_image_path'] ?? null,
        ];

        foreach (self::BRANDING_UPLOADS as $fieldName => $rules) {
            $uploadedPath = self::storeUploadedFile($businessId, $fieldName, $files, $rules);
            if ($uploadedPath !== null) {
                $branding[(string) $rules['column']] = $uploadedPath;
            }
        }

        $existingOverrides = self::contentOverridesForBusiness($businessId);
        $contentFields = self::contentFieldsFromInput($input, $existingOverrides);
        $serviceImages = self::serviceImagesForBusiness($businessId);

        foreach (self::serviceNumbersFromInput($input, $files) as $serviceNumber) {
            $fieldName = 'service_image_' . $serviceNumber;
            $uploadedPath = self::storeUploadedFile($businessId, $fieldName, $files, [
                'directory' => 'service-images',
                'extensions' => ['png', 'jpg', 'jpeg'],
                'mimes' => ['image/png', 'image/jpeg'],
            ]);

            if ($uploadedPath !== null) {
                $serviceImages[$serviceNumber] = $uploadedPath;
            }

            $serviceHeroPath = self::storeUploadedFile($businessId, 'service_' . $serviceNumber . '_hero_image', $files, [
                'directory' => 'page-hero-images',
                'extensions' => ['png', 'jpg', 'jpeg'],
                'mimes' => ['image/png', 'image/jpeg'],
            ]);

            if ($serviceHeroPath !== null) {
                $contentFields['service_' . $serviceNumber]['hero_image_path'] = $serviceHeroPath;
            }
        }

        foreach (['about' => 'about_hero_image', 'contact' => 'contact_hero_image'] as $pageKey => $fieldName) {
            $pageHeroPath = self::storeUploadedFile($businessId, $fieldName, $files, [
                'directory' => 'page-hero-images',
                'extensions' => ['png', 'jpg', 'jpeg'],
                'mimes' => ['image/png', 'image/jpeg'],
            ]);

            if ($pageHeroPath !== null) {
                $contentFields[$pageKey]['hero_image_path'] = $pageHeroPath;
            }
        }

        $pricingListPath = self::storeUploadedFile($businessId, 'pricing_list', $files, [
            'directory' => 'pricing-lists',
            'extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'webp'],
            'mimes' => ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'],
        ]);

        if ($pricingListPath !== null) {
            $contentFields['home']['pricing_list_path'] = $pricingListPath;
        }

        Database::connection()->beginTransaction();

        try {
            self::upsertBranding($businessId, $branding);
            self::upsertIntegrations($businessId, $integrations);
            self::replaceContentOverrides($businessId, $contentFields);
            self::upsertServiceImages($businessId, $serviceImages);
            self::logActivity($businessId, $userId, '247sp_website_manager_saved', '247SP website manager settings saved');
            Database::connection()->commit();
        } catch (Throwable $exception) {
            Database::connection()->rollBack();
            throw $exception;
        }
    }

    public static function saveAndRegenerate(int $businessId, int $userId, array $input, array $files): array
    {
        self::saveWebsiteManager($businessId, $userId, $input, $files);

        require_once __DIR__ . '/SiteGenerator.php';

        return SiteGenerator::websiteForBusiness($businessId) === null
            ? SiteGenerator::generateWebsite($businessId, $userId)
            : SiteGenerator::regenerateWebsite($businessId, $userId);
    }

    private static function contentFieldsFromInput(array $input, array $existingOverrides): array
    {
        $fields = [
            'home' => [
                'headline' => self::requiredText($input, 'home_headline', 'Homepage headline is required.'),
                'subheadline' => self::requiredText($input, 'home_subheadline', 'Homepage subheadline is required.'),
                'call_to_action' => self::requiredText($input, 'home_call_to_action', 'Homepage call to action is required.'),
            ],
            'about' => [
                'heading' => self::requiredText($input, 'about_heading', 'About heading is required.'),
                'description' => self::requiredText($input, 'about_description', 'About description is required.'),
            ],
            'contact' => [
                'heading' => self::requiredText($input, 'contact_heading', 'Contact heading is required.'),
                'description' => self::requiredText($input, 'contact_description', 'Contact description is required.'),
            ],
        ];

        $fields['home']['primary_cta_label'] = self::optionalText($input['primary_cta_label'] ?? $input['home_call_to_action'] ?? $existingOverrides['home']['primary_cta_label'] ?? '');
        self::carryOptionalCtaType($fields, $input, $existingOverrides, 'home', 'primary_cta_type', 'primary_cta_type');
        self::carryOptionalOverride($fields, $input, $existingOverrides, 'home', 'secondary_cta_label', 'secondary_cta_label');
        self::carryOptionalCtaType($fields, $input, $existingOverrides, 'home', 'secondary_cta_type', 'secondary_cta_type');
        self::carryOptionalOverride($fields, $input, $existingOverrides, 'home', 'pricing_list_path', 'pricing_list_path');
        for ($statNumber = 1; $statNumber <= 3; $statNumber++) {
            self::carryOptionalOverride($fields, $input, $existingOverrides, 'home', 'stat_' . $statNumber . '_value', 'stat_' . $statNumber . '_value');
            self::carryOptionalOverride($fields, $input, $existingOverrides, 'home', 'stat_' . $statNumber . '_label', 'stat_' . $statNumber . '_label');
        }

        self::carryOptionalOverride($fields, $input, $existingOverrides, 'about', 'hero_image_path', 'about_hero_image_path');
        self::carryOptionalOverride($fields, $input, $existingOverrides, 'contact', 'hero_image_path', 'contact_hero_image_path');

        foreach (self::serviceNumbersFromInput($input, []) as $serviceNumber) {
            $serviceKey = 'service_' . $serviceNumber;
            $fields[$serviceKey] = [
                'title' => self::requiredText($input, 'service_' . $serviceNumber . '_title', 'Service ' . $serviceNumber . ' title is required.'),
                'description' => self::requiredText($input, 'service_' . $serviceNumber . '_description', 'Service ' . $serviceNumber . ' description is required.'),
            ];
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'included_heading', $serviceKey . '_included_heading');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'included_description', $serviceKey . '_included_description');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'included_item_1', $serviceKey . '_included_item_1');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'included_item_2', $serviceKey . '_included_item_2');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'included_item_3', $serviceKey . '_included_item_3');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_heading', $serviceKey . '_trust_heading');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_1_title', $serviceKey . '_trust_1_title');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_1_text', $serviceKey . '_trust_1_text');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_2_title', $serviceKey . '_trust_2_title');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_2_text', $serviceKey . '_trust_2_text');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_3_title', $serviceKey . '_trust_3_title');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'trust_3_text', $serviceKey . '_trust_3_text');
            self::carryOptionalOverride($fields, $input, $existingOverrides, $serviceKey, 'hero_image_path', $serviceKey . '_hero_image_path');
        }

        return $fields;
    }

    private static function serviceNumbersFromInput(array $input, array $files): array
    {
        $serviceNumbers = [];

        foreach (array_keys($input) as $key) {
            if (preg_match('/^service_(\d+)_title$/', (string) $key, $matches)) {
                $serviceNumbers[] = (int) $matches[1];
            }
        }

        foreach (array_keys($files) as $key) {
            if (preg_match('/^service_image_(\d+)$/', (string) $key, $matches)) {
                $serviceNumbers[] = (int) $matches[1];
            }

            if (preg_match('/^service_(\d+)_hero_image$/', (string) $key, $matches)) {
                $serviceNumbers[] = (int) $matches[1];
            }
        }

        $serviceNumbers = array_values(array_unique(array_filter($serviceNumbers, static function (int $serviceNumber): bool {
            return $serviceNumber > 0;
        })));
        sort($serviceNumbers);

        return $serviceNumbers;
    }

    private static function carryOptionalOverride(array &$fields, array $input, array $existingOverrides, string $pageKey, string $fieldKey, string $inputKey): void
    {
        if (array_key_exists($inputKey, $input)) {
            $value = trim((string) $input[$inputKey]);
        } else {
            $value = trim((string) ($existingOverrides[$pageKey][$fieldKey] ?? ''));
        }

        if ($value !== '') {
            $fields[$pageKey][$fieldKey] = $value;
        }
    }

    private static function carryOptionalCtaType(array &$fields, array $input, array $existingOverrides, string $pageKey, string $fieldKey, string $inputKey): void
    {
        if (array_key_exists($inputKey, $input)) {
            $value = trim((string) $input[$inputKey]);
        } else {
            $value = trim((string) ($existingOverrides[$pageKey][$fieldKey] ?? ''));
        }

        if ($value !== '' && in_array($value, self::CTA_TYPES, true)) {
            $fields[$pageKey][$fieldKey] = $value;
        }
    }

    private static function requiredText(array $input, string $field, string $message): string
    {
        $value = trim((string) ($input[$field] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function optionalText($value): string
    {
        return trim((string) $value);
    }

    private static function optionalNullableText($value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private static function normalizeGaMeasurementId(string $value): ?string
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '') {
            return null;
        }

        return preg_match('/^G-[A-Z0-9]{6,20}$/', $normalized) === 1 ? $normalized : null;
    }

    private static function integrationsFromInput(int $businessId, array $input): array
    {
        $integrations = self::integrationsForBusiness($businessId);

        if (array_key_exists('ga_measurement_id', $input)) {
            $integrations['ga_measurement_id'] = self::normalizeGaMeasurementId((string) $input['ga_measurement_id']);

            if (trim((string) $input['ga_measurement_id']) !== '' && $integrations['ga_measurement_id'] === null) {
                throw new InvalidArgumentException('Google Analytics Measurement ID must use a format like G-XXXXXXXXXX.');
            }
        }

        foreach ([
            'google_search_console_property',
            'google_tag_manager_id',
            'microsoft_clarity_id',
            'meta_pixel_id',
            'google_business_profile_url',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                $integrations[$field] = self::optionalNullableText($input[$field]);
            }
        }

        return $integrations;
    }

    private static function upsertBranding(int $businessId, array $branding): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_website_branding` (
                business_id, logo_path, primary_color, secondary_color, hero_image_path, about_image_path, created_at, updated_at
             ) VALUES (
                :business_id, :logo_path, :primary_color, :secondary_color, :hero_image_path, :about_image_path, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                logo_path = VALUES(logo_path),
                primary_color = VALUES(primary_color),
                secondary_color = VALUES(secondary_color),
                hero_image_path = VALUES(hero_image_path),
                about_image_path = VALUES(about_image_path),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'logo_path' => $branding['logo_path'],
            'primary_color' => $branding['primary_color'],
            'secondary_color' => $branding['secondary_color'],
            'hero_image_path' => $branding['hero_image_path'],
            'about_image_path' => $branding['about_image_path'],
        ]);
    }

    private static function upsertIntegrations(int $businessId, array $integrations): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_website_integrations` (
                business_id, ga_measurement_id, google_search_console_property, google_tag_manager_id,
                microsoft_clarity_id, meta_pixel_id, google_business_profile_url, created_at, updated_at
             ) VALUES (
                :business_id, :ga_measurement_id, :google_search_console_property, :google_tag_manager_id,
                :microsoft_clarity_id, :meta_pixel_id, :google_business_profile_url, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                ga_measurement_id = VALUES(ga_measurement_id),
                google_search_console_property = VALUES(google_search_console_property),
                google_tag_manager_id = VALUES(google_tag_manager_id),
                microsoft_clarity_id = VALUES(microsoft_clarity_id),
                meta_pixel_id = VALUES(meta_pixel_id),
                google_business_profile_url = VALUES(google_business_profile_url),
                updated_at = NOW()'
        );
        $statement->execute([
            'business_id' => $businessId,
            'ga_measurement_id' => $integrations['ga_measurement_id'],
            'google_search_console_property' => $integrations['google_search_console_property'],
            'google_tag_manager_id' => $integrations['google_tag_manager_id'],
            'microsoft_clarity_id' => $integrations['microsoft_clarity_id'],
            'meta_pixel_id' => $integrations['meta_pixel_id'],
            'google_business_profile_url' => $integrations['google_business_profile_url'],
        ]);
    }

    private static function replaceContentOverrides(int $businessId, array $fields): void
    {
        $delete = Database::connection()->prepare(
            'DELETE FROM `247sp_website_content_overrides`
             WHERE business_id = :business_id'
        );
        $delete->execute(['business_id' => $businessId]);

        $insert = Database::connection()->prepare(
            'INSERT INTO `247sp_website_content_overrides` (
                business_id, page_key, field_key, field_value, created_at, updated_at
             ) VALUES (
                :business_id, :page_key, :field_key, :field_value, NOW(), NOW()
             )'
        );

        foreach ($fields as $pageKey => $pageFields) {
            foreach ($pageFields as $fieldKey => $fieldValue) {
                $insert->execute([
                    'business_id' => $businessId,
                    'page_key' => $pageKey,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue,
                ]);
            }
        }
    }

    private static function upsertServiceImages(int $businessId, array $serviceImages): void
    {
        if (count($serviceImages) === 0) {
            return;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO `247sp_website_service_images` (
                business_id, service_number, image_path, created_at, updated_at
             ) VALUES (
                :business_id, :service_number, :image_path, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                image_path = VALUES(image_path),
                updated_at = NOW()'
        );

        foreach ($serviceImages as $serviceNumber => $imagePath) {
            if ((int) $serviceNumber < 1 || trim((string) $imagePath) === '') {
                continue;
            }

            $statement->execute([
                'business_id' => $businessId,
                'service_number' => (int) $serviceNumber,
                'image_path' => (string) $imagePath,
            ]);
        }
    }

    private static function storeUploadedFile(int $businessId, string $fieldName, array $files, array $rules): ?string
    {
        if (!isset($files[$fieldName]) || !is_array($files[$fieldName])) {
            return null;
        }

        $file = $files[$fieldName];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('One of the uploaded files could not be saved.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Uploads must be 5 MB or smaller.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $rules['extensions'], true)) {
            throw new InvalidArgumentException('Unsupported upload type.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Uploaded file could not be validated.');
        }

        $mime = self::detectMimeType($tmpName);
        if ($mime !== '' && !in_array($mime, $rules['mimes'], true)) {
            throw new InvalidArgumentException('Uploaded file type does not match the selected file.');
        }

        if ($extension === 'svg') {
            $sample = file_get_contents($tmpName, false, null, 0, 2048);
            if (!is_string($sample) || stripos($sample, '<svg') === false) {
                throw new InvalidArgumentException('SVG logo uploads must contain valid SVG markup.');
            }
        }

        $directory = trim((string) $rules['directory'], '/');
        $publicDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($publicDirectory) && !mkdir($publicDirectory, 0755, true) && !is_dir($publicDirectory)) {
            throw new RuntimeException('Upload directory could not be created.');
        }

        $filename = $businessId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $targetPath = $publicDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Uploaded file could not be moved.');
        }

        return '/uploads/' . $directory . '/' . $filename;
    }

    private static function detectMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                return is_string($mime) ? $mime : '';
            }
        }

        return '';
    }

    private static function normalizeHexColor(string $color): ?string
    {
        $color = trim($color);

        if ($color === '') {
            return null;
        }

        if ($color[0] !== '#') {
            $color = '#' . $color;
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches) === 1) {
            $short = $matches[1];
            $color = '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2];
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            return null;
        }

        return strtoupper($color);
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
