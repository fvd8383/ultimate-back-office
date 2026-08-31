<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/classes/LegacyWebsitePlatformImporter.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function legacyImportOption(array $options, string $key, int $default): int
{
    if (!array_key_exists($key, $options)) {
        return $default;
    }
    $value = filter_var($options[$key], FILTER_VALIDATE_INT);
    if ($value === false) {
        throw new InvalidArgumentException("--{$key} must be an integer.");
    }
    return (int) $value;
}

try {
    $options = getopt('', ['batch::', 'after-id::', 'website-id::', 'report', 'hash-limit::']);
    if ($options === false) {
        throw new InvalidArgumentException('Command options could not be parsed.');
    }

    if (array_key_exists('report', $options)) {
        $result = LegacyWebsitePlatformImporter::reconciliationReport(
            legacyImportOption($options, 'hash-limit', 100)
        );
    } elseif (array_key_exists('website-id', $options)) {
        $result = LegacyWebsitePlatformImporter::compareWebsite(
            legacyImportOption($options, 'website-id', 0)
        );
    } else {
        $result = LegacyWebsitePlatformImporter::importBatch(
            legacyImportOption($options, 'batch', 25),
            legacyImportOption($options, 'after-id', 0)
        );
    }

    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
