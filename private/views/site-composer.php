<?php

declare(strict_types=1);

$composition = $workspace['composition'];
$catalog = $workspace['catalog'];
$revision = $workspace['revision'];
$url = static fn (array $query = []): string => 'site-composer.php?' . http_build_query(['revision_id' => $revisionId] + $query);
$beginForm = static function (string $operation, array $fields = []) use ($composition, $revisionId): void {
    echo '<form method="post" action="site-composer.php" class="form-stack">';
    echo Csrf::input(SITE_PLATFORM_CSRF_SCOPE);
    foreach (['revision_id' => $revisionId, 'expected_snapshot_hash' => $composition['snapshot_hash'], 'operation' => $operation] + $fields as $key => $value) {
        echo '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
    }
};
$assetFields = static function (string $name, array $selected) use ($workspace): void {
    echo '<details><summary>Existing permitted assets</summary><p>Match each usage key to the configuration field above. Leave the asset blank to omit a row.</p>';
    for ($i = 0; $i < min(25, max(3, count($selected) + 1)); $i++) {
        $row = $selected[$i] ?? [];
        echo '<fieldset class="schema-field"><legend>Asset ' . ($i + 1) . '</legend><label>Usage key<input name="' . e($name . '[' . $i . '][usage_key]') . '" value="' . e($row['usage_key'] ?? '') . '" maxlength="100"></label>';
        echo '<label>Asset<select name="' . e($name . '[' . $i . '][asset_id]') . '"><option value="">No asset</option>';
        $found = false;
        foreach ($workspace['assets'] as $asset) {
            $isSelected = ($row['asset_id'] ?? null) === $asset['asset_id'];
            $found = $found || $isSelected;
            echo '<option value="' . e($asset['asset_id']) . '"' . ($isSelected ? ' selected' : '') . '>#' . e($asset['asset_id']) . ' · ' . e($asset['asset_type']) . ' · ' . e($asset['mime_type']) . ' · ' . e($asset['byte_size']) . ' bytes</option>';
        }
        if (!$found && isset($row['asset_id'])) {
            // Preserve the unavailable selection so saving cannot silently drop it.
            echo '<option selected value="' . e($row['asset_id']) . '">Unavailable asset #' . e($row['asset_id']) . ' — remove or replace</option>';
        }
        echo '</select></label></fieldset>';
    }
    echo '</details>';
};
$selectionFields = static function (array $definition, array $selection, string $prefix = ''): void {
    $name = static fn (string $field): string => $prefix === '' ? $field : $prefix . '[' . $field . ']';
    echo '<input type="hidden" name="' . e($name('component_identity')) . '" value="' . e($definition['component_key'] . '@' . $definition['implementation_version']) . '">';
    echo '<p>' . e($definition['label']) . ' · ' . e($definition['implementation_version']) . '</p><label>Variant<select name="' . e($name('variant_key')) . '">';
    foreach ($definition['variants'] as $key => $variant) {
        echo '<option value="' . e($key) . '"' . (($selection['variant_key'] ?? '') === $key ? ' selected' : '') . '>' . e($variant['label']) . '</option>';
    }
    if (isset($selection['variant_key']) && !isset($definition['variants'][$selection['variant_key']])) {
        echo '<option selected value="' . e($selection['variant_key']) . '">Unavailable variant — choose a replacement explicitly</option>';
    }
    echo '</select></label>';
    echo SiteSchemaForm::render($definition['configuration_schema'], $name('configuration'), $selection['configuration'] ?? null, 'Configuration');
    foreach ($definition['asset_requirements'] as $variant => $requirements) {
        foreach ($requirements as $requirement) {
            echo '<p class="form-help">' . e($variant . ': ' . $requirement['field'] . ' — ' . $requirement['asset_type'] . (isset($requirement['mime_type']) ? ' / ' . $requirement['mime_type'] : '') . ($requirement['required'] ? ' required' : ' optional')) . '</p>';
        }
    }
};
$targetAssets = static fn (?string $page, ?string $section): array => array_values(array_filter($composition['assets'],
    static fn (array $asset): bool => $asset['page_key'] === $page && $asset['section_key'] === $section));
?>
<p><a href="site.php?site_id=<?= e($composition['site_id']) ?>">Site detail</a> · Revision <?= e($composition['revision_number']) ?>
<?php if ($composition['composition_state'] === 'composed'): ?> · <a href="site-preview.php?revision_id=<?= e($revisionId) ?>">Preview</a><?php endif; ?></p>
<?php if ($composition['composition_state'] === 'empty'): ?>
    <section class="composer-form"><h2>Empty draft</h2><p>Choose how to begin. Opening this editor does not create composition.</p>
    <?php $beginForm('initialize_new'); ?><?= ui_button('Initialize New Composition') ?></form>
    <?php if ($revision['based_on_revision_id'] !== null): ?>
        <?php $beginForm('initialize_from_based_on'); ?><?= ui_button('Start From Based-On Revision') ?></form>
    <?php endif; ?></section>
<?php else: ?>
<div class="composer-layout">
<nav class="composer-outline" aria-label="Composition outline">
    <h2>Pages</h2><ul>
    <?php foreach ($composition['pages'] as $page): ?>
        <li><a href="<?= e($url(['edit' => 'page', 'page_key' => $page['page_key']])) ?>"><?= e($page['title']) ?></a>
            <ul><?php foreach ($page['sections'] as $section): ?>
                <li><a href="<?= e($url(['edit' => 'section', 'page_key' => $page['page_key'], 'section_key' => $section['section_key']])) ?>"><?= e($section['section_key']) ?> · <?= e($section['component_key']) ?></a></li>
            <?php endforeach; ?></ul>
            <a href="<?= e($url(['edit' => 'add_section', 'page_key' => $page['page_key']])) ?>">Add section</a>
        </li>
    <?php endforeach; ?></ul>
    <p><a href="<?= e($url(['edit' => 'add_page'])) ?>">Add page</a></p>
    <p><a href="<?= e($url(['edit' => 'theme'])) ?>">Edit theme</a></p>
</nav>
<section class="composer-form">
<?php
try {
    $edit = is_string($_GET['edit'] ?? null) ? $_GET['edit'] : '';
    $pageKey = is_string($_GET['page_key'] ?? null) ? $_GET['page_key'] : '';
    $sectionKey = is_string($_GET['section_key'] ?? null) ? $_GET['section_key'] : '';
    $pagesByKey = array_column($composition['pages'], null, 'page_key');
    $page = $pagesByKey[$pageKey] ?? null;
    if ($edit === 'add_page' || ($edit === 'page' && $page !== null)) {
        $new = $edit === 'add_page';
        echo '<h2>' . ($new ? 'Add page' : 'Edit page: ' . e($pageKey)) . '</h2>';
        if ($new) {
            echo '<p>The page begins with a text section containing “Content pending review”.</p>';
        }
        $beginForm($new ? 'add_page' : 'update_page', $new ? [] : ['page_key' => $pageKey]);
        echo SiteSchemaForm::render(SiteCompositionEditor::pageFormSchema($new), 'page', $new ? [
            'title' => 'Draft page', 'page_type' => 'standard',
            'seo' => ['robots' => 'noindex_nofollow', 'canonical_policy' => 'none'],
            'presentation' => ['layout_width' => 'standard', 'show_in_navigation' => true],
        ] : $page, 'Page');
        echo ui_button('Save Page') . '</form>';
        if (!$new) {
            echo '<div class="composer-actions">';
            foreach (['up', 'down'] as $direction) {
                $beginForm('move_page', ['page_key' => $pageKey, 'direction' => $direction]);
                echo ui_button('Move ' . ucfirst($direction)) . '</form>';
            }
            $beginForm('remove_page', ['page_key' => $pageKey]);
            echo ui_button('Remove Page') . '</form></div>';
        }
    } elseif (($edit === 'section' || $edit === 'add_section') && $page !== null) {
        $new = $edit === 'add_section';
        $selection = $new ? [] : (array_column($page['sections'], null, 'section_key')[$sectionKey] ?? null);
        if ($selection === null) {
            throw new SiteServiceException('invalid_request', 'Section not found.');
        }
        if ($new) {
            $available = SiteAuthoringCatalog::forActor($actorId, $page['page_type'], $page['sections']);
            echo '<h2>Add section</h2><form method="get"><input type="hidden" name="revision_id" value="' . e($revisionId) . '"><input type="hidden" name="edit" value="add_section"><input type="hidden" name="page_key" value="' . e($pageKey) . '"><label>Component<select name="component">';
            foreach ($available as $identity => $definition) {
                echo '<option value="' . e($identity) . '"' . (($_GET['component'] ?? '') === $identity ? ' selected' : '') . '>' . e($definition['label'] . ' · ' . $definition['implementation_version']) . '</option>';
            }
            echo '</select></label>' . ui_button('Choose Component') . '</form>';
            $identity = is_string($_GET['component'] ?? null) ? $_GET['component'] : '';
            $definition = $available[$identity] ?? null;
        } else {
            $identity = $selection['component_key'] . '@' . $selection['implementation_version'];
            $definition = $catalog[$identity] ?? null;
            echo '<h2>Edit section: ' . e($sectionKey) . '</h2>';
        }
        if ($definition !== null) {
            $beginForm($new ? 'add_section' : 'update_section', ['page_key' => $pageKey] + ($new ? [] : ['section_key' => $sectionKey]));
            if ($new) {
                echo '<label>Stable section key<input name="section_key" maxlength="100" required></label>';
            }
            $selectionFields($definition, $selection);
            $assetFields('assets', $new ? [] : $targetAssets($pageKey, $sectionKey));
            echo ui_button('Save Section') . '</form>';
        } elseif (!$new) {
            echo '<p>The exact stored component is no longer authorable. No upgrade has been applied.</p>';
        }
        if (!$new) {
            echo '<div class="composer-actions">';
            foreach (['up', 'down'] as $direction) {
                $beginForm('move_section', ['page_key' => $pageKey, 'section_key' => $sectionKey, 'direction' => $direction]);
                echo ui_button('Move ' . ucfirst($direction)) . '</form>';
            }
            $beginForm('remove_section', ['page_key' => $pageKey, 'section_key' => $sectionKey]);
            echo ui_button('Remove Section') . '</form></div>';
        }
    } elseif ($edit === 'theme') {
        $theme = $composition['theme'];
        echo '<h2>Edit theme</h2>';
        $beginForm('update_theme');
        echo '<label>Theme<select name="theme_identity">';
        foreach (SiteAuthoringCatalog::themes() as $identity => $definition) {
            echo '<option value="' . e($identity) . '">' . e($identity) . '</option>';
        }
        echo '</select></label>';
        echo SiteSchemaForm::render(SiteCompositionEditor::themeFormSchema(), 'theme', $theme, 'Theme');
        foreach ($theme['configuration']['layouts'] as $slot => $selection) {
            $definition = $catalog[$selection['component_key'] . '@' . $selection['implementation_version']] ?? null;
            if ($definition === null) {
                throw new SiteServiceException('conflict', 'The exact stored layout is no longer authorable.');
            }
            echo '<h3>' . e(ucwords(str_replace('_', ' ', $slot))) . '</h3>';
            $selectionFields($definition, $selection, 'layouts[' . $slot . ']');
        }
        $assetFields('theme_assets', $targetAssets(null, null));
        echo ui_button('Save Theme') . '</form>';
    } else {
        echo '<h2>Choose a page, section, or theme</h2><p>Use the outline to edit one part of this draft.</p>';
    }
} catch (Throwable) {
    echo '<p>This selection cannot be edited. Return to the outline and choose an available item.</p>';
}
?>
</section></div>
<?php endif; ?>
