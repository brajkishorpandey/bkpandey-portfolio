<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__);
$public=dirname($private).'/public_html';
require $private.'/core.php';bp_security_headers();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id || $id<1){http_response_code(404);exit;}
try{
    $asset=bp_cached('public-asset:'.$id,function()use($id){
        $db=bp_db();$q=$db->prepare("SELECT a.* FROM bp_blog_assets a WHERE a.id=? AND (EXISTS(SELECT 1 FROM bp_blog_post_assets pa JOIN bp_blog_posts p ON p.id=pa.post_id WHERE pa.asset_id=a.id AND p.status='published') OR EXISTS(SELECT 1 FROM bp_site_settings s WHERE s.setting_key IN ('profile_asset','resume_asset') AND s.setting_value=CAST(a.id AS CHAR)))");$q->execute([$id]);return $q->fetch()?:null;
    });$public=(bool)$asset;
    if(!$asset && bp_admin()){$q=bp_db()->prepare('SELECT * FROM bp_blog_assets WHERE id=?');$q->execute([$id]);$asset=$q->fetch();}
    if(!$asset){http_response_code(404);exit;}
    if($asset['public_path']){header('Location: '.bp_asset_url($asset),true,302);exit;}
    if(!preg_match('/^[a-f0-9]{48}\.(pdf|png|jpg|jpeg|webp)$/',$asset['storage_key']??'')){http_response_code(404);exit;}
    $file=$private.'/uploads/'.$asset['storage_key'];if(!is_file($file)){http_response_code(404);exit;}
    if(!in_array($asset['mime_type'],['application/pdf','image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
    bp_serve_file($file,$asset['mime_type'],$asset['original_name'],isset($_GET['inline']),$public);
}catch(Throwable $e){error_log('Portfolio attachment unavailable.');http_response_code(503);}
