<?php
declare(strict_types=1);

/** MySQL packet framing and ONE armed test fault. No connection or application bootstrap. */
final class M6CSupplementWire
{
    private string $client='';private string $server='';private bool $armed=false;private bool $waiting=false;
    private bool $handshake=false;public ?array $fault=null;
    public function __construct(private string $mode)
    {if(!in_array($mode,['before-commit','drop-ack','drop-query'],true))throw new RuntimeException('wire_mode');}
    public function arm():void
    {if($this->armed||$this->fault!==null)throw new RuntimeException('wire_already_armed');$this->armed=true;}
    public static function packet(string $payload,int $sequence=0):string
    {return substr(pack('V',strlen($payload)),0,3).chr($sequence).$payload;}
    /** Returns only packets eligible for forwarding; suppressed bytes are never logged. */
    public function feed(string $side,string $bytes):string
    {
        if(!in_array($side,['client','server'],true)||$this->fault!==null)throw new RuntimeException('wire_closed');
        $buffer=&$this->$side;$buffer.=$bytes;if(strlen($buffer)>1048580)throw new RuntimeException('wire_bound');$out='';
        while(strlen($buffer)>=4){
            $length=unpack('V',substr($buffer,0,3)."\0")[1];
            if($length<1||$length>1048576)throw new RuntimeException('wire_packet_bound');
            if(strlen($buffer)<$length+4)break;
            $packet=substr($buffer,0,$length+4);$buffer=substr($buffer,$length+4);$payload=substr($packet,4);
            if($side==='client'&&!$this->handshake){
                if(ord($packet[3])!==1||strlen($payload)<32)throw new RuntimeException('wire_handshake');
                $flags=unpack('V',substr($payload,0,4))[1];
                // Cannot inspect encrypted/compressed COMMIT traffic. Fail closed, never
                // downgrade an operational connection or pretend a post-commit throw is transport loss.
                if(($flags&(0x0820|(1<<26)|(1<<27)))!==0)throw new RuntimeException('wire_unsupported_capabilities');
                $this->handshake=true;
            }elseif($side==='client'&&$this->armed&&ord($packet[3])===0){
                $commit=$payload==="\x03COMMIT";
                if($this->mode!=='drop-query'&&!$commit)throw new RuntimeException('wire_expected_commit');
                if($this->mode==='drop-query'||$commit){
                    if($this->waiting)throw new RuntimeException('wire_second_command');
                    if($this->mode!=='drop-ack'){$this->fault=['mode'=>$this->mode,'forwarded'=>false,'ok_received'=>false];return $out;}
                    $this->waiting=true;
                }
            }elseif($side==='server'&&$this->waiting){
                if(ord($packet[3])!==1||strlen($payload)<7||substr($payload,0,3)!=="\0\0\0"
                    ||(ord($payload[3])&1)!==0)throw new RuntimeException('wire_commit_not_ok');
                $this->fault=['mode'=>$this->mode,'forwarded'=>true,'ok_received'=>true];return $out;
            }
            $out.=$packet;
        }
        return $out;
    }
}
