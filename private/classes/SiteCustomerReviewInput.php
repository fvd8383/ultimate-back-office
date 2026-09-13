<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteServiceSupport.php';

/** Plain-text and advisory-input boundary; never interprets customer content. */
final class SiteCustomerReviewInput
{
    public const KINDS = ['feedback', 'presentation_preference', 'image_replacement_request'];

    public static function text(mixed $value, bool $required = true): string
    {
        if (!is_scalar($value) || !is_string($value) && !is_numeric($value)) self::invalid();
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        if (preg_match('//u', $text) !== 1
            || preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\n", "\t"], '', $text))) self::invalid();
        $text = trim($text);
        if (($required && $text === '') || strlen($text) > 5000
            || preg_match_all('/./us', $text) > 2000
            || preg_match('/[<>]|(?:javascript|vbscript|data)\s*:/iu', $text)) self::invalid();
        return $text;
    }

    public static function payload(array $input): array
    {
        if (array_diff(array_keys($input), ['kind', 'text', 'target', 'value']) !== []) self::invalid();
        $kind = $input['kind'] ?? null;
        if (!is_string($kind) || !in_array($kind, self::KINDS, true)) self::invalid();
        $text = self::text($input['text'] ?? '', $kind !== 'presentation_preference');
        $result = ['kind' => $kind, 'text' => $text];
        if ($kind === 'feedback') {
            if (array_key_exists('target', $input) || array_key_exists('value', $input)) self::invalid();
        } elseif ($kind === 'presentation_preference') {
            $target = $input['target'] ?? null;
            $value = $input['value'] ?? null;
            $allowed = ['tone' => ['professional', 'friendly', 'concise'], 'emphasis' => ['services', 'trust', 'contact']];
            if (!is_string($target) || !isset($allowed[$target]) || !in_array($value, $allowed[$target], true)) self::invalid();
            $result += ['target' => $target, 'value' => $value];
        } else {
            $target = $input['target'] ?? null;
            if (!is_string($target) || preg_match('/^[a-f0-9]{64}$/D', $target) !== 1 || array_key_exists('value', $input)) self::invalid();
            if (preg_match('~(?:https?|ftp)://|www\.~iu', $text)) self::invalid();
            $result['target'] = $target;
        }
        return $result;
    }

    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) { self::invalid(); }
    }

    private static function invalid(): never
    {
        throw new SiteServiceException('invalid_request', 'Use plain text within the permitted limits and choose a listed option.');
    }
}
