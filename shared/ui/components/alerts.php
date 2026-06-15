<?php

if (!function_exists('ui_alert')) {
    function ui_alert(string $message, string $type = 'info', array $attributes = []): string
    {
        $allowed = ['success', 'warning', 'error', 'info'];
        $type = in_array($type, $allowed, true) ? $type : 'info';
        $attributes['class'] = trim('ubo-alert ubo-alert--' . $type . ' ' . ($attributes['class'] ?? ''));
        $attributes['role'] = $attributes['role'] ?? ($type === 'error' ? 'alert' : 'status');

        return '<div' . ui_attributes($attributes) . '>' . e($message) . '</div>';
    }
}
