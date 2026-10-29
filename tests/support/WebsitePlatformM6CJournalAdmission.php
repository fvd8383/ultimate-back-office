<?php
declare(strict_types=1);

/** Fixed image-owned extension only; called after existing source/runtime attestation. No load here. */
final class M6CJournalAdmission
{
    public const LIBRARY='/usr/local/lib/ubo-m6c/m6c_journal.so';
    public static function verify(string $sha):void
    {
        foreach(['/','/usr','/usr/local','/usr/local/lib','/usr/local/lib/ubo-m6c'] as $path){
            $s=lstat($path);
            if(!$s||($s['mode']&0170000)!==0040000||$s['uid']!==0||($s['mode']&0022)!==0||realpath($path)!==$path)throw new RuntimeException('journal_helper_parent');
        }
        foreach([self::LIBRARY,self::LIBRARY.'.json'] as $path){$s=lstat($path);
            if(!$s||is_link($path)||($s['mode']&0170000)!==0100000||$s['uid']!==0||$s['nlink']!==1||($s['mode']&07777)!==0444
                ||$s['size']<1||$s['size']>10485760)throw new RuntimeException('journal_helper_file');}
        $bytes=file_get_contents(self::LIBRARY.'.json',false,null,0,4097);
        if(strlen($bytes)>4096)throw new RuntimeException('journal_helper_receipt');$r=json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
        if(!is_array($r))throw new RuntimeException('journal_helper_receipt');
        self::receipt($r,$sha,PHP_VERSION,PHP_ZTS,hash_file('sha256',__DIR__.'/../native/m6c/journal/m6c_journal.c'),hash_file('sha256',self::LIBRARY));
    }
    public static function receipt(array $r,string $sha,string $php,bool $zts,string $sourceHash,string $binaryHash):void
    {
        foreach([$sourceHash,$binaryHash] as $hash)if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('journal_helper_receipt');
        if(!preg_match('/^[a-f0-9]{40}$/D',$sha)||!preg_match('/^8\.4\.[0-9]+$/D',$php)||$zts!==false
            ||$r!==['version'=>1,'source_sha'=>$sha,'php'=>$php,'zts'=>false,'source_hash'=>$sourceHash,'binary_hash'=>$binaryHash])
            throw new RuntimeException('journal_helper_receipt');
    }
}
