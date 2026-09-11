<?php

declare(strict_types=1);

require_once __DIR__ . '/support/WebsitePlatformM5ADatabase.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$assertions = 0;
function checkM5AView(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
}
function renderM5AReview(?array $review): string
{
    $customerReview = $review;
    ob_start();
    require __DIR__ . '/../private/views/site-customer-review.php';
    return (string) ob_get_clean();
}
function renderM5APreview(?array $preview): string
{
    $customerPreview = $preview;
    ob_start();
    require __DIR__ . '/../private/views/site-customer-preview.php';
    return (string) ob_get_clean();
}

$db = WebsitePlatformM5ADatabase::fixture();
$review = SiteCustomerReviewWorkflow::workspace(3, 50);
$reviewHtml = renderM5AReview($review);
checkM5AView(str_contains($reviewHtml, 'Website Revision Review'), 'Website Manager review section has a semantic heading.');
checkM5AView(str_contains($reviewHtml, 'Revision ready for review') && str_contains($reviewHtml, 'Revision 1'), 'Issued revision status and number render.');
checkM5AView(str_contains($reviewHtml, 'View private revision preview'), 'Eligible review exposes only its server-generated preview navigation.');
checkM5AView(str_contains($reviewHtml, 'Business Profile') && str_contains($reviewHtml, 'Existing website settings do not change'), 'Review explains the facts and legacy-settings boundaries.');
checkM5AView(!preg_match('/<(?:form|button|textarea|select)\b/i', $reviewHtml), 'M5A review view has no decision, feedback, or input controls.');
checkM5AView(!preg_match('/\b(?:Approve|Request changes|Send feedback)\b/i', $reviewHtml), 'M5B action labels are absent.');

$unsafe = $review;
$unsafe['status_label'] = '<script>alert("status")</script>';
$unsafe['requested_at'] = '2026-09-10 <img src=x onerror=alert(1)>';
$unsafeHtml = renderM5AReview($unsafe);
checkM5AView(!str_contains($unsafeHtml, '<script>') && str_contains($unsafeHtml, '&lt;script&gt;'), 'Customer-facing status is HTML escaped.');
checkM5AView(!str_contains($unsafeHtml, '<img') && str_contains($unsafeHtml, '&lt;img'), 'Customer-facing timestamps are HTML escaped.');

$none = $review;
$none['status'] = 'not_issued';
$none['status_label'] = 'No website revision is currently waiting for your review.';
$none['revision_number'] = $none['requested_at'] = $none['decided_at'] = $none['preview_href'] = null;
$none['preview_available'] = false;
$noneHtml = renderM5AReview($none);
checkM5AView(str_contains($noneHtml, 'No website revision is currently waiting for your review.'), 'No issued review is a normal customer state.');
checkM5AView(!str_contains($noneHtml, 'View private revision preview'), 'No-review state has no preview URL.');
checkM5AView(str_contains(renderM5AReview(null), 'temporarily unavailable'), 'Eligibility or integrity failure renders a generic unavailable state.');

$preview = SiteCustomerPreview::render(3, 50, 700);
$shellHtml = renderM5APreview($preview);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $shellHtml);
$iframes = $dom->getElementsByTagName('iframe');
checkM5AView($iframes->length === 1, 'Preview shell contains one isolated iframe.');
$iframe = $iframes->item(0);
checkM5AView($iframe instanceof DOMElement && $iframe->hasAttribute('sandbox') && $iframe->getAttribute('sandbox') === '', 'Preview iframe uses an empty sandbox with no allowances.');
checkM5AView($iframe instanceof DOMElement && str_contains($iframe->getAttribute('title'), 'Private preview of revision 1'), 'Preview iframe has a descriptive revision title.');
checkM5AView(str_contains($shellHtml, 'Acme &lt;Review&gt; &amp; Sons'), 'Customer business label is escaped in the preview shell.');
checkM5AView(str_contains($shellHtml, 'Back to Website Manager'), 'Preview provides safe navigation back to the retained entry point.');

$document = $preview['document'];
$decodedDocument = html_entity_decode($document, ENT_QUOTES | ENT_HTML5, 'UTF-8');
foreach (["default-src 'none'", "script-src 'none'", "connect-src 'none'", "img-src 'none'", "font-src 'none'",
    "object-src 'none'", "frame-src 'none'", "form-action 'none'", "base-uri 'none'", "style-src 'unsafe-inline'"] as $directive) {
    checkM5AView(str_contains($decodedDocument, $directive), 'Srcdoc CSP contains ' . $directive . '.');
}
checkM5AView(str_contains($document, SiteCustomerPreview::NOTICE) && str_contains($document, SiteCustomerPreview::MEDIA_NOTICE), 'Preview states interaction, publication, and media limitations.');
checkM5AView(str_contains($document, 'data-page-key="home"'), 'Exact validated stored composition is rendered.');
checkM5AView(!preg_match('/<(?:a|form|script)\b/i', $document), 'Rendered customer srcdoc has no links, forms, or scripts.');
checkM5AView(!preg_match('/(?:https?:|tel:|mailto:|javascript:)/i', $document), 'Rendered customer srcdoc has no network or action URLs.');
foreach (['FACTS-SENTINEL', 'SOURCE-SENTINEL', 'METADATA-SENTINEL', 'INTERNAL-COMMENT', 'INTERNAL-REASON', 'CORRELATION-SENTINEL'] as $secret) {
    checkM5AView(!str_contains($shellHtml . $document, $secret), 'Private data is absent from view output: ' . $secret);
}

$hostileContext = [
    'preview_mode' => true,
    'navigation' => [['label' => 'Home', 'href' => '/live-home']],
    'call_href' => 'tel:+15551234567',
    'contact_href' => '/contact',
    'lead_form_action' => '/internal/leads',
    'asset_urls' => ['price' => 'https://provider.example/pricing.pdf'],
];
$components = '';
$components .= SiteComponentRenderers::render('site_header', 'standard', ['show_phone' => true], $hostileContext);
$components .= SiteComponentRenderers::render('service_grid', 'cards', [
    'heading' => 'Services', 'intro' => null,
    'services' => [['name' => 'Repair', 'description' => 'Repair work', 'path' => 'services/repair']],
], $hostileContext);
$components .= SiteComponentRenderers::render('cta', 'banner', [
    'heading' => null, 'body' => null, 'label' => 'Call now', 'action' => 'call',
], $hostileContext);
$components .= SiteComponentRenderers::render('pricing_list', 'link', [
    'label' => 'Pricing', 'description' => null, 'document_usage_key' => 'price',
], $hostileContext);
$components .= SiteComponentRenderers::render('lead_form', 'default', [
    'heading' => 'Contact', 'body' => null, 'submit_label' => 'Send',
    'fields' => ['name', 'email'], 'required_fields' => ['email'],
], $hostileContext);
checkM5AView(!preg_match('/<(?:a|form)\b/i', $components), 'Explicit preview mode makes navigation, CTA, service, pricing, and lead components keyboard inert.');
checkM5AView(!preg_match('/(?:live-home|services\/repair|provider\.example|internal\/leads|tel:)/i', $components), 'Explicit preview mode discards accidentally supplied action and asset context.');
checkM5AView(str_contains($components, 'type="button" disabled') && str_contains($components, 'data-preview="inert"'), 'Preview lead controls are disabled and non-submitting.');
checkM5AView(str_contains($components, '<span>Home</span>') && str_contains($components, '<span>Pricing</span>'), 'Interactive labels remain readable after links become inert.');

echo "Website platform M5A views: {$assertions} assertions passed.\n";
