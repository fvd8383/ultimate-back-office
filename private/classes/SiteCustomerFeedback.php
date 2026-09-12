<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCustomerReviewInput.php';

/** Strict append-only metadata format, shared by write and allowlisted read paths. */
final class SiteCustomerFeedback
{
    public static function metadata(?string $json): stdClass
    {
        try { $metadata = $json === null ? new stdClass() : json_decode($json, false, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException) { self::corrupt(); }
        // M2 historically encodes an empty metadata array as [].
        if ($metadata === []) $metadata = new stdClass();
        if (!$metadata instanceof stdClass) self::corrupt();
        foreach (array_keys(get_object_vars($metadata)) as $key) {
            if (str_starts_with($key, 'customer_review_') && $key !== 'customer_review_v1') self::corrupt();
        }
        if (!property_exists($metadata, 'customer_review_v1')) return $metadata;
        $namespace = $metadata->customer_review_v1;
        if (!$namespace instanceof stdClass || array_keys(get_object_vars($namespace)) !== ['entries']
            || !is_array($namespace->entries) || !array_is_list($namespace->entries)
            || count($namespace->entries) > 20 || strlen(SiteCustomerReviewInput::encode($namespace)) > 131072) self::corrupt();
        $ids = []; $keys = [];
        foreach ($namespace->entries as $entry) {
            if (!$entry instanceof stdClass) self::corrupt();
            $row = get_object_vars($entry);
            $payload = array_intersect_key($row, array_flip(['kind', 'text', 'target', 'value']));
            try { $canonical = SiteCustomerReviewInput::payload($payload); }
            catch (SiteServiceException) { self::corrupt(); }
            $expected = array_merge(array_keys($canonical), ['entry_id', 'actor_user_id', 'created_at', 'submission_key_hash', 'payload_hash', 'correlation_id']);
            if (array_diff(array_keys($row), $expected) !== [] || array_diff($expected, array_keys($row)) !== []
                || !is_int($row['actor_user_id']) || $row['actor_user_id'] < 1
                || !is_string($row['created_at']) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $row['created_at']) !== 1) self::corrupt();
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $row['created_at'], new DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $row['created_at']) self::corrupt();
            foreach (['entry_id', 'correlation_id'] as $field) {
                if (!is_string($row[$field]) || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $row[$field]) !== 1) self::corrupt();
            }
            foreach ($canonical as $field => $value) if ($payload[$field] !== $value) self::corrupt();
            foreach (['submission_key_hash', 'payload_hash'] as $field) {
                if (!is_string($row[$field]) || preg_match('/^[a-f0-9]{64}$/D', $row[$field]) !== 1) self::corrupt();
            }
            if (!hash_equals($row['payload_hash'], hash('sha256', SiteCustomerReviewInput::encode($canonical)))
                || isset($ids[$row['entry_id']]) || isset($keys[$row['submission_key_hash']])) self::corrupt();
            $ids[$row['entry_id']] = true; $keys[$row['submission_key_hash']] = true;
        }
        return $metadata;
    }

    public static function projection(?string $json, array $imageLabels = []): array
    {
        $metadata = self::metadata($json);
        return array_map(static function (object $entry) use ($imageLabels): array {
            $result = array_intersect_key(get_object_vars($entry), array_flip(['kind', 'text', 'target', 'value', 'created_at']));
            if ($entry->kind === 'image_replacement_request') $result['target'] = $imageLabels[$entry->target] ?? 'Image in the reviewed revision';
            return $result;
        }, $metadata->customer_review_v1->entries ?? []);
    }

    private static function corrupt(): never
    {
        throw new SiteServiceException('conflict', 'Customer submission history is unavailable. Reload or contact support.');
    }
}
