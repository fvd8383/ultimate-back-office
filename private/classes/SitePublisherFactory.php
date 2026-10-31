<?php

declare(strict_types=1);
require_once __DIR__ . '/SitePublisher.php';
require_once __DIR__ . '/SiteServiceSupport.php';

/** No operational adapter is approved in M6D. Never resolve a class from configuration. */
final class SitePublisherFactory
{
    public static function forTarget(array $target): SitePublisher
    {
        throw new SiteServiceException('future_gate_required', 'No approved site publisher is installed.');
    }
}
