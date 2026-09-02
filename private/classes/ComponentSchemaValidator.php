<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteServiceSupport.php';
require_once __DIR__ . '/CanonicalJson.php';

final class ComponentSchemaValidator
{
    private const UNSAFE_TEXT = '/<\?(?:php|=)|<\s*script\b|javascript\s*:|\bon(?:click|error|load)\s*=/i';

    public static function validate(mixed $value, array $schema, string $path = 'configuration'): mixed
    {
        if ($value === null && ($schema['nullable'] ?? false) === true) {
            return null;
        }
        $type = (string) ($schema['type'] ?? '');
        return match ($type) {
            'object' => self::validateObject($value, $schema, $path),
            'array' => self::validateArray($value, $schema, $path),
            'string' => self::validateString($value, $schema, $path),
            'integer' => self::validateInteger($value, $schema, $path),
            'number' => self::validateNumber($value, $schema, $path),
            'boolean' => self::validateBoolean($value, $path),
            'null' => self::validateNull($value, $path),
            default => throw new LogicException("Unsupported repository schema type at {$path}."),
        };
    }

    private static function validateObject(mixed $value, array $schema, string $path): array
    {
        if (!is_array($value) || array_is_list($value)) {
            self::invalid($path, 'must be an object');
        }
        if (($schema['passthrough'] ?? false) === true) {
            return $value;
        }
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $value)) {
                self::invalid("{$path}.{$field}", 'is required');
            }
        }
        foreach ($value as $field => $item) {
            if (!is_string($field) || !array_key_exists($field, $properties)) {
                self::invalid($path, 'contains an unknown field');
            }
            $value[$field] = self::validate($item, $properties[$field], "{$path}.{$field}");
        }
        ksort($value, SORT_STRING);
        return $value;
    }

    private static function validateArray(mixed $value, array $schema, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            self::invalid($path, 'must be an array');
        }
        $count = count($value);
        if ($count < (int) ($schema['minItems'] ?? 0) || $count > (int) ($schema['maxItems'] ?? PHP_INT_MAX)) {
            self::invalid($path, 'has an invalid number of items');
        }
        $normalized = [];
        foreach ($value as $index => $item) {
            $normalized[] = self::validate($item, $schema['items'], "{$path}[{$index}]");
        }
        if (($schema['uniqueItems'] ?? false) === true) {
            $encoded = array_map([CanonicalJson::class, 'encode'], $normalized);
            if (count($encoded) !== count(array_unique($encoded))) {
                self::invalid($path, 'must contain unique items');
            }
        }
        return $normalized;
    }

    private static function validateString(mixed $value, array $schema, string $path): string
    {
        if (!is_string($value)) {
            self::invalid($path, 'must be a string');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length < (int) ($schema['minLength'] ?? 0) || $length > (int) ($schema['maxLength'] ?? PHP_INT_MAX)) {
            self::invalid($path, 'has an invalid length');
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            self::invalid($path, 'has an invalid value');
        }
        if (($schema['plainText'] ?? true) && preg_match(self::UNSAFE_TEXT, $value) === 1) {
            self::invalid($path, 'contains executable or markup-like content');
        }
        $format = $schema['format'] ?? null;
        $valid = match ($format) {
            null => true,
            'token' => preg_match('/^[a-z][a-z0-9_-]{0,99}$/', $value) === 1,
            'asset-usage-key' => preg_match('/^[a-z][a-z0-9._-]{0,99}$/', $value) === 1,
            'relative-path' => self::isRelativePath($value),
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1,
            'slug' => preg_match('/^(?:[a-z0-9]+(?:-[a-z0-9]+)*)?$/', $value) === 1,
            default => throw new LogicException("Unsupported repository schema format at {$path}."),
        };
        if (!$valid) {
            self::invalid($path, 'has an invalid format');
        }
        return $value;
    }

    private static function validateInteger(mixed $value, array $schema, string $path): int
    {
        if (!is_int($value)) {
            self::invalid($path, 'must be an integer');
        }
        self::validateRange($value, $schema, $path);
        return $value;
    }

    private static function validateNumber(mixed $value, array $schema, string $path): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            self::invalid($path, 'must be a number');
        }
        self::validateRange($value, $schema, $path);
        return $value;
    }

    private static function validateBoolean(mixed $value, string $path): bool
    {
        if (!is_bool($value)) {
            self::invalid($path, 'must be a boolean');
        }
        return $value;
    }

    private static function validateNull(mixed $value, string $path): null
    {
        if ($value !== null) {
            self::invalid($path, 'must be null');
        }
        return null;
    }

    private static function validateRange(int|float $value, array $schema, string $path): void
    {
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            self::invalid($path, 'is below the minimum');
        }
        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            self::invalid($path, 'is above the maximum');
        }
    }

    private static function isRelativePath(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255
            && preg_match('#^(?![a-z][a-z0-9+.-]*:|//|/)(?!.*(?:^|/)\.\.(?:/|$))[a-z0-9][a-z0-9/_-]*(?:\.[a-z0-9]+)?$#i', $value) === 1;
    }

    private static function invalid(string $path, string $reason): never
    {
        throw new SiteServiceException('invalid_request', "{$path} {$reason}.");
    }
}
