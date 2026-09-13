<?php

declare(strict_types=1);
error_reporting(E_ALL);
require_once __DIR__ . '/support/WebsitePlatformM5BDatabase.php';
require_once __DIR__ . '/../private/classes/Csrf.php';
require_once __DIR__ . '/../private/classes/SiteReviewAdminWorkflow.php';
require_once __DIR__ . '/../private/classes/AdminPortal.php';
const SITE_PLATFORM_CSRF_SCOPE = 'admin-site-platform';
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ui_button(string $label): string { return '<button type="submit">' . e($label) . '</button>'; }
function ui_alert(string $message, string $type): string { return '<div>' . e($message) . '</div>'; }
function renderM5BV(array $customerReview): string { $saved = false; $reviewReceipt = null; ob_start(); require __DIR__ . '/../private/views/site-customer-review.php'; return (string) ob_get_clean(); }
function formsM5BV(string $html): array {
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); $forms = [];
    foreach ($dom->getElementsByTagName('form') as $form) {
        $fields = [];
        foreach ((new DOMXPath($dom))->query('.//input|.//select|.//textarea', $form) as $node) {
            $fields[$node->getAttribute('name')] = match ($node->tagName) {
                'textarea' => $node->textContent, 'select' => $node->getElementsByTagName('option')->item(0)->getAttribute('value'), default => $node->getAttribute('value'),
            };
        }
        $key = $fields['action'] === 'presentation_preference' ? $fields['action'] . ':' . $fields['target'] : $fields['action'];
        if (isset($forms[$key])) throw new RuntimeException('Duplicate rendered form would be lost by parser');
        $forms[$key] = $fields;
    }
    return $forms;
}
$assertions = 0;
function checkM5BV(bool $ok, string $message): void { global $assertions; $assertions++; if (!$ok) throw new RuntimeException($message); }
Session::start();
$db = WebsitePlatformM5BDatabase::fixture(); $db->addImage();
$html = renderM5BV(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)); $forms = formsM5BV($html);
checkM5BV(count($forms) === 6, 'Six forms include separate tone and emphasis preferences');
checkM5BV(array_values(array_unique(array_column($forms, 'action'))) === SiteCustomerReviewSession::ACTIONS, 'All five distinct actions remain represented');
foreach ($forms as $action => $fields) {
    checkM5BV(Csrf::validate($fields['csrf_token'], 'customer-website-manager'), 'Every actual form has scoped CSRF');
    foreach (['business_id', 'site_id', 'revision_id', 'request_id', 'actor_user_id', 'correlation_id', 'snapshot_hash'] as $secret) checkM5BV(!array_key_exists($secret, $fields), 'Browser cannot supply authoritative ' . $secret);
}
foreach (['Requesting changes ends this review', 'Internal review follows customer approval', 'does not change your preview', 'approving revision 1'] as $notice) checkM5BV(str_contains($html, $notice), 'Action consequence explained');
$dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); $xpath = new DOMXPath($dom);
foreach (['tone' => ['professional', 'friendly', 'concise'], 'emphasis' => ['services', 'trust', 'contact']] as $target => $allowed) {
    $nodes = $xpath->query('//form[.//input[@name="action" and @value="presentation_preference"]][.//input[@name="target" and @value="' . $target . '"]]');
    checkM5BV($nodes->length === 1, 'Exactly one rendered preference form per category');
    $node = $nodes->item(0);
    checkM5BV($xpath->query('.//*[@name="target"]', $node)->length === 1 && $xpath->query('.//input[@name="target" and @type="hidden"]', $node)->length === 1, 'Target is a single fixed hidden control');
    $values = array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($xpath->query('.//select[@name="value"]/option', $node)));
    checkM5BV($values === $allowed, 'All rendered options belong only to ' . $target);
    $actual = formsM5BV(renderM5BV(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)))['presentation_preference:' . $target];
    Csrf::requireValid($actual['csrf_token'], 'customer-website-manager');
    checkM5BV(SiteCustomerReviewWorkflow::submit(3, $actual, [], 1000)['mutated'], 'Actual rendered ' . $target . ' form succeeds');
    $forged = $actual; $forged['value'] = $target === 'tone' ? 'services' : 'professional';
    $snapshot = $db->read->snapshot();
    try { SiteCustomerReviewWorkflow::submit(3, $forged, [], 1000); throw new RuntimeException('Forged pair accepted'); }
    catch (SiteServiceException $exception) { checkM5BV($exception->classification() === 'invalid_request', 'Forged cross-category pair remains rejected'); }
    checkM5BV($snapshot === $db->read->snapshot(), 'Forged pair causes no write/event');
}
$form = $forms['feedback']; $form['text'] = 'Customer prose & "quotes"';
Csrf::requireValid($form['csrf_token'], 'customer-website-manager');
$receipt = SiteCustomerReviewWorkflow::submit(3, $form, [], 1000);
checkM5BV($receipt['mutated'], 'Actual rendered form submits through real workflow and service');
$review = SiteCustomerReviewWorkflow::workspaceWithForms(3, 50); $html = renderM5BV($review);
checkM5BV(str_contains($html, 'Customer prose &amp; &quot;quotes&quot;'), 'Accepted customer text escaped');
foreach (['METADATA-SENTINEL', 'INTERNAL-COMMENT', 'INTERNAL-REASON', 'CORRELATION-SENTINEL', 'actor_user_id', 'submission_key_hash', 'payload_hash', 'correlation_id', 'storage_key'] as $secret) checkM5BV(!str_contains($html, $secret), 'Customer HTML excludes private metadata');
$unsafe = $review; $unsafe['feedback'][0]['text'] = '<script>alert(1)</script>';
checkM5BV(str_contains(renderM5BV($unsafe), '&lt;script&gt;') && !str_contains(renderM5BV($unsafe), '<script>'), 'View escapes defense-in-depth injected prose');
$workspace = SiteReviewAdminWorkflow::workspace(1, 100);
$workspace['customer_submissions'][0]['text'] = '<img src=x onerror=alert(1)>';
ob_start(); require __DIR__ . '/../private/views/site-review.php'; $adminHtml = (string) ob_get_clean();
checkM5BV(str_contains($adminHtml, '&lt;img') && !str_contains($adminHtml, '<img src=x'), 'Internal timeline escaped');
foreach (['submission_key_hash', 'payload_hash', 'METADATA-SENTINEL'] as $secret) checkM5BV(!str_contains($adminHtml, $secret), 'Internal timeline has no raw metadata dump');
checkM5BV(formsM5BV(renderM5BV(SiteCustomerReviewWorkflow::workspaceWithForms(5, 50))) === [], 'Lower role sees no mutation forms');
$current = formsM5BV(renderM5BV(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)))['approve_revision'];
SiteCustomerReviewWorkflow::submit(3, $current, [], 1000);
$html = renderM5BV(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50));
checkM5BV(str_contains($html, 'Approved by customer; awaiting internal review.') && formsM5BV($html) === [], 'Terminal approval receipt replaces controls');
$token = Csrf::token('customer-website-manager');
foreach ([null, [], 'wrong', Csrf::token('admin-site-platform'), Csrf::token('shared-business-profile')] as $bad) checkM5BV(!Csrf::validate($bad, 'customer-website-manager'), 'Wrong and cross-scope CSRF rejected');
checkM5BV(Csrf::token('customer-website-manager') === $token, 'Rejected validation does not rotate token');
Csrf::rotate('customer-website-manager'); checkM5BV(!Csrf::validate($token, 'customer-website-manager'), 'Rotation invalidates old CSRF');

// Source guards supplement actual service/DOM execution; they are not HTTP/browser evidence.
$route = file_get_contents(__DIR__ . '/../public/app/247sp/website-manager.php');
$workflow = file_get_contents(__DIR__ . '/../private/classes/SiteCustomerReviewWorkflow.php');
$guard = file_get_contents(__DIR__ . '/../private/classes/SiteCustomerReviewGuard.php');
$policy = file_get_contents(__DIR__ . '/../private/classes/SiteAuthorizationPolicy.php');
foreach (["['GET', 'POST']", "header('Allow: GET, POST')", 'http_response_code(405)', "'legacy_save'", 'http_response_code(403)', "'invalid_request' => 400", "'unauthorized', 'not_found' => 404", 'default => 409', 'true, 303', "\$_SESSION['website_manager_flash']", "if (\$receipt['mutated']) Csrf::rotate"] as $token) checkM5BV(str_contains($route, $token), 'Route contract: ' . $token);
checkM5BV(strpos($route, 'Csrf::requireValid(') < strpos($route, 'SiteCustomerReviewWorkflow::submit('), 'Route validates CSRF before generic dispatch');
checkM5BV(!str_contains($route, '&saved=1') && !str_contains($route, '$saved = isset($_GET'), 'Success uses session flash, not query proof');
checkM5BV(str_contains($workflow, '$bodyBytes > 16384') && str_contains($workflow, '$files !== []'), 'Generic request size and uploads rejected independently of legacy');
foreach ([$route, $workflow] as $source) checkM5BV(!preg_match('/\b(?:UPDATE\s+(?:sites|site_approvals|site_revisions)|INSERT\s+INTO|DELETE\s+FROM)\b/i', $source), 'No lifecycle SQL in route or dispatch');
foreach ([$workflow, $guard] as $source) checkM5BV(!preg_match('/(?:Stripe|Twilio|Retell|Vendasta|Namecheap|DigitalOcean|DomainManager|LeadHub|SiteGenerator|WebsiteManager)::|curl_/i', $source), 'No provider or legacy calls from generic workflow');
checkM5BV(str_contains($policy, 'SELECT id FROM users WHERE id = :user_id FOR UPDATE') && str_contains($policy, 'SELECT role_id FROM user_roles') && str_contains($policy, 'SELECT r.id FROM roles'), 'Actor parent, assignment range, and role definitions receive current locks');
checkM5BV(str_contains($policy, "' FOR SHARE'") && str_contains($guard, 'component_definitions') && str_contains($guard, 'site_assets'), 'Current eligibility and render dependencies held through transaction');
$dashboard = file_get_contents(__DIR__ . '/../public/app/247sp/dashboard.php');
foreach (['Legacy Website Approval', 'Website Revision Review', 'TwentyFourSevenSalesPartner::approveWebsiteLaunch(', 'TwentyFourSevenSalesPartner::requestWebsiteChanges('] as $token) checkM5BV(str_contains($dashboard, $token), 'Legacy dashboard labels preserve authority');
checkM5BV(!str_contains($dashboard, 'SiteApprovalManager::'), 'Dashboard has no generic approval authority');
$baselineDashboard = shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' show 2c742190809006008b42f7e2c7075701047ac74b:public/app/247sp/dashboard.php');
checkM5BV(is_string($baselineDashboard), 'Authoritative dashboard baseline readable');
$baselineDashboard = str_replace(["\r\n", "\r"], "\n", $baselineDashboard);
$expectedDashboard = str_replace(["'label' => 'Website Approval'", "'label' => 'Approve & Launch Website'", '<label>Request Changes'],
    ["'label' => 'Legacy Website Approval'", "'label' => 'Approve Legacy Website Launch'", '<label>Request Legacy Website Changes'], $baselineDashboard);
$paragraph = '<p class="muted">Review your private website preview, adjust customer-editable content, or request changes from the 247SP team.</p>';
$expectedDashboard = str_replace($paragraph, $paragraph . "\n                    " . '<p class="muted">These legacy launch actions are separate from Website Revision Review in Website Manager. Approving a specific revision there does not approve launch or publish your website.</p>', $expectedDashboard);
checkM5BV($expectedDashboard === str_replace(["\r\n", "\r"], "\n", $dashboard), 'Dashboard differs only by the four authorized label/clarification edits');
session_destroy();
echo "Website platform M5B view/route: $assertions assertions passed.\n";
