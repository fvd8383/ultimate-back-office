<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/classes/ComponentRegistry.php';
require_once __DIR__ . '/../private/classes/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $report = ComponentRegistry::verifyDatabase(Database::connection());
    fwrite(STDOUT, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($report['pass'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: component registry verification failed (' . get_class($exception) . ').' . PHP_EOL);
    exit(1);
}
