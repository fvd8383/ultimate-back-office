<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';

/** Test-only decorators of actual service dependencies. No production selectors. */
final class M6CSupplementDependencies implements SiteBuildDependencies
{
    public M6CNativeDependencies $base;public int $builds=0;public ?Closure $progress=null;
    public ?Closure $guard=null;public ?Closure $inspect=null;
    public function __construct(array $config,int $operator)
    {
        $this->base=new M6CNativeDependencies($config,$operator,'build');
        (new ReflectionProperty(LocalSiteArtifactStore::class,'guard'))->setValue($this->base->store,function():void{
            SiteBuildRuntime::guard();if($this->guard!==null)($this->guard)();
        });
    }
    public function wire():void{(new ReflectionProperty(SiteBuildService::class,'dependencies'))->setValue(null,$this);}
    public function workerIdentity(string $capability):array{return $this->base->workerIdentity($capability);}
    public function operatorId():int{return $this->base->operatorId();}
    public function builderIdentity():array{return $this->base->builderIdentity();}
    public function prepareInput(array $source):array{return $this->base->prepareInput($source);}
    public function execute(array $job,array $producer,array $source,callable $pulse):array
    {
        $this->builds++;
        return $this->base->builder->build($job,$producer,$source,function(bool $force=false)use($pulse,$job,$producer):void{
            if($this->progress!==null)($this->progress)($job,$producer,$force);$pulse($force);
        });
    }
    public function verifyOutcome(array $job,array $producer,array $hint,callable $pulse):array
    {if($this->inspect!==null)($this->inspect)();return $this->base->verifyOutcome($job,$producer,$hint,$pulse);}
    public function inspectManifest(array $release):array{return $this->base->inspectManifest($release);}
}

final class M6CSupplementStatement extends PDOStatement
{
    public function __construct(private M6CSupplementConnection $owner,private PDOStatement $inner,private string $sql){}
    public function execute(?array $params=null):bool
    {$ok=$this->inner->execute($params);if($ok&&str_starts_with($this->sql,'INSERT INTO site_releases')){
        $this->owner->completion=true;if($this->owner->afterRelease!==null)($this->owner->afterRelease)();}return $ok;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->inner->fetch($mode,$cursorOrientation,$cursorOffset);}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->inner->fetchAll($mode,...$args);}
    public function fetchColumn(int $column=0):mixed{return $this->inner->fetchColumn($column);}
    public function rowCount():int{return $this->inner->rowCount();}
}
final class M6CSupplementConnection extends PDO
{
    public bool $completion=false;public ?Closure $beforeCommit=null;public ?Closure $afterRelease=null;
    public function __construct(private PDO $inner){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new M6CSupplementStatement($this,$this->inner->prepare($query,$options),$query);}
    public function beginTransaction():bool{return $this->inner->beginTransaction();}
    public function inTransaction():bool{return $this->inner->inTransaction();}
    public function commit():bool
    {if($this->completion&&$this->beforeCommit!==null){$hook=$this->beforeCommit;$this->beforeCommit=null;$hook();}return $this->inner->commit();}
    public function rollBack():bool{return $this->inner->rollBack();}
    public function lastInsertId(?string $name=null):string|false{return $this->inner->lastInsertId($name);}
}
