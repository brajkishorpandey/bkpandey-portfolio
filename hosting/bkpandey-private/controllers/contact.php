<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__);
require $private.'/core.php';require $private.'/contact.php';
bp_security_headers(true);header('Content-Type: application/json; charset=utf-8');
function bp_contact_response(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');bp_contact_response(405,['message'=>'Use the website contact form.']);}
try{
    bp_contact_request_allowed($_SERVER,$method==='POST');
    $config=bp_contact_config();if(!bp_contact_ready($config))bp_contact_response(503,['message'=>'The contact form is temporarily unavailable. Please use the email link.']);
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$now=time();$directory=bp_contact_state_dir();
    bp_contact_limit($directory,[['request:'.$ip,20,3600]],$now);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('bp_contact');session_set_cookie_params(['lifetime'=>0,'path'=>'/api/contact','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);session_start();
    if($method==='GET'){
        $_SESSION['contact']=['csrf'=>bin2hex(random_bytes(32)),'issued'=>$now,'used'=>false];
        $csrf=$_SESSION['contact']['csrf'];session_write_close();
        bp_contact_response(200,['csrf'=>$csrf,'binding'=>hash('sha256',$csrf),'sitekey'=>$config['sitekey']]);
    }
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384)throw new InvalidArgumentException('The message is too long.');
    $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Invalid contact form request.');
    bp_contact_limit($directory,[['attempt:'.$ip,5,900]],$now);
    $data=bp_contact_validate($input);$binding=bp_contact_claim($_SESSION,$input['csrf']??'',$now);session_write_close();
    if(!bp_contact_verify_token($input['turnstile'],$config['secret'],$ip,$binding))throw new InvalidArgumentException('Verification expired or failed. Refresh the verification and try again.');
    bp_contact_limit($directory,[['send-hour:'.$ip,3,3600],['send-day:'.$ip,8,86400],['email:'.strtolower($data['email']),3,86400],['global-hour',20,3600],['global-day',50,86400]],$now);
    if(!bp_contact_deliver($data,bp_public_site_values()))throw new RuntimeException('Email delivery is temporarily unavailable. Please use the email link.');
    bp_contact_response(200,['message'=>'Thank you. Your message has been submitted to Braj.']);
}catch(InvalidArgumentException|JsonException $e){bp_contact_response(400,['message'=>$e instanceof JsonException?'Invalid contact form request.':$e->getMessage()]);}
catch(BPContactRateLimit $e){header('Retry-After: 3600');bp_contact_response(429,['message'=>$e->getMessage()]);}
catch(RuntimeException $e){bp_contact_response(503,['message'=>'The contact form is temporarily unavailable. Please use the email link.']);}
catch(Throwable $e){error_log('Portfolio contact request unavailable.');bp_contact_response(503,['message'=>'The contact form is temporarily unavailable. Please use the email link.']);}
