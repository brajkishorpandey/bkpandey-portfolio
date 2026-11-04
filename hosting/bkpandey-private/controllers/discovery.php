<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__);$public=dirname($private).'/public_html';
require $private.'/core.php';bp_security_headers();
$feed=($discoveryMode??'sitemap')==='feed';
header('Content-Type: '.($feed?'application/rss+xml':'application/xml').'; charset=UTF-8');
try{
    if(!bp_configured()){$xml=file_get_contents($public.($feed?'/blog/feed.xml':'/sitemap.xml'));}
    else $xml=bp_cached('discovery:v1:'.($feed?'feed':'sitemap'),function()use($private,$feed){
        $db=bp_db();
        if($feed){$posts=$db->query("SELECT slug,title,excerpt,published_at FROM bp_blog_posts WHERE status='published' ORDER BY published_at DESC,id DESC LIMIT 100")->fetchAll();return bp_feed_xml($posts);}
        $paths=json_decode(file_get_contents($private.'/seo-pages.json'),true,512,JSON_THROW_ON_ERROR);
        return bp_sitemap_xml(bp_sitemap_rows($db,$paths));
    });
    if(!bp_etag($xml))echo $xml;
}catch(Throwable $e){error_log('Portfolio discovery unavailable.');http_response_code(503);header('Retry-After: 60');echo '<?xml version="1.0"?><error>Temporarily unavailable</error>';}
