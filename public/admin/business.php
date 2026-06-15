<?php

require_once __DIR__ . '/_common.php';

$context = admin_bootstrap();
if (!$context['is_admin']) {
    admin_access_denied($context);
    exit;
}

$businessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
$notice = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $businessId > 0) {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'enable_module' || $action === 'disable_module') {
            AdminPortal::setModuleStatus(
                $businessId,
                (int) $context['user']['id'],
                (string) ($_POST['module_key'] ?? ''),
                $action === 'enable_module'
            );
            $notice = $action === 'enable_module' ? 'Module enabled.' : 'Module disabled.';
        } elseif ($action === 'set_flags') {
            AdminPortal::setBusinessFlags($businessId, (int) $context['user']['id'], [
                'is_suspended' => isset($_POST['is_suspended']),
                'is_test_account' => isset($_POST['is_test_account']),
            ]);
            $notice = 'Business controls updated.';
        } elseif ($action === 'add_note') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            AdminPortal::addNote(
                $businessId,
                $userId > 0 ? $userId : null,
                (int) $context['user']['id'],
                (string) ($_POST['note'] ?? '')
            );
            $notice = 'Admin note added.';
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Business action could not be completed.';
}

$loadError = '';
$business = null;
$activeModules = [];
$allModules = [];
$notes = [];

try {
    $business = $businessId > 0 ? AdminPortal::business($businessId) : null;
    if ($business !== null) {
        $activeModules = AdminPortal::activeModulesForBusiness($businessId);
        $allModules = AdminPortal::allManagedModules();
        $notes = AdminPortal::notesForBusiness($businessId);
    }
} catch (Throwable $exception) {
    $loadError = 'Business detail could not be loaded.';
}

$activeModuleKeys = array_column($activeModules, 'module_key');

admin_begin('Business Detail', 'businesses', $context);
?>
<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= ui_alert($error, 'error') ?>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif ($business === null): ?>
    <section class="empty-state">
        <h1>Business not found</h1>
        <p>No matching business exists for this request.</p>
    </section>
<?php else: ?>
    <section class="hero-panel">
        <p class="eyebrow">Business Detail</p>
        <h1><?= e($business['business_name']) ?></h1>
        <p class="muted"><?= e($business['owner_name'] ?: 'Owner not set') ?> · <?= e(AdminPortal::statusLabel($business['internal_status'])) ?></p>
    </section>

    <section class="business-switcher">
        <h2>Business Information</h2>
        <div class="summary-list">
            <div><dt>Name</dt><dd><?= e($business['business_name']) ?></dd></div>
            <div><dt>Contact</dt><dd><?= e($business['owner_name'] ?: 'Not set') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($business['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($business['phone']) ?></dd></div>
            <div><dt>Onboarding</dt><dd><?= e(AdminPortal::statusLabel($business['onboarding_status'])) ?><?= $business['onboarding_step'] ? ' · ' . e(AdminPortal::statusLabel($business['onboarding_step'])) : '' ?></dd></div>
            <div><dt>Website</dt><dd><?= e(AdminPortal::statusLabel($business['website_status'])) ?></dd></div>
        </div>
        <div class="button-row secondary-link">
            <?= ui_button('Open Website Controls', 'website.php?business_id=' . urlencode((string) $businessId), 'secondary') ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Modules</h2>
        <div class="admin-module-grid">
            <?php foreach ($allModules as $module): ?>
                <?php $isActive = in_array($module['module_key'], $activeModuleKeys, true); ?>
                <form method="post" action="business.php" class="module-option admin-module-option">
                    <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                    <input type="hidden" name="module_key" value="<?= e($module['module_key']) ?>">
                    <input type="hidden" name="action" value="<?= $isActive ? 'disable_module' : 'enable_module' ?>">
                    <div>
                        <strong><?= e($module['name']) ?></strong>
                        <span><?= $isActive ? 'Active' : 'Inactive' ?></span>
                    </div>
                    <?= ui_button($isActive ? 'Disable Module' : 'Enable Module', '', $isActive ? 'secondary' : 'primary', ['class' => 'ubo-dashboard-action']) ?>
                </form>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="business-switcher">
        <h2>Actions</h2>
        <form method="post" action="business.php" class="admin-actions-form">
            <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
            <input type="hidden" name="action" value="set_flags">
            <label class="checkbox-line">
                <input type="checkbox" name="is_suspended" value="1" <?= (int) $business['is_suspended'] === 1 ? 'checked' : '' ?>>
                <span>Suspend Business</span>
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_test_account" value="1" <?= (int) $business['is_test_account'] === 1 ? 'checked' : '' ?>>
                <span>Mark Test Account</span>
            </label>
            <?= ui_button('Save Business Controls', '', 'primary') ?>
        </form>
    </section>

    <section class="business-switcher">
        <h2>Admin Notes</h2>
        <form method="post" action="business.php" class="form-stack">
            <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
            <input type="hidden" name="user_id" value="<?= e($business['owner_user_id']) ?>">
            <input type="hidden" name="action" value="add_note">
            <label>New Note<textarea name="note" rows="4" required></textarea></label>
            <?= ui_button('Add Note', '', 'primary') ?>
        </form>
        <div class="activity-list admin-notes">
            <?php foreach ($notes as $note): ?>
                <article>
                    <strong><?= e(trim((string) $note['admin_first_name'] . ' ' . (string) $note['admin_last_name'])) ?></strong>
                    <p><?= e($note['note']) ?></p>
                    <span><?= e($note['created_at']) ?></span>
                </article>
            <?php endforeach; ?>
            <?php if (count($notes) === 0): ?>
                <p class="muted">No admin notes yet.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php admin_end(); ?>
