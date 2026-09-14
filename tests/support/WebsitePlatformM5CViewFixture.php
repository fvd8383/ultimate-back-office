<?php

declare(strict_types=1);

// CLI-only synthetic renders. No HTTP authentication helper or real database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/WebsitePlatformM5BDatabase.php';
require_once __DIR__ . '/../../private/classes/Csrf.php';

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function m5cReview(?array $customerReview, ?string $reviewReceipt = null, bool $saved = false): string
{
    ob_start();
    require __DIR__ . '/../../private/views/site-customer-review.php';
    return (string) ob_get_clean();
}

function m5cError(string $reviewFailure): string
{
    ob_start();
    require __DIR__ . '/../../private/views/site-customer-review-error.php';
    return (string) ob_get_clean();
}

function m5cDom(string $html): DOMXPath
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    return new DOMXPath($dom);
}

function m5cForm(string $html, string $action): array
{
    $xpath = m5cDom($html);
    $form = $xpath->query('//form[.//input[@name="action" and @value="' . $action . '"]]')->item(0);
    if (!$form) throw new RuntimeException('Missing rendered action');
    $fields = [];
    foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $node) {
        $fields[$node->getAttribute('name')] = match ($node->tagName) {
            'textarea' => $node->textContent,
            'select' => $node->getElementsByTagName('option')->item(0)->getAttribute('value'),
            default => $node->getAttribute('value'),
        };
    }
    return $fields;
}

function m5cShell(string $content, bool $preview = false): string
{
    $pageTitle = 'Synthetic local M5C QA';
    $bodyClass = 'app-dashboard theme-247sp';
    $layoutUserName = 'Synthetic Owner';
    $layoutLogoutHref = 'logout.php';
    ob_start();
    require __DIR__ . '/../../private/views/header.php';
    require_once __DIR__ . '/../../private/views/account-navigation.php';
    application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => ['id' => 3],
        'business' => ['id' => 50, 'business_name' => 'Synthetic customer']]);
    if (!$preview) echo '<section class="hero-panel site-customer-context"><h1>Website Manager</h1></section>';
    echo $content;
    application_shell_end();
    require __DIR__ . '/../../private/views/footer.php';
    return (string) ob_get_clean();
}

function m5cDocuments(): array
{
    $db = WebsitePlatformM5BDatabase::fixture();
    $db->addImage();
    $initial = m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50));
    $form = m5cForm($initial, 'feedback');
    $form['text'] = 'https://example.test/customer/review/' . str_repeat('reference', 12);
    Csrf::requireValid($form['csrf_token'], 'customer-website-manager');
    $receipt = SiteCustomerReviewWorkflow::submit(3, $form, [], 1000);
    $review = SiteCustomerReviewWorkflow::workspaceWithForms(3, 50);
    $customerPreview = SiteCustomerPreview::render(3, 50, 700);
    ob_start();
    require __DIR__ . '/../../private/views/site-customer-preview.php';
    $preview = (string) ob_get_clean();
    $documents = [
        'initial' => m5cShell($initial),
        'long' => m5cShell(m5cReview($review)),
        'receipt' => m5cShell(m5cReview($review, $receipt['message'])),
        'hostile' => m5cShell(m5cReview($review, '<img src=x onerror=alert(1)>" data-customer-review-receipt="hostile')),
        'legacy' => m5cShell('<p>Website settings saved.</p>' . m5cReview($review, 'Website settings saved.', true)),
        'readonly' => m5cShell(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(5, 50))),
        'unavailable' => m5cShell(m5cReview(null)),
        'preview' => m5cShell($preview, true),
        'error' => m5cError(SiteCustomerReviewWorkflow::safeFailureMessage(new SiteServiceException('conflict', 'private reason'))),
    ];
    foreach (['approve_revision' => 'approved', 'request_changes' => 'changes'] as $action => $state) {
        WebsitePlatformM5BDatabase::fixture();
        $form = m5cForm(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)), $action);
        $form['text'] = 'Please review the customer decision.';
        $receipt = SiteCustomerReviewWorkflow::submit(3, $form, [], 1000);
        $documents[$state] = m5cShell(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50), $receipt['message']));
    }
    return $documents;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    Session::start();
    $documents = m5cDocuments();
    session_destroy();
    echo json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
