<?php

declare(strict_types=1);
require_once __DIR__ . '/SitePublisher.php';
require_once __DIR__ . '/SiteServiceSupport.php';
require_once __DIR__ . '/ApacheDigitalOceanSitePublisher.php';

/** Hard-coded provider only. Host bindings and dependencies are resolved before SQL work. */
final class SitePublisherFactory
{
    public static function forTarget(array $target): SitePublisher
    {
        if(!class_exists(SiteDeploymentRuntime::class,false))SitePublisherHostContract::deny();
        return SiteDeploymentRuntime::approvedPublisher($target);
    }
}
