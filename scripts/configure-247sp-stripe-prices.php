<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/classes/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class PricingConfigurationException extends RuntimeException
{
}

function pricingConfigurationFail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function pricingConfigurationPrice(string $key): string
{
    $value = trim((string) Database::config($key, ''));
    if (preg_match('/^price_[A-Za-z0-9]+$/', $value) !== 1) {
        pricingConfigurationFail("{$key} must contain a valid Stripe Price identifier.");
    }
    return $value;
}

try {
    $mode = strtolower(trim((string) Database::config('STRIPE_MODE', '')));
    $environment = strtolower(trim((string) Database::config('APP_ENV', '')));
    if (!in_array($mode, ['test', 'live'], true)) {
        pricingConfigurationFail('STRIPE_MODE must be test or live.');
    }
    if (($environment === 'production' && $mode !== 'live')
        || ($environment !== 'production' && $mode !== 'test')
    ) {
        pricingConfigurationFail('STRIPE_MODE does not match APP_ENV.');
    }

    $prefix = 'STRIPE_' . strtoupper($mode) . '_247SP_';
    $expected = [
        'alpha' => [
            'recurring' => pricingConfigurationPrice($prefix . 'ALPHA_RECURRING_PRICE_ID'),
            'setup' => null,
        ],
        'beta' => [
            'recurring' => pricingConfigurationPrice($prefix . 'BETA_RECURRING_PRICE_ID'),
            'setup' => null,
        ],
        'founding' => [
            'recurring' => pricingConfigurationPrice($prefix . 'FOUNDING_RECURRING_PRICE_ID'),
            'setup' => pricingConfigurationPrice($prefix . 'FOUNDING_SETUP_PRICE_ID'),
        ],
        'standard' => [
            'recurring' => pricingConfigurationPrice($prefix . 'STANDARD_RECURRING_PRICE_ID'),
            'setup' => pricingConfigurationPrice($prefix . 'STANDARD_SETUP_PRICE_ID'),
        ],
    ];

    $connection = Database::connection();
    $connection->beginTransaction();

    $statement = $connection->query(
        "SELECT pc.id, pc.cohort_key, pc.version,
                pc.stripe_recurring_price_ref, pc.stripe_setup_price_ref,
                (SELECT COUNT(*)
                 FROM product_customer_sequence_allocations a
                 WHERE a.pricing_cohort_id = pc.id) AS allocation_count
         FROM pricing_cohorts pc
         INNER JOIN plans p ON p.id = pc.plan_id
         WHERE p.product_key = '247sp'
           AND pc.is_active = 1
           AND pc.effective_from <= UTC_TIMESTAMP()
           AND (pc.effective_until IS NULL OR pc.effective_until > UTC_TIMESTAMP())
         ORDER BY pc.position_start ASC
         FOR UPDATE"
    );
    $rows = $statement->fetchAll();
    $actualKeys = array_column($rows, 'cohort_key');
    if ($actualKeys !== array_keys($expected)) {
        throw new PricingConfigurationException('The active 247SP cohort keys are incomplete or ambiguous.');
    }

    $update = $connection->prepare(
        'UPDATE pricing_cohorts
         SET stripe_recurring_price_ref = :recurring_ref,
             stripe_setup_price_ref = :setup_ref,
             updated_at = NOW()
         WHERE id = :cohort_id
           AND stripe_recurring_price_ref IS NULL
           AND stripe_setup_price_ref IS NULL'
    );
    $configured = [];
    $matched = [];

    foreach ($rows as $row) {
        $key = (string) $row['cohort_key'];
        $currentRecurring = $row['stripe_recurring_price_ref'] === null
            ? null
            : trim((string) $row['stripe_recurring_price_ref']);
        $currentSetup = $row['stripe_setup_price_ref'] === null
            ? null
            : trim((string) $row['stripe_setup_price_ref']);
        $wanted = $expected[$key];

        if ($currentRecurring === $wanted['recurring'] && $currentSetup === $wanted['setup']) {
            $matched[] = $key;
            continue;
        }
        if ($currentRecurring !== null || $currentSetup !== null) {
            $consumed = (int) $row['allocation_count'] > 0;
            throw new PricingConfigurationException(
                $consumed
                    ? "Cohort {$key} version {$row['version']} has assignments; create and review a new pricing configuration version."
                    : "Cohort {$key} version {$row['version']} already has different Price references; replacement requires explicit version review."
            );
        }

        $update->execute([
            'recurring_ref' => $wanted['recurring'],
            'setup_ref' => $wanted['setup'],
            'cohort_id' => (int) $row['id'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new PricingConfigurationException("Cohort {$key} changed concurrently; no update was applied.");
        }
        $configured[] = $key;
    }

    $connection->commit();
    fwrite(STDOUT, '247SP Stripe Price configuration complete for mode ' . $mode . ".\n");
    fwrite(STDOUT, 'Configured cohort keys: ' . (count($configured) ? implode(', ', $configured) : 'none') . ".\n");
    fwrite(STDOUT, 'Already matching cohort keys: ' . (count($matched) ? implode(', ', $matched) : 'none') . ".\n");
} catch (Throwable $exception) {
    try {
        $connection = Database::connection();
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    } catch (Throwable $rollbackException) {
        // Preserve the original safe operator error.
    }
    pricingConfigurationFail(
        $exception instanceof PricingConfigurationException
            ? $exception->getMessage()
            : 'Price configuration was not changed; review the environment and server log.'
    );
}
