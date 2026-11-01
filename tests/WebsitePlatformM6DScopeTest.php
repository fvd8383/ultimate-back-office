<?php
declare(strict_types=1);
require_once __DIR__.'/support/WebsitePlatformM6DScopePolicy.php';
try {
    $assertions=WebsitePlatformM6DScopePolicy::verify(dirname(__DIR__));
    echo "M6D scope: $assertions assertions PASS (HEAD/index/working source boundaries).\n";
} finally { M6CNativeProcess::cleanup(); }
