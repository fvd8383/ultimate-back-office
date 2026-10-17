<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteArtifactAssetResolver.php';
require_once __DIR__ . '/SiteArtifactStore.php';

/** Pure bounded construction from a service-owned locked source, outside SQL. */
final class SiteArtifactBuilder
{
    public function __construct(private SiteArtifactStore $store, private SiteArtifactAssetResolver $assets) {}

    public function prepareInput(array $source): array
    {
        $projection = SitePublicRenderContext::project($source);
        $descriptors = $this->assets->descriptors($source);
        foreach ($descriptors as $descriptor) $this->assets->bytes($source,$descriptor);
        return $projection + ['ordered_asset_digests'=>$descriptors];
    }

    public function build(array $job, array $producer, array $source, callable $pulse): array
    {
        $pulse();
        $input = SiteBuildContract::recordedInput($source,$job);
        $projection = $this->prepareInput($source);
        foreach ($projection as $key=>$value) if (CanonicalJson::hash($input[$key]) !== CanonicalJson::hash($value)) SiteArtifactFiles::fail('input_mismatch');
        $identity = ['site_key'=>SiteBuildContract::uuid($source['site']['site_key']), 'release_key'=>$job['release_key'],
            'revision_number'=>(int)$source['revision']['revision_number']];
        $candidate = $this->store->createCandidate($job,$producer,$identity);
        try {
            $entries = [];
            $write = function (string $path,string $bytes,string $mime) use (&$entries,$candidate,$pulse): void {
                $pulse();
                if (isset($entries[$path])) {
                    if ($entries[$path]['sha256'] !== hash('sha256',$bytes)) SiteArtifactFiles::fail();
                    return;
                }
                $this->store->writeFile($candidate,$path,$bytes);
                $entries[$path] = ['relative_path'=>$path,'sha256'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes),'mime_type'=>$mime];
            };
            $css = SiteArtifactValidator::css($input['public_composition']['theme']);
            $cssUrl = '/assets/' . hash('sha256',$css) . '.css';
            $write('public' . $cssUrl,$css,'text/css');
            foreach ($input['ordered_asset_digests'] as $asset) {
                $pulse();
                $write('public/assets/' . $asset['sha256'] . '.' . SiteArtifactValidator::extension($asset['mime_type']),
                    $this->assets->bytes($source,$asset),$asset['mime_type']);
            }
            $context = SitePublicRenderContext::renderer($input);
            foreach ($input['public_composition']['pages'] as $page) {
                $pulse();
                $e = [SiteComponentRenderers::class,'escape'];
                $seo = '<meta name="robots" content="' . $e(str_replace('_',', ',$page['seo']['robots'])) . '">';
                if (!empty($page['seo']['description'])) $seo .= '<meta name="description" content="' . $e($page['seo']['description']) . '">';
                if ($page['seo']['canonical_policy'] === 'self') $seo .= '<link rel="canonical" href="/' . $e($page['slug']) . '">';
                $html = '<!DOCTYPE html><html lang="' . $e($input['public_facts']['profile']['default_language']) . '"><head><meta charset="UTF-8">'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e(self::title($page,$input)) . '</title>'
                    . $seo
                    . '<meta http-equiv="Content-Security-Policy" content="' . $e(SiteArtifactValidator::CSP) . '">'
                    . '<meta name="site-release" content="' . $e($identity['site_key'] . '/' . $job['release_key']) . '">'
                    . '<link rel="stylesheet" href="' . $cssUrl . '"></head><body><p class="site-name">'
                    . $e($input['public_facts']['business']['display_name'] ?? '') . '</p>'
                    . SiteCompositionRenderer::renderPage($input['public_composition'],$page['page_key'],$context) . '</body></html>';
                $write(SiteArtifactFiles::route($page['slug']),$html,'text/html');
            }
            ksort($entries,SORT_STRING);
            $marker = ['contract_version'=>SiteArtifactValidator::CONTRACT,'site_key'=>$identity['site_key'],'release_key'=>$job['release_key'],
                'public_payload_hash'=>CanonicalJson::hash(array_values($entries))];
            $write('public/_release.json',CanonicalJson::encode($marker),'application/json');
            ksort($entries,SORT_STRING); $entries = array_values($entries);
            $summary = SiteArtifactValidator::summary($entries,count($input['public_composition']['pages']));
            $manifest = SiteArtifactValidator::manifest($job,$identity,$input,$entries,$summary);
            $this->store->writeFile($candidate,'metadata/validation.json',SiteBuildContract::json($summary,SiteArtifactFiles::MAX_VALIDATION));
            $this->store->writeFile($candidate,'metadata/manifest.json',SiteBuildContract::json($manifest,SiteArtifactFiles::MAX_MANIFEST));
            return $this->store->sealIfAbsent($candidate,$pulse);
        } finally {
            // Interruptions retain private evidence; no blind cleanup or retry of uncertain output.
            $this->store->finish($candidate);
        }
    }

    public static function title(array $page,array $input): string
    {
        return (string) (($page['seo']['title'] ?? null) ?: $page['title']) . ' | ' . ($input['public_facts']['business']['display_name'] ?? '');
    }
}
