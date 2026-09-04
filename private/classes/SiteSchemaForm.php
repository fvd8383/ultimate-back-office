<?php

declare(strict_types=1);

require_once __DIR__ . '/ComponentSchemaValidator.php';

/** HTML transport only; repository validation owns all content rules. */
final class SiteSchemaForm
{
    public static function assertSupported(array $schema): void
    {
        if (($schema['passthrough'] ?? false) || !in_array($schema['type'] ?? '', ['string', 'boolean', 'object', 'array'], true)) {
            throw new SiteServiceException('conflict', 'This repository schema cannot be represented by the editor.');
        }
        if ($schema['type'] === 'object') {
            foreach ($schema['properties'] ?? [] as $child) {
                self::assertSupported($child);
            }
        }
        if ($schema['type'] === 'array') {
            if (!isset($schema['maxItems']) || $schema['maxItems'] > 50) {
                throw new SiteServiceException('conflict', 'This repository array cannot be represented by the editor.');
            }
            self::assertSupported($schema['items']);
        }
    }

    public static function parse(array $schema, mixed $node): mixed
    {
        self::assertSupported($schema);
        return ComponentSchemaValidator::validate(self::decode($schema, $node), $schema);
    }

    private static function decode(array $schema, mixed $node): mixed
    {
        if (!is_array($node) || array_diff(array_keys($node), ['enabled', 'value']) !== []) {
            throw new SiteServiceException('invalid_request', 'The structured form field is invalid.');
        }
        if (($schema['nullable'] ?? false) && ($node['enabled'] ?? '') !== '1') {
            return null;
        }
        $value = $node['value'] ?? null;
        if ($schema['type'] === 'object') {
            if (!is_array($value) || array_diff(array_keys($value), array_keys($schema['properties'])) !== []) {
                throw new SiteServiceException('invalid_request', 'The structured object is invalid.');
            }
            $result = [];
            foreach ($schema['properties'] as $key => $child) {
                $required = in_array($key, $schema['required'] ?? [], true);
                if (!$required && (!isset($value[$key]) || ($value[$key]['enabled'] ?? '') !== '1')) {
                    continue;
                }
                $result[$key] = self::decode($child, $value[$key] ?? null);
            }
            return $result;
        }
        if ($schema['type'] === 'array') {
            if ($value === null) {
                return [];
            }
            if (!is_array($value) || count($value) > $schema['maxItems']) {
                throw new SiteServiceException('invalid_request', 'The structured list is invalid.');
            }
            $result = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    throw new SiteServiceException('invalid_request', 'The structured list item is invalid.');
                }
                if (($item['enabled'] ?? '') === '1') {
                    $result[] = self::decode($schema['items'], $item);
                }
            }
            return $result;
        }
        if ($schema['type'] === 'boolean') {
            return match ($value) {
                '1' => true, '0' => false,
                default => throw new SiteServiceException('invalid_request', 'Choose a boolean value.'),
            };
        }
        if (!is_string($value)) {
            throw new SiteServiceException('invalid_request', 'A text field is required.');
        }
        return $value;
    }

    public static function render(array $schema, string $name, mixed $value, string $label, bool $optional = false): string
    {
        self::assertSupported($schema);
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $toggle = $optional || ($schema['nullable'] ?? false);
        $html = '<fieldset class="schema-field"><legend>' . $e($label) . '</legend>';
        $html .= $toggle
            ? '<label><input type="checkbox" name="' . $e($name . '[enabled]') . '" value="1"' . ($value !== null ? ' checked' : '') . '> Include ' . $e($label) . '</label>'
            : '<input type="hidden" name="' . $e($name . '[enabled]') . '" value="1">';
        $field = $name . '[value]';
        if ($schema['type'] === 'object') {
            foreach ($schema['properties'] as $key => $child) {
                $html .= self::render($child, $field . '[' . $key . ']', $value[$key] ?? null,
                    ucwords(str_replace('_', ' ', $key)), !in_array($key, $schema['required'] ?? [], true));
            }
        } elseif ($schema['type'] === 'array') {
            $html .= '<p>Select the rows to include, in order.</p>';
            for ($i = 0; $i < $schema['maxItems']; $i++) {
                $html .= '<details' . (isset($value[$i]) ? ' open' : '') . '><summary>Item ' . ($i + 1) . '</summary>';
                $html .= self::render($schema['items'], $field . '[' . $i . ']', $value[$i] ?? null, 'Item ' . ($i + 1), true) . '</details>';
            }
        } elseif ($schema['type'] === 'boolean' || isset($schema['enum'])) {
            $choices = $schema['type'] === 'boolean' ? ['0' => 'No', '1' => 'Yes'] : array_combine($schema['enum'], $schema['enum']);
            $selected = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $html .= '<select aria-label="' . $e($label) . '" name="' . $e($field) . '">';
            foreach ($choices as $key => $text) {
                $html .= '<option value="' . $e($key) . '"' . ((string) $key === $selected ? ' selected' : '') . '>' . $e($text) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<textarea rows="' . (($schema['maxLength'] ?? 255) > 500 ? '3' : '1') . '" aria-label="' . $e($label)
                . '" name="' . $e($field) . '" maxlength="' . $e($schema['maxLength'] ?? 4000) . '">' . $e($value) . '</textarea>';
        }
        return $html . '</fieldset>';
    }
}
