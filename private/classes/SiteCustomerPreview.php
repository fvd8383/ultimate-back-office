<?php

declare(strict_types=1);

require_once __DIR__ . '/SiteCustomerReviewWorkflow.php';
require_once __DIR__ . '/SiteCompositionRenderer.php';

final class SiteCustomerPreview
{
    public const NOTICE = 'Private revision preview. Forms and links are inactive. Approval does not publish your website.';
    public const MEDIA_NOTICE = 'This structural preview is not final production output. Unresolved media may not appear.';

    public static function headers(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ];
    }

    public static function render(int $actorId, int $businessId, int $requestId): array
    {
        $resolved = SiteCustomerReviewWorkflow::validatedPreview($actorId, $businessId, $requestId);
        $html = SiteCompositionRenderer::render($resolved['composition'], ['preview_mode' => true]);
        $csp = "default-src 'none'; script-src 'none'; connect-src 'none'; img-src 'none'; font-src 'none'; "
            . "object-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'; style-src 'unsafe-inline'";
        $document = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta http-equiv="Content-Security-Policy" content="' . SiteComponentRenderers::escape($csp) . '">'
            . '<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Private revision preview</title><style>'
            . 'body{font:1rem/1.6 system-ui,sans-serif;margin:1rem;color:#172b4d;overflow-wrap:anywhere}'
            . '*{box-sizing:border-box}section,header,footer{padding:1rem 0;border-bottom:1px solid #ccd3df}'
            . 'nav ul{display:flex;gap:1rem;flex-wrap:wrap;padding-left:1.2rem}input{display:block;max-width:100%}'
            . 'main{margin-bottom:2rem}h1,h2,h3{line-height:1.3}.preview-page-label{font-weight:bold}'
            . '</style></head><body><p>' . self::NOTICE . '</p><p>' . self::MEDIA_NOTICE . '</p>'
            . $html . '</body></html>';
        return ['review' => $resolved['review'], 'document' => $document];
    }
}
