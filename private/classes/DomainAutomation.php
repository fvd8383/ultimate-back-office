<?php

require_once __DIR__ . '/domains/DomainManager.php';

final class DomainAutomation
{
    public const STATUSES = DomainManager::STATUSES;
    public const DNS_STATUSES = DomainManager::DNS_STATUSES;
    public const SSL_STATUSES = DomainManager::SSL_STATUSES;
    public const PUBLISH_STATUSES = DomainManager::PUBLISH_STATUSES;

    public static function ensureRequestForBusiness(int $businessId, string $domainName = ''): ?array
    {
        return DomainManager::ensureRequestForBusiness($businessId, $domainName);
    }

    public static function createCustomerRequest(int $businessId, int $userId, string $domainName, string $requestType): array
    {
        return DomainManager::createCustomerRequest($businessId, $userId, $domainName, $requestType);
    }

    public static function customerDomainsForUser(int $userId): array
    {
        return DomainManager::customerDomainsForUser($userId);
    }

    public static function customerBusinessesForUser(int $userId): array
    {
        return DomainManager::customerBusinessesForUser($userId);
    }

    public static function adminDomainRequests(): array
    {
        return DomainManager::adminDomainRequests();
    }

    public static function domainEvents(int $requestId): array
    {
        return DomainManager::domainEvents($requestId);
    }

    public static function dnsRecordsForRequest(int $requestId): array
    {
        return DomainManager::dnsRecordsForRequest($requestId);
    }

    public static function currentDomainForBusiness(int $businessId): ?array
    {
        return DomainManager::currentDomainForBusiness($businessId);
    }

    public static function syncWebsiteDomainForBusiness(int $businessId): void
    {
        DomainManager::syncWebsiteDomainForBusiness($businessId);
    }

    public static function updateDomainRequest(int $requestId, int $adminUserId, string $status, array $input): void
    {
        DomainManager::updateDomainRequest($requestId, $adminUserId, $status, $input);
    }

    public static function checkAvailability(int $requestId, int $adminUserId): void
    {
        DomainManager::checkAvailability($requestId, $adminUserId);
    }

    public static function purchaseDomain(int $requestId, int $adminUserId): void
    {
        DomainManager::purchaseDomain($requestId, $adminUserId);
    }

    public static function syncDnsRecords(int $requestId, int $adminUserId): void
    {
        DomainManager::syncDnsRecords($requestId, $adminUserId);
    }

    public static function verifyDns(int $requestId, int $adminUserId): void
    {
        DomainManager::verifyDns($requestId, $adminUserId);
    }

    public static function refreshRegistrarStatus(int $requestId, int $adminUserId): void
    {
        DomainManager::refreshRegistrarStatus($requestId, $adminUserId);
    }

    public static function updateSslStatus(int $requestId, int $adminUserId, string $sslStatus): void
    {
        DomainManager::updateSslStatus($requestId, $adminUserId, $sslStatus);
    }

    public static function markLive(int $requestId, int $adminUserId): void
    {
        DomainManager::markLive($requestId, $adminUserId);
    }

    public static function statusLabel(?string $status): string
    {
        return DomainManager::statusLabel($status);
    }

    public static function nextActionForDomain(array $domain): string
    {
        return DomainManager::nextActionForDomain($domain);
    }

    public static function timingForDomain(array $domain): string
    {
        return DomainManager::timingForDomain($domain);
    }

    public static function launchReady(array $domain): bool
    {
        return DomainManager::launchReady($domain);
    }
}
