<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CanonicalJson.php';
require_once __DIR__ . '/SiteRevisionSnapshotHasher.php';

final class LegacyWebsiteImportException extends RuntimeException
{
    public function __construct(private string $importErrorCode, string $message)
    {
        parent::__construct($message);
    }

    public function importErrorCode(): string
    {
        return $this->importErrorCode;
    }
}

final class LegacyWebsitePlatformImporter
{
    private const TEMPLATE_KEY = 'starter_local_service';
    private const COMPONENT_KEY = 'legacy_247sp_page';
    private const COMPONENT_IMPLEMENTATION_VERSION = 'legacy-preview-v1';
    private const PAGE_TYPES = ['home', 'service', 'about', 'contact'];
    private const MAX_BATCH_SIZE = 100;
    private const SNAPSHOT_SCHEMA_VERSION = 1;

    public static function importBatch(int $batchSize = 25, int $afterLegacyWebsiteId = 0): array
    {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE || $afterLegacyWebsiteId < 0) {
            throw new InvalidArgumentException('Batch size must be 1-100 and the cursor cannot be negative.');
        }

        $statement = Database::connection()->prepare(
            '/* legacy-import:list-batch */
             SELECT id
             FROM `247sp_generated_websites`
             WHERE id > :after_id
             ORDER BY id ASC
             LIMIT :batch_size'
        );
        $statement->bindValue(':after_id', $afterLegacyWebsiteId, PDO::PARAM_INT);
        $statement->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
        $statement->execute();
        $ids = array_map('intval', array_column($statement->fetchAll(), 'id'));

        $result = [
            'requested_batch_size' => $batchSize,
            'processed' => 0,
            'imported' => 0,
            'reconciled' => 0,
            'quarantined' => 0,
            'failed' => 0,
            'retryable' => 0,
            'next_after_id' => $afterLegacyWebsiteId,
            'has_more' => false,
            'units' => [],
        ];

        foreach ($ids as $legacyWebsiteId) {
            $unit = self::importWebsite($legacyWebsiteId);
            $result['units'][] = $unit;
            $result['processed']++;
            $result['next_after_id'] = $legacyWebsiteId;
            $status = (string) ($unit['result'] ?? 'quarantined');
            if (array_key_exists($status, $result)) {
                $result[$status]++;
            }
        }

        if (count($ids) === $batchSize) {
            $more = Database::connection()->prepare(
                '/* legacy-import:has-more */
                 SELECT 1 FROM `247sp_generated_websites` WHERE id > :after_id LIMIT 1'
            );
            $more->execute(['after_id' => $result['next_after_id']]);
            $result['has_more'] = (bool) $more->fetchColumn();
        }

        return $result;
    }

    public static function importWebsite(int $legacyWebsiteId): array
    {
        if ($legacyWebsiteId < 1) {
            throw new InvalidArgumentException('Legacy website ID must be positive.');
        }

        $connection = Database::connection();
        $sourceHash = null;

        try {
            // Filesystem inspection is deliberately completed before the write transaction.
            $preflightSource = self::loadSource($legacyWebsiteId, false);
            self::validateSource($preflightSource);
            $preflightDatabaseHash = self::hashValue(self::databaseSourcePayload($preflightSource));
            $assetEvidence = self::collectAssetEvidence($preflightSource);
            $sourceHash = self::hashValue(self::sourceHashPayload($preflightSource, $assetEvidence));

            $connection->beginTransaction();
            $source = self::loadSource($legacyWebsiteId, true);
            self::validateSource($source);
            $lockedDatabaseHash = self::hashValue(self::databaseSourcePayload($source));
            if (!hash_equals($preflightDatabaseHash, $lockedDatabaseHash)) {
                throw new LegacyWebsiteImportException(
                    'source_changed_during_import',
                    'The legacy database source changed during import; retry the import unit.'
                );
            }
            $mapping = self::lockMapping($legacyWebsiteId);

            if ($mapping !== null && $mapping['site_id'] !== null) {
                $result = self::reconcileExisting($source, $sourceHash, $mapping);
                $connection->commit();
                return $result;
            }

            $mappingId = $mapping === null
                ? self::insertPendingMapping($legacyWebsiteId, $sourceHash)
                : self::retryPendingMapping((int) $mapping['id'], $sourceHash);
            $result = self::createImportedAggregate($source, $assetEvidence, $sourceHash, $mappingId);
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $code = $exception instanceof LegacyWebsiteImportException
                ? $exception->importErrorCode()
                : 'database_failure';
            $summary = $exception instanceof LegacyWebsiteImportException
                ? $exception->getMessage()
                : 'The import unit failed; review application logs and repair the legacy source or schema.';

            if ($code === 'source_changed_during_import') {
                return [
                    'legacy_website_id' => $legacyWebsiteId,
                    'result' => 'retryable',
                    'retryable' => true,
                    'error_code' => $code,
                    'error_summary' => self::boundedSummary($summary),
                ];
            }

            $quarantine = self::recordQuarantine($legacyWebsiteId, $sourceHash, $code, $summary);

            return [
                'legacy_website_id' => $legacyWebsiteId,
                'result' => $quarantine['recorded'] ? 'quarantined' : 'failed',
                'error_code' => $code,
                'error_summary' => self::boundedSummary($summary),
                'quarantine_evidence' => $quarantine['status'],
            ] + ($quarantine['failure'] ?? []);
        }
    }

    public static function compareWebsite(int $legacyWebsiteId): array
    {
        $source = self::loadSource($legacyWebsiteId, false);
        $mapping = self::mappingForWebsite($legacyWebsiteId);
        $sourceHash = null;
        $eligibilityError = null;

        try {
            self::validateSource($source);
            $assetEvidence = self::collectAssetEvidence($source);
            $sourceHash = self::hashValue(self::sourceHashPayload($source, $assetEvidence));
        } catch (LegacyWebsiteImportException $exception) {
            $eligibilityError = $exception->importErrorCode();
        }

        $calculatedImportedHash = null;
        $storedRevisionHash = null;
        $importedPageCount = 0;
        if ($mapping !== null && $mapping['import_revision_id'] !== null) {
            $calculatedImportedHash = self::calculateImportedHash((int) $mapping['import_revision_id']);
            $storedRevisionHash = self::storedRevisionHash((int) $mapping['import_revision_id']);
            $importedPageCount = self::importedPageCount((int) $mapping['import_revision_id']);
        }

        return [
            'legacy_website_id' => $legacyWebsiteId,
            'eligible' => $eligibilityError === null,
            'eligibility_error' => $eligibilityError,
            'mapping_status' => $mapping['import_status'] ?? 'unmapped',
            'site_id' => isset($mapping['site_id']) ? (int) $mapping['site_id'] : null,
            'revision_id' => isset($mapping['import_revision_id']) ? (int) $mapping['import_revision_id'] : null,
            'legacy_page_count' => count($source['pages']),
            'imported_page_count' => $importedPageCount,
            'source_hash' => $sourceHash,
            'stored_source_hash' => $mapping['source_hash'] ?? null,
            'stored_imported_hash' => $mapping['imported_hash'] ?? null,
            'stored_revision_hash' => $storedRevisionHash,
            'calculated_imported_hash' => $calculatedImportedHash,
            'source_matches' => $mapping !== null && hash_equals((string) ($mapping['source_hash'] ?? ''), (string) $sourceHash),
            'import_matches' => $mapping !== null && hash_equals((string) ($mapping['imported_hash'] ?? ''), (string) $calculatedImportedHash),
            'revision_hash_matches' => $storedRevisionHash !== null
                && hash_equals($storedRevisionHash, (string) $calculatedImportedHash),
        ];
    }

    public static function reconciliationReport(int $hashLimit = 100): array
    {
        if ($hashLimit < 1 || $hashLimit > 500) {
            throw new InvalidArgumentException('Hash report limit must be 1-500.');
        }

        $connection = Database::connection();
        $scalar = static function (string $sql) use ($connection): int {
            return (int) $connection->query($sql)->fetchColumn();
        };

        $hashes = $connection->prepare(
            '/* legacy-import:report-hashes */
             SELECT legacy_website_id, site_id, import_revision_id, import_status,
                    source_hash, imported_hash, error_code
             FROM legacy_site_mappings
             ORDER BY legacy_website_id ASC
             LIMIT :hash_limit'
        );
        $hashes->bindValue(':hash_limit', $hashLimit, PDO::PARAM_INT);
        $hashes->execute();
        $hashRows = $hashes->fetchAll();
        foreach ($hashRows as &$hashRow) {
            $hashRow['calculated_imported_hash'] = null;
            $hashRow['stored_revision_hash'] = null;
            $hashRow['mapping_hash_matches'] = false;
            $hashRow['revision_hash_matches'] = false;
            if ($hashRow['import_revision_id'] === null) {
                continue;
            }
            try {
                $revisionId = (int) $hashRow['import_revision_id'];
                $calculated = self::calculateImportedHash($revisionId);
                $storedRevision = self::storedRevisionHash($revisionId);
                $hashRow['calculated_imported_hash'] = $calculated;
                $hashRow['stored_revision_hash'] = $storedRevision;
                $hashRow['mapping_hash_matches'] = hash_equals((string) ($hashRow['imported_hash'] ?? ''), $calculated);
                $hashRow['revision_hash_matches'] = $storedRevision !== null && hash_equals($storedRevision, $calculated);
            } catch (LegacyWebsiteImportException $exception) {
                $hashRow['reconciliation_error'] = $exception->importErrorCode();
            }
        }
        unset($hashRow);

        return [
            'candidate_legacy_count' => $scalar(
                "SELECT COUNT(*)
                 FROM `247sp_generated_websites` gw
                 INNER JOIN businesses b ON b.id = gw.business_id
                 INNER JOIN `247sp_onboarding` o ON o.id = gw.onboarding_id AND o.business_id = gw.business_id
                 INNER JOIN `247sp_templates` t ON t.id = gw.template_id AND t.template_key = 'starter_local_service'
                 WHERE EXISTS (
                    SELECT 1 FROM `247sp_generated_pages` gp
                    WHERE gp.website_id = gw.id AND gp.business_id = gw.business_id
                 )"
            ),
            'imported_count' => $scalar("SELECT COUNT(*) FROM legacy_site_mappings WHERE import_status = 'imported'"),
            'quarantined_count' => $scalar("SELECT COUNT(*) FROM legacy_site_mappings WHERE import_status = 'quarantined'"),
            'unmapped_candidate_count' => $scalar(
                "SELECT COUNT(*)
                 FROM `247sp_generated_websites` gw
                 INNER JOIN businesses b ON b.id = gw.business_id
                 INNER JOIN `247sp_onboarding` o ON o.id = gw.onboarding_id AND o.business_id = gw.business_id
                 INNER JOIN `247sp_templates` t ON t.id = gw.template_id AND t.template_key = 'starter_local_service'
                 LEFT JOIN legacy_site_mappings lm ON lm.legacy_website_id = gw.id
                 WHERE lm.id IS NULL
                   AND EXISTS (
                       SELECT 1 FROM `247sp_generated_pages` gp
                       WHERE gp.website_id = gw.id AND gp.business_id = gw.business_id
                   )"
            ),
            'mapping_count' => $scalar('SELECT COUNT(*) FROM legacy_site_mappings'),
            'legacy_page_count' => $scalar('SELECT COUNT(*) FROM `247sp_generated_pages`'),
            'imported_page_count' => $scalar('SELECT COUNT(*) FROM legacy_site_page_mappings'),
            'missing_association_count' => $scalar(
                "SELECT COUNT(*)
                 FROM legacy_site_mappings lm
                 INNER JOIN `247sp_generated_websites` gw ON gw.id = lm.legacy_website_id
                 LEFT JOIN site_business_associations sba
                   ON sba.site_id = lm.site_id
                  AND sba.business_id = gw.business_id
                  AND sba.association_role = 'customer'
                  AND sba.status = 'active'
                 WHERE lm.import_status = 'imported' AND sba.id IS NULL"
            ),
            'collision_count' => $scalar(
                "SELECT COUNT(*) FROM legacy_site_mappings
                 WHERE import_status = 'quarantined' AND error_code LIKE '%collision%'"
            ),
            'unexpected_cross_business_count' => $scalar(
                'SELECT COUNT(*)
                 FROM legacy_site_mappings lm
                 INNER JOIN `247sp_generated_websites` gw ON gw.id = lm.legacy_website_id
                 LEFT JOIN site_business_associations sba
                   ON sba.site_id = lm.site_id AND sba.status = \'active\'
                 LEFT JOIN `247sp_generated_pages` gp ON gp.website_id = gw.id
                 WHERE (sba.id IS NOT NULL AND sba.business_id <> gw.business_id)
                    OR (gp.id IS NOT NULL AND gp.business_id <> gw.business_id)'
            ),
            'hashes' => $hashRows,
        ];
    }

    private static function loadSource(int $legacyWebsiteId, bool $lock): array
    {
        $connection = Database::connection();
        $forUpdate = $lock ? ' FOR UPDATE' : '';
        $website = $connection->prepare(
            '/* legacy-import:load-website */
             SELECT gw.*, b.id AS resolved_business_id,
                    o.id AS resolved_onboarding_id, o.business_id AS onboarding_business_id,
                    t.id AS resolved_template_id, t.template_key
             FROM `247sp_generated_websites` gw
             LEFT JOIN businesses b ON b.id = gw.business_id
             LEFT JOIN `247sp_onboarding` o ON o.id = gw.onboarding_id
             LEFT JOIN `247sp_templates` t ON t.id = gw.template_id
             WHERE gw.id = :legacy_website_id
             LIMIT 1' . $forUpdate
        );
        $website->execute(['legacy_website_id' => $legacyWebsiteId]);
        $row = $website->fetch();
        if (!$row) {
            throw new LegacyWebsiteImportException('not_found', 'The legacy generated website does not exist.');
        }

        $businessId = (int) $row['business_id'];
        $pages = self::fetchAll(
            '/* legacy-import:load-pages */
             SELECT id, website_id, business_id, page_type, title, slug, content_json,
                    status, sort_order, created_at, updated_at
             FROM `247sp_generated_pages`
             WHERE website_id = :website_id
             ORDER BY sort_order ASC, id ASC' . $forUpdate,
            ['website_id' => $legacyWebsiteId]
        );

        return [
            'website' => $row,
            'pages' => $pages,
            'branding' => self::fetchOne(
                '/* legacy-import:load-branding */ SELECT * FROM `247sp_website_branding` WHERE business_id = :business_id LIMIT 1' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'overrides' => self::fetchAll(
                '/* legacy-import:load-overrides */ SELECT id, page_key, field_key, field_value, updated_at
                 FROM `247sp_website_content_overrides` WHERE business_id = :business_id ORDER BY page_key, field_key, id' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'service_images' => self::fetchAll(
                '/* legacy-import:load-service-images */ SELECT id, service_number, image_path, updated_at
                 FROM `247sp_website_service_images` WHERE business_id = :business_id ORDER BY service_number, id' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'integrations' => self::fetchOne(
                '/* legacy-import:load-integrations */ SELECT * FROM website_integrations WHERE business_id = :business_id LIMIT 1' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'configuration' => self::fetchOne(
                '/* legacy-import:load-configuration */ SELECT * FROM `247sp_website_configurations` WHERE business_id = :business_id LIMIT 1' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'business_content' => self::fetchOne(
                '/* legacy-import:load-business-content */ SELECT id, business_id, onboarding_id, updated_at
                 FROM `247sp_business_content` WHERE business_id = :business_id LIMIT 1' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'service_pages' => self::fetchAll(
                '/* legacy-import:load-service-pages */ SELECT id, business_id, onboarding_id, service_number,
                        parent_service_page_id, sort_order, status, slug, updated_at
                 FROM `247sp_service_pages` WHERE business_id = :business_id ORDER BY sort_order, id' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'business_profile' => self::fetchOne(
                '/* legacy-import:load-profile-reference */ SELECT id, business_id, lifecycle_status, updated_at
                 FROM business_profiles WHERE business_id = :business_id LIMIT 1' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'selected_services' => self::fetchAll(
                '/* legacy-import:load-selected-service-references */ SELECT business_id, sub_service_id
                 FROM business_sub_services WHERE business_id = :business_id ORDER BY sub_service_id' . $forUpdate,
                ['business_id' => $businessId]
            ),
            'custom_services' => self::fetchAll(
                '/* legacy-import:load-custom-service-references */ SELECT id, business_id, category_id, updated_at
                 FROM business_custom_services WHERE business_id = :business_id ORDER BY id' . $forUpdate,
                ['business_id' => $businessId]
            ),
        ];
    }

    private static function validateSource(array &$source): void
    {
        $website = $source['website'];
        $businessId = (int) $website['business_id'];
        if ((int) ($website['resolved_business_id'] ?? 0) !== $businessId) {
            throw new LegacyWebsiteImportException('missing_business', 'The legacy website business dependency is missing.');
        }
        if ((int) ($website['resolved_onboarding_id'] ?? 0) !== (int) $website['onboarding_id']
            || (int) ($website['onboarding_business_id'] ?? 0) !== $businessId
        ) {
            throw new LegacyWebsiteImportException('onboarding_mismatch', 'The legacy onboarding dependency is missing or belongs to another business.');
        }
        if ((string) ($website['template_key'] ?? '') !== self::TEMPLATE_KEY) {
            throw new LegacyWebsiteImportException('unsupported_template', 'The legacy website template is not supported by the M1 import allowlist.');
        }
        if (count($source['pages']) === 0) {
            throw new LegacyWebsiteImportException('missing_pages', 'The legacy website has no generated pages.');
        }

        $slugs = [];
        foreach ($source['pages'] as &$page) {
            if ((int) $page['website_id'] !== (int) $website['id'] || (int) $page['business_id'] !== $businessId) {
                throw new LegacyWebsiteImportException('cross_business_page', 'A legacy generated page has an unexpected website or business owner.');
            }
            $type = trim((string) $page['page_type']);
            if (!in_array($type, self::PAGE_TYPES, true)) {
                throw new LegacyWebsiteImportException('unsupported_page_type', 'A legacy generated page type is not supported by repository preview behavior.');
            }
            $slug = strtolower(trim((string) $page['slug'], '/ '));
            if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                throw new LegacyWebsiteImportException('invalid_page_slug', 'A legacy generated page has an invalid slug.');
            }
            if (strlen($slug) > 255) {
                throw new LegacyWebsiteImportException('page_slug_too_long', 'A legacy generated page slug exceeds the imported revision limit.');
            }
            if (isset($slugs[$slug])) {
                throw new LegacyWebsiteImportException('page_slug_collision', 'Legacy generated page slugs collide after normalization.');
            }
            $title = (string) $page['title'];
            if (trim($title) === '') {
                throw new LegacyWebsiteImportException('invalid_page_title', 'A legacy generated page title is empty.');
            }
            if (self::textLength($title) > 150) {
                throw new LegacyWebsiteImportException('page_title_too_long', 'A legacy generated page title exceeds the imported navigation-label limit.');
            }
            try {
                $decoded = json_decode((string) $page['content_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new LegacyWebsiteImportException('malformed_page_json', 'A legacy generated page contains malformed JSON.');
            }
            if (!is_array($decoded) || self::containsExecutableMarker($decoded)) {
                throw new LegacyWebsiteImportException('unsafe_page_content', 'A legacy generated page contains an unsupported executable-content marker.');
            }
            $page['normalized_slug'] = $slug;
            $page['decoded_content'] = $decoded;
            $slugs[$slug] = true;
        }
        unset($page);

        self::applyImportedPageSortOrders($source['pages']);
    }

    private static function applyImportedPageSortOrders(array &$pages): void
    {
        $sortOrder = 10;
        foreach ($pages as &$page) {
            $page['imported_sort_order'] = $sortOrder;
            $sortOrder += 10;
        }
        unset($page);
    }

    private static function createImportedAggregate(array $source, array $assetEvidence, string $sourceHash, int $mappingId): array
    {
        $connection = Database::connection();
        $website = $source['website'];
        $legacyWebsiteId = (int) $website['id'];
        $businessId = (int) $website['business_id'];
        $siteKey = self::deterministicKey('legacy-site', (string) $legacyWebsiteId);

        $collision = self::fetchOne(
            '/* legacy-import:site-key-collision */ SELECT id FROM sites WHERE site_key = :site_key LIMIT 1 FOR UPDATE',
            ['site_key' => $siteKey]
        );
        if ($collision !== null) {
            throw new LegacyWebsiteImportException('site_key_collision', 'The deterministic generic site key is already in use.');
        }

        self::execute(
            '/* legacy-import:insert-site */ INSERT INTO sites
                (site_key, purpose, lifecycle_status, lock_version, created_at, updated_at)
             VALUES (:site_key, :purpose, :lifecycle_status, 0, NOW(), NOW())',
            ['site_key' => $siteKey, 'purpose' => '247sp', 'lifecycle_status' => 'draft']
        );
        $siteId = (int) $connection->lastInsertId();

        self::execute(
            '/* legacy-import:insert-association */ INSERT INTO site_business_associations
                (site_id, business_id, association_role, status, effective_at, reason, correlation_id, created_at, updated_at)
             VALUES (:site_id, :business_id, :role, :status, NOW(), :reason, :correlation_id, NOW(), NOW())',
            [
                'site_id' => $siteId,
                'business_id' => $businessId,
                'role' => 'customer',
                'status' => 'active',
                'reason' => 'Imported from legacy 247SP generated website.',
                'correlation_id' => 'legacy-import-' . $legacyWebsiteId,
            ]
        );

        $brief = [
            'legacy_website_id' => $legacyWebsiteId,
            'template_key' => self::TEMPLATE_KEY,
            'page_count' => count($source['pages']),
            'source_tables' => [
                '247sp_generated_websites', '247sp_generated_pages', '247sp_website_branding',
                '247sp_website_content_overrides', '247sp_website_service_images', 'website_integrations',
            ],
            'authority' => 'legacy_presentation_snapshot_only',
        ];
        $briefHash = self::hashValue($brief);
        self::execute(
            '/* legacy-import:insert-brief */ INSERT INTO site_generation_briefs
                (site_id, brief_version, state, brief_json, source_type, source_reference, content_hash, created_at)
             VALUES (:site_id, 1, :state, :brief_json, :source_type, :source_reference, :content_hash, NOW())',
            [
                'site_id' => $siteId,
                'state' => 'imported',
                'brief_json' => self::encode($brief),
                'source_type' => 'legacy_247sp',
                'source_reference' => '247sp_generated_websites:' . $legacyWebsiteId,
                'content_hash' => $briefHash,
            ]
        );
        $briefId = (int) $connection->lastInsertId();

        $factsReferences = [
            'business_id' => $businessId,
            'business_profile_id' => isset($source['business_profile']['id']) ? (int) $source['business_profile']['id'] : null,
            'selected_sub_service_ids' => array_map('intval', array_column($source['selected_services'], 'sub_service_id')),
            'custom_service_ids' => array_map('intval', array_column($source['custom_services'], 'id')),
            'authority' => 'references_only',
        ];
        $sourceReferences = self::sourceReferences($source);
        $revisionHash = self::hashValue(self::revisionRepresentationFromSource(
            $source,
            $assetEvidence,
            $factsReferences,
            $sourceReferences,
            $brief,
            $briefHash
        ));

        self::execute(
            '/* legacy-import:insert-revision */ INSERT INTO site_revisions
                (site_id, revision_number, lifecycle_status, generation_brief_id, materiality,
                 snapshot_schema_version, facts_snapshot_json, source_references_json, snapshot_hash,
                 correlation_id, created_at, updated_at)
             VALUES (:site_id, 1, :status, :brief_id, :materiality, :schema_version,
                     :facts_json, :references_json, :snapshot_hash, :correlation_id, NOW(), NOW())',
            [
                'site_id' => $siteId,
                'status' => 'draft',
                'brief_id' => $briefId,
                'materiality' => 'undetermined',
                'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
                'facts_json' => self::encode($factsReferences),
                'references_json' => self::encode($sourceReferences),
                'snapshot_hash' => $revisionHash,
                'correlation_id' => 'legacy-import-' . $legacyWebsiteId,
            ]
        );
        $revisionId = (int) $connection->lastInsertId();
        self::bindPendingMapping($mappingId, $siteId, $revisionId);
        $variants = self::loadVariants();
        $pageImports = [];

        foreach ($source['pages'] as $page) {
            $logicalKey = self::logicalPageKey((string) $page['page_type'], (string) $page['normalized_slug']);
            self::execute(
                '/* legacy-import:insert-logical-page */ INSERT INTO site_pages (site_id, page_key, created_at)
                 VALUES (:site_id, :page_key, NOW())',
                ['site_id' => $siteId, 'page_key' => $logicalKey]
            );
            $sitePageId = (int) $connection->lastInsertId();
            $pageContentHash = self::hashValue($page['decoded_content']);
            $presentation = [
                'legacy_page_id' => (int) $page['id'],
                'legacy_status' => (string) $page['status'],
                'legacy_sort_order' => (int) $page['sort_order'],
                'snapshot_only' => true,
            ];
            self::execute(
                '/* legacy-import:insert-revision-page */ INSERT INTO site_revision_pages
                    (site_id, revision_id, site_page_id, title, slug, page_type, navigation_label,
                     sort_order, seo_json, presentation_json, content_hash, created_at)
                 VALUES (:site_id, :revision_id, :site_page_id, :title, :slug, :page_type, :navigation_label,
                         :sort_order, NULL, :presentation_json, :content_hash, NOW())',
                [
                    'site_id' => $siteId,
                    'revision_id' => $revisionId,
                    'site_page_id' => $sitePageId,
                    'title' => (string) $page['title'],
                    'slug' => (string) $page['normalized_slug'],
                    'page_type' => (string) $page['page_type'],
                    'navigation_label' => (string) $page['title'],
                    'sort_order' => (int) $page['imported_sort_order'],
                    'presentation_json' => self::encode($presentation),
                    'content_hash' => $pageContentHash,
                ]
            );
            $revisionPageId = (int) $connection->lastInsertId();
            self::execute(
                '/* legacy-import:insert-section */ INSERT INTO site_page_sections
                    (site_id, revision_id, revision_page_id, section_key, component_variant_id, sort_order,
                     configuration_schema_version, configuration_json, content_hash, created_at)
                 VALUES (:site_id, :revision_id, :revision_page_id, :section_key, :variant_id, 10, 1,
                         :configuration_json, :content_hash, NOW())',
                [
                    'site_id' => $siteId,
                    'revision_id' => $revisionId,
                    'revision_page_id' => $revisionPageId,
                    'section_key' => 'legacy-page-snapshot',
                    'variant_id' => $variants[(string) $page['page_type']],
                    'configuration_json' => self::encode($page['decoded_content']),
                    'content_hash' => $pageContentHash,
                ]
            );
            $sectionId = (int) $connection->lastInsertId();
            self::execute(
                '/* legacy-import:insert-page-mapping */ INSERT INTO legacy_site_page_mappings
                    (legacy_mapping_id, legacy_page_id, site_id, site_page_id, site_revision_page_id,
                     source_hash, imported_hash, created_at)
                 VALUES (:mapping_id, :legacy_page_id, :site_id, :site_page_id, :revision_page_id,
                         :source_hash, :imported_hash, NOW())',
                [
                    'mapping_id' => $mappingId,
                    'legacy_page_id' => (int) $page['id'],
                    'site_id' => $siteId,
                    'site_page_id' => $sitePageId,
                    'revision_page_id' => $revisionPageId,
                    'source_hash' => $pageContentHash,
                    'imported_hash' => $pageContentHash,
                ]
            );
            $pageImports[(int) $page['id']] = [
                'revision_page_id' => $revisionPageId,
                'section_id' => $sectionId,
                'content' => $page['decoded_content'],
            ];
        }

        $theme = self::themeSnapshot($source['branding']);
        self::execute(
            '/* legacy-import:insert-theme */ INSERT INTO site_themes
                (site_id, revision_id, theme_key, theme_version, primary_color, secondary_color,
                 typography_json, configuration_json, content_hash, created_at)
             VALUES (:site_id, :revision_id, :theme_key, 1, :primary_color, :secondary_color,
                     NULL, :configuration_json, :content_hash, NOW())',
            [
                'site_id' => $siteId,
                'revision_id' => $revisionId,
                'theme_key' => 'legacy_247sp_starter',
                'primary_color' => $theme['primary_color'],
                'secondary_color' => $theme['secondary_color'],
                'configuration_json' => self::encode($theme['configuration']),
                'content_hash' => self::hashValue($theme),
            ]
        );

        self::importAssets($assetEvidence, $siteId, $businessId, $revisionId, $pageImports);
        self::execute(
            '/* legacy-import:insert-event */ INSERT INTO site_events
                (site_id, revision_id, actor_type, event_type, result, reason, correlation_id, metadata_json, created_at)
             VALUES (:site_id, :revision_id, :actor_type, :event_type, :result, :reason,
                     :correlation_id, :metadata_json, NOW())',
            [
                'site_id' => $siteId,
                'revision_id' => $revisionId,
                'actor_type' => 'system',
                'event_type' => 'legacy_baseline_imported',
                'result' => 'succeeded',
                'reason' => 'Imported as dormant compatibility data; legacy runtime remains authoritative.',
                'correlation_id' => 'legacy-import-' . $legacyWebsiteId,
                'metadata_json' => self::encode(['legacy_website_id' => $legacyWebsiteId, 'page_count' => count($source['pages'])]),
            ]
        );

        $importedHash = self::calculateImportedHash($revisionId);
        if (!hash_equals($revisionHash, $importedHash)) {
            throw new LegacyWebsiteImportException(
                'revision_hash_mismatch',
                'The imported revision does not match its canonical preflight representation.'
            );
        }
        self::completePendingMapping($mappingId, $siteId, $revisionId, $sourceHash, $revisionHash);

        return [
            'legacy_website_id' => $legacyWebsiteId,
            'result' => 'imported',
            'site_id' => $siteId,
            'revision_id' => $revisionId,
            'page_count' => count($source['pages']),
            'source_hash' => $sourceHash,
            'imported_hash' => $revisionHash,
        ];
    }

    private static function reconcileExisting(array $source, string $sourceHash, array $mapping): array
    {
        if (($mapping['source_hash'] ?? null) === null || !hash_equals((string) $mapping['source_hash'], $sourceHash)) {
            throw new LegacyWebsiteImportException('source_changed', 'The legacy presentation changed after its M1 baseline import; a later revision workflow is required.');
        }
        if ($mapping['import_revision_id'] === null) {
            throw new LegacyWebsiteImportException('mapping_collision', 'The legacy mapping has a site but no imported revision.');
        }
        $calculated = self::calculateImportedHash((int) $mapping['import_revision_id']);
        $storedRevisionHash = self::storedRevisionHash((int) $mapping['import_revision_id']);
        if ($storedRevisionHash === null || !hash_equals($storedRevisionHash, $calculated)) {
            throw new LegacyWebsiteImportException('revision_hash_mismatch', 'The imported revision no longer matches its stored snapshot hash.');
        }
        if (!hash_equals((string) ($mapping['imported_hash'] ?? ''), $calculated)) {
            throw new LegacyWebsiteImportException('import_hash_mismatch', 'The imported generic representation no longer matches its stored hash.');
        }
        if (self::importedPageCount((int) $mapping['import_revision_id']) !== count($source['pages'])) {
            throw new LegacyWebsiteImportException('page_count_mismatch', 'Legacy and imported page counts do not match.');
        }

        self::execute(
            '/* legacy-import:reconcile-mapping */ UPDATE legacy_site_mappings
             SET import_status = :status, last_attempted_at = NOW(), attempt_count = attempt_count + 1,
                 quarantined_at = NULL, error_code = NULL, error_summary = NULL, updated_at = NOW()
             WHERE id = :mapping_id',
            ['status' => 'imported', 'mapping_id' => (int) $mapping['id']]
        );

        return [
            'legacy_website_id' => (int) $source['website']['id'],
            'result' => 'reconciled',
            'site_id' => (int) $mapping['site_id'],
            'revision_id' => (int) $mapping['import_revision_id'],
            'page_count' => count($source['pages']),
            'source_hash' => $sourceHash,
            'imported_hash' => $calculated,
        ];
    }

    private static function loadVariants(): array
    {
        $rows = self::fetchAll(
            '/* legacy-import:load-variants */ SELECT cv.id, cv.variant_key
             FROM component_variants cv
             INNER JOIN component_definitions cd ON cd.id = cv.component_definition_id
             WHERE cd.component_key = :component_key
               AND cd.implementation_version = :implementation_version
               AND cd.status = :definition_status AND cv.status = :variant_status
             ORDER BY cv.variant_key',
            [
                'component_key' => self::COMPONENT_KEY,
                'implementation_version' => self::COMPONENT_IMPLEMENTATION_VERSION,
                'definition_status' => 'active',
                'variant_status' => 'active',
            ]
        );
        $variants = [];
        foreach ($rows as $row) {
            $variants[(string) $row['variant_key']] = (int) $row['id'];
        }
        foreach (self::PAGE_TYPES as $type) {
            if (!isset($variants[$type])) {
                throw new LegacyWebsiteImportException('missing_component_metadata', 'Required repository-backed legacy component metadata is missing.');
            }
        }
        return $variants;
    }

    private static function importAssets(array $assetEvidence, int $siteId, int $businessId, int $revisionId, array $pageImports): void
    {
        foreach ($assetEvidence as $usage) {
            $path = (string) $usage['normalized_path'];
            $assetKey = self::deterministicKey('legacy-asset-' . $businessId, $path);
            $existing = self::fetchOne(
                '/* legacy-import:asset-collision */ SELECT * FROM site_assets WHERE asset_key = :asset_key LIMIT 1 FOR UPDATE',
                ['asset_key' => $assetKey]
            );
            if ($existing !== null) {
                if ((int) $existing['site_id'] !== $siteId
                    || (string) $existing['storage_key'] !== $path
                    || !hash_equals((string) $existing['checksum_sha256'], (string) $usage['checksum_sha256'])
                ) {
                    throw new LegacyWebsiteImportException('asset_key_collision', 'A deterministic imported asset key collides with different asset evidence.');
                }
                $assetId = (int) $existing['id'];
            } else {
                self::execute(
                    '/* legacy-import:insert-asset */ INSERT INTO site_assets
                        (site_id, business_id, asset_key, asset_type, storage_key, checksum_sha256,
                         mime_type, byte_size, source, rights_classification, rights_metadata_json,
                         lifecycle_status, retention_hold, created_at, updated_at)
                     VALUES (:site_id, :business_id, :asset_key, :asset_type, :storage_key, :checksum,
                             :mime_type, :byte_size, :source, :rights, :rights_json, :status, 0, NOW(), NOW())',
                    [
                        'site_id' => $siteId,
                        'business_id' => $businessId,
                        'asset_key' => $assetKey,
                        'asset_type' => $usage['asset_type'],
                        'storage_key' => $path,
                        'checksum' => $usage['checksum_sha256'],
                        'mime_type' => $usage['mime_type'],
                        'byte_size' => $usage['byte_size'],
                        'source' => 'legacy_247sp',
                        'rights' => 'unknown',
                        'rights_json' => self::encode(['review_required' => true, 'legacy_reference' => true]),
                        'status' => 'ready',
                    ]
                );
                $assetId = (int) Database::connection()->lastInsertId();
            }

            $pageImport = $usage['legacy_page_id'] !== null ? $pageImports[(int) $usage['legacy_page_id']] : null;
            self::execute(
                '/* legacy-import:insert-revision-asset */ INSERT INTO site_revision_assets
                    (site_id, revision_id, asset_id, usage_key, site_revision_page_id,
                     site_page_section_id, source_reference, created_at)
                 VALUES (:site_id, :revision_id, :asset_id, :usage_key, :revision_page_id,
                         :section_id, :source_reference, NOW())',
                [
                    'site_id' => $siteId,
                    'revision_id' => $revisionId,
                    'asset_id' => $assetId,
                    'usage_key' => substr((string) $usage['usage_key'], 0, 100),
                    'revision_page_id' => $pageImport['revision_page_id'] ?? null,
                    'section_id' => $pageImport['section_id'] ?? null,
                    'source_reference' => $path,
                ]
            );
        }
    }

    private static function collectAssetEvidence(array $source): array
    {
        if (Database::connection()->inTransaction()) {
            throw new LegacyWebsiteImportException(
                'filesystem_in_transaction',
                'Legacy asset inspection must run before the database write transaction.'
            );
        }

        $usages = [];
        foreach (self::pathsInValue($source['branding'] ?? [], 'theme') as $usage) {
            $usages[] = $usage + ['legacy_page_id' => null];
        }
        foreach ($source['service_images'] as $row) {
            $path = trim((string) ($row['image_path'] ?? ''));
            if ($path !== '') {
                $usages[] = ['path' => $path, 'usage_key' => 'service-image-' . (int) $row['service_number'], 'legacy_page_id' => null];
            }
        }
        foreach ($source['pages'] as $page) {
            foreach (self::pathsInValue($page['decoded_content'], 'page-' . (int) $page['id']) as $usage) {
                $usages[] = $usage + ['legacy_page_id' => (int) $page['id']];
            }
        }

        $evidence = [];
        $seen = [];
        foreach ($usages as $usage) {
            $path = self::normalizeAssetPath((string) $usage['path']);
            $dedupe = $path . '|' . (string) $usage['usage_key'] . '|' . (string) ($usage['legacy_page_id'] ?? '');
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $evidence[] = [
                'normalized_path' => $path,
                'usage_key' => substr((string) $usage['usage_key'], 0, 100),
                'legacy_page_id' => $usage['legacy_page_id'],
            ] + self::inspectAsset($path);
        }
        usort($evidence, static fn (array $left, array $right): int => [
            $left['usage_key'], $left['normalized_path'], $left['legacy_page_id'] ?? 0,
        ] <=> [
            $right['usage_key'], $right['normalized_path'], $right['legacy_page_id'] ?? 0,
        ]);
        return $evidence;
    }

    private static function normalizeAssetPath(string $publicPath): string
    {
        $publicPath = str_replace('\\', '/', trim($publicPath));
        if (str_starts_with($publicPath, '//')) {
            throw new LegacyWebsiteImportException('invalid_asset_reference', 'A legacy asset reference is not a safe application-public path.');
        }
        $publicPath = preg_replace('#/+#', '/', $publicPath) ?? $publicPath;
        if ($publicPath === '' || $publicPath[0] !== '/' || str_contains($publicPath, '..')) {
            throw new LegacyWebsiteImportException('invalid_asset_reference', 'A legacy asset reference is not a safe application-public path.');
        }
        if (strlen($publicPath) > 500) {
            throw new LegacyWebsiteImportException('asset_reference_too_long', 'A legacy asset reference exceeds the imported storage-key limit.');
        }
        return $publicPath;
    }

    private static function inspectAsset(string $publicPath): array
    {
        $publicPath = self::normalizeAssetPath($publicPath);
        $root = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'app');
        if ($root === false) {
            throw new LegacyWebsiteImportException('missing_asset', 'A referenced legacy asset is missing or unreadable.');
        }
        return self::inspectAssetWithinRoot($publicPath, $root);
    }

    private static function inspectAssetWithinRoot(string $publicPath, string $publicRoot): array
    {
        $publicPath = self::normalizeAssetPath($publicPath);
        $root = realpath($publicRoot);
        $candidate = $root === false
            ? false
            : realpath($root . str_replace('/', DIRECTORY_SEPARATOR, $publicPath));
        if ($root === false || $candidate === false || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) || !is_file($candidate) || !is_readable($candidate)) {
            throw new LegacyWebsiteImportException('missing_asset', 'A referenced legacy asset is missing or unreadable.');
        }
        $checksum = hash_file('sha256', $candidate);
        $byteSize = filesize($candidate);
        if (!is_string($checksum) || $checksum === '' || $byteSize === false) {
            throw new LegacyWebsiteImportException('asset_read_failure', 'A referenced legacy asset could not be hashed.');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            if ($info !== false) {
                $detected = finfo_file($info, $candidate);
                finfo_close($info);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }
        return [
            'checksum_sha256' => $checksum,
            'byte_size' => (int) $byteSize,
            'mime_type' => $mime,
            'asset_type' => str_starts_with($mime, 'image/') ? 'image' : ($mime === 'application/pdf' ? 'document' : 'file'),
        ];
    }

    private static function calculateImportedHash(int $revisionId): string
    {
        $revision = self::fetchOne(
            '/* legacy-import:hash-revision */ SELECT snapshot_schema_version, facts_snapshot_json,
                    source_references_json, generation_brief_id
             FROM site_revisions WHERE id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        if ($revision === null || $revision['generation_brief_id'] === null) {
            throw new LegacyWebsiteImportException('missing_import_revision', 'The imported baseline revision or generation brief is missing.');
        }
        $brief = self::fetchOne(
            '/* legacy-import:hash-brief */ SELECT brief_version, state, brief_json, source_type,
                    source_reference, content_hash
             FROM site_generation_briefs WHERE id = :brief_id LIMIT 1',
            ['brief_id' => (int) $revision['generation_brief_id']]
        );
        if ($brief === null) {
            throw new LegacyWebsiteImportException('missing_import_brief', 'The imported baseline generation brief is missing.');
        }
        try {
            return SiteRevisionSnapshotHasher::hashStoredRevision(Database::connection(), $revisionId);
        } catch (SiteServiceException $exception) {
            $code = $exception->classification() === 'not_found'
                ? 'missing_import_revision'
                : 'revision_hash_mismatch';
            throw new LegacyWebsiteImportException($code, $exception->getMessage());
        }
        /* M1's canonical representation now lives in SiteRevisionSnapshotHasher. */
        /* @codeCoverageIgnoreStart */
        $revision = self::fetchOne(
            '/* legacy-import:hash-revision */ SELECT snapshot_schema_version, facts_snapshot_json,
                    source_references_json, generation_brief_id
             FROM site_revisions WHERE id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        if ($revision === null || $revision['generation_brief_id'] === null) {
            throw new LegacyWebsiteImportException('missing_import_revision', 'The imported baseline revision or generation brief is missing.');
        }
        $brief = self::fetchOne(
            '/* legacy-import:hash-brief */ SELECT brief_version, state, brief_json, source_type,
                    source_reference, content_hash
             FROM site_generation_briefs WHERE id = :brief_id LIMIT 1',
            ['brief_id' => (int) $revision['generation_brief_id']]
        );
        if ($brief === null) {
            throw new LegacyWebsiteImportException('missing_import_brief', 'The imported baseline generation brief is missing.');
        }

        $pages = self::fetchAll(
            '/* legacy-import:hash-pages */ SELECT rp.id, p.page_key, rp.title, rp.slug, rp.page_type,
                    navigation_label, sort_order, seo_json, presentation_json, content_hash
             FROM site_revision_pages rp
             INNER JOIN site_pages p ON p.id = rp.site_page_id AND p.site_id = rp.site_id
             WHERE rp.revision_id = :revision_id ORDER BY rp.sort_order, rp.id',
            ['revision_id' => $revisionId]
        );
        foreach ($pages as &$page) {
            $sections = self::fetchAll(
                '/* legacy-import:hash-sections */ SELECT s.section_key, cd.component_key,
                        cd.implementation_version, cv.variant_key,
                        cv.configuration_schema_version AS variant_configuration_schema_version,
                        s.sort_order, s.configuration_schema_version, s.configuration_json, s.content_hash
                 FROM site_page_sections s
                 INNER JOIN component_variants cv ON cv.id = s.component_variant_id
                 INNER JOIN component_definitions cd ON cd.id = cv.component_definition_id
                 WHERE s.revision_page_id = :revision_page_id ORDER BY s.sort_order, s.id',
                ['revision_page_id' => (int) $page['id']]
            );
            foreach ($sections as &$section) {
                $section = [
                    'section_key' => (string) $section['section_key'],
                    'component_key' => (string) $section['component_key'],
                    'implementation_version' => (string) $section['implementation_version'],
                    'variant_key' => (string) $section['variant_key'],
                    'variant_configuration_schema_version' => (int) $section['variant_configuration_schema_version'],
                    'sort_order' => (int) $section['sort_order'],
                    'configuration_schema_version' => (int) $section['configuration_schema_version'],
                    'configuration' => self::decodeNullableJson($section['configuration_json']),
                    'content_hash' => (string) $section['content_hash'],
                ];
            }
            unset($section);
            $page = [
                'page_key' => (string) $page['page_key'],
                'title' => (string) $page['title'],
                'slug' => (string) $page['slug'],
                'page_type' => (string) $page['page_type'],
                'navigation_label' => self::nullableString($page['navigation_label']),
                'sort_order' => (int) $page['sort_order'],
                'seo' => self::decodeNullableJson($page['seo_json']),
                'presentation' => self::decodeNullableJson($page['presentation_json']),
                'content_hash' => (string) $page['content_hash'],
                'sections' => $sections,
            ];
        }
        unset($page);
        $theme = self::fetchOne(
            '/* legacy-import:hash-theme */ SELECT theme_key, theme_version, primary_color,
                    secondary_color, typography_json, configuration_json, content_hash
             FROM site_themes WHERE revision_id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        if ($theme === null) {
            throw new LegacyWebsiteImportException('missing_import_theme', 'The imported baseline revision theme is missing.');
        }
        $theme = [
            'theme_key' => (string) $theme['theme_key'],
            'theme_version' => (int) $theme['theme_version'],
            'primary_color' => self::nullableString($theme['primary_color']),
            'secondary_color' => self::nullableString($theme['secondary_color']),
            'typography' => self::decodeNullableJson($theme['typography_json']),
            'configuration' => self::decodeNullableJson($theme['configuration_json']),
            'content_hash' => (string) $theme['content_hash'],
        ];
        $assets = self::fetchAll(
            '/* legacy-import:hash-assets */ SELECT a.asset_type, a.storage_key, a.checksum_sha256,
                    a.mime_type, a.byte_size, ra.usage_key, ra.source_reference,
                    p.page_key, s.section_key
             FROM site_revision_assets ra
             INNER JOIN site_assets a ON a.id = ra.asset_id
             LEFT JOIN site_revision_pages rp ON rp.id = ra.site_revision_page_id
             LEFT JOIN site_pages p ON p.id = rp.site_page_id
             LEFT JOIN site_page_sections s ON s.id = ra.site_page_section_id
             WHERE ra.revision_id = :revision_id ORDER BY ra.usage_key, a.storage_key, p.page_key',
            ['revision_id' => $revisionId]
        );
        foreach ($assets as &$asset) {
            $asset = [
                'asset_type' => (string) $asset['asset_type'],
                'storage_key' => (string) $asset['storage_key'],
                'checksum_sha256' => (string) $asset['checksum_sha256'],
                'mime_type' => (string) $asset['mime_type'],
                'byte_size' => (int) $asset['byte_size'],
                'usage_key' => (string) $asset['usage_key'],
                'source_reference' => self::nullableString($asset['source_reference']),
                'page_key' => self::nullableString($asset['page_key']),
                'section_key' => self::nullableString($asset['section_key']),
            ];
        }
        unset($asset);

        return self::hashValue([
            'schema_version' => (int) $revision['snapshot_schema_version'],
            'facts_snapshot' => self::decodeNullableJson($revision['facts_snapshot_json']),
            'source_references' => self::decodeNullableJson($revision['source_references_json']),
            'generation_brief' => [
                'brief_version' => (int) $brief['brief_version'],
                'state' => (string) $brief['state'],
                'brief' => self::decodeNullableJson($brief['brief_json']),
                'source_type' => (string) $brief['source_type'],
                'source_reference' => self::nullableString($brief['source_reference']),
                'content_hash' => (string) $brief['content_hash'],
            ],
            'pages' => $pages,
            'theme' => $theme,
            'assets' => $assets,
        ]);
        /* @codeCoverageIgnoreEnd */
    }

    private static function storedRevisionHash(int $revisionId): ?string
    {
        if ($revisionId < 1) {
            return null;
        }
        $row = self::fetchOne(
            '/* legacy-import:stored-revision-hash */ SELECT snapshot_hash FROM site_revisions WHERE id = :revision_id LIMIT 1',
            ['revision_id' => $revisionId]
        );
        return isset($row['snapshot_hash']) ? (string) $row['snapshot_hash'] : null;
    }

    private static function recordQuarantine(int $legacyWebsiteId, ?string $sourceHash, string $errorCode, string $summary): array
    {
        $connection = Database::connection();
        try {
            $connection->beginTransaction();
            $exists = self::fetchOne(
                '/* legacy-import:quarantine-source */ SELECT id FROM `247sp_generated_websites`
                 WHERE id = :legacy_website_id LIMIT 1 FOR UPDATE',
                ['legacy_website_id' => $legacyWebsiteId]
            );
            if ($exists === null) {
                $connection->rollBack();
                return ['recorded' => false, 'status' => 'source_missing'];
            }
            self::execute(
                '/* legacy-import:record-quarantine */ INSERT INTO legacy_site_mappings
                    (legacy_website_id, import_status, source_hash, attempt_count, last_attempted_at,
                     quarantined_at, error_code, error_summary, created_at, updated_at)
                 VALUES (:legacy_website_id, :status, :source_hash, 1, NOW(), NOW(), :error_code, :error_summary, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    import_status = VALUES(import_status),
                    source_hash = COALESCE(legacy_site_mappings.source_hash, VALUES(source_hash)),
                    attempt_count = legacy_site_mappings.attempt_count + 1,
                    last_attempted_at = NOW(), quarantined_at = NOW(),
                    error_code = VALUES(error_code), error_summary = VALUES(error_summary), updated_at = NOW()',
                [
                    'legacy_website_id' => $legacyWebsiteId,
                    'status' => 'quarantined',
                    'source_hash' => $sourceHash,
                    'error_code' => substr($errorCode, 0, 64),
                    'error_summary' => self::boundedSummary($summary),
                ]
            );
            $connection->commit();
            return ['recorded' => true, 'status' => 'recorded'];
        } catch (Throwable $quarantineFailure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            return [
                'recorded' => false,
                'status' => 'persistence_failed',
                'failure' => [
                    'quarantine_failure_class' => get_class($quarantineFailure),
                    'quarantine_failure_code' => substr((string) $quarantineFailure->getCode(), 0, 32),
                ],
            ];
        }
    }

    private static function lockMapping(int $legacyWebsiteId): ?array
    {
        return self::fetchOne(
            '/* legacy-import:lock-mapping */ SELECT * FROM legacy_site_mappings
             WHERE legacy_website_id = :legacy_website_id LIMIT 1 FOR UPDATE',
            ['legacy_website_id' => $legacyWebsiteId]
        );
    }

    private static function insertPendingMapping(int $legacyWebsiteId, string $sourceHash): int
    {
        self::execute(
            '/* legacy-import:insert-mapping */ INSERT INTO legacy_site_mappings
                (legacy_website_id, import_status, source_hash, attempt_count, last_attempted_at, created_at, updated_at)
             VALUES (:legacy_website_id, :status, :source_hash, 1, NOW(), NOW(), NOW())',
            ['legacy_website_id' => $legacyWebsiteId, 'status' => 'pending', 'source_hash' => $sourceHash]
        );
        return (int) Database::connection()->lastInsertId();
    }

    private static function retryPendingMapping(int $mappingId, string $sourceHash): int
    {
        self::execute(
            '/* legacy-import:retry-mapping */ UPDATE legacy_site_mappings
             SET import_status = :status, source_hash = :source_hash,
                 attempt_count = attempt_count + 1, last_attempted_at = NOW(),
                 quarantined_at = NULL, error_code = NULL, error_summary = NULL, updated_at = NOW()
             WHERE id = :mapping_id AND site_id IS NULL',
            ['status' => 'pending', 'source_hash' => $sourceHash, 'mapping_id' => $mappingId]
        );
        return $mappingId;
    }

    private static function bindPendingMapping(int $mappingId, int $siteId, int $revisionId): void
    {
        $statement = Database::connection()->prepare(
            '/* legacy-import:bind-mapping */ UPDATE legacy_site_mappings
             SET site_id = :site_id, import_revision_id = :revision_id, updated_at = NOW()
             WHERE id = :mapping_id
               AND site_id IS NULL
               AND import_revision_id IS NULL
               AND import_status = :pending_status'
        );
        $statement->execute([
            'site_id' => $siteId,
            'revision_id' => $revisionId,
            'mapping_id' => $mappingId,
            'pending_status' => 'pending',
        ]);
        if ($statement->rowCount() !== 1) {
            throw new LegacyWebsiteImportException('mapping_collision', 'The pending legacy mapping could not be bound to its imported site and revision.');
        }
    }

    private static function completePendingMapping(
        int $mappingId,
        int $siteId,
        int $revisionId,
        string $sourceHash,
        string $importedHash
    ): void {
        $statement = Database::connection()->prepare(
            '/* legacy-import:complete-mapping */ UPDATE legacy_site_mappings
             SET import_status = :imported_status,
                 source_hash = :source_hash, imported_hash = :imported_hash,
                 imported_at = NOW(), quarantined_at = NULL, error_code = NULL, error_summary = NULL,
                 updated_at = NOW()
             WHERE id = :mapping_id
               AND site_id = :site_id
               AND import_revision_id = :revision_id
               AND import_status = :pending_status'
        );
        $statement->execute([
            'imported_status' => 'imported',
            'source_hash' => $sourceHash,
            'imported_hash' => $importedHash,
            'mapping_id' => $mappingId,
            'site_id' => $siteId,
            'revision_id' => $revisionId,
            'pending_status' => 'pending',
        ]);
        if ($statement->rowCount() !== 1) {
            throw new LegacyWebsiteImportException('mapping_collision', 'The bound legacy mapping could not be finalized for its imported site and revision.');
        }
    }

    private static function mappingForWebsite(int $legacyWebsiteId): ?array
    {
        return self::fetchOne(
            '/* legacy-import:read-mapping */ SELECT * FROM legacy_site_mappings WHERE legacy_website_id = :legacy_website_id LIMIT 1',
            ['legacy_website_id' => $legacyWebsiteId]
        );
    }

    private static function importedPageCount(int $revisionId): int
    {
        $row = self::fetchOne(
            '/* legacy-import:page-count */ SELECT COUNT(*) AS page_count FROM site_revision_pages WHERE revision_id = :revision_id',
            ['revision_id' => $revisionId]
        );
        return (int) ($row['page_count'] ?? 0);
    }

    private static function databaseSourcePayload(array $source): array
    {
        return [
            'website' => $source['website'],
            'pages' => array_map(static function (array $page): array {
                unset($page['decoded_content'], $page['imported_sort_order']);
                return $page;
            }, $source['pages']),
            'branding' => $source['branding'],
            'overrides' => $source['overrides'],
            'service_images' => $source['service_images'],
            'integrations' => $source['integrations'],
            'configuration' => $source['configuration'],
            'business_content_reference' => $source['business_content'],
            'service_page_references' => $source['service_pages'],
            'business_profile_reference' => $source['business_profile'],
            'selected_service_references' => $source['selected_services'],
            'custom_service_references' => $source['custom_services'],
        ];
    }

    private static function sourceHashPayload(array $source, array $assetEvidence = []): array
    {
        return [
            'database_source' => self::databaseSourcePayload($source),
            'asset_evidence' => array_map(static fn (array $asset): array => [
                'normalized_path' => (string) $asset['normalized_path'],
                'checksum_sha256' => (string) $asset['checksum_sha256'],
                'byte_size' => (int) $asset['byte_size'],
                'mime_type' => (string) $asset['mime_type'],
                'asset_type' => (string) $asset['asset_type'],
                'usage_key' => (string) $asset['usage_key'],
                'legacy_page_id' => $asset['legacy_page_id'] === null ? null : (int) $asset['legacy_page_id'],
            ], $assetEvidence),
        ];
    }

    private static function revisionRepresentationFromSource(
        array $source,
        array $assetEvidence,
        array $factsReferences,
        array $sourceReferences,
        array $brief,
        string $briefHash
    ): array {
        $pageKeys = [];
        $pages = [];
        foreach ($source['pages'] as $page) {
            $pageKey = self::logicalPageKey((string) $page['page_type'], (string) $page['normalized_slug']);
            $pageKeys[(int) $page['id']] = $pageKey;
            $contentHash = self::hashValue($page['decoded_content']);
            $pages[] = [
                'page_key' => $pageKey,
                'title' => (string) $page['title'],
                'slug' => (string) $page['normalized_slug'],
                'page_type' => (string) $page['page_type'],
                'navigation_label' => (string) $page['title'],
                'sort_order' => (int) $page['imported_sort_order'],
                'seo' => null,
                'presentation' => [
                    'legacy_page_id' => (int) $page['id'],
                    'legacy_status' => (string) $page['status'],
                    'legacy_sort_order' => (int) $page['sort_order'],
                    'snapshot_only' => true,
                ],
                'content_hash' => $contentHash,
                'sections' => [[
                    'section_key' => 'legacy-page-snapshot',
                    'component_key' => self::COMPONENT_KEY,
                    'implementation_version' => self::COMPONENT_IMPLEMENTATION_VERSION,
                    'variant_key' => (string) $page['page_type'],
                    'variant_configuration_schema_version' => 1,
                    'sort_order' => 10,
                    'configuration_schema_version' => 1,
                    'configuration' => $page['decoded_content'],
                    'content_hash' => $contentHash,
                ]],
            ];
        }

        $themeSnapshot = self::themeSnapshot($source['branding']);
        $assets = array_map(static function (array $asset) use ($pageKeys): array {
            $legacyPageId = $asset['legacy_page_id'] === null ? null : (int) $asset['legacy_page_id'];
            return [
                'asset_type' => (string) $asset['asset_type'],
                'storage_key' => (string) $asset['normalized_path'],
                'checksum_sha256' => (string) $asset['checksum_sha256'],
                'mime_type' => (string) $asset['mime_type'],
                'byte_size' => (int) $asset['byte_size'],
                'usage_key' => (string) $asset['usage_key'],
                'source_reference' => (string) $asset['normalized_path'],
                'page_key' => $legacyPageId === null ? null : ($pageKeys[$legacyPageId] ?? null),
                'section_key' => $legacyPageId === null ? null : 'legacy-page-snapshot',
            ];
        }, $assetEvidence);

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'facts_snapshot' => $factsReferences,
            'source_references' => $sourceReferences,
            'generation_brief' => [
                'brief_version' => 1,
                'state' => 'imported',
                'brief' => $brief,
                'source_type' => 'legacy_247sp',
                'source_reference' => '247sp_generated_websites:' . (int) $source['website']['id'],
                'content_hash' => $briefHash,
            ],
            'pages' => $pages,
            'theme' => [
                'theme_key' => 'legacy_247sp_starter',
                'theme_version' => 1,
                'primary_color' => $themeSnapshot['primary_color'],
                'secondary_color' => $themeSnapshot['secondary_color'],
                'typography' => null,
                'configuration' => $themeSnapshot['configuration'],
                'content_hash' => self::hashValue($themeSnapshot),
            ],
            'assets' => $assets,
        ];
    }

    private static function sourceReferences(array $source): array
    {
        return [
            'legacy_website_id' => (int) $source['website']['id'],
            'onboarding_id' => (int) $source['website']['onboarding_id'],
            'template_id' => (int) $source['website']['template_id'],
            'configuration_id' => isset($source['configuration']['id']) ? (int) $source['configuration']['id'] : null,
            'business_content_id' => isset($source['business_content']['id']) ? (int) $source['business_content']['id'] : null,
            'branding_id' => isset($source['branding']['id']) ? (int) $source['branding']['id'] : null,
            'integration_id' => isset($source['integrations']['id']) ? (int) $source['integrations']['id'] : null,
            'legacy_page_ids' => array_map('intval', array_column($source['pages'], 'id')),
            'service_page_ids' => array_map('intval', array_column($source['service_pages'], 'id')),
            'override_ids' => array_map('intval', array_column($source['overrides'], 'id')),
            'service_image_ids' => array_map('intval', array_column($source['service_images'], 'id')),
        ];
    }

    private static function themeSnapshot(?array $branding): array
    {
        $branding ??= [];
        $primary = self::nullableColor($branding['primary_color'] ?? null);
        $secondary = self::nullableColor($branding['secondary_color'] ?? null);
        return [
            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'configuration' => [
                'logo_path' => self::nullableString($branding['logo_path'] ?? null),
                'hero_image_path' => self::nullableString($branding['hero_image_path'] ?? null),
                'about_image_path' => self::nullableString($branding['about_image_path'] ?? null),
                'source' => 'legacy_247sp_branding',
            ],
        ];
    }

    private static function pathsInValue(mixed $value, string $prefix): array
    {
        $paths = [];
        $walk = static function (mixed $current, string $keyPath) use (&$walk, &$paths): void {
            if (!is_array($current)) {
                return;
            }
            foreach ($current as $key => $item) {
                $next = $keyPath . '-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $key));
                if (is_array($item)) {
                    $walk($item, $next);
                    continue;
                }
                if (is_string($item) && $item !== '' && preg_match('/(?:_path|^path)$/', (string) $key) === 1) {
                    $paths[] = ['path' => $item, 'usage_key' => trim($next, '-')];
                }
            }
        };
        $walk($value, $prefix);
        return $paths;
    }

    private static function containsExecutableMarker(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsExecutableMarker($item)) {
                    return true;
                }
            }
            return false;
        }
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/<\?(?:php|=)|<script\b|javascript\s*:|on(?:load|error|click)\s*=/i', $value) === 1;
    }

    private static function logicalPageKey(string $type, string $slug): string
    {
        $base = 'legacy-' . $type . '-' . $slug;
        return strlen($base) <= 100 ? $base : substr($base, 0, 83) . '-' . substr(hash('sha256', $base), 0, 16);
    }

    private static function deterministicKey(string $namespace, string $value): string
    {
        $hex = substr(hash('sha256', $namespace . ':' . $value), 0, 32);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    private static function hashValue(mixed $value): string
    {
        return CanonicalJson::hash($value);
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function decodeNullableJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function nullableColor(mixed $value): ?string
    {
        $color = strtoupper(trim((string) $value));
        if ($color === '') {
            return null;
        }
        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            throw new LegacyWebsiteImportException('invalid_theme_color', 'Legacy branding contains an invalid theme color.');
        }
        return $color;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private static function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }
        $count = preg_match_all('/./us', $value);
        return $count === false ? strlen($value) : $count;
    }

    private static function boundedSummary(string $summary): string
    {
        $summary = preg_replace('/\s+/', ' ', trim($summary)) ?? 'Import failed.';
        return function_exists('mb_substr') ? mb_substr($summary, 0, 500) : substr($summary, 0, 500);
    }

    private static function fetchOne(string $sql, array $params): ?array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private static function fetchAll(string $sql, array $params): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private static function execute(string $sql, array $params): void
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
    }
}
