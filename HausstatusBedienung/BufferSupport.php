<?php
declare(strict_types=1);

/** Lossless cache storage below Symcon's 256 KiB warning / 1 MiB hard limit. */
trait HausstatusBufferSupport
{
    private function ReadLargeBuffer(string $name): string
    {
        $raw=$this->GetBuffer($name);
        $meta=json_decode($raw,true);
        if (!is_array($meta) || ($meta['_svhsChunks']??null)!==1) { return $raw; }
        $count=$meta['count']??null; $generation=$meta['generation']??'';
        if (!is_int($count) || $count<1 || $count>128 || !is_string($generation)
            || preg_match('/^[a-f0-9]{16}$/D',$generation)!==1) { return ''; }
        $data='';
        for($i=0;$i<$count;$i++) {
            $part=$this->GetBuffer($name.'.'.$generation.'.'.$i);
            if($part==='') { return ''; }
            $data.=$part;
        }
        if(($meta['encoding']??'')==='base64') {
            $decoded=base64_decode($data,true);
            if($decoded===false) { return ''; }
            $data=$decoded;
        }
        return strlen($data)===($meta['length']??null)
            && hash_equals((string)($meta['sha256']??''),hash('sha256',$data)) ? $data : '';
    }

    private function WriteLargeBuffer(string $name,string $data): void
    {
        $previous=json_decode($this->GetBuffer($name),true);
        $size=196608;
        if(strlen($data)<=$size) {
            $this->SetBuffer($name,$data);
        } else {
            // ASCII chunks keep split UTF-8 characters out of native API strings.
            $encoded=base64_encode($data);
            $count=(int)ceil(strlen($encoded)/$size);
            if($count>128) { throw new RuntimeException('Zwischenspeicher überschreitet 18 MiB.'); }
            $generation=bin2hex(random_bytes(8));
            for($i=0;$i<$count;$i++) { $this->SetBuffer($name.'.'.$generation.'.'.$i,substr($encoded,$i*$size,$size)); }
            // Publish the manifest only after every new chunk is present.
            $this->SetBuffer($name,json_encode(['_svhsChunks'=>1,'generation'=>$generation,
                'encoding'=>'base64','count'=>$count,'length'=>strlen($data),'sha256'=>hash('sha256',$data)],JSON_THROW_ON_ERROR));
        }
        if(is_array($previous) && ($previous['_svhsChunks']??null)===1
            && is_int($previous['count']??null) && $previous['count']>0 && $previous['count']<=128
            && preg_match('/^[a-f0-9]{16}$/D',(string)($previous['generation']??''))===1) {
            for($i=0;$i<$previous['count'];$i++) { $this->SetBuffer($name.'.'.$previous['generation'].'.'.$i,''); }
        }
    }
}
