<?php
declare(strict_types=1);
require __DIR__.'/core.php';bp_security_headers();
$root=dirname(__DIR__).'/public_html';
$fallback=$kind==='resume'?'/assets/documents/Brajkishor_Pandey_Resume.pdf':'/assets/images/braj-portrait.webp';
try{
    $asset=bp_configured()?bp_cached('site-media:'.$kind,function()use($kind){
        $db=bp_db();$q=$db->prepare('SELECT a.* FROM bp_blog_assets a JOIN bp_site_settings s ON s.setting_value=CAST(a.id AS CHAR) WHERE s.setting_key=?');$q->execute([$kind.'_asset']);return $q->fetch()?:null;
    }):null;
    if($asset && preg_match('/^[a-f0-9]{48}\.(pdf|png|jpg|jpeg|webp)$/',$asset['storage_key']??'')){
        $path=__DIR__.'/uploads/'.$asset['storage_key'];
        if(is_file($path)){bp_serve_file($path,$asset['mime_type'],$asset['original_name'],$kind==='profile',true,true);exit;}
    }
}catch(Throwable $e){error_log('Portfolio media fallback in use.');}
bp_serve_file($root.$fallback,$kind==='resume'?'application/pdf':'image/webp',basename($fallback),$kind==='profile',true,true);
