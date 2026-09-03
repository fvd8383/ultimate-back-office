<?php

declare(strict_types=1);

require_once __DIR__ . '/CanonicalJson.php';
require_once __DIR__ . '/SiteManager.php';

final class SiteGenerationBriefManager
{
    public const SOURCE_TYPE = 'admin_manual';
    public const AUTHORED_STATE = 'authored';
    public const FIELD_LIMITS = [
        'summary' => 2000,
        'target_audience' => 1000,
        'tone_notes' => 1000,
        'design_notes' => 2000,
        'conversion_notes' => 2000,
        'page_notes' => 4000,
    ];

    public static function createBrief(
        int $actingUserId,
        int $siteId,
        array $input,
        ?string $correlationId = null
    ): array {
        $actor = SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        $brief = self::validateBrief($input);
        $briefJson = CanonicalJson::encode($brief);
        $contentHash = CanonicalJson::hash($brief);
        $correlationId = SiteServiceSupport::correlationId($correlationId);

        return SiteServiceSupport::transaction(static function (object $connection) use (
            $actingUserId, $actor, $siteId, $briefJson, $contentHash, $correlationId
        ): array {
            $site = SiteManager::lockSite($connection, $siteId);
            SiteServiceSupport::assertSiteOperational($site);

            $duplicate = $connection->prepare(
                'SELECT id FROM site_generation_briefs
                 WHERE site_id = :site_id AND content_hash = :content_hash LIMIT 1 FOR UPDATE'
            );
            $duplicate->execute(['site_id' => $siteId, 'content_hash' => $contentHash]);
            if ($duplicate->fetchColumn() !== false) {
                throw new SiteServiceException('conflict', 'An identical generation brief already exists for this site.');
            }

            $version = $connection->prepare(
                'SELECT COALESCE(MAX(brief_version), 0) + 1
                 FROM site_generation_briefs WHERE site_id = :site_id'
            );
            $version->execute(['site_id' => $siteId]);
            $briefVersion = (int) $version->fetchColumn();

            $insert = $connection->prepare(
                'INSERT INTO site_generation_briefs (
                    site_id, brief_version, state, brief_json, source_type,
                    source_reference, created_by_user_id, superseded_at, content_hash, created_at
                 ) VALUES (
                    :site_id, :brief_version, :state, :brief_json, :source_type,
                    NULL, :actor_id, NULL, :content_hash, NOW()
                 )'
            );
            $insert->execute([
                'site_id' => $siteId,
                'brief_version' => $briefVersion,
                'state' => self::AUTHORED_STATE,
                'brief_json' => $briefJson,
                'source_type' => self::SOURCE_TYPE,
                'actor_id' => $actingUserId,
                'content_hash' => $contentHash,
            ]);
            $briefId = (int) $connection->lastInsertId();

            SiteServiceSupport::event(
                $connection,
                $siteId,
                null,
                $actor,
                'site_generation_brief_created',
                $correlationId,
                null,
                ['brief_id' => $briefId, 'brief_version' => $briefVersion, 'source_type' => self::SOURCE_TYPE]
            );

            return [
                'brief_id' => $briefId,
                'site_id' => $siteId,
                'brief_version' => $briefVersion,
                'state' => self::AUTHORED_STATE,
                'source_type' => self::SOURCE_TYPE,
                'content_hash' => $contentHash,
                'correlation_id' => $correlationId,
            ];
        });
    }

    public static function briefForActor(int $actingUserId, int $briefId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($briefId, 'Generation brief ID');
        return SiteServiceSupport::read(static function (object $connection) use ($briefId): array {
            $statement = $connection->prepare(
                'SELECT id, site_id, brief_version, state, brief_json, source_type,
                        source_reference, superseded_at, content_hash, created_at
                 FROM site_generation_briefs WHERE id = :brief_id LIMIT 1'
            );
            $statement->execute(['brief_id' => $briefId]);
            $row = $statement->fetch();
            if (!$row) {
                throw new SiteServiceException('not_found', 'The generation brief was not found.');
            }
            return self::safeRow($row);
        });
    }

    public static function briefsForSite(int $actingUserId, int $siteId): array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): array {
            $site = $connection->prepare('SELECT id FROM sites WHERE id = :site_id LIMIT 1');
            $site->execute(['site_id' => $siteId]);
            if ($site->fetchColumn() === false) {
                throw new SiteServiceException('not_found', 'The site was not found.');
            }
            $statement = $connection->prepare(
                'SELECT id, site_id, brief_version, state, brief_json, source_type,
                        source_reference, superseded_at, content_hash, created_at
                 FROM site_generation_briefs
                 WHERE site_id = :site_id ORDER BY brief_version DESC, id DESC'
            );
            $statement->execute(['site_id' => $siteId]);
            return array_map([self::class, 'safeRow'], $statement->fetchAll());
        });
    }

    public static function latestCurrentBrief(int $actingUserId, int $siteId): ?array
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
        SiteServiceSupport::positiveId($siteId, 'Site ID');
        return SiteServiceSupport::read(static function (object $connection) use ($siteId): ?array {
            $statement = $connection->prepare(
                'SELECT id, site_id, brief_version, state, brief_json, source_type,
                        source_reference, superseded_at, content_hash, created_at
                 FROM site_generation_briefs
                 WHERE site_id = :site_id AND source_type = :source_type
                 ORDER BY brief_version DESC LIMIT 1'
            );
            $statement->execute([
                'site_id' => $siteId,
                'source_type' => self::SOURCE_TYPE,
            ]);
            $row = $statement->fetch();
            return $row ? self::safeRow($row) : null;
        });
    }

    public static function validateBrief(array $input): array
    {
        if (array_diff(array_keys($input), array_keys(self::FIELD_LIMITS)) !== []) {
            throw new SiteServiceException('invalid_request', 'The generation brief contains unsupported fields.');
        }

        $brief = [];
        foreach (self::FIELD_LIMITS as $field => $limit) {
            $value = str_replace(["\r\n", "\r"], "\n", trim((string) ($input[$field] ?? '')));
            if (strlen($value) > $limit) {
                throw new SiteServiceException('invalid_request', self::label($field) . " must be {$limit} characters or fewer.");
            }
            if ($value !== '' && self::containsUnsafeContent($value)) {
                throw new SiteServiceException('invalid_request', self::label($field) . ' must contain plain presentation notes only.');
            }
            $brief[$field] = $value;
        }
        if ($brief['summary'] === '') {
            throw new SiteServiceException('invalid_request', 'Summary is required.');
        }
        return $brief;
    }

    private static function containsUnsafeContent(string $value): bool
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1 || strpbrk($value, '<>') !== false) {
            return true;
        }
        return preg_match(
            '~(?:<\?|\?>|javascript\s*:|data\s*:\s*text/html|\{\{|\{%|\b(?:onload|onclick|onerror)\s*=|@import\b|url\s*\(|\.\./|\.\.\\\\|(?:^|[\\\\/])templates?[\\\\/]|\.(?:php|phtml|js|css|twig)\b|\b(?:api[_ -]?key|client[_ -]?secret|password|access[_ -]?token|private[_ -]?key)\s*[:=])~i',
            $value
        ) === 1;
    }

    private static function safeRow(array $row): array
    {
        $brief = json_decode((string) $row['brief_json'], true, 512, JSON_THROW_ON_ERROR);
        return [
            'id' => (int) $row['id'],
            'site_id' => (int) $row['site_id'],
            'brief_version' => (int) $row['brief_version'],
            'state' => (string) $row['state'],
            'brief' => is_array($brief) ? $brief : [],
            'source_type' => (string) $row['source_type'],
            'source_reference' => $row['source_reference'] === null ? null : (string) $row['source_reference'],
            'superseded_at' => $row['superseded_at'],
            'content_hash' => (string) $row['content_hash'],
            'created_at' => $row['created_at'],
        ];
    }

    private static function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}
