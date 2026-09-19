<?php

declare(strict_types=1);
require_once __DIR__ . '/WebsitePlatformM6BMySqlSupport.php';
try {
    $identity=m6mysqlIdentity();
    $payload=json_decode(base64_decode($argv[2]??'',true),true,32,JSON_THROW_ON_ERROR);
    $pdo=m6mysqlConnect($identity,$argv[1]);
    $connection=new M6NativeConnection($pdo);
    $runtime=m6mysqlWire($connection);$runtime->operator=$payload['fixture']['operator'];
    echo 'READY '.(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()."\n";flush();
    if(trim((string)fgets(STDIN))!=='GO')throw new RuntimeException('Missing start barrier.');
    $result=match($payload['action']){
        'request'=>m6mysqlRequest($payload['fixture'],$payload['actor']),
        'claim'=>SiteBuildService::claimBuild([]),
        'claim_hold'=>(function()use($connection){$connection->holdClaim=true;return SiteBuildService::claimBuild([]);})(),
        'revoke'=>(function()use($pdo,$payload){echo "REVOCATION_START\n";flush();m6mysqlRevoke($pdo,$payload['fixture'],$payload['kind']);return ['revoked'=>true];})(),
        default=>throw new RuntimeException('Unknown worker test action.'),
    };
    echo 'RESULT '.json_encode($result,JSON_THROW_ON_ERROR)."\n";flush();
}catch(Throwable $e){
    echo 'ERROR '.($e instanceof SiteServiceException?$e->classification():get_class($e))."\n";exit(1);
}
