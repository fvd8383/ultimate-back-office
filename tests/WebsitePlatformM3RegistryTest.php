<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/support/WebsitePlatformM3FakeDatabase.php';

$assertions = 0;
function assertM3Registry(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expectM3Registry(callable $callback, string $classification): void
{
    try {
        $callback();
    } catch (SiteServiceException $exception) {
        assertM3Registry($exception->classification() === $classification, "Expected {$classification}.");
        return;
    }
    throw new RuntimeException("Expected {$classification}.");
}

$manifest = ComponentRegistry::manifest();
assertM3Registry(count($manifest) === 16, 'Manifest must contain 15 authored definitions and one legacy definition.');
assertM3Registry(count(array_filter($manifest, static fn (array $d): bool => $d['scope'] === 'section')) === 12, 'Twelve section definitions are required.');
assertM3Registry(count(array_filter($manifest, static fn (array $d): bool => $d['scope'] === 'layout')) === 3, 'Three layout definitions are required.');
assertM3Registry(count(array_filter($manifest, static fn (array $d): bool => $d['scope'] === 'legacy')) === 1, 'One legacy compatibility definition is required.');
$variantCount = array_sum(array_map(static fn (array $d): int => count($d['variants']), $manifest));
assertM3Registry($variantCount === 22, 'Manifest must contain 22 variants including four legacy variants.');
assertM3Registry(isset($manifest['hero@1.0.0']['variants']['split_media']), 'Hero split-media must be registered.');
assertM3Registry($manifest['legacy_247sp_page@legacy-preview-v1']['authorable'] === false, 'Legacy snapshots are not authorable.');
assertM3Registry($manifest['legacy_247sp_page@legacy-preview-v1']['renderable'] === true, 'Legacy snapshots remain renderable.');

ComponentRegistry::validateManifest([
    ['component_key' => 'hero', 'implementation_version' => '1.0.0', 'scope' => 'section'],
    ['component_key' => 'hero', 'implementation_version' => '2.0.0', 'scope' => 'section'],
]);
assertM3Registry(true, 'A second immutable implementation version is structurally supported.');
try {
    ComponentRegistry::validateManifest([
        ['component_key' => 'hero', 'implementation_version' => '1.0.0', 'scope' => 'section'],
        ['component_key' => 'hero', 'implementation_version' => '1.0.0', 'scope' => 'section'],
    ]);
    throw new RuntimeException('Duplicate definition declaration was accepted.');
} catch (LogicException $exception) {
    assertM3Registry(str_contains($exception->getMessage(), 'Duplicate repository component identity'), 'Duplicate definition declarations must fail before associative overwrite.');
}
$componentFactory = new ReflectionMethod(ComponentRegistry::class, 'component');
try {
    $componentFactory->invoke(null, 'duplicate_test', 'Duplicate', 'test', 'section', 'text_block', ['same', 'same'], [], [], 1, []);
    throw new RuntimeException('Duplicate variant declaration was accepted.');
} catch (LogicException $exception) {
    assertM3Registry(str_contains($exception->getMessage(), 'Duplicate repository component variant'), 'Duplicate variant declarations must fail before associative overwrite.');
}
expectM3Registry(static fn () => ComponentRegistry::definition('unknown', '1.0.0'), 'invalid_request');
expectM3Registry(static fn () => ComponentRegistry::definition('hero', '9.0.0'), 'invalid_request');

$database = new WebsitePlatformM3FakeDatabase();
$hero = ComponentRegistry::resolve($database, 'hero', '1.0.0', 'default', 1, 'section', true);
assertM3Registry($hero['variant_id'] > 0 && $hero['renderer'] === 'hero', 'Registry must resolve DB metadata to a repository renderer.');
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'hero', '1.0.0', 'missing', 1, 'section', true), 'invalid_request');
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'site_header', '1.0.0', 'standard', 1, 'section', true), 'invalid_request');
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'hero', '1.0.0', 'default', 1, 'layout', true), 'invalid_request');
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'legacy_247sp_page', 'legacy-preview-v1', 'home', 1, 'legacy', true), 'invalid_request');
$legacy = ComponentRegistry::resolve($database, 'legacy_247sp_page', 'legacy-preview-v1', 'home', 1, 'legacy', false);
assertM3Registry($legacy['snapshot_compatibility'] === true, 'Historical legacy identity must resolve exactly.');

$report = ComponentRegistry::verifyDatabase($database);
assertM3Registry($report['pass'] === true && $report['repository_definitions'] === 16, 'Matching DB metadata must reconcile.');
unset($database->definitions['hero@1.0.0']['variants']['default']);
$report = ComponentRegistry::verifyDatabase($database);
assertM3Registry($report['pass'] === false && in_array('missing DB variant hero@1.0.0:default', $report['issues'], true), 'Missing variants must be reported.');

$database = new WebsitePlatformM3FakeDatabase();
$database->definitions['hero@1.0.0']['configuration_schema_version'] = 2;
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'hero', '1.0.0', 'default', 1, 'section', true), 'conflict');
$database = new WebsitePlatformM3FakeDatabase();
$database->definitions['hero@1.0.0']['definition_status'] = 'inactive';
expectM3Registry(static fn () => ComponentRegistry::resolve($database, 'hero', '1.0.0', 'default', 1, 'section', true), 'conflict');
$historical = ComponentRegistry::resolve($database, 'hero', '1.0.0', 'default', 1, 'section', false);
assertM3Registry($historical['renderable'] === true, 'Known inactive historical metadata may still resolve for rendering.');

$source = file_get_contents(__DIR__ . '/../private/classes/ComponentRegistry.php');
assertM3Registry(!preg_match('/call_user_func|\beval\s*\(|include\s+\$|require\s+\$/i', (string) $source), 'Registry must not provide DB-driven execution.');
assertM3Registry(!str_contains((string) $source, "['renderer']" . ' = $row'), 'DB rows must not select renderer identifiers.');

echo "Website platform M3 registry: {$assertions} assertions passed.\n";
