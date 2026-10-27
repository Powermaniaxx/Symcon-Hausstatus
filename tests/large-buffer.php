<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/BufferSupport.php';
class BufferFixture {
 use HausstatusBufferSupport;
 public array $buffers=[];
 function GetBuffer($key){return $this->buffers[$key]??'';}
 function SetBuffer($key,$value){if(strlen($value)>262144)throw new RuntimeException('Symcon size limit');if(preg_match('//u',$value)!==1)throw new RuntimeException('Invalid native UTF-8 string');$this->buffers[$key]=$value;}
 function read($key){return $this->ReadLargeBuffer($key);}
 function write($key,$value){$this->WriteLargeBuffer($key,$value);}
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new BufferFixture();$payload=json_encode(['data'=>str_repeat('Heizphase 51 W → 260 W · °C ',70000)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
foreach(['WaterbedPageCache','LastState'] as $key){
 $f->write($key,$payload);check($f->read($key)===$payload,'Lossless multibyte JSON');
 $old=json_decode($f->buffers[$key],true);$f->write($key,$payload.' ');check($f->read($key)===$payload.' ','Replacement published');
 for($i=0;$i<$old['count'];$i++)check($f->buffers[$key.'.'.$old['generation'].'.'.$i]==='','Previous chunks cleaned');
 $meta=json_decode($f->buffers[$key],true);$f->buffers[$key.'.'.$meta['generation'].'.0']='broken';check($f->read($key)==='','Truncated/corrupt data is a cache miss');
 $f->write($key,'');check($f->read($key)==='','Cache invalidation');
 $f->buffers[$key]='{"legacy":true}';check($f->read($key)==='{"legacy":true}','Existing plain buffers remain readable');
}
echo "large-buffer: OK (multi-MB state/page cache, UTF-8, overwrite, cleanup, corruption, invalidation, legacy)\n";
