<?php
declare(strict_types=1);
/** Raw Git blob verification; no filters, bootstrap, SQL, or native execution. */
final class M6CNativeSource
{
    public static function verify(string $root,string $entries): array
    {
        $files=[];
        foreach(explode("\0",rtrim($entries,"\0")) as $entry){
            if(!preg_match('/^(100644|100755) ([a-f0-9]{40}) 0\t(.+)$/Ds',$entry,$m))throw new RuntimeException('source_index_entry');
            $relative=$m[3];
            if(str_starts_with($relative,'/')||str_contains($relative,'..')||str_contains($relative,'\\')||isset($files[$relative]))throw new RuntimeException('source_path');
            $path=$root.'/'.$relative;
            if(!is_file($path)||is_link($path)||filesize($path)>16777216)throw new RuntimeException('source_regular_file');
            $bytes=file_get_contents($path);
            if(sha1('blob '.strlen($bytes)."\0".$bytes)!==$m[2])throw new RuntimeException('source_raw_blob');
            if(PHP_OS_FAMILY==='Linux'&&((fileperms($path)&0111)!==($m[1]==='100755'?0111:0)))throw new RuntimeException('source_executable_mode');
            $files[$relative]=$m[2];
        }
        if($files===[])throw new RuntimeException('source_empty');
        return $files;
    }
}
