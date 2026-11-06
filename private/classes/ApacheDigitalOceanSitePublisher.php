<?php
declare(strict_types=1);
require_once __DIR__.'/SitePublisher.php';
require_once __DIR__.'/SitePublisherSession.php';
require_once __DIR__.'/SiteHttpHealthChecker.php';

/** Staging only. The helper reads authority independently; DTO possession grants nothing. */
final class ApacheDigitalOceanSitePublisher implements SitePublisher
{
    public function __construct(private SitePublisherSession $session,private array $target,private SiteHttpHealthChecker $health) {}
    private function call(string $op,array $payload=[]): array
    {SiteDeploymentService::guardExternal();return $this->session->request($op,$this->target,$payload);}
    private function target(array $target): void
    {if($target!==$this->target)throw new SiteServiceException('conflict','Publisher target differs.');}
    public function stageRelease(array $release,array $target,array $context): array
    {$this->target($target);return SiteDeploymentContract::receipt($this->call('stage',['release'=>$release,'context'=>$context]),$context,'stage');}
    public function activateRelease(array $staged,array $expectedCurrent,array $context): array
    {return SiteDeploymentContract::receipt($this->call('activate',['staged'=>$staged,'expected'=>$expectedCurrent,'context'=>$context]),$context,'activation');}
    public function restoreRelease(array $stagedKnownGood,array $expectedCurrent,array $context): array
    {return SiteDeploymentContract::receipt($this->call('restore',['staged'=>$stagedKnownGood,'expected'=>$expectedCurrent,'context'=>$context]),$context,'activation');}
    public function inspectCurrentRelease(array $target): array
    {$this->target($target);return SiteDeploymentContract::observation($this->call('inspect'));}
    public function healthCheck(array $expected,array $target,array $context): array
    {
        $this->target($target);$origin=$this->call('probe',['expected'=>$expected,'context'=>$context]);
        return $this->health->check($expected,$target,$context,$origin);
    }
    public function reconcileExternalState(array $intent,array $target,array $context): array
    {$this->target($target);return SiteDeploymentContract::receipt($this->call('compensate',['intent'=>$intent,'context'=>$context]),$context,'compensation');}
}
