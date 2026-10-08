<?php

declare(strict_types=1);

/** Split the actual canonical migrations, including quoted dynamic SQL in 015/019/020.
 * No schema recreation, statement rewriting, skipped failures or disabled constraints. */
function m6bSqlStatements(string $sql): array
{
    $statements = []; $buffer = ''; $quote = null; $lineComment = false; $blockComment = false;
    for ($i=0,$n=strlen($sql);$i<$n;$i++) {
        $c=$sql[$i]; $next=$sql[$i+1]??'';
        if ($lineComment) { if ($c==="\n") {$lineComment=false;$buffer.="\n";} continue; }
        if ($blockComment) { if ($c==='*' && $next==='/') {$blockComment=false;$i++;$buffer.=' ';} continue; }
        if ($quote!==null) {
            $buffer.=$c;
            if ($c==='\\' && $i+1<$n) {$buffer.=$sql[++$i];continue;}
            if ($c===$quote) {
                if ($next===$quote) {$buffer.=$sql[++$i];continue;}
                $quote=null;
            }
            continue;
        }
        if ($c==='-' && $next==='-' && ctype_space($sql[$i+2]??' ')) {$lineComment=true;$i++;continue;}
        if ($c==='#') {$lineComment=true;continue;}
        if ($c==='/' && $next==='*') {$blockComment=true;$i++;continue;}
        if (in_array($c,["'",'"','`'],true)) {$quote=$c;$buffer.=$c;continue;}
        if ($c===';') {if(trim($buffer)!=='')$statements[]=trim($buffer);$buffer='';continue;}
        $buffer.=$c;
    }
    if($quote!==null || $blockComment)throw new RuntimeException('Unterminated canonical SQL');
    if(trim($buffer)!=='')$statements[]=trim($buffer);
    return $statements;
}
