<?php

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$migrationPath = __DIR__ . '/../database/migrations/022_247sp_pricing_cohorts.sql';
$sql = file_get_contents($migrationPath);
if (!is_string($sql)) {
    throw new RuntimeException('Migration 022 must be readable.');
}

$migrationAssertions = 0;
function assertPricingMigration(bool $condition, string $message): void
{
    global $migrationAssertions;
    $migrationAssertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tables = [
    'pricing_cohorts',
    'product_customer_sequence_counters',
    'product_customer_sequence_allocations',
    'subscription_commercial_terms',
];
$positions = [];
foreach ($tables as $table) {
    $needle = 'CREATE TABLE ' . $table;
    assertPricingMigration(substr_count($sql, $needle) === 1, "Migration must create {$table} exactly once.");
    $positions[] = strpos($sql, $needle);
}
assertPricingMigration($positions === array_values($positions) && $positions === array_values(array_unique($positions)), 'Migration tables must be created once in dependency order.');
assertPricingMigration($positions === array_values(array_filter($positions, static fn ($position): bool => $position !== false)), 'Every required table must have a valid source position.');
assertPricingMigration($positions === [...$positions] && $positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3], 'Migration table creation order must satisfy foreign-key dependencies.');

assertPricingMigration(!str_contains($sql, 'CREATE TABLE products'), 'Migration must reuse plans instead of creating a parallel product table.');
assertPricingMigration(substr_count($sql, 'REFERENCES plans (id) ON DELETE RESTRICT') >= 3, 'Product relationships must reuse plans.id with restrictive deletion.');
assertPricingMigration(!preg_match('/ON DELETE CASCADE/i', $sql), 'Historical allocation and commercial evidence must not cascade-delete.');
assertPricingMigration(substr_count($sql, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') === 4, 'Every P1 table must use the repository InnoDB/utf8mb4 convention.');
assertPricingMigration(substr_count($sql, 'DECIMAL(10,2)') === 4, 'All configured and locked money fields must use DECIMAL.');
assertPricingMigration(!preg_match('/\b(?:FLOAT|DOUBLE)\b/i', $sql), 'Migration must not use floating-point money.');

foreach ([
    'uq_pricing_cohorts_plan_key_version',
    'uq_product_sequence_allocation',
    'uq_product_sequence_subscription',
    'uq_product_sequence_signup_key',
    'uq_subscription_commercial_terms_subscription',
    'uq_subscription_commercial_terms_allocation',
] as $constraint) {
    assertPricingMigration(str_contains($sql, $constraint), "Migration must define {$constraint}.");
}

foreach ([
    'CHECK (position_start > 0)',
    'position_end IS NULL OR position_end >= position_start',
    'CHECK (setup_fee >= 0)',
    'CHECK (monthly_fee >= 0)',
    'CHECK (free_introductory_months >= 0)',
    'CHECK (next_sequence_number > 0)',
] as $check) {
    assertPricingMigration(str_contains($sql, $check), "Migration must include validation: {$check}.");
}

$seedPatterns = [
    "SELECT 'alpha' AS cohort_key, 'Alpha' AS display_name, 1 AS position_start, 5 AS position_end,\n           0.00 AS setup_fee, 79.00 AS monthly_fee, 6 AS free_introductory_months",
    "SELECT 'beta', 'Beta', 6, 10, 0.00, 97.00, 0",
    "SELECT 'founding', 'Founding', 11, 25, 100.00, 147.00, 0",
    "SELECT 'standard', 'Standard', 26, NULL, 250.00, 197.00, 0",
];
foreach ($seedPatterns as $seedPattern) {
    assertPricingMigration(str_contains($sql, $seedPattern), 'Migration must contain each exact approved cohort seed.');
}
assertPricingMigration(str_contains($sql, "WHERE p.product_key = '247sp'"), 'Cohorts must seed against the stable 247SP plan identity.');
assertPricingMigration(str_contains($sql, "SELECT id, 1, 0, NOW(), NOW()\nFROM plans\nWHERE product_key = '247sp'"), 'The 247SP counter must start at sequence 1.');
assertPricingMigration(preg_match('/^\s+stripe_recurring_price_ref VARCHAR\(255\) NULL,$/m', $sql) === 1, 'Recurring Stripe configuration must remain nullable in P1.');
assertPricingMigration(preg_match('/^\s+stripe_setup_price_ref VARCHAR\(255\) NULL,$/m', $sql) === 1, 'Setup Stripe configuration must remain nullable in P1.');
assertPricingMigration(!preg_match('/(?:secret|api_key|webhook_secret)/i', $sql), 'Migration must not store provider secrets.');

echo "Pricing cohort migration: {$migrationAssertions} assertions passed.\n";
