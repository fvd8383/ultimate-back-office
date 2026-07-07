<?php

interface RegistrarInterface
{
    public function checkAvailability(string $domainName): array;

    public function registerDomain(string $domainName, array $contact, array $options = []): array;

    public function transferDomain(string $domainName, array $transferDetails = []): array;

    public function getDomain(string $domainName): array;

    public function getDnsRecords(string $domainName): array;

    public function setDnsRecords(string $domainName, array $records): array;

    public function verifyOwnership(string $domainName): array;

    public function enableAutoRenew(string $domainName): array;

    public function disableAutoRenew(string $domainName): array;

    public function renewDomain(string $domainName, int $years = 1): array;

    public function getStatus(string $domainName): array;
}
