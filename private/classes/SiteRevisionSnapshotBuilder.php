<?php

declare(strict_types=1);

require_once __DIR__ . '/CanonicalJson.php';
require_once __DIR__ . '/SharedBusinessProfile.php';
require_once __DIR__ . '/SiteAuthorizationPolicy.php';

final class SiteRevisionSnapshotBuilder
{
    public const SNAPSHOT_SCHEMA_VERSION = 1;

    public static function buildForSite(int $actingUserId, int $siteId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');

        $site = SiteServiceSupport::read(static function (object $connection) use ($siteId): array {
            $statement = $connection->prepare(
                'SELECT s.id, s.site_key, s.purpose, s.lifecycle_status,
                        sba.business_id, b.status AS business_status, b.is_suspended,
                        EXISTS (
                            SELECT 1 FROM business_modules bm
                            INNER JOIN modules m ON m.id = bm.module_id
                            WHERE bm.business_id = sba.business_id
                              AND bm.status = :module_status
                              AND m.module_key = :module_key
                              AND m.is_active = 1
                        ) AS has_247sp_module
                 FROM sites s
                 LEFT JOIN site_business_associations sba
                   ON sba.site_id = s.id
                  AND sba.association_role = :association_role
                  AND sba.status = :association_status
                 LEFT JOIN businesses b ON b.id = sba.business_id
                 WHERE s.id = :site_id LIMIT 1'
            );
            $statement->execute([
                'module_status' => 'active',
                'module_key' => '247sp',
                'association_role' => 'customer',
                'association_status' => 'active',
                'site_id' => $siteId,
            ]);
            $row = $statement->fetch();
            if (!$row) {
                throw new SiteServiceException('not_found', 'The site was not found.');
            }
            SiteServiceSupport::assertSiteOperational($row);
            return $row;
        });

        $purpose = (string) $site['purpose'];
        if ($purpose !== '247sp') {
            return self::minimalPurposeSnapshot($site);
        }

        $businessId = (int) ($site['business_id'] ?? 0);
        if ($businessId < 1
            || (string) ($site['business_status'] ?? '') !== 'active'
            || (int) ($site['is_suspended'] ?? 1) === 1
            || (int) ($site['has_247sp_module'] ?? 0) !== 1) {
            throw new SiteServiceException('conflict', 'The active 247SP business association is not eligible for a revision snapshot.');
        }

        try {
            $profile = SharedBusinessProfile::getProfileForBusiness($businessId, $actingUserId);
        } catch (SharedBusinessProfileException $exception) {
            throw new SiteServiceException('conflict', 'An available Shared Business Profile is required for a 247SP revision snapshot.');
        }

        $shared = $profile['shared_business_facts'];
        $publicAddress = (bool) ($shared['address']['is_public_physical_location'] ?? false);
        $facts = [
            'purpose' => '247sp',
            'business' => [
                'display_name' => (string) (($shared['public_display_name'] ?? '') ?: ($shared['business_name'] ?? '')),
                'phone' => $shared['phone'] ?? null,
                'email' => $shared['email'] ?? null,
                'website_url' => $shared['website_url'] ?? null,
                'public_address' => [
                    'line_1' => $publicAddress ? ($shared['address']['line_1'] ?? null) : null,
                    'line_2' => $publicAddress ? ($shared['address']['line_2'] ?? null) : null,
                    'city' => $publicAddress ? ($shared['address']['city'] ?? null) : null,
                    'state' => $publicAddress ? ($shared['address']['state'] ?? null) : null,
                    'postal_code' => $publicAddress ? ($shared['address']['postal_code'] ?? null) : null,
                    'country' => $publicAddress ? ($shared['address']['country'] ?? null) : null,
                ],
            ],
            'profile' => [
                'timezone' => $shared['timezone'] ?? null,
                'default_language' => $shared['default_language'] ?? 'en',
                'short_description' => $shared['short_description'] ?? null,
                'long_description' => $shared['long_description'] ?? null,
                'primary_greeting' => $shared['primary_greeting'] ?? null,
                'value_proposition' => $shared['value_proposition'] ?? null,
                'tone' => $shared['tone'] ?? null,
                'personality' => $shared['personality'] ?? null,
                'prohibited_claims' => $shared['prohibited_claims'] ?? null,
                'appointment_requests_enabled' => (bool) ($shared['appointment_requests_enabled'] ?? false),
                'emergency_service_enabled' => (bool) ($shared['emergency_service_enabled'] ?? false),
            ],
            'services' => self::presentationServices($profile['services']),
            'service_area' => self::presentationServiceArea($profile['service_area']),
            'hours' => self::withoutKeys($profile['hours'], ['id', 'updated_at']),
            'hour_exceptions' => self::withoutKeys($profile['exceptions'], ['id', 'updated_at']),
            'faqs' => self::presentationFaqs($profile['faqs']),
            'pricing_guidance' => self::activeRows($profile['pricing_guidance'], ['id', 'updated_at', 'is_active']),
        ];

        $references = [
            'authority' => 'server_resolved_authoritative_business_facts',
            'business' => [
                'table' => 'businesses',
                'business_id' => $businessId,
            ],
            'shared_business_profile' => [
                'table' => 'business_profiles',
                'business_profile_id' => (int) $shared['business_profile_id'],
                'updated_at' => $profile['lifecycle']['updated_at'] ?? null,
            ],
            'selected_services' => [
                'table' => 'business_sub_services',
                'sub_service_ids' => array_values(array_map(
                    'intval',
                    array_column($profile['services']['selected_sub_services'], 'sub_service_id')
                )),
            ],
            'custom_services' => [
                'table' => 'business_custom_services',
                'business_custom_service_ids' => array_values(array_map(
                    'intval',
                    array_column($profile['services']['custom_services'], 'business_custom_service_id')
                )),
            ],
            'service_area' => [
                'table' => '247sp_website_configurations',
                'updated_at' => $profile['service_area']['updated_at'] ?? null,
            ],
            'hours' => [
                'table' => 'business_profile_hours',
                'row_ids' => array_values(array_map('intval', array_column($profile['hours'], 'id'))),
            ],
            'hour_exceptions' => [
                'table' => 'business_profile_hour_exceptions',
                'row_ids' => array_values(array_map('intval', array_column($profile['exceptions'], 'id'))),
            ],
            'faqs' => [
                'table' => 'business_profile_faqs',
                'row_ids' => array_values(array_map('intval', array_column($profile['faqs'], 'id'))),
            ],
            'pricing_guidance' => [
                'table' => 'business_profile_pricing_guidance',
                'row_ids' => array_values(array_map('intval', array_column($profile['pricing_guidance'], 'id'))),
            ],
        ];

        return [
            'snapshot_schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'site_id' => (int) $site['id'],
            'site_key' => (string) $site['site_key'],
            'purpose' => $purpose,
            'business_id' => $businessId,
            'facts_snapshot' => CanonicalJson::canonicalize($facts),
            'source_references' => CanonicalJson::canonicalize($references),
        ];
    }

    public static function seedHash(array $snapshot, array $brief, ?array $basedOnRevision): string
    {
        return CanonicalJson::hash([
            'snapshot_schema_version' => (int) $snapshot['snapshot_schema_version'],
            'site' => ['site_key' => (string) $snapshot['site_key'], 'purpose' => (string) $snapshot['purpose']],
            'facts_snapshot' => $snapshot['facts_snapshot'],
            'source_references' => $snapshot['source_references'],
            'generation_brief' => [
                'brief_version' => (int) $brief['brief_version'],
                'content_hash' => (string) $brief['content_hash'],
                'source_type' => (string) $brief['source_type'],
            ],
            'based_on_revision' => $basedOnRevision === null ? null : [
                'revision_number' => (int) $basedOnRevision['revision_number'],
                'snapshot_hash' => (string) $basedOnRevision['snapshot_hash'],
            ],
            'composition' => ['state' => 'empty', 'pages' => [], 'theme' => null, 'assets' => []],
        ]);
    }

    private static function minimalPurposeSnapshot(array $site): array
    {
        $purpose = (string) $site['purpose'];
        if (!in_array($purpose, ['emd', 'internal_demo'], true)) {
            throw new SiteServiceException('invalid_request', 'The site purpose is invalid.');
        }
        return [
            'snapshot_schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'site_id' => (int) $site['id'],
            'site_key' => (string) $site['site_key'],
            'purpose' => $purpose,
            'business_id' => null,
            'facts_snapshot' => CanonicalJson::canonicalize([
                'purpose' => $purpose,
                'business' => null,
                'presentation' => ['customer_business_association' => 'none'],
            ]),
            'source_references' => CanonicalJson::canonicalize([
                'authority' => 'site_purpose_minimal_snapshot',
                'business_association' => null,
                'customer_business_fabricated' => false,
            ]),
        ];
    }

    private static function presentationServices(array $services): array
    {
        return [
            'selected' => array_map(static fn (array $row): array => [
                'name' => (string) $row['name'],
                'category_name' => (string) $row['category_name'],
            ], $services['selected_sub_services']),
            'custom' => array_map(static fn (array $row): array => [
                'name' => (string) $row['name'],
                'category_name' => (string) $row['category_name'],
            ], $services['custom_services']),
        ];
    }

    private static function presentationServiceArea(array $area): array
    {
        $customersVisit = (bool) ($area['customers_visit_business'] ?? false);
        return [
            'mode' => (string) ($area['mode'] ?? 'unconfigured'),
            'customers_visit_business' => $customersVisit,
            'business_travels_to_customers' => (bool) ($area['business_travels_to_customers'] ?? false),
            'base_location' => [
                'line_1' => $customersVisit ? ($area['base_address']['line_1'] ?? null) : null,
                'city' => $area['base_address']['city'] ?? null,
                'state' => $area['base_address']['state'] ?? null,
                'postal_code' => $area['base_address']['postal_code'] ?? null,
                'country' => $area['base_address']['country'] ?? null,
            ],
            'radius_miles' => $area['radius_miles'] ?? null,
            'radius_is_custom' => (bool) ($area['radius_is_custom'] ?? false),
        ];
    }

    private static function presentationFaqs(array $rows): array
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool =>
            (bool) ($row['is_active'] ?? false)
            && in_array((string) ($row['channel_scope'] ?? ''), ['all', 'website'], true)
        ));
        return self::withoutKeys($rows, ['id', 'updated_at', 'is_active']);
    }

    private static function activeRows(array $rows, array $removedKeys): array
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => (bool) ($row['is_active'] ?? false)));
        return self::withoutKeys($rows, $removedKeys);
    }

    private static function withoutKeys(array $rows, array $keys): array
    {
        return array_map(static function (array $row) use ($keys): array {
            foreach ($keys as $key) {
                unset($row[$key]);
            }
            return $row;
        }, array_values($rows));
    }
}
