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

    $businessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
    $business = $businessId > 0
        ? BusinessFoundation::businessForUser($businessId, (int) $user['id'])
        : BusinessFoundation::firstBusinessForUser((int) $user['id']);

    if ($business === null) {
        header('Location: business-create.php');
        exit;
    }

    $businessId = (int) $business['id'];
    $legalStructures = BusinessFoundation::legalStructures();
    $categories = BusinessFoundation::categories();
    $allSubServices = BusinessFoundation::subServices();
    $selectedSubServiceIds = BusinessFoundation::selectedSubServiceIds($businessId);
    $notice = '';
    $errors = [];
} catch (Throwable $exception) {
    $user = null;
    $business = null;
    $legalStructures = [];
    $categories = [];
    $allSubServices = [];
    $selectedSubServiceIds = [];
    $notice = '';
    $errors = ['Business profile could not be loaded. Check the environment and database setup.'];
}

$serviceCategoryById = [];
foreach ($allSubServices as $service) {
    $serviceCategoryById[(int) $service['id']] = (int) $service['category_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $business !== null) {
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
        'primary_category_id' => 'Primary Category',
    ];

    foreach ($required as $field => $label) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $errors[] = "{$label} is required.";
        }
    }

    if (trim((string) ($_POST['email'] ?? '')) !== '' && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid business email.';
    }

    $postedServices = $_POST['sub_services'] ?? [];
    if (!is_array($postedServices) || count($postedServices) === 0) {
        $errors[] = 'Select at least one service.';
    }

    $categoryId = (int) ($_POST['primary_category_id'] ?? 0);
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
        try {
            BusinessFoundation::saveBusinessInfo((int) $user['id'], $_POST, $businessId);
            BusinessFoundation::saveServices($businessId, (int) $user['id'], $categoryId, $postedServices);
            $business = BusinessFoundation::businessForUser($businessId, (int) $user['id']);
            $selectedSubServiceIds = BusinessFoundation::selectedSubServiceIds($businessId);
            $notice = 'Business profile saved.';
        } catch (Throwable $exception) {
            $errors[] = 'Business profile could not be saved. Check the database setup and try again.';
        }
    }
}

$subServicesByCategory = [];
foreach ($allSubServices as $service) {
    $subServicesByCategory[(int) $service['category_id']][] = $service;
}

function profile_value(?array $business, string $key, string $default = ''): string
{
    if (isset($_POST[$key])) {
        return (string) $_POST[$key];
    }

    return (string) ($business[$key] ?? $default);
}

function profile_checked($value, array $selected): string
{
    return in_array((int) $value, array_map('intval', $selected), true) ? ' checked' : '';
}

$pageTitle = 'Business Profile - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="dashboard-card dashboard-card--wide">
    <p class="eyebrow">Business profile</p>
    <h1><?= $business ? e($business['business_name']) : 'Business Profile' ?></h1>
    <p class="muted">Manage the business details, category, and services used by Lead Hub.</p>
</section>

<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
    <?= ui_alert($error, 'error') ?>
<?php endforeach; ?>

<?php if ($business): ?>
    <form method="post" action="business.php" class="dashboard-card form-stack">
        <input type="hidden" name="business_id" value="<?= e($businessId) ?>">

        <div class="form-grid">
            <label>Business Name
                <input name="business_name" required value="<?= e(profile_value($business, 'business_name')) ?>">
            </label>
            <label>Legal Name
                <input name="legal_name" required value="<?= e(profile_value($business, 'legal_name')) ?>">
            </label>
            <label>Business Email
                <input name="email" type="email" required value="<?= e(profile_value($business, 'email')) ?>">
            </label>
            <label>Business Phone
                <input name="phone" required value="<?= e(profile_value($business, 'phone')) ?>">
            </label>
            <label>Address Line 1
                <input name="address_line_1" required value="<?= e(profile_value($business, 'address_line_1')) ?>">
            </label>
            <label>Address Line 2
                <input name="address_line_2" value="<?= e(profile_value($business, 'address_line_2')) ?>">
            </label>
            <label>City
                <input name="city" required value="<?= e(profile_value($business, 'city')) ?>">
            </label>
            <label>State
                <input name="state" required value="<?= e(profile_value($business, 'state')) ?>">
            </label>
            <label>Postal Code
                <input name="postal_code" required value="<?= e(profile_value($business, 'postal_code')) ?>">
            </label>
            <label>Country
                <input name="country" required value="<?= e(profile_value($business, 'country', 'US')) ?>">
            </label>
            <label>Legal Structure
                <select name="legal_structure_id" required>
                    <option value="">Select legal structure</option>
                    <?php foreach ($legalStructures as $structure): ?>
                        <option value="<?= e($structure['id']) ?>" <?= (int) profile_value($business, 'legal_structure_id') === (int) $structure['id'] ? 'selected' : '' ?>>
                            <?= e($structure['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Primary Category
                <select name="primary_category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= (int) profile_value($business, 'primary_category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_public_physical_location" value="1" <?= (int) profile_value($business, 'is_public_physical_location', '1') === 1 ? 'checked' : '' ?>>
                Physical Location
            </label>
        </div>

        <div class="service-groups">
            <?php foreach ($categories as $category): ?>
                <fieldset>
                    <legend><?= e($category['name']) ?></legend>
                    <?php foreach ($subServicesByCategory[(int) $category['id']] ?? [] as $service): ?>
                        <label class="checkbox-line">
                            <input type="checkbox" name="sub_services[]" value="<?= e($service['id']) ?>"<?= profile_checked($service['id'], $_POST['sub_services'] ?? $selectedSubServiceIds) ?>>
                            <?= e($service['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="button-row">
            <?= ui_button('Back to dashboard', 'dashboard.php', 'secondary') ?>
            <?= ui_button('View billing', 'billing.php', 'secondary') ?>
            <?= ui_button('View email', 'email.php', 'secondary') ?>
            <?= ui_button('Save business profile') ?>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../../private/views/footer.php'; ?>
