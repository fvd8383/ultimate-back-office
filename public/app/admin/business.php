<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../private/classes/BillingFoundation.php';

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
$billingSubscription = null;
$recentWebsiteLeads = [];

try {
    $business = $businessId > 0 ? AdminPortal::business($businessId) : null;
    if ($business !== null) {
        $activeModules = AdminPortal::activeModulesForBusiness($businessId);
        $allModules = AdminPortal::allManagedModules();
        $notes = AdminPortal::notesForBusiness($businessId);
        $billingSubscription = BillingFoundation::subscriptionForBusiness($businessId);
        $recentWebsiteLeads = AdminPortal::recent247spWebsiteLeadsForBusiness($businessId, 5);
    }
} catch (Throwable $exception) {
    $loadError = 'Business detail could not be loaded.';
}

$activeModuleKeys = array_column($activeModules, 'module_key');
$has247spAccess = in_array('247sp', $activeModuleKeys, true);
$has247spSubscription = $billingSubscription !== null;

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
        <?php if ($has247spSubscription && !$has247spAccess): ?>
            <?= ui_alert('Subscription exists, but 24/7 Sales Partner module access is not active.', 'warning') ?>
        <?php endif; ?>
        <div class="summary-list">
            <div><dt>Name</dt><dd><?= e($business['business_name']) ?></dd></div>
            <div><dt>Contact</dt><dd><?= e($business['owner_name'] ?: 'Not set') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($business['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($business['phone']) ?></dd></div>
            <div><dt>Onboarding</dt><dd><?= e(AdminPortal::statusLabel($business['onboarding_status'])) ?><?= $business['onboarding_step'] ? ' · ' . e(AdminPortal::statusLabel($business['onboarding_step'])) : '' ?></dd></div>
            <div><dt>Website</dt><dd><?= e(AdminPortal::statusLabel($business['website_status'])) ?></dd></div>
            <div><dt>247SP Subscription</dt><dd><?= $has247spSubscription ? ui_badge(AdminPortal::statusLabel($billingSubscription['status']), in_array((string) $billingSubscription['status'], ['past_due', 'cancelled'], true) ? 'role' : 'status') . ' ' . e($billingSubscription['plan_name']) : e('No subscription') ?></dd></div>
            <div><dt>247SP Module Access</dt><dd><?= ui_badge($has247spAccess ? 'Active' : 'Inactive', $has247spAccess ? 'status' : 'role') ?></dd></div>
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
        <h2>Recent 247SP Website Leads</h2>
        <?php if (count($recentWebsiteLeads) === 0): ?>
            <p class="muted">No 247SP website submissions yet.</p>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentWebsiteLeads as $lead): ?>
                    <?php $leadName = trim((string) $lead['first_name'] . ' ' . (string) $lead['last_name']); ?>
                    <article>
                        <strong><?= e($leadName !== '' ? $leadName : 'Website lead') ?></strong>
                        <p><?= e(trim(implode(' · ', array_filter([(string) $lead['phone'], (string) $lead['email'], (string) $lead['status_name']])))) ?></p>
                        <span><?= e($lead['source_detail'] ?: '247SP website') ?> · <?= e($lead['submitted_at'] ?: $lead['updated_at']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
