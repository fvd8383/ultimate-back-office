<?php

if (!function_exists('ui_sidebar')) {
    function ui_sidebar(string $title, array $items, string $label = 'Primary navigation'): string
    {
        if (count($items) === 0) {
            $items = [
                ['label' => 'Dashboard'],
                ['label' => 'Businesses'],
                ['label' => 'Modules'],
            ];
        }

        $html = '<aside class="app-sidebar"><h2>' . e($title) . '</h2><nav aria-label="' . e($label) . '">';

        foreach ($items as $item) {
            $text = (string) ($item['label'] ?? '');
            $href = (string) ($item['href'] ?? '');
            $current = !empty($item['current']);

            if ($href !== '') {
                $html .= '<a class="ubo-sidebar__item" href="' . e($href) . '"' . ($current ? ' aria-current="page"' : '') . '>' . e($text) . '</a>';
            } else {
                $html .= '<span class="ubo-sidebar__item">' . e($text) . '</span>';
            }
        }

        return $html . '</nav></aside>';
    }
}
