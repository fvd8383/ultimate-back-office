<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/private/classes/SiteBuildRuntime.php';
require_once dirname(__DIR__,2).'/private/classes/SiteBuildService.php';
require_once dirname(__DIR__,2).'/private/classes/SiteCompositionEditor.php';
require_once __DIR__.'/WebsitePlatformM6CNativeGitSource.php';

/** Instrumented lane/request setup ONLY. Real PDO, builder, assets, validator and store. */
final class M6CNativeDependencies implements SiteBuildDependencies
{
    public LocalSiteArtifactStore $store;
    public SiteArtifactBuilder $builder;
    public int $builds=0;
    public ?Closure $beforeBuild=null;
    public ?Closure $afterBuild=null;
    public ?Closure $verification=null;
    public function __construct(public array $config,private int $operator=1,private string $sourceMode='coordinator')
    {
        $guard=Closure::fromCallable([SiteBuildRuntime::class,'guard']);
        $this->store=new LocalSiteArtifactStore($config['work_root'],$config['artifact_root'],$guard);
        $this->builder=new SiteArtifactBuilder($this->store,new SiteArtifactAssetResolver($config['legacy_upload_root'],$guard));
    }
    public function wire(): void { (new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,$this); }
    public function workerIdentity(string $capability): array
    {
        if(!in_array($capability,['execution','recovery'],true)||posix_geteuid()!==2000)throw new RuntimeException('instrumented_worker_uid');
        return ['worker_id'=>$this->config['worker_id'],'environment'=>'staging'];
    }
    public function operatorId(): int { return $this->operator; }
    public function builderIdentity(): array
    {
        SiteBuildRuntime::guard();M6CNativeGitSource::verify(dirname(__DIR__,2),$this->config['builder_code_sha'],mode:$this->sourceMode);
        return SiteBuildRuntime::identity($this->config['builder_code_sha']);
    }
    public function prepareInput(array $source): array { return $this->builder->prepareInput($source); }
    public function execute(array $job,array $producer,array $source,callable $pulse): array
    {
        $this->builds++;if($this->beforeBuild!==null)($this->beforeBuild)($job,$producer);
        $receipt=$this->builder->build($job,$producer,$source,$pulse);
        if($this->afterBuild!==null)($this->afterBuild)($job,$producer);
        return $receipt;
    }
    public function verifyOutcome(array $job,array $producer,array $hint,callable $pulse): array
    {
        SiteBuildContract::hint($hint);
        $hook=$this->verification;
        return $this->store->verifyRelease($job,$producer,static function()use($pulse,$hook):void{if($hook!==null)$hook($pulse);else $pulse();});
    }
    public function inspectManifest(array $release): array
    {
        $db=Database::connection();$job=SiteBuildStore::one($db,'SELECT * FROM site_build_jobs WHERE id=:id',['id'=>(int)$release['build_job_id']]);
        $attempt=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>(int)$job['current_attempt_id']]);
        if($attempt['attempt_kind']==='recovery')$attempt=SiteBuildStore::one($db,'SELECT * FROM site_build_attempts WHERE id=:id',['id'=>(int)$attempt['recovery_of_attempt_id']]);
        return $this->store->inspectManifest($job,$attempt);
    }
}

function m6nInsert(PDO $db,string $table,array $values): int
{
    if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('fixture_table');
    $q=$db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES (:'.implode(',:',array_keys($values)).')');
    $q->execute($values);return (int)$db->lastInsertId();
}
function m6nSelection(string $key,array $config,string $variant='default'): array
{return ['component_key'=>$key,'implementation_version'=>'1.0.0','variant_key'=>$variant,'configuration_schema_version'=>1,'configuration'=>$config];}
function m6nComposition(string $hash,int $asset): array
{
    $theme=['theme_key'=>'local_service','theme_version'=>1,'primary_color'=>'#123456','secondary_color'=>'#ABCDEF',
        'typography'=>['heading_family'=>'system_sans','body_family'=>'system_serif','scale'=>'standard'],
        'configuration'=>['section_spacing'=>'standard','corner_style'=>'soft','button_style'=>'rounded','layouts'=>[
            'site_header'=>m6nSelection('site_header',['show_phone'=>true,'logo_usage_key'=>'brand_logo'],'standard'),
            'site_footer'=>m6nSelection('site_footer',['copyright_text'=>'Synthetic Café','show_navigation'=>true,'show_contact'=>true]),
            'mobile_cta'=>m6nSelection('mobile_cta',['label'=>'Contact','action'=>'contact'])]],
        'assets'=>[['asset_id'=>$asset,'usage_key'=>'brand_logo','source_reference'=>'LOCATOR-CANARY']]];
    $pages=[];
    foreach([['home','','home','Home only'],['roof','roof-cleaning','service','Service only'],['contact','contact','contact','Contact only']] as $i=>$p){
        $sections=[m6nSelection('hero',['headline'=>$p[3]])+['section_key'=>'heading','sort_order'=>10,'assets'=>[]]];
        if($p[2]==='service')$sections[]=m6nSelection('service_detail',['heading'=>'Roof care','body'=>'Selected service copy','included_items'=>[],
            'media_usage_key'=>'service_photo','media_alt'=>'Approved illustration'])+['section_key'=>'detail','sort_order'=>20,'assets'=>[['asset_id'=>$asset,'usage_key'=>'service_photo']]];
        if($p[2]==='contact')$sections[]=m6nSelection('lead_form',['heading'=>'Contact us','submit_label'=>'Ask us','fields'=>['name','email','message'],
            'required_fields'=>['email']])+['section_key'=>'form','sort_order'=>20,'assets'=>[]];
        $pages[]=['page_key'=>$p[0],'title'=>ucfirst($p[0]),'slug'=>$p[1],'page_type'=>$p[2],'navigation_label'=>ucfirst($p[0]),'sort_order'=>($i+1)*10,
            'seo'=>['title'=>null,'description'=>null,'robots'=>'index_follow','canonical_policy'=>'self'],
            'presentation'=>['layout_width'=>'standard','show_in_navigation'=>true],'sections'=>$sections];
    }
    return ['expected_snapshot_hash'=>$hash,'pages'=>$pages,'theme'=>$theme];
}
/** Direct synthetic identity/approval seeding is NOT UI/approval-workflow coverage. */
function m6nFixture(PDO $db,array $config): array
{
    $key=bin2hex(random_bytes(8));$ids=[];
    foreach(['requester','operator','owner'] as $type)$ids[$type]=m6nInsert($db,'users',[
        'first_name'=>'Synthetic','last_name'=>$type,'email'=>$key.'-'.$type.'@example.invalid','status'=>'active']);
    $role=m6nInsert($db,'roles',['name'=>'Admin','scope'=>'internal']);
    foreach(['requester','operator'] as $type)m6nInsert($db,'user_roles',['user_id'=>$ids[$type],'role_id'=>$role]);
    $business=m6nInsert($db,'businesses',['business_name'=>'M6C synthetic '.$key,'owner_user_id'=>$ids['owner'],
        'phone'=>'5550100000','email'=>$key.'@example.invalid','address_line_1'=>'1 Test St','city'=>'Test','state'=>'NY','postal_code'=>'10000','country'=>'US']);
    $module=(int)$db->query("SELECT id FROM modules WHERE module_key='247sp'")->fetchColumn();
    m6nInsert($db,'business_modules',['business_id'=>$business,'module_id'=>$module,'status'=>'active']);
    $site=m6nInsert($db,'sites',['site_key'=>SiteServiceSupport::uuidV4(),'purpose'=>'247sp','lifecycle_status'=>'draft']);
    m6nInsert($db,'site_business_associations',['site_id'=>$site,'business_id'=>$business,'association_role'=>'customer','status'=>'active','effective_at'=>'2026-09-19 00:00:00']);
    $revision=m6nInsert($db,'site_revisions',['site_id'=>$site,'revision_number'=>1,'materiality'=>'material','snapshot_schema_version'=>1,
        'facts_snapshot_json'=>CanonicalJson::encode(['business'=>['display_name'=>'Synthetic Café','phone'=>'+1 555 123 4567','email'=>'hello@example.test',
            'account_id'=>'ACCOUNT-CANARY','credentials'=>'CREDENTIAL-CANARY'],'profile'=>['default_language'=>'en','short_description'=>'Approved description'],
            'brief'=>'BRIEF-CANARY','session'=>'SESSION-CANARY']),
        'source_references_json'=>CanonicalJson::encode(['business'=>['business_id'=>$business]]),'snapshot_hash'=>str_repeat('0',64)]);
    SiteCompositionEditor::apply($ids['requester'],$revision,['operation'=>'initialize_new','expected_snapshot_hash'=>str_repeat('0',64)]);
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nN8AAAAASUVORK5CYII=',true);
    $path=$config['legacy_upload_root'].'/'.$key.'.png';if(file_put_contents($path,$png)!==strlen($png))throw new RuntimeException('fixture_asset_write');
    chmod($path,0444);
    $asset=m6nInsert($db,'site_assets',['site_id'=>$site,'business_id'=>$business,'asset_key'=>SiteServiceSupport::uuidV4(),'asset_type'=>'image',
        'storage_key'=>'/uploads/'.$key.'.png','checksum_sha256'=>hash('sha256',$png),'mime_type'=>'image/png','byte_size'=>strlen($png),
        'source'=>'legacy_247sp','rights_classification'=>'customer_owned','lifecycle_status'=>'ready']);
    $hash=$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.$revision)->fetchColumn();
    SiteCompositionManager::replaceDraftComposition($ids['requester'],$revision,m6nComposition($hash,$asset));
    $db->prepare("UPDATE site_revisions SET lifecycle_status='internally_approved',review_ready_at=UTC_TIMESTAMP() WHERE id=?")->execute([$revision]);
    $db->prepare("UPDATE sites SET lifecycle_status='approved' WHERE id=?")->execute([$site]);
    foreach(['customer','internal'] as $type)m6nInsert($db,'site_approvals',['site_id'=>$site,'revision_id'=>$revision,'approval_type'=>$type,
        'actor_type'=>$type==='customer'?'customer':'internal_admin','state'=>'approved','requested_at'=>'2026-09-19 00:00:00','decided_at'=>'2026-09-19 00:00:00','metadata_json'=>'{}']);
    $hash=$db->query('SELECT snapshot_hash FROM site_revisions WHERE id='.$revision)->fetchColumn();
    $adapter=new M6CNativeDependencies($config,$ids['operator']);$adapter->wire();
    $request=SiteBuildService::requestBuild($ids['requester'],['site_id'=>$site,'revision_id'=>$revision,'expected_snapshot_hash'=>$hash,'build_profile'=>SiteBuildContract::PROFILE]);
    return $ids+['site_id'=>$site,'revision_id'=>$revision,'hash'=>$hash,'asset_id'=>$asset,'asset_path'=>$path,'job_id'=>$request['id']];
}
