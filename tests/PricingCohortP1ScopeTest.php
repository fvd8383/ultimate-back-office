<?php

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$scopeAssertions = 0;
function assertPricingScope(bool $condition, string $message): void
{
    global $scopeAssertions;
    $scopeAssertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$managerPath = __DIR__ . '/../private/classes/PricingCohortManager.php';
$manager = file_get_contents($managerPath);
assertPricingScope(is_string($manager), 'PricingCohortManager source must be readable.');
assertPricingScope(!preg_match('/curl_|api\.stripe\.com|StripeBilling|createCheckoutSession|handleWebhook/', $manager), 'P1 service must not call Stripe or alter Checkout/webhook behavior.');
assertPricingScope(!preg_match('/\b(?:79|97|147|197|250)(?:\.00)?\b/', $manager), 'Cohort prices must come from durable configuration, not service constants.');
assertPricingScope(substr_count($manager, 'product_customer_sequence_counters') === 2, 'Counter SQL must remain inside the authoritative service mutation boundary.');
assertPricingScope(str_contains($manager, 'SELECT plan_id, next_sequence_number, lock_version') && str_contains($manager, 'FOR UPDATE'), 'Sequence allocation must use a locking read.');
assertPricingScope(str_contains($manager, "'247sp_pricing_assigned'"), 'Service must emit the bounded canonical success activity.');

$publicRoots = [__DIR__ . '/../public/accounts', __DIR__ . '/../public/app'];
$newTables = [
    'pricing_cohorts',
    'product_customer_sequence_counters',
    'product_customer_sequence_allocations',
    'subscription_commercial_terms',
];
foreach ($publicRoots as $root) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            throw new RuntimeException('Public PHP source must be readable.');
        }
        foreach ($newTables as $table) {
            assertPricingScope(!str_contains($source, $table), 'P1 routes must not contain direct pricing-cohort SQL.');
        }
        assertPricingScope(!str_contains($source, 'PricingCohortManager::assignCompletedSignup'), 'P1 must not integrate the completed-signup route yet.');
    }
}

echo "Pricing P1 scope: {$scopeAssertions} assertions passed.\n";
