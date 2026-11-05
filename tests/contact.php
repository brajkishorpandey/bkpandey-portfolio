<?php
declare(strict_types=1);
require __DIR__.'/../hosting/bkpandey-private/core.php';
require __DIR__.'/../hosting/bkpandey-private/contact.php';
require __DIR__.'/../hosting/bkpandey-private/admin-navigation.php';
bp_session();$checks=0;
function check_contact(bool $condition,string $message): void {global $checks;$checks++;if(!$condition)throw new RuntimeException($message);}
function reject_contact(callable $run,string $message): void {try{$run();}catch(InvalidArgumentException|RuntimeException $e){check_contact(true,$message);return;}check_contact(false,$message);}
$server=['HTTPS'=>'on','HTTP_X_BP_CONTACT'=>'1','HTTP_SEC_FETCH_SITE'=>'same-origin','HTTP_ORIGIN'=>'https://bkpandey.com','CONTENT_TYPE'=>'application/json','CONTENT_LENGTH'=>'1000'];
bp_contact_request_allowed($server,true);check_contact(true,'Accept same-origin JSON request');
foreach([['HTTPS'=>'off'],['HTTP_X_BP_CONTACT'=>''],['HTTP_SEC_FETCH_SITE'=>'cross-site'],['HTTP_ORIGIN'=>'https://other.example'],['CONTENT_TYPE'=>'application/x-www-form-urlencoded'],['CONTENT_LENGTH'=>'17000']] as $change)reject_contact(fn()=>bp_contact_request_allowed(array_replace($server,$change),true),'Reject unsafe request context');
$input=['csrf'=>str_repeat('a',64),'turnstile'=>'test-token','website'=>'','name'=>'A visitor','email'=>'visitor@example.test','service'=>'Engineering leadership','message'=>'I would like to discuss a platform engineering project.'];
$data=bp_contact_validate($input);check_contact($data['email']==='visitor@example.test','Accept bounded valid message');
foreach([['to'=>'unrelated@example.test'],['cc'=>'unrelated@example.test'],['from'=>'spoof@example.test'],['website'=>'https://spam.example'],['name'=>"Visitor\r\nBcc: x@example.test"],['email'=>"x@example.test\r\nBcc: y@example.test"],['email'=>['x@example.test']],['service'=>'arbitrary service'],['message'=>str_repeat('x',5001)],['message'=>'short'],['turnstile'=>''],['turnstile'=>str_repeat('t',2049)]] as $change)reject_contact(fn()=>bp_contact_validate(array_replace($input,$change)),'Reject injection, honeypot, unexpected fields, or invalid bounds');
$session=['contact'=>['csrf'=>$input['csrf'],'issued'=>1000,'used'=>false]];
reject_contact(function()use(&$session){bp_contact_claim($session,'wrong',1005);},'Reject mismatched CSRF');
reject_contact(function()use(&$session,$input){bp_contact_claim($session,$input['csrf'],1001);},'Reject implausibly fast submission');
$binding=bp_contact_claim($session,$input['csrf'],1005);check_contact($binding===hash('sha256',$input['csrf']),'Bind verification to form session');
reject_contact(function()use(&$session,$input){bp_contact_claim($session,$input['csrf'],1006);},'Reject nonce replay');
$session=['contact'=>['csrf'=>$input['csrf'],'issued'=>1000,'used'=>false]];
reject_contact(function()use(&$session,$input){bp_contact_claim($session,$input['csrf'],3000);},'Reject expired form');
$verified=['success'=>true,'hostname'=>'bkpandey.com','action'=>'contact','cdata'=>$binding];
check_contact(bp_contact_verify_result($verified,$binding),'Accept matching verification claims');
foreach([['success'=>false],['hostname'=>'attacker.example'],['action'=>'login'],['cdata'=>'other-session']] as $change)check_contact(!bp_contact_verify_result(array_replace($verified,$change),$binding),'Reject invalid verification claims');
check_contact(!bp_contact_ready(['enabled'=>false,'sitekey'=>'','secret'=>'']),'Fail closed before configuration');
check_contact(!bp_contact_ready(['enabled'=>true,'sitekey'=>'1x00000000000000000000AA','secret'=>'1x0000000000000000000000000000000AA']),'Reject production use of demo keys');
$directory=sys_get_temp_dir().'/bp-contact-test-'.bin2hex(random_bytes(8));mkdir($directory,0700);
try{
    bp_contact_limit($directory,[['ip:a',2,60],['global',3,60]],1000);
    bp_contact_limit($directory,[['ip:a',2,60],['global',3,60]],1001);
    reject_contact(fn()=>bp_contact_limit($directory,[['ip:a',2,60],['global',3,60]],1002),'Enforce per-client limit');
    bp_contact_limit($directory,[['ip:b',2,60],['global',3,60]],1003);
    reject_contact(fn()=>bp_contact_limit($directory,[['ip:c',2,60],['global',3,60]],1004),'Enforce global limit');
    $stored=file_get_contents($directory.'/limits.json');check_contact(!str_contains($stored,'ip:a') && !str_contains($stored,'global'),'Do not persist raw limiter identifiers');
    bp_contact_limit($directory,[['ip:a',2,60],['global',3,60]],1061);check_contact(true,'Allow requests after limit expiry');
    file_put_contents($directory.'/limits.json','corrupted state');reject_contact(fn()=>bp_contact_limit($directory,[['x',1,60]],1100),'Fail closed on corrupted limiter state');
}finally{unlink($directory.'/limits.json');rmdir($directory);}
$site=['contact_email'=>'owner@example.test','email_subject'=>"Portfolio enquiry: {service}\r\nBcc: attempted@example.test",'email_body'=>"Name: {name}\nReply email: {email}\n\n{message}"];$captured=null;
check_contact(bp_contact_deliver($data,$site,function(...$arguments)use(&$captured){$captured=$arguments;return true;}),'Pass message to mail transport');
check_contact($captured[0]==='owner@example.test','Only owner-configured recipient is used');
check_contact($captured[3]['Reply-To']==='visitor@example.test' && $captured[3]['From']==='Brajkishor Pandey Website <contact@bkpandey.com>','Keep sender fixed and reply address validated');
check_contact(!array_key_exists('Cc',$captured[3]) && !array_key_exists('Bcc',$captured[3]) && $captured[4]==='-fcontact@bkpandey.com','No arbitrary mail headers or command parameters');
check_contact(str_contains(base64_decode($captured[2]),$data['message']),'Preserve message in plain-text MIME body');
check_contact(!str_contains($captured[1],"\r\nBcc:"),'Subject template cannot inject headers');
reject_contact(fn()=>bp_contact_deliver($data,array_replace($site,['contact_email'=>"x@example.test\r\nBcc: y@example.test"]),fn()=>true),'Validate configured recipient at send time');
reject_contact(fn()=>bp_contact_deliver(array_replace($data,['email'=>"x@example.test\r\nBcc: y@example.test"]),$site,fn()=>true),'Validate reply address at send time');
foreach(['articles'=>[],'new'=>['new'=>'1'],'site'=>['site'=>'1'],'contact'=>['contact'=>'1'],'traffic'=>['traffic'=>'1'],'account'=>['account'=>'1'],'database'=>['database'=>'1']] as $section=>$query){
    check_contact(bp_admin_section($query)===$section,'Select current admin section');$html=bp_admin_navigation($query);
    check_contact(substr_count($html,'aria-current="page"')===1,'Exactly one admin navigation link is current');
    check_contact(str_contains($html, 'href="'.bp_h(BP_ADMIN_SECTIONS[$section][1]).'" aria-current="page"'), 'The correct admin link is highlighted');
}
check_contact(bp_admin_section(['edit'=>'12'])==='articles','Editing keeps article collection current');
check_contact(bp_admin_section(['account'=>'1','database'=>'1'])==='account','Navigation follows controller view precedence');
check_contact(bp_admin_return_path(['traffic'=>'1','day'=>'2026-11-03','next'=>'https://example.test'])==='/admin?traffic=1&day=2026-11-03','Sign-in retains the linked daily report');
check_contact(bp_admin_return_path(['traffic'=>'1','day'=>"2026-11-03\r\nX: a"])==='/admin?traffic=1&day=2026-11-03%0D%0AX%3A%20a','Sign-in encodes header-breaking input');
echo $checks.' contact and navigation checks passed.'.PHP_EOL;
