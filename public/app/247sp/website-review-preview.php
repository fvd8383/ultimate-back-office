<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/SiteCustomerPreview.php';

foreach (SiteCustomerPreview::headers() as $name => $value) header($name . ': ' . $value);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable) {
    $accountsBaseUrl = '../../accounts';
}
Session::requireAuth($accountsBaseUrl . '/login.php');
// Session startup may emit its own cache policy; reassert the M5 private response contract.
foreach (SiteCustomerPreview::headers() as $name => $value) header($name . ': ' . $value);
$user = Auth::currentUser();
if ($user === null) {
    Session::logout();
    header('Location: ' . $accountsBaseUrl . '/login.php');
    exit;
}
$customerPreview = null;
try {
    if (array_diff(array_keys($_GET), ['business_id', 'request_id']) !== []) {
        throw new InvalidArgumentException('Unknown preview parameters.');
    }
    $customerPreview = SiteCustomerPreview::render(
        (int) $user['id'],
        SiteCustomerReviewWorkflow::positiveId($_GET['business_id'] ?? null),
        SiteCustomerReviewWorkflow::positiveId($_GET['request_id'] ?? null)
    );
} catch (Throwable) {
    http_response_code(404);
}
$pageTitle = 'Website Revision Preview - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user]);
require __DIR__ . '/../../../private/views/site-customer-preview.php';
application_shell_end();
require __DIR__ . '/../../../private/views/footer.php';
