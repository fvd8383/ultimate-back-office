<?php
declare(strict_types=1);

/** Pure native assertion predicate; callers load the existing build contract. No bootstrap or I/O. */
final class M6CNativeInput
{
    public static function matches(array $job,array $release): bool
    {
        return CanonicalJson::hash(SiteBuildContract::decode($job['input_manifest_json']))===$job['build_input_hash']
            && $release['build_input_hash']===$job['build_input_hash'];
    }
}
