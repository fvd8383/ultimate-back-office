<?php

if (!function_exists('ui_launch_readiness')) {
    function ui_launch_readiness(string $title, string $description, array $items, array $options = []): string
    {
        $completedCount = 0;
        foreach ($items as $item) {
            if (!empty($item['completed'])) {
                $completedCount++;
            }
        }

        $moduleLabel = trim((string) ($options['module_label'] ?? 'Module'));
        $summaryLabel = $completedCount . ' of ' . count($items) . ' complete';
        static $sectionCount = 0;
        $sectionCount++;
        $sectionId = trim((string) ($options['id'] ?? 'launch-readiness-' . $sectionCount));
        $titleId = $sectionId . '-title';

        $html = '<section class="launch-readiness" id="' . e($sectionId) . '" aria-labelledby="' . e($titleId) . '">';
        $html .= '<div class="launch-readiness__header">';
        $html .= '<div>';
        $html .= '<p class="eyebrow">' . e($moduleLabel) . '</p>';
        $html .= '<h2 id="' . e($titleId) . '">' . e($title) . '</h2>';
        if ($description !== '') {
            $html .= '<p class="muted">' . e($description) . '</p>';
        }
        $html .= '</div>';
        $html .= '<span class="launch-readiness__summary">' . e($summaryLabel) . '</span>';
        $html .= '</div>';

        $html .= '<ol class="launch-readiness__list">';
        foreach ($items as $item) {
            $isComplete = !empty($item['completed']);
            $label = (string) ($item['label'] ?? '');
            $detail = (string) ($item['detail'] ?? '');
            $action = $item['action'] ?? null;

            $html .= '<li class="launch-readiness__item' . ($isComplete ? ' is-complete' : ' is-incomplete') . '">';
            $html .= '<span class="launch-readiness__status" aria-hidden="true">' . ($isComplete ? '&#10003;' : '') . '</span>';
            $html .= '<div class="launch-readiness__content">';
            $html .= '<strong>' . e($label) . '</strong>';
            if ($detail !== '') {
                $html .= '<span>' . e($detail) . '</span>';
            }
            $html .= '</div>';

            if (!$isComplete && is_array($action)) {
                $actionLabel = trim((string) ($action['label'] ?? ''));
                $actionHref = trim((string) ($action['href'] ?? ''));
                $actionVariant = (string) ($action['variant'] ?? 'secondary');
                $actionAttributes = is_array($action['attributes'] ?? null) ? $action['attributes'] : [];

                if ($actionLabel !== '') {
                    $html .= ui_button($actionLabel, $actionHref, $actionVariant, $actionAttributes + ['class' => 'ubo-button--compact']);
                }
            }

            $html .= '</li>';
        }
        $html .= '</ol>';

        $primaryAction = $options['primary_action'] ?? null;
        $supportingText = trim((string) ($options['supporting_text'] ?? ''));
        if (is_array($primaryAction) || $supportingText !== '') {
            $html .= '<div class="launch-readiness__footer">';
            if ($supportingText !== '') {
                $html .= '<p class="muted">' . e($supportingText) . '</p>';
            }

            if (is_array($primaryAction)) {
                $actionLabel = trim((string) ($primaryAction['label'] ?? ''));
                $actionHref = trim((string) ($primaryAction['href'] ?? ''));
                $actionVariant = (string) ($primaryAction['variant'] ?? 'primary');
                $actionAttributes = is_array($primaryAction['attributes'] ?? null) ? $primaryAction['attributes'] : [];

                if ($actionLabel !== '') {
                    $html .= ui_button($actionLabel, $actionHref, $actionVariant, $actionAttributes);
                }
            }
            $html .= '</div>';
        }

        return $html . '</section>';
    }
}
