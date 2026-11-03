<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__);
$public=dirname($private).'/public_html';
require $private.'/core.php';bp_security_headers();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id || $id<1){http_response_code(404);exit;}
try{
    $asset=bp_cached('public-asset:'.$id,function()use($id){
        return bp_public_asset(bp_db(),$id);
    });$public=(bool)$asset;
    if(!$asset && bp_admin()){$q=bp_db()->prepare('SELECT * FROM bp_blog_assets WHERE id=?');$q->execute([$id]);$asset=$q->fetch();}
    if(!$asset){http_response_code(404);exit;}
    if($asset['public_path']){header('Location: '.bp_asset_url($asset),true,302);exit;}
    if(!preg_match('/^[a-f0-9]{48}\.(pdf|png|jpg|jpeg|webp)$/',$asset['storage_key']??'')){http_response_code(404);exit;}
    $file=$private.'/uploads/'.$asset['storage_key'];if(!is_file($file)){http_response_code(404);exit;}
    if(!in_array($asset['mime_type'],['application/pdf','image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
    bp_serve_file($file,$asset['mime_type'],$asset['original_name'],isset($_GET['inline']),$public);
}catch(Throwable $e){
    // Keep diagnostics private and omit exception text, queries, identifiers, and credentials.
    error_log('Portfolio attachment unavailable ['.get_class($e).':'.(int)$e->getCode().':'.($e instanceof PDOException?(int)($e->errorInfo[1]??0):0).' '.basename($e->getFile()).':'.$e->getLine().'].');
    http_response_code(503);
}
