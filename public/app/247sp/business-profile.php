<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/Csrf.php';
require_once __DIR__ . '/../../../private/classes/SharedBusinessProfileUi.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$user = Auth::currentUser();
if ($user === null) {
    Session::logout();
    header('Location: ' . $accountsBaseUrl . '/login.php');
    exit;
}

$requestedBusinessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
$business = null;
$profile = null;
$accessDenied = false;
$loadError = '';
$messages = [];
$fieldErrors = [];
$activeSection = '';
$submittedPayload = null;
$csrfScope = 'shared-business-profile';

try {
    $business = TwentyFourSevenSalesPartner::businessForUser(
        $requestedBusinessId > 0 ? $requestedBusinessId : null,
        (int) $user['id']
    );
    if ($business !== null) {
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess((int) $business['id']);
    }
} catch (Throwable $exception) {
    $loadError = 'The Shared Business Profile could not be loaded.';
}

if ($business !== null && !$accessDenied && $loadError === '') {
    $businessId = (int) $business['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Csrf::requireValid($_POST['csrf_token'] ?? null, $csrfScope);
            $action = SharedBusinessProfileUi::action($_POST);
            $activeSection = SharedBusinessProfileUi::sectionForAction($action);
            $submittedPayload = SharedBusinessProfileUi::payload($action, $_POST);
            SharedBusinessProfileUi::dispatch($action, $businessId, (int) $user['id'], $submittedPayload);
            Csrf::rotate($csrfScope);
            header(
                'Location: business-profile.php?business_id=' . urlencode((string) $businessId)
                . '&saved=' . urlencode($activeSection)
            );
            exit;
        } catch (CsrfException | InvalidArgumentException $exception) {
            $messages[] = $exception->getMessage();
        } catch (SharedBusinessProfileException $exception) {
            if ($exception->errorType() === 'unauthorized') {
                $accessDenied = true;
            } else {
                $messages[] = $exception->getMessage();
                $fieldErrors = SharedBusinessProfileUi::fieldErrorsForSection(
                    $activeSection !== '' ? $activeSection : 'overview',
                    $exception->fieldErrors()
                );
            }
        } catch (Throwable $exception) {
            $messages[] = 'The profile change could not be saved. No profile records were changed.';
        }
    }

    if (!$accessDenied) {
        try {
            $profile = SharedBusinessProfile::getProfileForBusiness($businessId, (int) $user['id']);
        } catch (SharedBusinessProfileException $exception) {
            if ($exception->errorType() === 'unauthorized') {
                $accessDenied = true;
            } else {
                $loadError = $exception->getMessage();
            }
        } catch (Throwable $exception) {
            $loadError = 'The Shared Business Profile could not be loaded.';
        }
    }
}

$savedMessages = [
    'overview' => 'Profile submitted for review.',
    'shared_facts' => 'Shared wording saved.',
    'hours' => 'Weekly hours saved.',
    'exceptions' => 'Hour exceptions saved.',
    'faqs' => 'FAQs saved.',
    'pricing_guidance' => 'Pricing guidance saved.',
    'appointment_settings' => 'Appointment settings saved.',
    'appointment_rules' => 'Appointment rules saved.',
    'transfer_rules' => 'Transfer rules saved.',
    'escalation_rules' => 'Escalation rules saved.',
    'notification_preferences' => 'Notification preferences saved.',
];
$savedSection = trim((string) ($_GET['saved'] ?? ''));
$savedMessage = $savedMessages[$savedSection] ?? '';
$businessIdForLinks = $business ? (int) $business['id'] : 0;
$sp247NavItems = [
    ['label' => 'Dashboard', 'href' => 'dashboard.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : '')],
    ['label' => 'Business Profile', 'href' => 'business-profile.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : ''), 'current' => true],
    ['label' => 'Onboarding', 'href' => 'onboarding.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : '')],
    ['label' => 'Review', 'href' => 'review.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : '')],
    ['label' => 'Preview', 'href' => 'site-preview.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : '')],
];
if ($businessIdForLinks > 0 && !$accessDenied) {
    $sp247NavItems[] = [
        'label' => 'Website Manager',
        'href' => 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks),
    ];
}

function sbp_field_value(?array $profile, string $field, string $section, string $activeSection, ?array $submittedPayload): string
{
    if ($section === $activeSection && is_array($submittedPayload) && array_key_exists($field, $submittedPayload)) {
        return (string) $submittedPayload[$field];
    }

    return (string) ($profile['shared_business_facts'][$field] ?? '');
}

function sbp_collection_rows(array $profile, string $key, string $section, string $activeSection, ?array $submittedPayload): array
{
    if ($section === $activeSection && is_array($submittedPayload)) {
        return $submittedPayload;
    }

    return is_array($profile[$key] ?? null) ? $profile[$key] : [];
}

function sbp_checked($value): string
{
    return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true) ? ' checked' : '';
}

function sbp_selected($value, $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function sbp_section_open(string $section, array $readiness, string $activeSection): bool
{
    if ($section === $activeSection) {
        return true;
    }

    $readinessSections = [
        'shared_facts' => ['timezone', 'greeting'],
        'hours' => ['hours'],
        'faqs' => ['faqs'],
        'appointment_settings' => ['appointment_rules'],
        'appointment_rules' => ['appointment_rules'],
        'transfer_rules' => ['transfer_behavior'],
        'escalation_rules' => ['escalation_behavior'],
        'notification_preferences' => ['notification_destinations'],
    ];
    $incomplete = $readiness['incomplete_sections'] ?? [];

    return count(array_intersect($readinessSections[$section] ?? [], $incomplete)) > 0;
}

function sbp_error_for(array $fieldErrors, string $field): string
{
    if (isset($fieldErrors[$field])) {
        return '<span class="form-help form-help--error">' . e($fieldErrors[$field]) . '</span>';
    }

    foreach ($fieldErrors as $errorField => $message) {
        if (str_starts_with((string) $errorField, $field . '.')) {
            return '<span class="form-help form-help--error">' . e($message) . '</span>';
        }
    }

    return '';
}

function sbp_new_row_controls(int $index, bool $submitted = false): string
{
    return '<label class="checkbox-line"><input type="checkbox" name="rows[' . $index . '][_include]" value="1"'
        . ($submitted ? ' checked' : '') . '><span>Add this entry</span></label>';
}

function sbp_existing_row_controls(int $index, array $row): string
{
    if ((int) ($row['id'] ?? 0) <= 0) {
        return sbp_new_row_controls($index, !empty($row['_submitted']));
    }

    return '<input type="hidden" name="rows[' . $index . '][id]" value="' . e($row['id']) . '">'
        . '<label class="checkbox-line"><input type="checkbox" name="rows[' . $index . '][_remove]" value="1">'
        . '<span>Remove this entry when saving</span></label>';
}

function sbp_service_options(array $profile, array $row): string
{
    $html = '<option value="">All services</option>';
    foreach ($profile['services']['selected_sub_services'] ?? [] as $service) {
        $value = 'sub:' . (int) $service['sub_service_id'];
        $selected = (int) ($row['sub_service_id'] ?? 0) === (int) $service['sub_service_id'];
        $html .= '<option value="' . e($value) . '"' . ($selected ? ' selected' : '') . '>' . e($service['name']) . '</option>';
    }
    foreach ($profile['services']['custom_services'] ?? [] as $service) {
        $value = 'custom:' . (int) $service['business_custom_service_id'];
        $selected = (int) ($row['business_custom_service_id'] ?? 0) === (int) $service['business_custom_service_id'];
        $html .= '<option value="' . e($value) . '"' . ($selected ? ' selected' : '') . '>' . e($service['name']) . ' (custom)</option>';
    }

    return $html;
}

function sbp_rule_reference_inputs(int $index, array $row, array $profile): string
{
    $options = sbp_service_options($profile, $row);

    return '<label>Applies to<select name="rows[' . $index . '][service_reference]">' . $options . '</select></label>';
}

function sbp_mark_submitted_rows(array $rows, string $section, string $activeSection): array
{
    if ($section !== $activeSection) {
        return $rows;
    }

    foreach ($rows as &$row) {
        if (is_array($row) && empty($row['id'])) {
            $row['_submitted'] = true;
        }
    }

    return $rows;
}

$pageTitle = 'Business Profile - 24/7 Sales Partner';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
?>
<?php application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user, 'business' => $business, 'secondary_nav' => $sp247NavItems]); ?>
<section class="hero-panel product-hero product-hero--247sp">
    <img class="product-hero__logo" src="../assets/img/247sp-logo.svg" alt="24/7 Sales Partner">
    <p class="eyebrow">Shared Business Profile</p>
    <h1><?= $business ? e($business['business_name']) : 'Business Profile' ?></h1>
    <p class="muted">Maintain the approved business facts used by your website and future front-office channels.</p>
</section>

<?php if ($savedMessage !== ''): ?>
    <?= ui_alert($savedMessage, 'success') ?>
<?php endif; ?>
<?php foreach ($messages as $message): ?>
    <?= ui_alert($message, 'error') ?>
<?php endforeach; ?>
<?php foreach ($fieldErrors as $field => $message): ?>
    <?= ui_alert(SharedBusinessProfileUi::statusLabel((string) $field) . ': ' . $message, 'error') ?>
<?php endforeach; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php elseif ($business === null): ?>
    <section class="empty-state"><h2>Business not found</h2><p>Select a linked business to manage its profile.</p></section>
<?php elseif ($accessDenied): ?>
    <section class="empty-state"><h2>Access denied</h2><p>You cannot access this business profile.</p></section>
<?php elseif ($profile !== null): ?>
    <?php
        $facts = $profile['shared_business_facts'];
        $readiness = $profile['readiness'];
        $lifecycle = $profile['lifecycle'];
        $completedCount = count($readiness['completed_sections'] ?? []);
        $incompleteCount = count($readiness['incomplete_sections'] ?? []);
    ?>
    <section class="business-switcher" id="overview">
        <div class="button-row">
            <?= ui_badge(SharedBusinessProfileUi::statusLabel((string) $lifecycle['status']), $readiness['is_complete'] ? 'status' : 'role') ?>
            <?= ui_badge($readiness['is_complete'] ? 'Ready for review' : $incompleteCount . ' sections need attention', $readiness['is_complete'] ? 'status' : 'role') ?>
        </div>
        <h2>Profile readiness</h2>
        <p class="muted"><?= e($completedCount) ?> required sections complete. Readiness is calculated from current authoritative records.</p>
        <?php if ($incompleteCount > 0): ?>
            <div class="summary-list">
                <?php foreach ($readiness['missing_fields'] ?? [] as $section => $missing): ?>
                    <div><dt><?= e(SharedBusinessProfileUi::statusLabel((string) $section)) ?></dt><dd><?= e(implode(', ', array_map([SharedBusinessProfileUi::class, 'statusLabel'], $missing))) ?></dd></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php foreach ($readiness['warnings'] ?? [] as $warning): ?>
            <?= ui_alert($warning, 'warning') ?>
        <?php endforeach; ?>
        <?php if (in_array((string) $lifecycle['status'], ['draft', 'incomplete'], true)): ?>
            <form method="post" action="business-profile.php" class="form-stack">
                <?= Csrf::input($csrfScope) ?>
                <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
                <input type="hidden" name="profile_action" value="submit_for_review">
                <?= ui_button('Submit profile for review') ?>
                <span class="form-help">Submitting does not activate the profile or bypass readiness checks.</span>
            </form>
        <?php endif; ?>
    </section>

    <details class="business-switcher" open>
        <summary><strong>Authoritative business information</strong></summary>
        <p class="muted">Identity, services, and service area are managed in their existing authoritative editors.</p>
        <div class="summary-list">
            <div><dt>Business</dt><dd><?= e($facts['business_name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($facts['email'] ?: 'Not set') ?></dd></div>
            <div><dt>Phone</dt><dd><?= e($facts['phone'] ?: 'Not set') ?></dd></div>
            <div><dt>Services</dt><dd><?= e(implode(', ', array_column(array_merge($profile['services']['selected_sub_services'], $profile['services']['custom_services']), 'name')) ?: 'Not set') ?></dd></div>
            <div><dt>Service area</dt><dd><?= e(SharedBusinessProfileUi::statusLabel((string) $profile['service_area']['mode'])) ?><?= $profile['service_area']['radius_miles'] ? e(' · ' . $profile['service_area']['radius_miles'] . ' miles') : '' ?></dd></div>
        </div>
        <div class="button-row">
            <?= ui_button('Edit identity and services', $accountsBaseUrl . '/business.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
            <?= ui_button('Edit service area', 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=service_area', 'secondary') ?>
        </div>
    </details>

    <details class="business-switcher"<?= sbp_section_open('shared_facts', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Shared wording and greeting</strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?>
            <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
            <input type="hidden" name="profile_action" value="save_shared_facts">
            <div class="form-grid">
                <label>Public display name<input name="public_display_name" maxlength="255" value="<?= e(sbp_field_value($profile, 'public_display_name', 'shared_facts', $activeSection, $submittedPayload)) ?>"><?= sbp_error_for($fieldErrors, 'public_display_name') ?></label>
                <label>Website URL<input type="url" name="website_url" maxlength="255" value="<?= e(sbp_field_value($profile, 'website_url', 'shared_facts', $activeSection, $submittedPayload)) ?>"><?= sbp_error_for($fieldErrors, 'website_url') ?></label>
                <label>Timezone<select name="timezone"><option value="">Choose timezone</option><?php foreach (DateTimeZone::listIdentifiers() as $timezone): ?><option value="<?= e($timezone) ?>"<?= sbp_selected(sbp_field_value($profile, 'timezone', 'shared_facts', $activeSection, $submittedPayload), $timezone) ?>><?= e($timezone) ?></option><?php endforeach; ?></select><?= sbp_error_for($fieldErrors, 'timezone') ?></label>
                <label>Language code<input name="default_language" maxlength="20" value="<?= e(sbp_field_value($profile, 'default_language', 'shared_facts', $activeSection, $submittedPayload)) ?>" placeholder="en"><?= sbp_error_for($fieldErrors, 'default_language') ?></label>
            </div>
            <label>Primary greeting<textarea name="primary_greeting" rows="3" maxlength="2000"><?= e(sbp_field_value($profile, 'primary_greeting', 'shared_facts', $activeSection, $submittedPayload)) ?></textarea><?= sbp_error_for($fieldErrors, 'primary_greeting') ?></label>
            <label>Short description<textarea name="short_description" rows="3" maxlength="10000"><?= e(sbp_field_value($profile, 'short_description', 'shared_facts', $activeSection, $submittedPayload)) ?></textarea></label>
            <label>Long description<textarea name="long_description" rows="5" maxlength="30000"><?= e(sbp_field_value($profile, 'long_description', 'shared_facts', $activeSection, $submittedPayload)) ?></textarea></label>
            <label>Value proposition<textarea name="value_proposition" rows="3" maxlength="5000"><?= e(sbp_field_value($profile, 'value_proposition', 'shared_facts', $activeSection, $submittedPayload)) ?></textarea></label>
            <div class="form-grid">
                <label>Tone<input name="tone" maxlength="100" value="<?= e(sbp_field_value($profile, 'tone', 'shared_facts', $activeSection, $submittedPayload)) ?>"></label>
                <label>Personality<input name="personality" maxlength="255" value="<?= e(sbp_field_value($profile, 'personality', 'shared_facts', $activeSection, $submittedPayload)) ?>"></label>
            </div>
            <label>Prohibited claims<textarea name="prohibited_claims" rows="3" maxlength="10000"><?= e(sbp_field_value($profile, 'prohibited_claims', 'shared_facts', $activeSection, $submittedPayload)) ?></textarea></label>
            <?= ui_button('Save shared wording') ?>
        </form>
    </details>

    <?php
        $hourRows = sbp_collection_rows($profile, 'hours', 'hours', $activeSection, $submittedPayload);
        $hoursByDay = [];
        foreach ($hourRows as $row) {
            $row['_submitted'] = $activeSection === 'hours' && empty($row['id']);
            $hoursByDay[(int) ($row['day_of_week'] ?? 0)][] = $row;
        }
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    ?>
    <details class="business-switcher"<?= sbp_section_open('hours', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Weekly hours</strong></summary>
        <p class="muted">Save any subset while drafting. Readiness identifies days that still need an explicit schedule.</p>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_hours">
            <?php $hourIndex = 0; foreach ($dayNames as $day => $dayName): ?>
                <?php $rowsForDay = $hoursByDay[$day] ?? []; $rowsForDay[] = ['day_of_week' => $day, 'state' => 'open']; ?>
                <fieldset><legend><?= e($dayName) ?></legend>
                <?php foreach ($rowsForDay as $row): ?>
                    <?php $state = !empty($row['is_closed']) ? 'closed' : (!empty($row['is_24_hours']) ? '24_hours' : 'open'); ?>
                    <div class="form-grid">
                        <input type="hidden" name="rows[<?= $hourIndex ?>][day_of_week]" value="<?= e($day) ?>">
                        <label>Status<select name="rows[<?= $hourIndex ?>][state]"><option value="open"<?= sbp_selected($state, 'open') ?>>Open</option><option value="closed"<?= sbp_selected($state, 'closed') ?>>Closed</option><option value="24_hours"<?= sbp_selected($state, '24_hours') ?>>24 hours</option></select></label>
                        <label>Opens<input type="time" name="rows[<?= $hourIndex ?>][opens_at]" value="<?= e(substr((string) ($row['opens_at'] ?? ''), 0, 5)) ?>"></label>
                        <label>Closes<input type="time" name="rows[<?= $hourIndex ?>][closes_at]" value="<?= e(substr((string) ($row['closes_at'] ?? ''), 0, 5)) ?>"></label>
                        <label>Range order<input type="number" min="1" name="rows[<?= $hourIndex ?>][time_range_order]" value="<?= e($row['time_range_order'] ?? (count($rowsForDay))) ?>"></label>
                    </div>
                    <?= sbp_existing_row_controls($hourIndex, $row) ?>
                    <?php $hourIndex++; ?>
                <?php endforeach; ?></fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'hours') ?>
            <?= ui_button('Save weekly hours') ?>
        </form>
    </details>

    <?php $exceptionRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'exceptions', 'exceptions', $activeSection, $submittedPayload), 'exceptions', $activeSection); $exceptionRows[] = ['is_closed' => true]; ?>
    <details class="business-switcher"<?= sbp_section_open('exceptions', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Hour exceptions</strong></summary>
        <p class="muted">Optional holiday and special-date hours.</p>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_exceptions">
            <?php foreach ($exceptionRows as $index => $row): $state = !empty($row['is_closed']) ? 'closed' : (!empty($row['is_24_hours']) ? '24_hours' : 'open'); ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved exception' : 'New exception' ?></legend>
                    <div class="form-grid">
                        <label>Date<input type="date" name="rows[<?= $index ?>][exception_date]" value="<?= e($row['exception_date'] ?? '') ?>"></label>
                        <label>Label<input name="rows[<?= $index ?>][label]" maxlength="150" value="<?= e($row['label'] ?? '') ?>"></label>
                        <label>Status<select name="rows[<?= $index ?>][state]"><option value="open"<?= sbp_selected($state, 'open') ?>>Open</option><option value="closed"<?= sbp_selected($state, 'closed') ?>>Closed</option><option value="24_hours"<?= sbp_selected($state, '24_hours') ?>>24 hours</option></select></label>
                        <label>Opens<input type="time" name="rows[<?= $index ?>][opens_at]" value="<?= e(substr((string) ($row['opens_at'] ?? ''), 0, 5)) ?>"></label>
                        <label>Closes<input type="time" name="rows[<?= $index ?>][closes_at]" value="<?= e(substr((string) ($row['closes_at'] ?? ''), 0, 5)) ?>"></label>
                        <label>Range order<input type="number" min="1" name="rows[<?= $index ?>][time_range_order]" value="<?= e($row['time_range_order'] ?? 1) ?>"></label>
                    </div><?= sbp_existing_row_controls($index, $row) ?>
                </fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'exceptions') ?><?= ui_button('Save hour exceptions') ?>
        </form>
    </details>

    <?php $faqRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'faqs', 'faqs', $activeSection, $submittedPayload), 'faqs', $activeSection); $faqRows[] = ['channel_scope' => 'all', 'is_active' => true, 'sort_order' => count($faqRows)]; ?>
    <details class="business-switcher"<?= sbp_section_open('faqs', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Frequently asked questions</strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_faqs">
            <?php foreach ($faqRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved FAQ' : 'New FAQ' ?></legend>
                    <label>Question<input name="rows[<?= $index ?>][question]" maxlength="500" value="<?= e($row['question'] ?? '') ?>"></label>
                    <label>Answer<textarea name="rows[<?= $index ?>][answer]" rows="3" maxlength="5000"><?= e($row['answer'] ?? '') ?></textarea></label>
                    <div class="form-grid"><label>Channel<select name="rows[<?= $index ?>][channel_scope]"><?php foreach (['all', 'website', 'voice', 'sms', 'chat'] as $scope): ?><option value="<?= e($scope) ?>"<?= sbp_selected($row['channel_scope'] ?? 'all', $scope) ?>><?= e(SharedBusinessProfileUi::statusLabel($scope)) ?></option><?php endforeach; ?></select></label><label>Sort order<input type="number" min="0" name="rows[<?= $index ?>][sort_order]" value="<?= e($row['sort_order'] ?? $index) ?>"></label></div>
                    <label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active FAQ</span></label>
                    <?= sbp_existing_row_controls($index, $row) ?>
                </fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'faqs') ?><?= ui_button('Save FAQs') ?>
        </form>
    </details>

    <?php $pricingRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'pricing_guidance', 'pricing_guidance', $activeSection, $submittedPayload), 'pricing_guidance', $activeSection); $pricingRows[] = ['guidance_type' => 'general_guidance', 'currency_code' => 'USD', 'is_active' => true, 'sort_order' => count($pricingRows)]; ?>
    <details class="business-switcher"<?= sbp_section_open('pricing_guidance', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Pricing guidance <span class="muted">(optional)</span></strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_pricing_guidance">
            <?php foreach ($pricingRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved guidance' : 'New guidance' ?></legend>
                    <div class="form-grid"><label>Type<select name="rows[<?= $index ?>][guidance_type]"><?php foreach (['starting_price','service_call_fee','estimate_policy','deposit_policy','financing','disclaimer','prohibited_statement','general_guidance'] as $type): ?><option value="<?= e($type) ?>"<?= sbp_selected($row['guidance_type'] ?? '', $type) ?>><?= e(SharedBusinessProfileUi::statusLabel($type)) ?></option><?php endforeach; ?></select></label><label>Title<input name="rows[<?= $index ?>][title]" maxlength="150" value="<?= e($row['title'] ?? '') ?>"></label></div>
                    <label>Approved guidance<textarea name="rows[<?= $index ?>][guidance_text]" rows="3" maxlength="5000"><?= e($row['guidance_text'] ?? '') ?></textarea></label>
                    <div class="form-grid"><label>Minimum amount<input type="number" min="0" step="0.01" name="rows[<?= $index ?>][amount_min]" value="<?= e($row['amount_min'] ?? '') ?>"></label><label>Maximum amount<input type="number" min="0" step="0.01" name="rows[<?= $index ?>][amount_max]" value="<?= e($row['amount_max'] ?? '') ?>"></label><label>Currency<input name="rows[<?= $index ?>][currency_code]" maxlength="3" value="<?= e($row['currency_code'] ?? 'USD') ?>"></label><label>Sort order<input type="number" min="0" name="rows[<?= $index ?>][sort_order]" value="<?= e($row['sort_order'] ?? $index) ?>"></label></div>
                    <label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active guidance</span></label><?= sbp_existing_row_controls($index, $row) ?>
                </fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'pricing_guidance') ?><?= ui_button('Save pricing guidance') ?>
        </form>
    </details>

    <?php $appointmentFacts = $activeSection === 'appointment_settings' && is_array($submittedPayload) ? $submittedPayload : $facts; ?>
    <details class="business-switcher"<?= sbp_section_open('appointment_settings', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Appointment settings</strong></summary>
        <p class="muted">These settings describe request policy only. They do not create scheduling or calendar availability.</p>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_appointment_settings">
            <label class="checkbox-line"><input type="checkbox" name="appointment_requests_enabled" value="1"<?= sbp_checked($appointmentFacts['appointment_requests_enabled'] ?? false) ?>><span>Accept appointment requests</span></label>
            <label class="checkbox-line"><input type="checkbox" name="automatic_booking_enabled" value="1"<?= sbp_checked($appointmentFacts['automatic_booking_enabled'] ?? false) ?>><span>Automatic booking configured for future use</span></label>
            <label class="checkbox-line"><input type="checkbox" name="emergency_service_enabled" value="1"<?= sbp_checked($appointmentFacts['emergency_service_enabled'] ?? false) ?>><span>Emergency service is offered</span></label>
            <div class="form-grid"><label>Minimum notice (minutes)<input type="number" min="0" max="525600" name="minimum_notice_minutes" value="<?= e($appointmentFacts['minimum_notice_minutes'] ?? '') ?>"></label><label>Default duration (minutes)<input type="number" min="1" max="1440" name="default_appointment_duration_minutes" value="<?= e($appointmentFacts['default_appointment_duration_minutes'] ?? '') ?>"></label></div>
            <?= sbp_error_for($fieldErrors, 'appointment_requests_enabled') ?><?= ui_button('Save appointment settings') ?>
        </form>
    </details>

    <?php $appointmentRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'appointment_rules', 'appointment_rules', $activeSection, $submittedPayload), 'appointment_rules', $activeSection); $appointmentRows[] = ['rule_type' => 'general', 'is_active' => true, 'sort_order' => count($appointmentRows)]; ?>
    <details class="business-switcher"<?= sbp_section_open('appointment_rules', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Appointment rules</strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_appointment_rules">
            <?php foreach ($appointmentRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved rule' : 'New rule' ?></legend><div class="form-grid"><label>Rule type<select name="rows[<?= $index ?>][rule_type]"><?php foreach (['general','request_only','automatic_booking','minimum_notice','preparation','service_eligibility'] as $type): ?><option value="<?= e($type) ?>"<?= sbp_selected($row['rule_type'] ?? '', $type) ?>><?= e(SharedBusinessProfileUi::statusLabel($type)) ?></option><?php endforeach; ?></select></label><?= sbp_rule_reference_inputs($index, $row, $profile) ?><label>Sort order<input type="number" min="0" name="rows[<?= $index ?>][sort_order]" value="<?= e($row['sort_order'] ?? $index) ?>"></label></div><label>Approved rule<textarea name="rows[<?= $index ?>][rule_text]" rows="3" maxlength="5000"><?= e($row['rule_text'] ?? '') ?></textarea></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active rule</span></label><?= sbp_existing_row_controls($index, $row) ?></fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'appointment_rules') ?><?= ui_button('Save appointment rules') ?>
        </form>
    </details>

    <?php $transferRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'transfer_rules', 'transfer_rules', $activeSection, $submittedPayload), 'transfer_rules', $activeSection); $transferRows[] = ['fallback_behavior' => 'create_leadhub_task', 'priority' => 100, 'maximum_attempts' => 1, 'applies_during_business_hours' => true, 'applies_after_hours' => true, 'is_active' => true]; ?>
    <details class="business-switcher"<?= sbp_section_open('transfer_rules', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Transfer rules</strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_transfer_rules">
            <?php foreach ($transferRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved transfer rule' : 'New transfer rule' ?></legend><div class="form-grid"><label>Name<input name="rows[<?= $index ?>][name]" maxlength="150" value="<?= e($row['name'] ?? '') ?>"></label><label>Primary number<input type="tel" name="rows[<?= $index ?>][transfer_number]" value="<?= e($row['transfer_number'] ?? '') ?>"></label><label>Backup number<input type="tel" name="rows[<?= $index ?>][backup_transfer_number]" value="<?= e($row['backup_transfer_number'] ?? '') ?>"></label><?= sbp_rule_reference_inputs($index, $row, $profile) ?></div><label>Condition<textarea name="rows[<?= $index ?>][condition_text]" rows="2" maxlength="5000"><?= e($row['condition_text'] ?? '') ?></textarea></label><div class="form-grid"><label>Priority<input type="number" min="1" name="rows[<?= $index ?>][priority]" value="<?= e($row['priority'] ?? 100) ?>"></label><label>Maximum attempts<input type="number" min="1" max="10" name="rows[<?= $index ?>][maximum_attempts]" value="<?= e($row['maximum_attempts'] ?? 1) ?>"></label><label>Fallback<select name="rows[<?= $index ?>][fallback_behavior]"><?php foreach (['create_leadhub_task','collect_message','owner_notification','voicemail','end_conversation'] as $fallback): ?><option value="<?= e($fallback) ?>"<?= sbp_selected($row['fallback_behavior'] ?? '', $fallback) ?>><?= e(SharedBusinessProfileUi::statusLabel($fallback)) ?></option><?php endforeach; ?></select></label></div><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][applies_during_business_hours]" value="1"<?= sbp_checked($row['applies_during_business_hours'] ?? true) ?>><span>During business hours</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][applies_after_hours]" value="1"<?= sbp_checked($row['applies_after_hours'] ?? true) ?>><span>After hours</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active rule</span></label><?= sbp_existing_row_controls($index, $row) ?></fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'transfer_rules') ?><?= ui_button('Save transfer rules') ?>
        </form>
    </details>

    <?php $escalationRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'escalation_rules', 'escalation_rules', $activeSection, $submittedPayload), 'escalation_rules', $activeSection); $escalationRows[] = ['rule_type' => 'owner_alert', 'urgency_level' => 'normal', 'priority' => 100, 'requires_owner_alert' => true, 'is_active' => true]; ?>
    <details class="business-switcher"<?= sbp_section_open('escalation_rules', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Escalation rules</strong></summary>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_escalation_rules">
            <?php foreach ($escalationRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved escalation rule' : 'New escalation rule' ?></legend><div class="form-grid"><label>Name<input name="rows[<?= $index ?>][name]" maxlength="150" value="<?= e($row['name'] ?? '') ?>"></label><label>Type<select name="rows[<?= $index ?>][rule_type]"><?php foreach (['immediate_transfer','owner_alert','prohibited_ai_handling','disclaimer_language'] as $type): ?><option value="<?= e($type) ?>"<?= sbp_selected($row['rule_type'] ?? '', $type) ?>><?= e(SharedBusinessProfileUi::statusLabel($type)) ?></option><?php endforeach; ?></select></label><label>Urgency<select name="rows[<?= $index ?>][urgency_level]"><?php foreach (['low','normal','high','urgent','emergency'] as $urgency): ?><option value="<?= e($urgency) ?>"<?= sbp_selected($row['urgency_level'] ?? '', $urgency) ?>><?= e(SharedBusinessProfileUi::statusLabel($urgency)) ?></option><?php endforeach; ?></select></label><?= sbp_rule_reference_inputs($index, $row, $profile) ?><label>Priority<input type="number" min="1" name="rows[<?= $index ?>][priority]" value="<?= e($row['priority'] ?? 100) ?>"></label></div><label>Condition<textarea name="rows[<?= $index ?>][condition_text]" rows="2" maxlength="5000"><?= e($row['condition_text'] ?? '') ?></textarea></label><label>Approved instruction<textarea name="rows[<?= $index ?>][instruction_text]" rows="2" maxlength="5000"><?= e($row['instruction_text'] ?? '') ?></textarea></label><label>Disclaimer<textarea name="rows[<?= $index ?>][disclaimer_text]" rows="2" maxlength="5000"><?= e($row['disclaimer_text'] ?? '') ?></textarea></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][requires_immediate_transfer]" value="1"<?= sbp_checked($row['requires_immediate_transfer'] ?? false) ?>><span>Immediate transfer required</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][requires_owner_alert]" value="1"<?= sbp_checked($row['requires_owner_alert'] ?? false) ?>><span>Owner alert required</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active rule</span></label><?= sbp_existing_row_controls($index, $row) ?></fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'escalation_rules') ?><?= ui_button('Save escalation rules') ?>
        </form>
    </details>

    <?php $notificationRows = sbp_mark_submitted_rows(sbp_collection_rows($profile, 'notification_preferences', 'notification_preferences', $activeSection, $submittedPayload), 'notification_preferences', $activeSection); $notificationRows[] = ['notification_type' => 'new_lead', 'email_enabled' => true, 'in_app_enabled' => true, 'is_active' => true]; ?>
    <details class="business-switcher"<?= sbp_section_open('notification_preferences', $readiness, $activeSection) ? ' open' : '' ?>>
        <summary><strong>Notification preferences</strong></summary>
        <p class="muted">These settings store destinations only; they do not send notifications yet.</p>
        <form method="post" action="business-profile.php" class="form-stack">
            <?= Csrf::input($csrfScope) ?><input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>"><input type="hidden" name="profile_action" value="save_notification_preferences">
            <?php foreach ($notificationRows as $index => $row): ?>
                <fieldset><legend><?= (int) ($row['id'] ?? 0) > 0 ? 'Saved preference' : 'New preference' ?></legend><div class="form-grid"><label>Notification type<select name="rows[<?= $index ?>][notification_type]"><?php foreach (['new_lead','missed_call','transfer_failed','urgent_lead','new_message','appointment_request','unresolved_lead_summary'] as $type): ?><option value="<?= e($type) ?>"<?= sbp_selected($row['notification_type'] ?? '', $type) ?>><?= e(SharedBusinessProfileUi::statusLabel($type)) ?></option><?php endforeach; ?></select></label><label>Email destination<input type="email" name="rows[<?= $index ?>][destination_email]" value="<?= e($row['destination_email'] ?? '') ?>"></label><label>SMS destination<input type="tel" name="rows[<?= $index ?>][destination_phone]" value="<?= e($row['destination_phone'] ?? '') ?>"></label></div><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][email_enabled]" value="1"<?= sbp_checked($row['email_enabled'] ?? false) ?>><span>Email enabled</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][sms_enabled]" value="1"<?= sbp_checked($row['sms_enabled'] ?? false) ?>><span>SMS enabled</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][in_app_enabled]" value="1"<?= sbp_checked($row['in_app_enabled'] ?? true) ?>><span>In-app enabled</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][daily_summary_enabled]" value="1"<?= sbp_checked($row['daily_summary_enabled'] ?? false) ?>><span>Daily summary</span></label><label class="checkbox-line"><input type="checkbox" name="rows[<?= $index ?>][is_active]" value="1"<?= sbp_checked($row['is_active'] ?? true) ?>><span>Active preference</span></label><?= sbp_existing_row_controls($index, $row) ?></fieldset>
            <?php endforeach; ?>
            <?= sbp_error_for($fieldErrors, 'notification_preferences') ?><?= ui_button('Save notification preferences') ?>
        </form>
    </details>

<?php endif; ?>
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
