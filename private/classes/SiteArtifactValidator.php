<?php

declare(strict_types=1);
require_once __DIR__ . '/SitePublicRenderContext.php';

final class SiteArtifactValidator
{
    public const VERSION = 'static-validator-v1';
    public const CONTRACT = 'ubo-static-site-v1';
    public const CSP = "default-src 'none'; script-src 'none'; style-src 'self'; img-src 'self'; connect-src 'none'; object-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'";
    public const MIME = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','application/pdf'=>'pdf'];

    public static function extension(string $mime): string
    {
        return self::MIME[$mime] ?? SiteArtifactFiles::fail();
    }

    public static function asset(string $bytes, array $asset): void
    {
        self::extension($asset['mime_type']);
        if (strlen($bytes) > SiteArtifactFiles::MAX_ASSET || strlen($bytes) !== $asset['byte_size']
            || !hash_equals($asset['sha256'], hash('sha256', $bytes))) SiteArtifactFiles::fail();
        if ($asset['mime_type'] === 'application/pdf') {
            // No PDF sanitizer exists: refuse active/embedded payloads and ambiguous compressed object streams.
            if (!str_starts_with($bytes, '%PDF-') || !str_contains(substr($bytes, -1024), '%%EOF')
                || preg_match('~/(?:JavaScript|JS|Launch|OpenAction|AA|EmbeddedFile|RichMedia|XFA|ObjStm|SubmitForm|ImportData|URI|GoToR|AcroForm|Encrypt|Movie|Sound|Rendition)\b|#[a-f0-9]{2}~i', $bytes)) SiteArtifactFiles::fail();
        } else {
            if (!function_exists('getimagesizefromstring')) SiteArtifactFiles::fail('builder_unavailable');
            $size = @getimagesizefromstring($bytes);
            if ($size === false || ($size['mime'] ?? null) !== $asset['mime_type'] || $size[0] < 1 || $size[1] < 1
                || $size[0] > 20000 || $size[1] > 20000 || $size[0] * $size[1] > 100000000) SiteArtifactFiles::fail();
        }
        if (preg_match('~<\?(?:php|=)|<script\b|<svg\b~i', $bytes)) SiteArtifactFiles::fail();
    }

    public static function css(array $theme): string
    {
        $base = SiteArtifactFiles::read(dirname(__DIR__) . '/site-static/static-review-v1.css', SiteArtifactFiles::MAX_HTML);
        $schemas = ThemeRegistry::authoringSchemas();
        foreach (['primary_color','secondary_color'] as $key) ComponentSchemaValidator::validate($theme[$key], $schemas['color'], $key);
        $type = ComponentSchemaValidator::validate($theme['typography'], $schemas['typography'], 'typography');
        $config = ComponentSchemaValidator::validate($theme['configuration'], $schemas['configuration'], 'configuration');
        $families = ['system_sans'=>'system-ui,sans-serif','system_serif'=>'Georgia,serif'];
        return $base . "\n:root{--primary:" . $theme['primary_color'] . ';--secondary:' . $theme['secondary_color']
            . ';--body-font:' . $families[$type['body_family']] . ';--heading-font:' . $families[$type['heading_family']]
            . ';--font-size:' . ['compact'=>'15px','standard'=>'16px','large'=>'18px'][$type['scale']]
            . ';--spacing:' . ['compact'=>'1rem','standard'=>'2rem','relaxed'=>'3rem'][$config['section_spacing']]
            . ';--radius:' . ['square'=>'0','soft'=>'6px','rounded'=>'16px'][$config['corner_style']]
            . ';--button-radius:' . ['square'=>'0','rounded'=>'6px','pill'=>'999px'][$config['button_style']] . ";}\n";
    }

    /** @return array<string,string> Relative public file => exact expected MIME. */
    public static function expected(array $input): array
    {
        $expected = [];
        foreach ($input['public_composition']['pages'] as $page) {
            $path = SiteArtifactFiles::route($page['slug']);
            if (isset($expected[$path])) SiteArtifactFiles::fail();
            $expected[$path] = 'text/html';
        }
        if (count($expected) < 1 || count($expected) > 100 || !isset($expected['public/index.html'])) SiteArtifactFiles::fail();
        foreach ($input['ordered_asset_digests'] as $asset) {
            $expected['public/assets/' . $asset['sha256'] . '.' . self::extension($asset['mime_type'])] = $asset['mime_type'];
        }
        $css = self::css($input['public_composition']['theme']);
        $expected['public/assets/' . hash('sha256', $css) . '.css'] = 'text/css';
        $expected['public/_release.json'] = 'application/json';
        if (count($expected) + 2 > SiteArtifactFiles::MAX_FILES) SiteArtifactFiles::fail();
        ksort($expected, SORT_STRING); return $expected;
    }

    /** Full observed-byte inventory; $pulse checks external-work/lease bounds between files. */
    public static function publicEntries(string $directory, array $input, array $identity, callable $pulse): array
    {
        $entries = []; $total = 0;
        $expected = self::expected($input);
        $pages = [];
        foreach ($input['public_composition']['pages'] as $page) $pages[SiteArtifactFiles::route($page['slug'])] = $page;
        foreach ($expected as $path => $mime) {
            $pulse();
            $max = $mime === 'text/html' ? SiteArtifactFiles::MAX_HTML : ($mime === 'application/json' ? 1024 : SiteArtifactFiles::MAX_ASSET);
            $file = SiteArtifactFiles::contained($directory, $path);
            $stat = SiteArtifactFiles::regular($file);
            if ($total + $stat['size'] > SiteArtifactFiles::MAX_PACKAGE) SiteArtifactFiles::fail();
            $bytes = SiteArtifactFiles::read($file, $max, $pulse); $total += strlen($bytes);
            if ($mime === 'text/html') self::html($bytes, $pages[$path], $input, $identity, $expected);
            elseif ($mime === 'text/css') {
                if ($bytes !== self::css($input['public_composition']['theme'])) SiteArtifactFiles::fail();
            } elseif ($mime !== 'application/json') {
                $hash = pathinfo($path, PATHINFO_FILENAME);
                $matched = false;
                foreach ($input['ordered_asset_digests'] as $asset) if ($asset['sha256'] === $hash && $asset['mime_type'] === $mime) {
                    self::asset($bytes, $asset); $matched = true;
                }
                if (!$matched) SiteArtifactFiles::fail();
            }
            $entries[] = ['relative_path'=>$path, 'sha256'=>hash('sha256', $bytes), 'byte_size'=>strlen($bytes), 'mime_type'=>$mime];
            $pulse();
        }
        return $entries;
    }

    public static function summary(array $entries, int $pages): array
    {
        return ['profile'=>SiteBuildContract::PROFILE,'validator_version'=>self::VERSION,'result'=>'pass',
            'page_count'=>$pages,'file_count'=>count($entries),'byte_size'=>array_sum(array_column($entries,'byte_size')),
            'checks'=>['inventory','html','assets','privacy','hashes','static_csp'],
            'unavailable_capabilities'=>['leadhub_forms','production_publication','deployed_http_headers']];
    }

    /** Exact canonical manifest bytes; producer/worker/correlation and current clock are excluded. */
    public static function manifest(array $job, array $identity, array $input, array $entries, array $summary): array
    {
        $components = [];
        $selections = array_values($input['public_composition']['theme']['configuration']['layouts']);
        foreach ($input['public_composition']['pages'] as $page) array_push($selections, ...$page['sections']);
        foreach ($selections as $s) {
            $item = array_intersect_key($s, array_flip(['component_key','implementation_version','variant_key','configuration_schema_version']));
            $components[CanonicalJson::hash($item)] = $item;
        }
        ksort($components, SORT_STRING);
        $payload = array_values(array_filter($entries, static fn ($e) => $e['relative_path'] !== 'public/_release.json'));
        return ['contract_version'=>self::CONTRACT,'schema_version'=>1,'site_key'=>$identity['site_key'], 'release_key'=>$job['release_key'],
            'source_revision_id'=>(int)$job['revision_id'],'revision_number'=>$identity['revision_number'],
            'source_snapshot_hash'=>$job['snapshot_hash'],'composition_hash_mode'=>'generic','composition_hash_version'=>1,
            'components'=>array_values($components),'theme'=>array_intersect_key($input['public_composition']['theme'],array_flip(['theme_key','theme_version'])),
            'build_input_hash'=>$job['build_input_hash'],'builder_version'=>$job['builder_version'],'builder_code_sha'=>$job['builder_code_sha'],
            'registry_manifest_digest'=>$input['registry_manifest_digest'],'toolchain_contract'=>$input['toolchain_contract'],
            'build_profile'=>$job['build_profile'],'build_options'=>SiteBuildContract::OPTIONS,'files'=>$entries,
            'file_count'=>count($entries),'byte_size'=>array_sum(array_column($entries,'byte_size')),
            'created_at'=>$job['created_at'],'public_payload_hash'=>CanonicalJson::hash($payload),'artifact_hash'=>CanonicalJson::hash($entries),
            'validation_hash'=>hash('sha256',SiteBuildContract::json($summary,SiteArtifactFiles::MAX_VALIDATION)),
            'validation_summary'=>$summary,'unavailable_capabilities'=>$summary['unavailable_capabilities']];
    }

    public static function verify(string $directory, array $job, array $identity, callable $pulse): array
    {
        $input = SiteBuildContract::decode($job['input_manifest_json']);
        if (hash('sha256', $job['input_manifest_json']) !== $job['build_input_hash']) SiteArtifactFiles::fail();
        $expected = array_merge(array_keys(self::expected($input)), ['metadata/manifest.json','metadata/validation.json']);
        sort($expected, SORT_STRING);
        $pulse();
        if (SiteArtifactFiles::inventory($directory,$pulse) !== $expected) SiteArtifactFiles::fail();
        $entries = self::publicEntries($directory, $input, $identity, $pulse);
        $summary = self::summary($entries, count($input['public_composition']['pages']));
        $manifest = self::manifest($job, $identity, $input, $entries, $summary);
        $marker = ['contract_version'=>self::CONTRACT,'site_key'=>$identity['site_key'],'release_key'=>$job['release_key'],
            'public_payload_hash'=>$manifest['public_payload_hash']];
        if (SiteArtifactFiles::read($directory . '/public/_release.json',1024,$pulse) !== CanonicalJson::encode($marker)
            || SiteArtifactFiles::read($directory . '/metadata/validation.json',SiteArtifactFiles::MAX_VALIDATION,$pulse) !== CanonicalJson::encode($summary)
            || SiteArtifactFiles::read($directory . '/metadata/manifest.json',SiteArtifactFiles::MAX_MANIFEST,$pulse) !== SiteBuildContract::json($manifest,SiteArtifactFiles::MAX_MANIFEST)) SiteArtifactFiles::fail();
        return $manifest;
    }

    private static function html(string $html, array $page, array $input, array $identity, array $files): void
    {
        if (!class_exists(DOMDocument::class)) SiteArtifactFiles::fail('builder_unavailable');
        if (!str_starts_with($html,'<!DOCTYPE html><html ') || !str_ends_with($html,'</body></html>')
            || preg_match('~<!ENTITY|<!DOCTYPE(?! html>)|<\?|BEGIN [A-Z ]*PRIVATE KEY|(?:api[_-]?key|session[_-]?token|password)\s*[=:]~i', $html)) SiteArtifactFiles::fail();
        $prior = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument(); $dom->resolveExternals = false; $dom->substituteEntities = false;
            if (!$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) SiteArtifactFiles::fail();
        } finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
        $xp = new DOMXPath($dom);
        $one = static function (string $query) use ($xp): DOMElement {
            $nodes = $xp->query($query);
            if ($nodes === false || $nodes->length !== 1 || !($nodes->item(0) instanceof DOMElement)) SiteArtifactFiles::fail();
            return $nodes->item(0);
        };
        if ($one('//html')->getAttribute('lang') !== $input['public_facts']['profile']['default_language']
            || $one('//head/meta[@charset]')->getAttribute('charset') !== 'UTF-8'
            || $one('//title')->textContent !== SiteArtifactBuilder::title($page, $input)
            || $one('//main')->getAttribute('data-page-key') !== $page['page_key']
            || $one('//meta[@name="viewport"]')->getAttribute('content') !== 'width=device-width, initial-scale=1'
            || $one('//meta[@name="site-release"]')->getAttribute('content') !== $identity['site_key'] . '/' . $identity['release_key']
            || $one('//meta[@name="robots"]')->getAttribute('content') !== str_replace('_',', ',$page['seo']['robots'])
            || $one('//meta[@http-equiv="Content-Security-Policy"]')->getAttribute('content') !== self::CSP) SiteArtifactFiles::fail();
        $one('//body'); $one('//head'); $one('//link[@rel="stylesheet"]');
        $tags = ['html','head','meta','title','link','body','div','section','main','header','footer','nav','h1','h2','h3','p','a','span','img','ul','li','article','strong','details','summary','label','input','button'];
        $attrs = ['lang','charset','name','content','http-equiv','rel','href','class','data-theme','data-page-key','data-preview','role','src','alt','type','required','disabled'];
        foreach ($dom->getElementsByTagName('*') as $el) {
            if (!in_array($el->tagName,$tags,true)) SiteArtifactFiles::fail();
            foreach ($el->attributes as $a) if (!in_array($a->name,$attrs,true)) SiteArtifactFiles::fail();
            if ($el->hasAttribute('http-equiv') && $el->getAttribute('http-equiv') !== 'Content-Security-Policy') SiteArtifactFiles::fail();
            if ($el->tagName === 'link' && !in_array($el->getAttribute('rel'),['stylesheet','canonical'],true)) SiteArtifactFiles::fail();
            if ($el->tagName === 'link' && $el->getAttribute('rel') === 'canonical'
                && ($page['seo']['canonical_policy'] !== 'self' || $el->getAttribute('href') !== '/' . $page['slug'])) SiteArtifactFiles::fail();
            if (in_array($el->tagName,['input','button'],true) && (!$el->hasAttribute('disabled')
                || ($el->tagName === 'button' && $el->getAttribute('type') !== 'button'))) SiteArtifactFiles::fail();
            foreach (['href','src'] as $attr) if ($el->hasAttribute($attr)) {
                $url = $el->getAttribute($attr);
                $context = SitePublicRenderContext::renderer($input);
                if ($el->tagName === 'a' && in_array($url, array_filter([$context['call_href'],$context['email_href']]), true)) continue;
                if (!str_starts_with($url,'/') || str_starts_with($url,'//')) SiteArtifactFiles::fail();
                $relative = substr($url,1);
                $target = 'public/' . $relative;
                if (!isset($files[$target])) $target = SiteArtifactFiles::route($relative);
                if (!isset($files[$target])) SiteArtifactFiles::fail();
                if ($el->tagName === 'img' && !str_starts_with($files[$target],'image/')) SiteArtifactFiles::fail();
                if ($el->tagName === 'link' && $el->getAttribute('rel') === 'stylesheet' && $files[$target] !== 'text/css') SiteArtifactFiles::fail();
            }
        }
        foreach ($input['public_composition']['pages'] as $p) if ($p['presentation']['show_in_navigation']) {
            $found = false;
            foreach ($xp->query('//nav//a') as $a) if ($a->getAttribute('href') === '/' . $p['slug'] && $a->textContent === ($p['navigation_label'] ?: $p['title'])) $found = true;
            if (!$found) SiteArtifactFiles::fail();
        }
    }
}
