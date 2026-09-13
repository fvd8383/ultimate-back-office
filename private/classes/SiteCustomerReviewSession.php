<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteServiceSupport.php';

/** Only opaque handles and action-bound nonces leave the PHP session. */
final class SiteCustomerReviewSession
{
    public const ACTIONS = ['feedback', 'presentation_preference', 'image_replacement_request', 'request_changes', 'approve_revision'];

    public static function issue(array $context): array
    {
        self::prune();
        while (count($_SESSION['customer_reviews'] ?? []) >= 20) array_shift($_SESSION['customer_reviews']);
        $handle = bin2hex(random_bytes(32));
        $nonces = [];
        foreach (self::ACTIONS as $action) $nonces[$action] = bin2hex(random_bytes(32));
        $_SESSION['customer_reviews'][$handle] = ['context' => $context, 'expires' => time() + 7200, 'nonces' => $nonces];
        return ['handle' => $handle, 'nonces' => $nonces];
    }

    public static function resolve(mixed $handle, mixed $nonce, string $action, int $actorId): array
    {
        self::prune();
        if (!is_string($handle) || !is_string($nonce) || !in_array($action, self::ACTIONS, true)
            || preg_match('/^[a-f0-9]{64}$/D', $handle) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $nonce) !== 1) self::stale();
        $record = $_SESSION['customer_reviews'][$handle] ?? null;
        if (!is_array($record) || ($record['context']['actor_user_id'] ?? null) !== $actorId
            || !hash_equals($record['nonces'][$action] ?? '', $nonce)) self::stale();
        return ['context' => $record['context'], 'submission_key_hash' => hash('sha256', $handle . ':' . $action . ':' . $nonce)];
    }

    private static function prune(): void
    {
        foreach ($_SESSION['customer_reviews'] ?? [] as $handle => $record) {
            if (!is_array($record) || ($record['expires'] ?? 0) <= time()) unset($_SESSION['customer_reviews'][$handle]);
        }
    }

    private static function stale(): never
    {
        throw new SiteServiceException('conflict', 'This review form has expired or changed. Reload and review before submitting again.');
    }
}
