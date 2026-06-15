<?php

if (!function_exists('ui_button')) {
    function ui_button(string $label, string $href = '', string $variant = 'primary', array $attributes = []): string
    {
        $class = 'ubo-button' . ($variant === 'secondary' ? ' ubo-button--secondary' : '');
        $attributes['class'] = trim($class . ' ' . ($attributes['class'] ?? ''));

        if ($href !== '') {
            return '<a href="' . e($href) . '"' . ui_attributes($attributes) . '>' . e($label) . '</a>';
        }

        $attributes['type'] = $attributes['type'] ?? 'submit';

        return '<button' . ui_attributes($attributes) . '>' . e($label) . '</button>';
    }
}

if (!function_exists('ui_attributes')) {
    function ui_attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . e((string) $name);
                continue;
            }

            $html .= ' ' . e((string) $name) . '="' . e((string) $value) . '"';
        }

        return $html;
    }
}
