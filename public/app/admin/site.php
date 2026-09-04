<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/Csrf.php';
require_once __DIR__ . '/../../../private/classes/SiteAdminWorkspace.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerationBriefManager.php';

const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$actingUserId = (int) $context['user']['id'];
try {
    SiteAuthorizationPolicy::requireInternalAdmin($actingUserId);
} catch (SiteServiceException) {
    admin_access_denied($context);
    exit;
}

$siteId = (int) ($_POST['site_id'] ?? $_GET['site_id'] ?? 0);
$selectedRevisionId = (int) ($_GET['revision_id'] ?? 0);
$error = '';
$briefForm = [];
foreach (array_keys(SiteGenerationBriefManager::FIELD_LIMITS) as $field) {
    $briefForm[$field] = (string) ($_POST[$field] ?? '');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::requireValid($_POST['csrf_token'] ?? null, SITE_PLATFORM_CSRF_SCOPE);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create_brief') {
            $briefInput = [];
            foreach (array_keys(SiteGenerationBriefManager::FIELD_LIMITS) as $field) {
                $briefInput[$field] = (string) ($_POST[$field] ?? '');
            }
            SiteGenerationBriefManager::createBrief($actingUserId, $siteId, $briefInput, null);
            Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
            header('Location: site.php?site_id=' . urlencode((string) $siteId) . '&created_brief=1', true, 303);
            exit;
        }
        if ($action === 'create_revision') {
            $briefId = (int) ($_POST['generation_brief_id'] ?? 0);
            $basedOn = (int) ($_POST['based_on_revision_id'] ?? 0);
            $created = SiteRevisionManager::createAuthoredDraftRevision(
                $actingUserId,
                $siteId,
                $briefId,
                $basedOn > 0 ? $basedOn : null
            );
            Csrf::rotate(SITE_PLATFORM_CSRF_SCOPE);
            header(
                'Location: site.php?site_id=' . urlencode((string) $siteId)
                . '&revision_id=' . urlencode((string) $created['revision_id'])
                . '&created_revision=1',
                true,
                303
            );
            exit;
        }
        throw new InvalidArgumentException('The requested Site Platform action is invalid.');
    }
} catch (SiteServiceException | CsrfException | InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'The Site Platform action could not be completed.';
}

$detail = null;
$loadError = '';
try {
    $detail = SiteAdminWorkspace::siteDetail($actingUserId, $siteId);
} catch (SiteServiceException | InvalidArgumentException $exception) {
    $loadError = $exception->getMessage();
} catch (Throwable $exception) {
    $loadError = 'Site Platform detail could not be loaded.';
}

admin_begin('Site Detail', 'sites', $context);
?>
<?php if (isset($_GET['created_site'])): ?>
    <?= ui_alert('Generic site created.', 'success') ?>
<?php endif; ?>
<?php if (isset($_GET['created_brief'])): ?>
    <?= ui_alert('Generation brief version created.', 'success') ?>
<?php endif; ?>
<?php if (isset($_GET['created_revision'])): ?>
    <?= ui_alert('Authored draft revision created with an empty composition.', 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>
<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php endif; ?>

<?php if ($detail === null): ?>
    <section class="empty-state">
        <p class="eyebrow">Site Platform</p>
        <h1>Site unavailable</h1>
        <p>The requested generic site could not be loaded.</p>
        <a href="sites.php">Return to Site Platform</a>
    </section>
<?php else: ?>
    <?php $site = $detail['site']; ?>
    <section class="hero-panel">
        <p class="eyebrow">Site Platform · Generic Site #<?= e($site['id']) ?></p>
        <h1><?= e(AdminPortal::statusLabel($site['purpose'])) ?> site</h1>
        <p class="muted"><?= e($site['site_key']) ?></p>
        <div class="button-row">
            <?= ui_badge(AdminPortal::statusLabel($site['lifecycle_status']), 'status') ?>
            <a href="sites.php">All generic sites</a>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Site status</h2>
        <?= ui_alert('Not publicly deployed. The legacy 247SP runtime remains authoritative.', 'warning') ?>
        <div class="summary-list">
            <div><dt>Site ID</dt><dd><?= e($site['id']) ?></dd></div>
            <div><dt>Purpose</dt><dd><?= e(AdminPortal::statusLabel($site['purpose'])) ?></dd></div>
            <div><dt>Lifecycle</dt><dd><?= e(AdminPortal::statusLabel($site['lifecycle_status'])) ?></dd></div>
            <div><dt>Lock version</dt><dd><?= e($site['lock_version']) ?></dd></div>
            <div><dt>Business association</dt><dd>
                <?php if ($detail['business_association'] === null): ?>
                    None (purpose does not require a fabricated customer business)
                <?php else: ?>
                    <a href="business.php?business_id=<?= e($detail['business_association']['business_id']) ?>"><?= e($detail['business_association']['business_name']) ?></a>
                    · <?= e(AdminPortal::statusLabel($detail['business_association']['status'])) ?>
                <?php endif; ?>
            </dd></div>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Create generation brief</h2>
        <p class="muted">Creative and presentation direction only. Reusable business facts are captured server-side from authoritative sources when a draft revision is created.</p>
        <form method="post" action="site.php" class="form-stack">
            <?= Csrf::input(SITE_PLATFORM_CSRF_SCOPE) ?>
            <input type="hidden" name="action" value="create_brief">
            <input type="hidden" name="site_id" value="<?= e($site['id']) ?>">
            <label>Summary
                <textarea name="summary" rows="4" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['summary']) ?>" required><?= e($briefForm['summary']) ?></textarea>
            </label>
            <label>Target audience
                <textarea name="target_audience" rows="3" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['target_audience']) ?>"><?= e($briefForm['target_audience']) ?></textarea>
            </label>
            <label>Tone notes
                <textarea name="tone_notes" rows="3" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['tone_notes']) ?>"><?= e($briefForm['tone_notes']) ?></textarea>
            </label>
            <label>Design notes
                <textarea name="design_notes" rows="4" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['design_notes']) ?>"><?= e($briefForm['design_notes']) ?></textarea>
            </label>
            <label>Conversion notes
                <textarea name="conversion_notes" rows="4" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['conversion_notes']) ?>"><?= e($briefForm['conversion_notes']) ?></textarea>
            </label>
            <label>Page notes
                <textarea name="page_notes" rows="5" maxlength="<?= e(SiteGenerationBriefManager::FIELD_LIMITS['page_notes']) ?>"><?= e($briefForm['page_notes']) ?></textarea>
            </label>
            <?= ui_button('Create Brief Version') ?>
        </form>
    </section>

    <section class="business-switcher">
        <h2>Generation brief history</h2>
        <div class="activity-list">
            <?php foreach ($detail['briefs'] as $brief): ?>
                <article>
                    <strong>Version <?= e($brief['brief_version']) ?> · <?= e(AdminPortal::statusLabel($brief['state'])) ?></strong>
                    <p><?= e($brief['summary']) ?></p>
                    <span><?= e(AdminPortal::statusLabel($brief['source_type'])) ?> · <?= e($brief['created_at']) ?> · hash <?= e(substr($brief['content_hash'], 0, 12)) ?>…</span>
                </article>
            <?php endforeach; ?>
            <?php if ($detail['briefs'] === []): ?>
                <p class="muted">No generation brief versions exist.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Create authored draft revision</h2>
        <?php if ($detail['mutable_revision'] !== null): ?>
            <?= ui_alert('Mutable revision ID ' . e($detail['mutable_revision']['id']) . ' already exists. Use that revision before creating another draft.', 'warning') ?>
            <p class="muted">Open the mutable revision below to edit its composition.</p>
        <?php elseif ($detail['briefs'] === []): ?>
            <p class="muted">Create a structured generation brief before creating a draft revision.</p>
        <?php else: ?>
            <form method="post" action="site.php" class="form-stack">
                <?= Csrf::input(SITE_PLATFORM_CSRF_SCOPE) ?>
                <input type="hidden" name="action" value="create_revision">
                <input type="hidden" name="site_id" value="<?= e($site['id']) ?>">
                <label>Generation brief
                    <select name="generation_brief_id" required>
                        <option value="">Choose a same-site brief</option>
                        <?php foreach ($detail['briefs'] as $brief): ?>
                            <option value="<?= e($brief['id']) ?>">Version <?= e($brief['brief_version']) ?> · <?= e(AdminPortal::statusLabel($brief['state'])) ?> · <?= e($brief['summary']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Based-on immutable revision (optional)
                    <select name="based_on_revision_id">
                        <option value="">Start with an empty composition</option>
                        <?php foreach ($detail['revisions'] as $revision): ?>
                            <?php if (!in_array($revision['lifecycle_status'], SiteRevisionManager::MUTABLE_COMPOSITION_STATES, true)): ?>
                                <option value="<?= e($revision['id']) ?>">Revision <?= e($revision['revision_number']) ?> · <?= e(AdminPortal::statusLabel($revision['lifecycle_status'])) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="form-help">M4A records ancestry but does not copy composition. The new revision starts empty.</span>
                <?= ui_button('Create Draft Revision') ?>
            </form>
        <?php endif; ?>
    </section>

    <section class="business-switcher">
        <h2>Revision history</h2>
        <div class="activity-list">
            <?php foreach ($detail['revisions'] as $revision): ?>
                <article<?= $selectedRevisionId === $revision['id'] ? ' aria-current="true"' : '' ?>>
                    <strong>Revision <?= e($revision['revision_number']) ?> · <?= e(AdminPortal::statusLabel($revision['lifecycle_status'])) ?></strong>
                    <p>
                        Materiality: <?= e(AdminPortal::statusLabel($revision['materiality'])) ?>
                        · Based on: <?= e($revision['based_on_revision_id'] ?? 'None') ?>
                        · Restored from: <?= e($revision['restored_from_revision_id'] ?? 'None') ?>
                    </p>
                    <span>Hash <?= e(substr($revision['snapshot_hash'], 0, 12)) ?>… · Created <?= e($revision['created_at']) ?><?= $revision['review_ready_at'] ? ' · Review ' . e($revision['review_ready_at']) : '' ?><?= $revision['published_at'] ? ' · Published ' . e($revision['published_at']) : '' ?></span>
                    <?php if ($revision['has_composition']): ?>
                        <p><a href="site-preview.php?revision_id=<?= e($revision['id']) ?>">Preview</a></p>
                        <p><a href="site-review.php?revision_id=<?= e($revision['id']) ?>">Review Workflow</a></p>
                    <?php endif; ?>
                    <?php if (in_array($revision['lifecycle_status'], SiteRevisionManager::MUTABLE_COMPOSITION_STATES, true)): ?>
                        <p><a href="site-composer.php?revision_id=<?= e($revision['id']) ?>">Edit Composition</a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($detail['revisions'] === []): ?>
                <p class="muted">No revisions exist.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Approval summary</h2>
        <div class="activity-list">
            <?php foreach ($detail['approvals'] as $approval): ?>
                <article>
                    <strong><?= e(AdminPortal::statusLabel($approval['approval_type'])) ?> · <?= e(AdminPortal::statusLabel($approval['state'])) ?></strong>
                    <p>Revision <?= e($approval['revision_number']) ?></p>
                    <span>Requested <?= e($approval['requested_at']) ?><?= $approval['decided_at'] ? ' · Decided ' . e($approval['decided_at']) : '' ?></span>
                </article>
            <?php endforeach; ?>
            <?php if ($detail['approvals'] === []): ?>
                <p class="muted">No approval requests or decisions exist.</p>
            <?php endif; ?>
        </div>
        <p class="muted">Internal review is available through each composed revision’s Review Workflow link. Customer approval decisions remain owned by M5. Generic sites are not publicly deployed.</p>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
