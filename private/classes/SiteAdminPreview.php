<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCompositionManager.php';
require_once __DIR__ . '/SiteCompositionRenderer.php';

final class SiteAdminPreview
{
    public static function render(int $actorId, int $revisionId): string
    {
        SiteAuthorizationPolicy::requireInternalAdmin($actorId);
        $state = SiteCompositionManager::compositionForActor($actorId, $revisionId);
        if ($state['composition_state'] === 'empty') {
            return '<p>No composed preview is available yet.</p>';
        }
        $validated = SiteCompositionManager::validatedCompositionForActor($actorId, $revisionId);
        return SiteCompositionRenderer::render($validated);
    }
}
