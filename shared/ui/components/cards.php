<?php

if (!function_exists('ui_card_open')) {
    function ui_card_open(array $attributes = []): string
    {
        $attributes['class'] = trim('ubo-card ' . ($attributes['class'] ?? ''));

        return '<section' . ui_attributes($attributes) . '>';
    }
}

if (!function_exists('ui_card_close')) {
    function ui_card_close(): string
    {
        return '</section>';
    }
}
