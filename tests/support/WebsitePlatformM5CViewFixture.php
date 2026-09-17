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

function m5cManagerTitle(mixed $reviewReceipt = null, bool $saved = false, string $method = 'GET'): string
{
    // Execute only the real route's title presentation block, never its auth/DB/POST route.
    // The evaluated PHP is repository source; receipt values remain data, not code.
    $route = file_get_contents(__DIR__ . '/../../public/app/247sp/website-manager.php');
    $start = strpos($route, '$pageTitle =');
    $end = strpos($route, '$bodyClass =', $start === false ? 0 : $start);
    if ($start === false || $end === false) throw new RuntimeException('Missing route title block');
    $priorMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    try {
        $_SERVER['REQUEST_METHOD'] = $method;
        eval(substr($route, $start, $end - $start));
        return $pageTitle;
    } finally {
        if ($priorMethod === null) unset($_SERVER['REQUEST_METHOD']);
        else $_SERVER['REQUEST_METHOD'] = $priorMethod;
    }
}

function m5cShell(string $content, bool $preview = false, ?string $reviewReceipt = null, bool $saved = false): string
{
    $pageTitle = $preview ? 'Synthetic local M5C QA' : m5cManagerTitle($reviewReceipt, $saved);
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

function m5cHostileReceipt(): string
{
    return '</template></noscript><script>window.receiptInjected=true</script><img src=x onerror=alert(1)>" data-customer-review-receipt="hostile & café';
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
        'receipt' => m5cShell(m5cReview($review, $receipt['message']), false, $receipt['message']),
        'hostile' => m5cShell(m5cReview($review, m5cHostileReceipt()), false, m5cHostileReceipt()),
        'legacy' => m5cShell('<p>Website settings saved.</p>' . m5cReview($review, 'Website settings saved.', true), false, 'Website settings saved.', true),
        'readonly' => m5cShell(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(5, 50))),
        'unavailable' => m5cShell(m5cReview(null)),
        'preview' => m5cShell($preview, true),
        'error' => m5cError(SiteCustomerReviewWorkflow::safeFailureMessage(new SiteServiceException('conflict', 'private reason'))),
    ];
    // Defense-in-depth view projection; the real input validator already rejects HTML.
    $hostileReview = $review;
    $hostileReview['feedback'][0]['text'] = m5cHostileReceipt();
    $documents['hostile-feedback'] = m5cShell(m5cReview($hostileReview, $receipt['message']), false, $receipt['message']);
    foreach (['approve_revision' => 'approved', 'request_changes' => 'changes'] as $action => $state) {
        WebsitePlatformM5BDatabase::fixture();
        $form = m5cForm(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)), $action);
        $form['text'] = 'Please review the customer decision.';
        $receipt = SiteCustomerReviewWorkflow::submit(3, $form, [], 1000);
        $documents[$state] = m5cShell(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50), $receipt['message']), false, $receipt['message']);
        $documents[$state . '-get'] = m5cShell(m5cReview(SiteCustomerReviewWorkflow::workspaceWithForms(3, 50)));
    }
    return $documents;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    Session::start();
    $documents = m5cDocuments();
    session_destroy();
    echo json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
