<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM6BDatabase.php';
require_once dirname(__DIR__,2) . '/private/classes/SiteBuildRuntime.php';

/** Isolated test wiring only: real renderer/validator/store, fake lifecycle SQL. */
final class WebsitePlatformM6CFixture implements SiteBuildDependencies
{
    public WebsitePlatformM6BDatabase $db;
    public LocalSiteArtifactStore $store;
    public SiteArtifactAssetResolver $assets;
    public SiteArtifactBuilder $builder;
    private SiteBuildRuntime $artifactRuntime;
    public string $root;
    public int $io = 0;
    public int $builds = 0;
    public $afterVerify = null;
    public $duringBuild = null;
    public $duringProgress = null;
    public $verificationProgress = null;
    public static array $roots = [];
    public array $identity;

    public function __construct()
    {
        $this->db = WebsitePlatformM6BDatabase::fixture();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ubo-m6c-' . bin2hex(random_bytes(12));
        mkdir($this->root,0700); self::$roots[] = $this->root;
        foreach (['work','sealed','uploads'] as $dir) mkdir($this->root . '/' . $dir,0700);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nN8AAAAASUVORK5CYII=');
        file_put_contents($this->root . '/uploads/approved.png',$png);
        $base = $this->db->read->base;
        $base->siteAssets[1] = array_replace($base->siteAssets[1],['source'=>'legacy_247sp','storage_key'=>'/uploads/approved.png',
            'checksum_sha256'=>hash('sha256',$png),'mime_type'=>'image/png','byte_size'=>strlen($png)]);
        $base->revisions[100]['facts_snapshot_json'] = CanonicalJson::encode([
            'business'=>['display_name'=>'Synthetic Café','phone'=>'+1 555 123 4567','email'=>'hello@example.test',
                'public_address'=>['city'=>'Test City'],'account_id'=>'ACCOUNT-CANARY','credentials'=>'CREDENTIAL-CANARY'],
            'profile'=>['default_language'=>'en','short_description'=>'Approved description','prohibited_claims'=>'GUIDANCE-CANARY'],
            'services'=>['selected'=>[['name'=>'Roof care','category_name'=>'Cleaning','internal_id'=>'ID-CANARY']]],
            'faqs'=>[['question'=>'Where?','answer'=>'Test City','channel_scope'=>'website','internal_note'=>'FAQ-CANARY']],
            'brief'=>'BRIEF-CANARY','source_references'=>['value'=>'SOURCE-CANARY'],'escalation_rules'=>'RULES-CANARY',
            'customer_feedback'=>'FEEDBACK-CANARY','session'=>'SESSION-CANARY']);
        $base->revisions[100]['lifecycle_status']='draft';
        useWebsitePlatformM3ServiceDatabase($base);
        SiteCompositionManager::replaceDraftComposition(1,100,self::composition($base->revisions[100]['snapshot_hash']));
        $base->revisions[100]['lifecycle_status']='internally_approved';
        (new ReflectionProperty(Database::class,'connection'))->setValue(null,$this->db);
        $guard = function():void { $this->io++; if ($this->db->inTransaction()) throw new LogicException('Artifact I/O under SQL locks'); };
        $this->store = new LocalSiteArtifactStore(realpath($this->root . '/work'),realpath($this->root . '/sealed'),$guard);
        $this->assets = new SiteArtifactAssetResolver(realpath($this->root . '/uploads'),$guard);
        $this->builder = new SiteArtifactBuilder($this->store,$this->assets);
        $this->identity = SiteBuildRuntime::identity(str_repeat('a',40));
        // Isolated fixture only: exercise the real adapter without any host bootstrap/configuration.
        $runtimeClass=new ReflectionClass(SiteBuildRuntime::class);
        $this->artifactRuntime=$runtimeClass->newInstanceWithoutConstructor();
        $runtimeClass->getConstructor()->invoke($this->artifactRuntime,$this->workerIdentity('execution'),$this->identity,$this->store,$this->builder,dirname(__DIR__,2));
        (new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,$this);
    }
    public static function selection(string $key,array $config,string $variant='default'):array
    { return ['component_key'=>$key,'implementation_version'=>'1.0.0','variant_key'=>$variant,'configuration_schema_version'=>1,'configuration'=>$config]; }
    public static function composition(string $hash):array
    {
        $theme=['theme_key'=>'local_service','theme_version'=>1,'primary_color'=>'#123456','secondary_color'=>'#ABCDEF',
            'typography'=>['heading_family'=>'system_sans','body_family'=>'system_serif','scale'=>'standard'],
            'configuration'=>['section_spacing'=>'standard','corner_style'=>'soft','button_style'=>'rounded','layouts'=>[
                'site_header'=>self::selection('site_header',['show_phone'=>true,'logo_usage_key'=>'brand_logo'],'standard'),
                'site_footer'=>self::selection('site_footer',['copyright_text'=>'Synthetic Café','show_navigation'=>true,'show_contact'=>true]),
                'mobile_cta'=>self::selection('mobile_cta',['label'=>'Contact','action'=>'contact'])]],
            'assets'=>[['asset_id'=>1,'usage_key'=>'brand_logo','source_reference'=>'LOCATOR-CANARY']]];
        $pages=[];
        foreach ([['home','','home','Home only'],['roof','roof-cleaning','service','Service only'],['contact','contact','contact','Contact only']] as $i=>$p) {
            $sections=[self::selection('hero',['headline'=>$p[3]])+['section_key'=>'heading','sort_order'=>10,'assets'=>[]]];
            if ($p[2]==='service') $sections[]=self::selection('service_detail',['heading'=>'Roof care','body'=>'Selected service copy','included_items'=>[],
                'media_usage_key'=>'service_photo','media_alt'=>'Approved illustration'])+['section_key'=>'detail','sort_order'=>20,'assets'=>[['asset_id'=>1,'usage_key'=>'service_photo']]];
            if ($p[2]==='contact') $sections[]=self::selection('lead_form',['heading'=>'Contact us','submit_label'=>'Ask us','fields'=>['name','email','message'],
                'required_fields'=>['email']])+['section_key'=>'form','sort_order'=>20,'assets'=>[]];
            $pages[]=['page_key'=>$p[0],'title'=>ucfirst($p[0]),'slug'=>$p[1],'page_type'=>$p[2],'navigation_label'=>ucfirst($p[0]),'sort_order'=>($i+1)*10,
                'seo'=>['title'=>null,'description'=>null,'robots'=>'index_follow','canonical_policy'=>'self'],
                'presentation'=>['layout_width'=>'standard','show_in_navigation'=>true],'sections'=>$sections];
        }
        return ['expected_snapshot_hash'=>$hash,'pages'=>$pages,'theme'=>$theme];
    }
    public function source():array
    { return SiteBuildStore::transaction(fn($db)=>SiteRevisionManager::lockBuildEligibility($db,10,100,$this->db->read->base->revisions[100]['snapshot_hash'])); }
    public function workerIdentity(string $capability):array { return ['worker_id'=>'synthetic-filesystem-worker','environment'=>'local']; }
    public function operatorId():int { return 2; }
    public function builderIdentity():array { return $this->identity; }
    public function prepareInput(array $lockedSource):array { SiteBuildRuntime::guard(); return $this->builder->prepareInput($lockedSource); }
    public function execute(array $job,array $producerAttempt,array $lockedSource,callable $pulse):array
    { $this->builds++; if($this->duringBuild!==null)($this->duringBuild)(); return $this->builder->build($job,$producerAttempt,$lockedSource,$this->progress($pulse,$this->duringProgress)); }
    private function progress(callable $pulse,?callable $hook): Closure
    { return function(bool $force=false)use($pulse,$hook):void{if($this->db->inTransaction())throw new LogicException('Progress inside SQL');if($hook!==null)$hook($pulse,$force);else $pulse($force);}; }
    public function verifyOutcome(array $job,array $producerAttempt,array $hint,callable $pulse):array
    { $e=$this->artifactRuntime->verifyOutcome($job,$producerAttempt,$hint,$this->progress($pulse,$this->verificationProgress)); if($this->afterVerify!==null){$f=$this->afterVerify;$this->afterVerify=null;$f();} return $e; }
    public function inspectManifest(array $release):array
    { $job=$this->db->tables['site_build_jobs'][$release['build_job_id']];$attempt=$this->db->tables['site_build_attempts'][$job['current_attempt_id']];if($attempt['attempt_kind']==='recovery')$attempt=$this->db->tables['site_build_attempts'][$attempt['recovery_of_attempt_id']];return $this->store->inspectManifest($job,$attempt); }
    public function run():array
    { $request=$this->db->request();$claim=$this->db->claim();$receipt=SiteBuildService::executeBuild($claim['lease']);return [$request,$claim,$receipt]; }
    public function rows(array $claim):array
    { return [$this->db->tables['site_build_jobs'][$claim['lease']['job_id']],$this->db->tables['site_build_attempts'][$claim['lease']['attempt_id']]]; }
    public function releasePath(array $job):string {return $this->root . '/sealed/' . $this->db->read->base->sites[10]['site_key'] . '/' . $job['release_key'];}
    public static function cleanup():void
    {
        foreach(self::$roots as $root){
            $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
            if($resolved===false||dirname($resolved)!==$parent||!preg_match('/^ubo-m6c-[a-f0-9]{24}$/D',basename($resolved)))throw new RuntimeException('Cleanup scope');
            $walk=function($dir)use(&$walk):void{foreach(new DirectoryIterator($dir)as$f){if($f->isDot())continue;$p=$f->getPathname();if($f->isLink()){unlink($p);continue;}if($f->isDir())$walk($p);else{chmod($p,0600);unlink($p);}}rmdir($dir);};
            $walk($resolved);
        }
        self::$roots=[];
    }
}

$assertions=0;
function m6c(bool $ok,string $why):void { global $assertions;$assertions++;if(!$ok)throw new RuntimeException($why); }
function m6cDeny(callable $f):void {try{$f();}catch(SiteArtifactException|SiteServiceException $e){m6c(strlen($e->getMessage())<300,'Bounded failure');return;}throw new RuntimeException('Expected denial');}
