<?php

declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../private/classes/SiteGenerator.php';

$assertions = 0;
$tests = 0;

function assertSiteGenerator(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function siteGeneratorTest(string $name, callable $callback): void
{
    global $tests;
    $callback();
    $tests++;
    echo "PASS {$name}\n";
}

function buildGeneratedPages(int $serviceCount): array
{
    $services = [];
    for ($number = 1; $number <= $serviceCount; $number++) {
        $services[] = [
            'id' => 100 + $number,
            'service_number' => $number,
            'service_name' => 'Service ' . $number,
            'short_description' => 'Description ' . $number,
            'sort_order' => $number * 10,
            'slug' => 'service-' . $number,
        ];
    }

    $source = [
        'business' => [
            'business_name' => 'Example Business',
            'business_started_on' => '2020-01-01',
            'phone' => '555-0100',
            'email' => 'hello@example.test',
        ],
        'configuration' => null,
        'content' => [
            'business_description' => 'Example description',
            'years_in_business' => 6,
            'about_company' => 'About Example Business',
            'special_offer' => '',
            'financing_available' => 0,
        ],
        'service_pages' => $services,
        'branding' => [],
        'content_overrides' => [],
        'service_images' => [],
    ];

    $method = new ReflectionMethod(SiteGenerator::class, 'buildPages');
    return $method->invoke(null, $source);
}

foreach ([0, 1, 3, 4, 5] as $serviceCount) {
    siteGeneratorTest("{$serviceCount}-service page order is unique and sequential", static function () use ($serviceCount): void {
        $pages = buildGeneratedPages($serviceCount);
        $expectedTypes = array_merge(['home'], array_fill(0, $serviceCount, 'service'), ['about', 'contact']);
        $expectedOrders = range(10, ($serviceCount + 3) * 10, 10);

        assertSiteGenerator(array_column($pages, 'page_type') === $expectedTypes, 'Home, services, About, and Contact must retain their presentation sequence.');
        assertSiteGenerator(array_column($pages, 'sort_order') === $expectedOrders, 'Generated page sort orders must use the next available ordinal position.');
        assertSiteGenerator(count(array_unique(array_column($pages, 'sort_order'))) === count($pages), 'Generated page sort orders must be unique.');
        assertSiteGenerator($pages[count($pages) - 2]['page_type'] === 'about', 'About must follow every service page.');
        assertSiteGenerator($pages[count($pages) - 1]['page_type'] === 'contact', 'Contact must remain last.');
    });
}

echo "Site generator order: {$tests} tests, {$assertions} assertions passed.\n";
