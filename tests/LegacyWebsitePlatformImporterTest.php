<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php';

$assertions = 0;
$tests = 0;

function assertLegacyImport(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function legacyImportTest(string $name, callable $callback): void
{
    global $tests;
    $callback();
    $tests++;
    echo "PASS {$name}\n";
}

function callLegacyPrivate(string $method, array &$arguments): mixed
{
    $reflection = new ReflectionMethod(LegacyWebsitePlatformImporter::class, $method);
    return $reflection->invokeArgs(null, $arguments);
}

function legacyPage(int $id, int $websiteId, int $businessId, string $type, string $slug, int $order, array $content): array
{
    return [
        'id' => $id,
        'website_id' => $websiteId,
        'business_id' => $businessId,
        'page_type' => $type,
        'title' => ucfirst($type),
        'slug' => $slug,
        'content_json' => json_encode($content, JSON_THROW_ON_ERROR),
        'status' => 'generated',
        'sort_order' => $order,
        'created_at' => '2026-08-30 12:00:00',
        'updated_at' => '2026-08-30 12:00:00',
    ];
}

function legacySource(int $websiteId = 1, int $businessId = 10): array
{
    return [
        'website' => [
            'id' => $websiteId,
            'business_id' => $businessId,
            'onboarding_id' => 100 + $websiteId,
            'template_id' => 1,
            'status' => 'generated',
            'generated_at' => '2026-08-30 12:00:00',
            'published_at' => null,
            'created_at' => '2026-08-30 12:00:00',
            'updated_at' => '2026-08-30 12:00:00',
            'resolved_business_id' => $businessId,
            'resolved_onboarding_id' => 100 + $websiteId,
            'onboarding_business_id' => $businessId,
            'resolved_template_id' => 1,
            'template_key' => 'starter_local_service',
        ],
        'pages' => [
            legacyPage(1000 + $websiteId, $websiteId, $businessId, 'home', 'home', 10, [
                'headline' => 'Example Business',
                'service_highlights' => [['name' => 'Repair', 'description' => 'Reliable repair']],
            ]),
            legacyPage(2000 + $websiteId, $websiteId, $businessId, 'contact', 'contact', 60, [
                'contact_heading' => 'Contact Example Business',
                'phone' => '555-0100',
            ]),
        ],
        'branding' => [
            'id' => 1,
            'business_id' => $businessId,
            'logo_path' => null,
            'primary_color' => '#3144D3',
            'secondary_color' => null,
            'hero_image_path' => null,
            'about_image_path' => null,
        ],
        'overrides' => [],
        'service_images' => [],
        'integrations' => ['id' => 1, 'business_id' => $businessId, 'ga_measurement_id' => 'G-ABCDEF12'],
        'configuration' => ['id' => 1, 'business_id' => $businessId, 'service_area_city' => 'Albany'],
        'business_content' => ['id' => 1, 'business_id' => $businessId, 'onboarding_id' => 100 + $websiteId],
        'service_pages' => [['id' => 1, 'business_id' => $businessId, 'service_number' => 1]],
        'business_profile' => ['id' => 7, 'business_id' => $businessId, 'lifecycle_status' => 'active'],
        'selected_services' => [['business_id' => $businessId, 'sub_service_id' => 3]],
        'custom_services' => [['id' => 9, 'business_id' => $businessId, 'category_id' => 2]],
    ];
}

function validateLegacySource(array &$source): void
{
    $arguments = [&$source];
    callLegacyPrivate('validateSource', $arguments);
}

function expectLegacyImportError(array $source, string $expected): void
{
    try {
        validateLegacySource($source);
    } catch (LegacyWebsiteImportException $exception) {
        assertLegacyImport($exception->importErrorCode() === $expected, "Expected {$expected}; got {$exception->importErrorCode()}.");
        return;
    }
    throw new RuntimeException("Expected import error {$expected}.");
}

function legacyHash(mixed $value): string
{
    $arguments = [$value];
    return callLegacyPrivate('hashValue', $arguments);
}

function legacyRevisionRepresentation(array $source, array $assetEvidence = []): array
{
    validateLegacySource($source);
    $facts = [
        'business_id' => (int) $source['website']['business_id'],
        'business_profile_id' => isset($source['business_profile']['id']) ? (int) $source['business_profile']['id'] : null,
        'selected_sub_service_ids' => array_map('intval', array_column($source['selected_services'], 'sub_service_id')),
        'custom_service_ids' => array_map('intval', array_column($source['custom_services'], 'id')),
        'authority' => 'references_only',
    ];
    $referenceArguments = [&$source];
    $references = callLegacyPrivate('sourceReferences', $referenceArguments);
    $brief = [
        'legacy_website_id' => (int) $source['website']['id'],
        'template_key' => 'starter_local_service',
        'page_count' => count($source['pages']),
        'source_tables' => [
            '247sp_generated_websites', '247sp_generated_pages', '247sp_website_branding',
            '247sp_website_content_overrides', '247sp_website_service_images', 'website_integrations',
        ],
        'authority' => 'legacy_presentation_snapshot_only',
    ];
    $arguments = [$source, $assetEvidence, $facts, $references, $brief, legacyHash($brief)];
    return callLegacyPrivate('revisionRepresentationFromSource', $arguments);
}

legacyImportTest('one eligible legacy site preserves meaningful presentation', static function (): void {
    $source = legacySource();
    $before = $source['pages'][0]['content_json'];
    validateLegacySource($source);
    assertLegacyImport($source['pages'][0]['normalized_slug'] === 'home', 'Home slug must normalize deterministically.');
    assertLegacyImport($source['pages'][0]['decoded_content']['headline'] === 'Example Business', 'Meaningful legacy content must be decoded without rewriting it.');
    assertLegacyImport($source['pages'][0]['content_json'] === $before, 'Legacy JSON evidence must remain unchanged.');
});

legacyImportTest('multiple eligible sites receive different deterministic identities', static function (): void {
    $first = legacySource(1, 10);
    $second = legacySource(2, 20);
    validateLegacySource($first);
    validateLegacySource($second);
    $argsA = ['legacy-site', '1'];
    $argsB = ['legacy-site', '2'];
    $keyA = callLegacyPrivate('deterministicKey', $argsA);
    $keyB = callLegacyPrivate('deterministicKey', $argsB);
    assertLegacyImport($keyA !== $keyB, 'Different legacy websites must not share a site key.');
    assertLegacyImport(strlen($keyA) === 36 && strlen($keyB) === 36, 'Deterministic keys must fit sites.site_key.');
});

legacyImportTest('missing and mismatched dependencies quarantine safely', static function (): void {
    $missingBusiness = legacySource();
    $missingBusiness['website']['resolved_business_id'] = null;
    expectLegacyImportError($missingBusiness, 'missing_business');

    $wrongOnboarding = legacySource();
    $wrongOnboarding['website']['onboarding_business_id'] = 999;
    expectLegacyImportError($wrongOnboarding, 'onboarding_mismatch');

    $wrongTemplate = legacySource();
    $wrongTemplate['website']['template_key'] = 'unknown';
    expectLegacyImportError($wrongTemplate, 'unsupported_template');

    $noPages = legacySource();
    $noPages['pages'] = [];
    expectLegacyImportError($noPages, 'missing_pages');
});

legacyImportTest('malformed and unsafe legacy content is rejected', static function (): void {
    $malformed = legacySource();
    $malformed['pages'][0]['content_json'] = '{broken';
    expectLegacyImportError($malformed, 'malformed_page_json');

    $executable = legacySource();
    $executable['pages'][0]['content_json'] = json_encode(['headline' => '<script>alert(1)</script>'], JSON_THROW_ON_ERROR);
    expectLegacyImportError($executable, 'unsafe_page_content');
});

legacyImportTest('ownership, slug, order, and variant collisions are rejected', static function (): void {
    $crossBusiness = legacySource();
    $crossBusiness['pages'][0]['business_id'] = 11;
    expectLegacyImportError($crossBusiness, 'cross_business_page');

    $slugCollision = legacySource();
    $slugCollision['pages'][1]['slug'] = 'home';
    expectLegacyImportError($slugCollision, 'page_slug_collision');

    $orderCollision = legacySource();
    $orderCollision['pages'][1]['sort_order'] = 10;
    expectLegacyImportError($orderCollision, 'page_order_collision');

    $unsupported = legacySource();
    $unsupported['pages'][0]['page_type'] = 'pricing';
    expectLegacyImportError($unsupported, 'unsupported_page_type');
});

legacyImportTest('rerun identity and source hashes are stable', static function (): void {
    $first = legacySource();
    $second = legacySource();
    validateLegacySource($first);
    validateLegacySource($second);
    $argsFirst = [&$first];
    $argsSecond = [&$second];
    $payloadFirst = callLegacyPrivate('sourceHashPayload', $argsFirst);
    $payloadSecond = callLegacyPrivate('sourceHashPayload', $argsSecond);
    assertLegacyImport(hash_equals(legacyHash($payloadFirst), legacyHash($payloadSecond)), 'Unchanged sources must have stable hashes.');

    $keyArgsA = ['legacy-site', '1'];
    $keyArgsB = ['legacy-site', '1'];
    assertLegacyImport(callLegacyPrivate('deterministicKey', $keyArgsA) === callLegacyPrivate('deterministicKey', $keyArgsB), 'Repeated imports must derive the same site key.');
});

legacyImportTest('source changes are visible to reconciliation', static function (): void {
    $original = legacySource();
    $changed = legacySource();
    $changed['pages'][0]['content_json'] = json_encode(['headline' => 'Changed'], JSON_THROW_ON_ERROR);
    validateLegacySource($original);
    validateLegacySource($changed);
    $originalArgs = [&$original];
    $changedArgs = [&$changed];
    $originalPayload = callLegacyPrivate('sourceHashPayload', $originalArgs);
    $changedPayload = callLegacyPrivate('sourceHashPayload', $changedArgs);
    assertLegacyImport(legacyHash($originalPayload) !== legacyHash($changedPayload), 'A presentation change must change the source hash.');
});

legacyImportTest('canonical hashes ignore associative insertion order but preserve list order', static function (): void {
    assertLegacyImport(legacyHash(['b' => 2, 'a' => 1]) === legacyHash(['a' => 1, 'b' => 2]), 'Associative key order must not affect hashes.');
    assertLegacyImport(legacyHash(['a', 'b']) !== legacyHash(['b', 'a']), 'Composition list order must affect hashes.');
});

legacyImportTest('canonical revision hash covers pages theme assets and stable component keys', static function (): void {
    $asset = [[
        'normalized_path' => '/assets/example.png',
        'checksum_sha256' => str_repeat('a', 64),
        'byte_size' => 123,
        'mime_type' => 'image/png',
        'asset_type' => 'image',
        'usage_key' => 'theme-logo-path',
        'legacy_page_id' => null,
    ]];
    $base = legacyRevisionRepresentation(legacySource(), $asset);
    $equivalent = legacyRevisionRepresentation(legacySource(), $asset);
    assertLegacyImport(legacyHash($base) === legacyHash($equivalent), 'Equivalent imported revisions must hash deterministically.');

    $pageMutation = legacySource();
    $pageMutation['pages'][0]['content_json'] = json_encode(['headline' => 'Mutated page'], JSON_THROW_ON_ERROR);
    assertLegacyImport(legacyHash($base) !== legacyHash(legacyRevisionRepresentation($pageMutation, $asset)), 'Page content must participate in the revision hash.');

    $themeMutation = legacySource();
    $themeMutation['branding']['primary_color'] = '#FFFFFF';
    assertLegacyImport(legacyHash($base) !== legacyHash(legacyRevisionRepresentation($themeMutation, $asset)), 'Theme content must participate in the revision hash.');

    $assetMutation = $asset;
    $assetMutation[0]['checksum_sha256'] = str_repeat('b', 64);
    assertLegacyImport(legacyHash($base) !== legacyHash(legacyRevisionRepresentation(legacySource(), $assetMutation)), 'Asset checksums must participate in the revision hash.');

    $encoded = json_encode($base, JSON_THROW_ON_ERROR);
    assertLegacyImport(str_contains($encoded, 'component_key') && str_contains($encoded, 'variant_key'), 'Stable component and variant keys must be hashed.');
    assertLegacyImport(!str_contains($encoded, 'component_variant_id'), 'Environment-local component variant IDs must not be hashed.');
});

legacyImportTest('source drift evidence includes asset bytes path size and type', static function (): void {
    $source = legacySource();
    validateLegacySource($source);
    $asset = [[
        'normalized_path' => '/assets/example.png',
        'checksum_sha256' => str_repeat('c', 64),
        'byte_size' => 456,
        'mime_type' => 'image/png',
        'asset_type' => 'image',
        'usage_key' => 'theme-logo-path',
        'legacy_page_id' => null,
    ]];
    $arguments = [&$source, $asset];
    $first = callLegacyPrivate('sourceHashPayload', $arguments);
    $changed = $asset;
    $changed[0]['checksum_sha256'] = str_repeat('d', 64);
    $changedArguments = [&$source, $changed];
    assertLegacyImport(legacyHash($first) !== legacyHash(callLegacyPrivate('sourceHashPayload', $changedArguments)), 'Same-path byte changes must alter source evidence.');
    $changed[0]['normalized_path'] = '/assets/renamed.png';
    $pathArguments = [&$source, $changed];
    assertLegacyImport(legacyHash($first) !== legacyHash(callLegacyPrivate('sourceHashPayload', $pathArguments)), 'Asset path changes must alter source evidence.');
});

legacyImportTest('asset inspection detects byte drift missing files and unchanged files', static function (): void {
    $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'ubo-m1-assets-' . bin2hex(random_bytes(12));
    $assetsDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . 'assets';
    $path = $assetsDirectory . DIRECTORY_SEPARATOR . 'example.png';
    $outsidePath = $temporaryRoot . '-outside.png';
    $escapePath = $assetsDirectory . DIRECTORY_SEPARATOR . 'escape.png';
    if (!mkdir($assetsDirectory, 0700, true)) {
        throw new RuntimeException('Could not create an isolated asset inspection root.');
    }
    try {
        if (file_put_contents($path, 'first asset bytes') === false) {
            throw new RuntimeException('Could not write the isolated asset fixture.');
        }
        $arguments = ['/assets/example.png', $temporaryRoot];
        $first = callLegacyPrivate('inspectAssetWithinRoot', $arguments);
        $sameArguments = ['/assets/example.png', $temporaryRoot];
        $same = callLegacyPrivate('inspectAssetWithinRoot', $sameArguments);
        assertLegacyImport($first === $same, 'An unchanged asset must produce identical evidence.');

        file_put_contents($path, 'second asset bytes');
        $changedArguments = ['/assets/example.png', $temporaryRoot];
        $changed = callLegacyPrivate('inspectAssetWithinRoot', $changedArguments);
        assertLegacyImport($first['checksum_sha256'] !== $changed['checksum_sha256'], 'Changed bytes at the same path must be detected.');

        file_put_contents($outsidePath, 'outside root');
        if (@symlink($outsidePath, $escapePath)) {
            try {
                $escapeArguments = ['/assets/escape.png', $temporaryRoot];
                callLegacyPrivate('inspectAssetWithinRoot', $escapeArguments);
                throw new RuntimeException('An asset resolving outside the supplied root was accepted.');
            } catch (LegacyWebsiteImportException $exception) {
                assertLegacyImport($exception->importErrorCode() === 'missing_asset', 'A resolved path outside the supplied root must be rejected.');
            }
            unlink($escapePath);
        }

        unlink($path);
        try {
            $missingArguments = ['/assets/example.png', $temporaryRoot];
            callLegacyPrivate('inspectAssetWithinRoot', $missingArguments);
        } catch (LegacyWebsiteImportException $exception) {
            assertLegacyImport($exception->importErrorCode() === 'missing_asset', 'A deleted asset must report missing_asset.');
            return;
        }
        throw new RuntimeException('A deleted asset was accepted.');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if (is_link($escapePath) || is_file($escapePath)) {
            unlink($escapePath);
        }
        if (is_file($outsidePath)) {
            unlink($outsidePath);
        }
        if (is_dir($assetsDirectory)) {
            rmdir($assetsDirectory);
        }
        if (is_dir($temporaryRoot)) {
            rmdir($temporaryRoot);
        }
    }
});

legacyImportTest('production asset inspection remains anchored to repository public app', static function (): void {
    $arguments = ['/assets/css/app.css'];
    $evidence = callLegacyPrivate('inspectAsset', $arguments);
    $expected = hash_file('sha256', __DIR__ . '/../public/app/assets/css/app.css');
    assertLegacyImport(is_string($expected) && hash_equals($expected, $evidence['checksum_sha256']), 'Production inspection must resolve beneath repository public/app.');
});

legacyImportTest('logical page identity is stable and bounded', static function (): void {
    $argsA = ['service', 'drain-cleaning'];
    $argsB = ['service', 'drain-cleaning'];
    $first = callLegacyPrivate('logicalPageKey', $argsA);
    $second = callLegacyPrivate('logicalPageKey', $argsB);
    assertLegacyImport($first === $second && $first === 'legacy-service-drain-cleaning', 'Logical page key must remain stable across revisions.');
    $longArgs = ['service', str_repeat('a', 200)];
    assertLegacyImport(strlen(callLegacyPrivate('logicalPageKey', $longArgs)) <= 100, 'Logical page key must fit the schema.');
});

legacyImportTest('authoritative facts remain references rather than imported mutable facts', static function (): void {
    $source = legacySource();
    validateLegacySource($source);
    $arguments = [&$source];
    $references = callLegacyPrivate('sourceReferences', $arguments);
    assertLegacyImport($references['business_content_id'] === 1 && $references['service_page_ids'] === [1], 'Legacy fact/presentation sources must be retained by identifier.');
    assertLegacyImport(!array_key_exists('business_description', $references) && !array_key_exists('service_name', $references), 'Source references must not become a second facts database.');
});

legacyImportTest('theme snapshots contain presentation only', static function (): void {
    $branding = legacySource()['branding'];
    $arguments = [$branding];
    $theme = callLegacyPrivate('themeSnapshot', $arguments);
    assertLegacyImport($theme['primary_color'] === '#3144D3', 'Legacy primary color must be preserved.');
    assertLegacyImport(!isset($theme['business_name']) && !isset($theme['services']), 'Theme snapshot must not copy business facts.');
});

legacyImportTest('invalid asset references quarantine before filesystem access', static function (): void {
    foreach (['https://example.com/x.png', '/../secret', '//remote/path'] as $path) {
        try {
            $arguments = [$path];
            callLegacyPrivate('inspectAsset', $arguments);
        } catch (LegacyWebsiteImportException $exception) {
            assertLegacyImport($exception->importErrorCode() === 'invalid_asset_reference', 'Unsafe assets must receive the expected quarantine code.');
            continue;
        }
        throw new RuntimeException('Unsafe asset reference was accepted.');
    }
});

legacyImportTest('bounded processing rejects oversized work before database access', static function (): void {
    try {
        LegacyWebsitePlatformImporter::importBatch(101);
    } catch (InvalidArgumentException $exception) {
        assertLegacyImport(str_contains($exception->getMessage(), '1-100'), 'Oversized batches must fail with a bounded safe error.');
        return;
    }
    throw new RuntimeException('Oversized batch was accepted.');
});

legacyImportTest('implementation declares atomic quarantine and no legacy mutation', static function (): void {
    $source = file_get_contents(__DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php');
    assertLegacyImport(is_string($source) && str_contains($source, 'if ($connection->inTransaction())') && str_contains($source, '$connection->rollBack();'), 'A failed unit must roll back.');
    assertLegacyImport(is_string($source) && str_contains($source, 'recordQuarantine'), 'A rolled-back failure must be recorded for repair.');
    assertLegacyImport(is_string($source) && !preg_match('/\b(?:UPDATE|DELETE FROM)\s+`?247sp_generated_/i', $source), 'Importer must never mutate legacy websites or pages.');
    assertLegacyImport(is_string($source) && str_contains($source, "'result' => 'reconciled'"), 'Idempotent reruns must reconcile instead of duplicating.');
});

echo "Legacy website platform importer: {$tests} tests, {$assertions} assertions passed.\n";
