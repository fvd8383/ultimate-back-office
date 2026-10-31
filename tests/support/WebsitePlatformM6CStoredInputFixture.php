<?php
declare(strict_types=1);
require_once __DIR__.'/WebsitePlatformM6CFixture.php';
// Definitions only: never invoke the native SQL seeder, adapter or coordinator here.
require_once __DIR__.'/WebsitePlatformM6CNativeFixture.php';

function m6cStoredFixture(): WebsitePlatformM6CFixture
{
    $f=new WebsitePlatformM6CFixture();$base=$f->db->read->base;
    // Match the native fixture's facts; the ordinary local fixture has extra public/private facts.
    $base->revisions[100]['facts_snapshot_json']=CanonicalJson::encode([
        'business'=>['display_name'=>'Synthetic Café','phone'=>'+1 555 123 4567','email'=>'hello@example.test',
            'account_id'=>'ACCOUNT-CANARY','credentials'=>'CREDENTIAL-CANARY'],
        'profile'=>['default_language'=>'en','short_description'=>'Approved description'],
        'brief'=>'BRIEF-CANARY','session'=>'SESSION-CANARY']);
    $base->revisions[100]['lifecycle_status']='draft';
    $hash=$base->revisions[100]['snapshot_hash'];
    m6c(CanonicalJson::encode(m6nComposition($hash,1))===CanonicalJson::encode($f::composition($hash)),
        'Native and local three-page compositions match, including shared PNG, layouts, SEO and inert fields');
    useWebsitePlatformM3ServiceDatabase($base);
    SiteCompositionManager::replaceDraftComposition(1,100,m6nComposition($hash,1));
    $base->revisions[100]['lifecycle_status']='internally_approved';
    (new ReflectionProperty(Database::class,'connection'))->setValue(null,$f->db);
    return $f;
}

/** Model documented JSON-column separators and an object-key order, not a MySQL emulator.
 * https://dev.mysql.com/doc/refman/8.4/en/json.html#json-normalization
 * MySQL key order is not guaranteed. Lists, scalar types and string contents stay intact.
 */
function m6cStoredJson(mixed $value): string
{
    if(!is_array($value))return CanonicalJson::encode($value);
    if(array_is_list($value))return '['.implode(', ',array_map('m6cStoredJson',$value)).']';
    uksort($value,static fn($a,$b)=>strlen((string)$a)<=>strlen((string)$b) ?: strcmp((string)$a,(string)$b));
    $parts=[];foreach($value as $key=>$item)$parts[]=CanonicalJson::encode((string)$key).': '.m6cStoredJson($item);
    return '{'.implode(', ',$parts).'}';
}
