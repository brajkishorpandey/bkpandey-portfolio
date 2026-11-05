<?php
declare(strict_types=1);
class BPContactRateLimit extends RuntimeException {}

function bp_contact_config(): array {
    $path=__DIR__.'/contact-config.json';$config=is_file($path)?json_decode((string)file_get_contents($path),true):[];
    if(!is_array($config))$config=[];
    foreach(['sitekey'=>'BP_TURNSTILE_SITEKEY','secret'=>'BP_TURNSTILE_SECRET'] as $key=>$env){$value=getenv($env);if($value!==false)$config[$key]=$value;}
    if(getenv('BP_CONTACT_ENABLED')!==false)$config['enabled']=getenv('BP_CONTACT_ENABLED')==='1';
    return $config+['enabled'=>false,'sitekey'=>'','secret'=>''];
}
function bp_contact_key_valid(string $key): bool {
    return (bool)preg_match('/^[A-Za-z0-9_-]{20,256}$/',$key) && !preg_match('/^[123]x0{10}/',$key);
}
function bp_contact_ready(array $config): bool {
    return $config['enabled']===true && bp_contact_key_valid((string)$config['sitekey']) && bp_contact_key_valid((string)$config['secret']);
}
function bp_contact_save_config(array $input): void {
    $old=bp_contact_config();$sitekey=trim((string)($input['turnstile_sitekey']??''));$secret=trim((string)($input['turnstile_secret']??''));
    if($secret==='')$secret=(string)$old['secret'];$enabled=($input['contact_enabled']??'')==='1';
    if($enabled && (!bp_contact_key_valid($sitekey) || !bp_contact_key_valid($secret)))throw new InvalidArgumentException('Enter production Turnstile keys. Demo keys cannot enable contact email.');
    $config=['enabled'=>$enabled,'sitekey'=>$sitekey,'secret'=>$secret];$temp=tempnam(__DIR__,'contact-config-');
    if($temp===false)throw new RuntimeException('Private contact configuration is not writable.');
    try{chmod($temp,0600);if(file_put_contents($temp,json_encode($config,JSON_THROW_ON_ERROR),LOCK_EX)===false || !rename($temp,__DIR__.'/contact-config.json'))throw new RuntimeException('Could not save private contact configuration.');}
    finally{if(is_file($temp))unlink($temp);}
}
function bp_contact_state_dir(): string {
    $directory=__DIR__.'/contact-state';
    if(!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory))throw new RuntimeException('Contact protection is unavailable.');
    return $directory;
}
// Each request reserves every applicable bucket while holding one exclusive lock.
// Identifiers are salted hashes; names, addresses, and messages are not persisted here.
function bp_contact_limit(string $directory,array $buckets,int $now): void {
    if(!is_dir($directory))throw new RuntimeException('Contact protection is unavailable.');
    $file=$directory.'/limits.json';$handle=fopen($file,'c+b');if(!$handle)throw new RuntimeException('Contact protection is unavailable.');
    chmod($file,0600);
    try{
        if(!flock($handle,LOCK_EX))throw new RuntimeException('Contact protection is busy.');
        $raw=stream_get_contents($handle,1048577);if(strlen($raw)>1048576)throw new RuntimeException('Contact protection is busy.');
        try{$state=$raw===''?['salt'=>bin2hex(random_bytes(32)),'buckets'=>[]]:json_decode($raw,true,32,JSON_THROW_ON_ERROR);}
        catch(JsonException $e){throw new RuntimeException('Contact protection is unavailable.',0,$e);}
        if(!is_array($state) || !is_string($state['salt']??null) || !is_array($state['buckets']??null))throw new RuntimeException('Contact protection is unavailable.');
        foreach($state['buckets'] as $key=>$bucket)if(($bucket['expires']??0)<=$now)unset($state['buckets'][$key]);
        $next=[];
        foreach($buckets as [$identifier,$maximum,$seconds]){
            $key=hash_hmac('sha256',$identifier,$state['salt']);$bucket=$state['buckets'][$key]??['count'=>0,'expires'=>$now+$seconds];
            if((int)$bucket['count']>=$maximum)throw new BPContactRateLimit('Too many requests. Please try again later or email Braj directly.');
            $bucket['count']++;$next[$key]=$bucket;
        }
        if(count($state['buckets'])+count($next)>5000)throw new RuntimeException('Contact protection is busy. Please try again later.');
        $state['buckets']=array_replace($state['buckets'],$next);$encoded=json_encode($state,JSON_THROW_ON_ERROR);
        rewind($handle);if(!ftruncate($handle,0) || fwrite($handle,$encoded)!==strlen($encoded) || !fflush($handle))throw new RuntimeException('Contact protection is unavailable.');
    }finally{flock($handle,LOCK_UN);fclose($handle);}
}
function bp_contact_request_allowed(array $server,bool $post): void {
    if(empty($server['HTTPS']) || $server['HTTPS']==='off')throw new InvalidArgumentException('Use the secure website contact form.');
    if(($server['HTTP_X_BP_CONTACT']??'')!=='1')throw new InvalidArgumentException('Open the contact form on this website.');
    if(isset($server['HTTP_SEC_FETCH_SITE']) && $server['HTTP_SEC_FETCH_SITE']!=='same-origin')throw new InvalidArgumentException('Use the contact form on this website.');
    if($post && ($server['HTTP_ORIGIN']??'')!=='https://bkpandey.com')throw new InvalidArgumentException('Use the contact form on this website.');
    if($post && strtolower(trim(explode(';',$server['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException('Invalid contact form request.');
    if((int)($server['CONTENT_LENGTH']??0)>16384)throw new InvalidArgumentException('The message is too long.');
}
function bp_contact_validate(array $input): array {
    $allowed=['csrf','turnstile','name','email','service','message','website'];
    if(array_diff(array_keys($input),$allowed))throw new InvalidArgumentException('Unexpected contact form field.');
    foreach($input as $value)if(!is_string($value))throw new InvalidArgumentException('Invalid contact form field.');
    if(trim($input['website']??'')!=='')throw new InvalidArgumentException('The contact form could not be verified.');
    $data=[];foreach(['name','email','service','message'] as $key)$data[$key]=trim($input[$key]??'');
    if(!preg_match('//u',implode('',$data)))throw new InvalidArgumentException('Use valid text in your message.');
    if(strlen($data['name'])<2 || strlen($data['name'])>100 || preg_match('/[\x00-\x1F\x7F]/',$data['name']))throw new InvalidArgumentException('Enter your name (2–100 characters).');
    if(strlen($data['email'])>254 || preg_match('/[\x00-\x20\x7F]/',$data['email']) || !filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid reply email address.');
    $services=['Fintech & payment architecture','Engineering leadership','Full-stack product development','Cloud, security & modernization','AI, forecasting & data','Remote engineering talent','Other collaboration'];
    if(!in_array($data['service'],$services,true))throw new InvalidArgumentException('Choose an area of interest.');
    if(strlen($data['message'])<20 || strlen($data['message'])>5000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$data['message']))throw new InvalidArgumentException('Write a message of 20–5,000 characters.');
    if(strlen($input['turnstile']??'')<1 || strlen($input['turnstile']??'')>2048)throw new InvalidArgumentException('Complete the verification before sending.');
    return $data;
}
function bp_contact_claim(array &$session,string $csrf,int $now): string {
    $stored=$session['contact']??[];
    if(empty($stored['csrf']) || !hash_equals($stored['csrf'],$csrf) || !empty($stored['used']))throw new InvalidArgumentException('This form has expired. Refresh the verification and try again.');
    $age=$now-(int)($stored['issued']??0);
    if($age<3 || $age>1800)throw new InvalidArgumentException('Refresh the contact form verification and try again.');
    $session['contact']['used']=true;return hash('sha256',$stored['csrf']);
}
function bp_contact_verify_result(array $result,string $binding): bool {
    return ($result['success']??false)===true && ($result['hostname']??'')==='bkpandey.com' && ($result['action']??'')==='contact' && is_string($result['cdata']??null) && hash_equals($binding,$result['cdata']);
}
function bp_contact_verify_token(string $token,string $secret,string $ip,string $binding): bool {
    $url='https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $body=http_build_query(['secret'=>$secret,'response'=>$token,'remoteip'=>$ip]);
    $headers=['Content-Type: application/x-www-form-urlencoded','Accept: application/json'];
    if(function_exists('curl_init')){
        // Shared hosting may disable PHP URL streams even when outbound HTTPS works.
        $curl=curl_init($url);
        if($curl===false)return false;
        $raw='';
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$raw): int {
                if(strlen($raw)+strlen($chunk)>16384)return 0;
                $raw.=$chunk;
                return strlen($chunk);
            },
        ]);
        if(curl_exec($curl)===false)return false;
    }else{
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers)."\r\n",'content'=>$body,'timeout'=>8,'follow_location'=>0,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $raw=@file_get_contents($url,false,$context,0,16385);
    }
    if($raw===false || strlen($raw)>16384)return false;
    $result=json_decode($raw,true);return is_array($result) && bp_contact_verify_result($result,$binding);
}
function bp_contact_subject(string $text): string {
    $text=preg_replace('/[\x00-\x1F\x7F]/u',' ',$text);$characters=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $chunks=[];$part='';
    foreach(array_slice($characters,0,180) as $character){if(strlen($part.$character)>42){$chunks[]=$part;$part='';}$part.=$character;}
    if($part!=='')$chunks[]=$part;
    return implode("\r\n ",array_map(fn($chunk)=>'=?UTF-8?B?'.base64_encode($chunk).'?=',$chunks));
}
function bp_contact_deliver(array $data,array $site,?callable $transport=null): bool {
    $recipient=(string)($site['contact_email']??'');
    if(preg_match('/[\x00-\x20\x7F]/',$recipient) || !filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Contact delivery is not configured.');
    if(preg_match('/[\x00-\x20\x7F]/',$data['email']) || !filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid reply email address.');
    $render=fn($text)=>preg_replace_callback('/\{(name|email|service|message)\}/',fn($m)=>$data[$m[1]],$text);
    $subject=bp_contact_subject($render((string)$site['email_subject']));
    $body=$render((string)$site['email_body'])."\n\n— Submitted through bkpandey.com contact form.\n";
    $headers=['From'=>'Brajkishor Pandey Website <contact@bkpandey.com>','Reply-To'=>$data['email'],'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
    // The envelope sender and headers are fixed; the public request cannot choose a recipient.
    $transport=$transport??fn($to,$subject,$body,$headers,$parameters)=>mail($to,$subject,$body,$headers,$parameters);
    return (bool)$transport($recipient,$subject,chunk_split(base64_encode($body),76,"\r\n"),$headers,'-fcontact@bkpandey.com');
}
