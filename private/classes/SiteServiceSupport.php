<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class SiteServiceException extends RuntimeException
{
    private const CLASSIFICATIONS = [
        'invalid_request',
        'not_found',
        'unauthorized',
        'invalid_transition',
        'immutable_revision',
        'stale_write',
        'conflict',
        'future_gate_required',
        'database_failure',
    ];

    public function __construct(private string $classification, string $message)
    {
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            $classification = 'database_failure';
            $message = 'The website operation could not be completed.';
        }
        $this->classification = $classification;
        parent::__construct($message);
    }

    public function classification(): string
    {
        return $this->classification;
    }
}

final class SiteServiceSupport
{
    public static function correlationId(?string $correlationId): string
    {
        $correlationId = trim((string) $correlationId);
        if ($correlationId === '') {
            return 'site:' . bin2hex(random_bytes(16));
        }
        if (strlen($correlationId) > 100 || preg_match('/^[A-Za-z0-9._:-]+$/', $correlationId) !== 1) {
            throw new SiteServiceException('invalid_request', 'The correlation identifier is invalid.');
        }
        return $correlationId;
    }

    public static function positiveId(int $value, string $label): int
    {
        if ($value < 1) {
            throw new SiteServiceException('invalid_request', "{$label} must be a positive identifier.");
        }
        return $value;
    }

    public static function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $reason)) {
            throw new SiteServiceException('invalid_request', 'A safe reason of 1-500 characters is required.');
        }
        return $reason;
    }

    public static function optionalComment(?string $comment): ?string
    {
        if ($comment === null || trim($comment) === '') {
            return null;
        }
        $comment = trim($comment);
        if (strlen($comment) > 5000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $comment)) {
            throw new SiteServiceException('invalid_request', 'The comment is invalid.');
        }
        return $comment;
    }

    public static function snapshotJson(mixed $value, string $label): string
    {
        if (!is_array($value)) {
            throw new SiteServiceException('invalid_request', "{$label} must be array or object data.");
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new SiteServiceException('invalid_request', "{$label} is not valid JSON data.");
        }
    }

    public static function metadata(array $metadata): string
    {
        try {
            return json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new SiteServiceException('invalid_request', 'Audit metadata is invalid.');
        }
    }

    public static function assertSnapshotHash(string $hash): string
    {
        $hash = strtolower(trim($hash));
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new SiteServiceException('invalid_request', 'The snapshot hash must be a SHA-256 hexadecimal value.');
        }
        return $hash;
    }

    public static function transaction(callable $callback): mixed
    {
        $connection = Database::connection();
        if ($connection->inTransaction()) {
            throw new SiteServiceException('conflict', 'A website mutation cannot join an existing transaction.');
        }

        try {
            $connection->beginTransaction();
            $result = $callback($connection);
            $connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            if ($exception instanceof SiteServiceException) {
                throw $exception;
            }
            error_log('Website M2 database operation failed: ' . get_class($exception));
            throw new SiteServiceException('database_failure', 'The website operation could not be completed.');
        }
    }

    public static function read(callable $callback): mixed
    {
        try {
            return $callback(Database::connection());
        } catch (SiteServiceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            error_log('Website M2 database read failed: ' . get_class($exception));
            throw new SiteServiceException('database_failure', 'The website information could not be loaded.');
        }
    }

    public static function event(
        object $connection,
        int $siteId,
        ?int $revisionId,
        array $actor,
        string $eventType,
        string $correlationId,
        ?string $reason = null,
        array $metadata = [],
        string $result = 'success'
    ): void {
        $statement = $connection->prepare(
            'INSERT INTO site_events (
                site_id, revision_id, actor_user_id, actor_type, event_type,
                result, reason, correlation_id, metadata_json, created_at
             ) VALUES (
                :site_id, :revision_id, :actor_user_id, :actor_type, :event_type,
                :result, :reason, :correlation_id, :metadata_json, NOW()
             )'
        );
        $statement->execute([
            'site_id' => $siteId,
            'revision_id' => $revisionId,
            'actor_user_id' => (int) $actor['acting_user_id'],
            'actor_type' => (string) $actor['actor_type'],
            'event_type' => $eventType,
            'result' => $result,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'metadata_json' => self::metadata($metadata),
        ]);
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
