<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/BusinessFoundation.php';

Session::requireAuth('login.php');

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }
} catch (Throwable $exception) {
    $user = null;
}

if ($user === null) {
    $pageTitle = 'Create Business - Ultimate Back Office';
    $bodyClass = 'accounts-dashboard';
    require __DIR__ . '/../../private/views/header.php';
    ?>
    <div class="error">Business onboarding could not be loaded. Check the environment and database setup.</div>
    <?php
    require __DIR__ . '/../../private/views/footer.php';
    exit;
}

$step = $_POST['step'] ?? $_GET['step'] ?? 'business_info';
$allowedSteps = ['business_info', 'services', 'modules', 'confirmation'];

if (!in_array($step, $allowedSteps, true)) {
    $step = 'business_info';
}

$businessId = isset($_POST['business_id']) ? (int) $_POST['business_id'] : (int) ($_GET['business_id'] ?? 0);
$business = null;
$errors = [];
$notice = '';

try {
    if ($businessId > 0) {
        $business = BusinessFoundation::businessForUser($businessId, (int) $user['id']);

        if ($business === null) {
            $errors[] = 'That business could not be found for this account.';
            $businessId = 0;
        }
    }

    $legalStructures = BusinessFoundation::legalStructures();
    $categories = BusinessFoundation::categories();
    $allSubServices = BusinessFoundation::subServices();
    $availableModules = BusinessFoundation::availableModules();
    $selectedSubServiceIds = $businessId > 0 ? BusinessFoundation::selectedSubServiceIds($businessId) : [];
    $activeModules = $businessId > 0 ? BusinessFoundation::activeModules($businessId) : [];
    $selectedModuleKeys = BusinessFoundation::selectedModuleKeysFromActiveModules($activeModules);
} catch (Throwable $exception) {
    $legalStructures = [];
    $categories = [];
    $allSubServices = [];
    $availableModules = [];
    $selectedSubServiceIds = [];
    $activeModules = [];
    $selectedModuleKeys = [];
    $errors[] = 'Business onboarding data could not be loaded.';
}

$serviceCategoryById = [];
foreach ($allSubServices as $service) {
    $serviceCategoryById[(int) $service['id']] = (int) $service['category_id'];
}

function business_onboarding_debug_enabled(): bool
{
    try {
        return (bool) Database::config('APP_DEBUG', false);
    } catch (Throwable $exception) {
        return false;
    }
}

function report_business_onboarding_exception(Throwable $exception, array &$errors): void
{
    if (!business_onboarding_debug_enabled()) {
        return;
    }

    error_log('Business onboarding exception: ' . (string) $exception);

    $errors[] = 'Debug exception: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($errors) === 0) {
    try {
        if ($step === 'business_info') {
            $required = [
                'business_name' => 'Business Name',
                'legal_name' => 'Legal Name',
                'email' => 'Business Email',
                'phone' => 'Business Phone',
                'address_line_1' => 'Address Line 1',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'Country',
                'legal_structure_id' => 'Legal Structure',
            ];

            foreach ($required as $field => $label) {
                if (trim((string) ($_POST[$field] ?? '')) === '') {
                    $errors[] = "{$label} is required.";
                }
            }

            if (trim((string) ($_POST['email'] ?? '')) !== '' && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid business email.';
            }

            if (count($errors) === 0) {
                $businessId = BusinessFoundation::saveBusinessInfo((int) $user['id'], $_POST, $businessId > 0 ? $businessId : null);
                header('Location: business-create.php?step=services&business_id=' . $businessId);
                exit;
            }
        }

        if ($step === 'services') {
            if ($business === null) {
                $errors[] = 'Complete business information first.';
            }

            $categoryId = (int) ($_POST['primary_category_id'] ?? 0);
            $postedServices = $_POST['sub_services'] ?? [];

            if ($categoryId <= 0) {
                $errors[] = 'Select one primary category.';
            }

            if (!is_array($postedServices) || count($postedServices) === 0) {
                $errors[] = 'Select at least one service.';
            }

            $validPostedServices = 0;
            foreach ((array) $postedServices as $serviceId) {
                if (($serviceCategoryById[(int) $serviceId] ?? 0) === $categoryId) {
                    $validPostedServices++;
                }
            }

            if ($categoryId > 0 && $validPostedServices === 0) {
                $errors[] = 'Select at least one service from the primary category.';
            }

            if (count($errors) === 0) {
                BusinessFoundation::saveServices($businessId, (int) $user['id'], $categoryId, $postedServices);
                header('Location: business-create.php?step=modules&business_id=' . $businessId);
                exit;
            }
        }

        if ($step === 'modules') {
            if ($business === null) {
                $errors[] = 'Complete business information first.';
            }

            $postedModules = $_POST['modules'] ?? [];
            $postedModules = is_array($postedModules) ? $postedModules : [];

            if (count($postedModules) === 0) {
                $errors[] = 'Select at least one module or platform tier.';
            }

            if (count($errors) === 0) {
                BusinessFoundation::saveModules($businessId, (int) $user['id'], $postedModules);
                header('Location: business-create.php?step=confirmation&business_id=' . $businessId);
                exit;
            }
        }

        if ($step === 'confirmation' && isset($_POST['complete_onboarding'])) {
            if ($business === null) {
                $errors[] = 'Complete business information first.';
            }

            if (count($errors) === 0) {
                BusinessFoundation::completeOnboarding($businessId, (int) $user['id']);
                header('Location: dashboard.php');
                exit;
            }
        }
    } catch (Throwable $exception) {
        $errors[] = 'Business onboarding could not be saved. Check the database setup and try again.';
        report_business_onboarding_exception($exception, $errors);
    }
}

if ($businessId > 0) {
    try {
        $business = BusinessFoundation::businessForUser($businessId, (int) $user['id']);
        $selectedSubServiceIds = BusinessFoundation::selectedSubServiceIds($businessId);
        $activeModules = BusinessFoundation::activeModules($businessId);
        $selectedModuleKeys = BusinessFoundation::selectedModuleKeysFromActiveModules($activeModules);
        $selectedServices = BusinessFoundation::selectedServices($businessId);
    } catch (Throwable $exception) {
        $selectedServices = [];
    }
} else {
    $selectedServices = [];
}

$subServicesByCategory = [];
foreach ($allSubServices as $service) {
    $subServicesByCategory[(int) $service['category_id']][] = $service;
}

$categoryNames = [];
foreach ($categories as $category) {
    $categoryNames[(int) $category['id']] = $category['name'];
}

function form_value(?array $business, string $key, string $default = ''): string
{
    if (isset($_POST[$key])) {
        return (string) $_POST[$key];
    }

    return (string) ($business[$key] ?? $default);
}

function is_checked($value, array $selected): string
{
    return in_array((int) $value, array_map('intval', $selected), true) ? ' checked' : '';
}

function module_checked(string $moduleKey, array $selected): string
{
    return in_array($moduleKey, $selected, true) ? ' checked' : '';
}

$pageTitle = 'Create Business - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Business onboarding</p>
    <h1>Create Business</h1>
    <p class="muted">Build the business profile, service selections, and module access foundation for Lead Hub.</p>
</section>

<?php if ($notice !== ''): ?>
    <div class="notice"><?= e($notice) ?></div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endforeach; ?>

<nav class="step-nav" aria-label="Business onboarding steps">
    <span class="<?= $step === 'business_info' ? 'is-active' : '' ?>">1. Business</span>
    <span class="<?= $step === 'services' ? 'is-active' : '' ?>">2. Services</span>
    <span class="<?= $step === 'modules' ? 'is-active' : '' ?>">3. Modules</span>
    <span class="<?= $step === 'confirmation' ? 'is-active' : '' ?>">4. Confirm</span>
</nav>

<?php if ($step === 'business_info'): ?>
    <form method="post" action="business-create.php" class="dashboard-card form-stack">
        <input type="hidden" name="step" value="business_info">
        <?php if ($businessId > 0): ?>
            <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <label>Business Name
                <input name="business_name" required value="<?= e(form_value($business, 'business_name')) ?>">
            </label>
            <label>Legal Name
                <input name="legal_name" required value="<?= e(form_value($business, 'legal_name')) ?>">
            </label>
            <label>Business Email
                <input name="email" type="email" required value="<?= e(form_value($business, 'email')) ?>">
            </label>
            <label>Business Phone
                <input name="phone" required value="<?= e(form_value($business, 'phone')) ?>">
            </label>
            <label>Address Line 1
                <input name="address_line_1" required value="<?= e(form_value($business, 'address_line_1')) ?>">
            </label>
            <label>Address Line 2
                <input name="address_line_2" value="<?= e(form_value($business, 'address_line_2')) ?>">
            </label>
            <label>City
                <input name="city" required value="<?= e(form_value($business, 'city')) ?>">
            </label>
            <label>State
                <input name="state" required value="<?= e(form_value($business, 'state')) ?>">
            </label>
            <label>Postal Code
                <input name="postal_code" required value="<?= e(form_value($business, 'postal_code')) ?>">
            </label>
            <label>Country
                <input name="country" required value="<?= e(form_value($business, 'country', 'US')) ?>">
            </label>
            <label>Legal Structure
                <select name="legal_structure_id" required>
                    <option value="">Select legal structure</option>
                    <?php foreach ($legalStructures as $structure): ?>
                        <option value="<?= e($structure['id']) ?>" <?= (int) form_value($business, 'legal_structure_id') === (int) $structure['id'] ? 'selected' : '' ?>>
                            <?= e($structure['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_public_physical_location" value="1" <?= (int) form_value($business, 'is_public_physical_location', '1') === 1 ? 'checked' : '' ?>>
                Physical Location
            </label>
        </div>

        <button type="submit">Save and continue</button>
    </form>
<?php elseif ($step === 'services'): ?>
    <form method="post" action="business-create.php" class="dashboard-card form-stack">
        <input type="hidden" name="step" value="services">
        <input type="hidden" name="business_id" value="<?= e($businessId) ?>">

        <label>Primary Category
            <select name="primary_category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category): ?>
                    <?php $selectedCategory = (int) ($_POST['primary_category_id'] ?? ($business['primary_category_id'] ?? 0)); ?>
                    <option value="<?= e($category['id']) ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="service-groups">
            <?php foreach ($categories as $category): ?>
                <fieldset>
                    <legend><?= e($category['name']) ?></legend>
                    <?php foreach ($subServicesByCategory[(int) $category['id']] ?? [] as $service): ?>
                        <label class="checkbox-line">
                            <input type="checkbox" name="sub_services[]" value="<?= e($service['id']) ?>"<?= is_checked($service['id'], $_POST['sub_services'] ?? $selectedSubServiceIds) ?>>
                            <?= e($service['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="button-row">
            <a class="button-link button-link--secondary" href="business-create.php?step=business_info&business_id=<?= e($businessId) ?>">Back</a>
            <button type="submit">Save and continue</button>
        </div>
    </form>
<?php elseif ($step === 'modules'): ?>
    <form method="post" action="business-create.php" class="dashboard-card form-stack">
        <input type="hidden" name="step" value="modules">
        <input type="hidden" name="business_id" value="<?= e($businessId) ?>">

        <div class="module-grid">
            <?php foreach ($availableModules as $module): ?>
                <label class="module-option">
                    <input type="checkbox" name="modules[]" value="<?= e($module['module_key']) ?>"<?= module_checked($module['module_key'], $_POST['modules'] ?? $selectedModuleKeys) ?>>
                    <strong><?= e($module['name']) ?></strong>
                    <?php if ($module['module_key'] === 'kyn'): ?>
                        <span>KYN requires SSP. If SSP is not selected, it will be included automatically.</span>
                    <?php elseif ($module['module_key'] === 'full_os'): ?>
                        <span>Activates Lead Hub and all current modules.</span>
                    <?php elseif ($module['module_key'] === 'enterprise'): ?>
                        <span>Activates Full OS access for the business.</span>
                    <?php else: ?>
                        <span>Includes Lead Hub access.</span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="button-row">
            <a class="button-link button-link--secondary" href="business-create.php?step=services&business_id=<?= e($businessId) ?>">Back</a>
            <button type="submit">Save and continue</button>
        </div>
    </form>
<?php else: ?>
    <section class="dashboard-card">
        <h2>Business Summary</h2>
        <?php if ($business): ?>
            <dl class="summary-list">
                <div><dt>Business</dt><dd><?= e($business['business_name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($business['email']) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($business['phone']) ?></dd></div>
                <div><dt>Address</dt><dd><?= e($business['address_line_1']) ?>, <?= e($business['city']) ?>, <?= e($business['state']) ?> <?= e($business['postal_code']) ?></dd></div>
                <div><dt>Category</dt><dd><?= e($categoryNames[(int) ($business['primary_category_id'] ?? 0)] ?? 'Not selected') ?></dd></div>
            </dl>

            <h3>Selected Services</h3>
            <div class="pill-list">
                <?php foreach ($selectedServices as $service): ?>
                    <span><?= e($service['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <h3>Selected Modules</h3>
            <div class="pill-list">
                <?php foreach ($activeModules as $module): ?>
                    <span><?= e($module['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <form method="post" action="business-create.php" class="button-row">
                <input type="hidden" name="step" value="confirmation">
                <input type="hidden" name="business_id" value="<?= e($businessId) ?>">
                <a class="button-link button-link--secondary" href="business-create.php?step=modules&business_id=<?= e($businessId) ?>">Back</a>
                <button type="submit" name="complete_onboarding" value="1">Complete onboarding</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../../private/views/footer.php'; ?>
