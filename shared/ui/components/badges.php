<?php

if (!function_exists('ui_badge')) {
    function ui_badge(string $label, string $variant = 'module', array $attributes = []): string
    {
        $allowed = ['module', 'status', 'role'];
        $variant = in_array($variant, $allowed, true) ? $variant : 'module';
        $attributes['class'] = trim('ubo-badge ubo-badge--' . $variant . ' ' . ($attributes['class'] ?? ''));

        return '<span' . ui_attributes($attributes) . '>' . e($label) . '</span>';
    }
}
