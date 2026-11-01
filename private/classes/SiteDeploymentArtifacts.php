<?php
declare(strict_types=1);
require_once __DIR__.'/SiteDeploymentService.php';
require_once __DIR__.'/SiteArtifactStore.php';

/** Integration seam over the unchanged M6C immutable store; no caller storage paths. */
final class SiteDeploymentArtifacts
{
    public static function verify(SiteArtifactStore $store, array $release, ?callable $pulse = null): array
    {
        SiteDeploymentService::guardExternal();
        [$job,$producer]=SiteServiceSupport::read(static function(object $db) use($release): array {
            $job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id = :id AND site_id = :site_id',
                ['id'=>(int)$release['build_job_id'],'site_id'=>(int)$release['site_id']]);
            if ($job===null || $job['status']!=='succeeded' || $job['release_key']!==$release['release_key']) throw new SiteServiceException('conflict','Successful artifact identity is missing.');
            $a=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$job['current_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>(int)$job['site_id']]);
            if ($a!==null && $a['attempt_kind']==='recovery') $a=SiteBuildStore::one($db,
                'SELECT * FROM site_build_attempts WHERE id = :id AND build_job_id = :job_id AND site_id = :site_id',
                ['id'=>(int)$a['recovery_of_attempt_id'],'job_id'=>(int)$job['id'],'site_id'=>(int)$job['site_id']]);
            if ($a===null || $a['attempt_kind']!=='execution') throw new SiteServiceException('conflict','Artifact producer is missing.');
            return [$job,$a];
        });
        $e=$store->verifyRelease($job,$producer,$pulse);
        if ($e['disposition']!=='sealed' || $e['artifact_hash']!==$release['artifact_hash'] || $e['manifest_hash']!==$release['manifest_hash']) {
            throw new SiteServiceException('conflict','Immutable artifact verification failed.');
        }
        return ['release_id'=>(int)$release['id'],'release_key'=>$release['release_key'],'artifact_hash'=>$release['artifact_hash'],
            'manifest_hash'=>$release['manifest_hash'],'result'=>'pass'];
    }
}
