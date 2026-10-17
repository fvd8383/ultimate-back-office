<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteArtifactValidator.php';

/** Read-only legacy compatibility. No URL fetching, uploads, or fallback placeholders. */
final class SiteArtifactAssetResolver
{
    public function __construct(private ?string $legacyRoot, private Closure $guard) {}

    /** @return list<array{usage_key:string,sha256:string,byte_size:int,mime_type:string}> */
    public function descriptors(array $source): array
    {
        $assets = $source['composition']['assets'];
        if (count($assets) > SiteArtifactFiles::MAX_FILES - 3) SiteArtifactFiles::fail();
        $out = []; $total = 0;
        foreach ($assets as $asset) {
            if ($asset['byte_size'] < 0 || $asset['byte_size'] > SiteArtifactFiles::MAX_ASSET
                || ($total += $asset['byte_size']) > SiteArtifactFiles::MAX_PACKAGE) SiteArtifactFiles::fail();
            $usage = SiteBuildContract::key($asset['usage_key']);
            if (isset($out[$usage])) SiteArtifactFiles::fail();
            $out[$usage] = ['usage_key'=>$usage,'sha256'=>SiteBuildContract::hash($asset['checksum_sha256']),
                'byte_size'=>$asset['byte_size'],'mime_type'=>$asset['mime_type']];
            SiteArtifactValidator::extension($asset['mime_type']);
        }
        ksort($out,SORT_STRING); return array_values($out);
    }

    public function bytes(array $source, array $descriptor): string
    {
        ($this->guard)();
        $matches = array_values(array_filter($source['composition']['assets'],static fn($a)=>$a['usage_key']===$descriptor['usage_key']));
        if (count($matches) !== 1) SiteArtifactFiles::fail();
        $asset = $matches[0];
        $rows = array_values(array_filter($source['asset_metadata'],static fn($a)=>(int)$a['id']===$asset['asset_id']));
        if (count($rows) < 1) SiteArtifactFiles::fail();
        $row = $rows[0];
        foreach ($rows as $same) if ($same !== $row) SiteArtifactFiles::fail();
        if ((int)$row['site_id'] !== (int)$source['site']['id'] || $row['lifecycle_status'] !== 'ready'
            || (int)$row['rights_current'] !== 1 || !in_array($row['rights_classification'],SiteCompositionValidator::RIGHTS_ALLOWED,true)
            || ($row['business_id'] !== null && (int)$row['business_id'] !== $source['business_id'])
            || (in_array($row['rights_classification'],['customer_owned','customer_licensed_for_site'],true) && (int)$row['business_id'] !== $source['business_id'])
            || $row['storage_key'] !== $asset['storage_key'] || $row['checksum_sha256'] !== $descriptor['sha256']
            || (int)$row['byte_size'] !== $descriptor['byte_size'] || $row['mime_type'] !== $descriptor['mime_type']) SiteArtifactFiles::fail();
        if ($row['source'] !== 'legacy_247sp' || !str_starts_with($row['storage_key'],'/uploads/') || $this->legacyRoot === null) {
            SiteArtifactFiles::fail('builder_unavailable');
        }
        $relative = substr($row['storage_key'],strlen('/uploads/'));
        $path = SiteArtifactFiles::contained($this->legacyRoot,$relative);
        $bytes = SiteArtifactFiles::read($path,SiteArtifactFiles::MAX_ASSET);
        SiteArtifactValidator::asset($bytes,$descriptor);
        return $bytes;
    }
}
