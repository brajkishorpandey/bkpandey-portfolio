<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__);
$public=dirname($private).'/public_html';
$slug=(string)($_GET['post']??'');
if(!is_file($private.'/core.php')){http_response_code(503);exit('The blog service is being configured.');}
require $private.'/core.php';bp_security_headers();
// A static publication is available until the owner configures the publishing backend.
if(!bp_configured()){
    $file=$slug===''?$public.'/blog/index.html':(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)?$public.'/blog/'.$slug.'.html':'');
    if($file && is_file($file)){readfile($file);exit;}http_response_code(404);readfile($public.'/404.html');exit;
}
// A warm public response opens no database connection.
try{
    $html=bp_cached('html:blog:v3:'.$slug,function()use($slug,$public){
    $db=bp_db();ob_start();
if($slug!==''){
    $post=bp_post($db,$slug);
    if(!$post){ob_end_clean();throw new OutOfBoundsException('Article not found.');}
    $assets=bp_assets($db,(int)$post['id']);
    bp_page_start($post['title'],$post['excerpt'],'/blog/'.$post['slug'].'.html');
    echo '<main id="main"><section class="page-hero" id="top"><div class="container"><div class="breadcrumb"><a href="/">Home</a><span>/</span><a href="/blog/">Blog</a></div><p class="eyebrow">'.bp_h($post['category']).' / '.bp_h($post['origin']).'</p><h1>'.bp_h($post['title']).'</h1><p class="lead">'.bp_h($post['excerpt']).'</p><div class="article-byline"><img src="/media/profile" width="42" height="42" alt=""><div><strong>Brajkishor Pandey</strong><p>Engineering leader · Fintech architect</p></div></div></div></section><article class="article-body container">';
    if($post['source_url'])echo '<div class="article-source-note">Adapted from my LinkedIn writing and related technical material.<a class="btn btn-small" target="_blank" rel="noopener noreferrer" href="'.bp_h($post['source_url']).'">Original LinkedIn post ↗</a></div>';
    echo bp_media_markup($assets,$post['pdf_display'],(int)$post['slider_interval']);echo bp_markdown($post['body']);
    echo '<div class="next-work"><h3>More from the blog.</h3><a class="btn btn-primary" href="/blog/">All articles ↗</a></div></article></main>';bp_page_end();return ob_get_clean();
}
$posts=bp_public_catalog($db);
bp_page_start('Fintech & technology blog','Engineering perspectives on payment architecture, software, AI, and fintech.');
echo '<main id="main"><section class="page-hero" id="top"><div class="container"><div class="breadcrumb"><a href="/">Home</a><span>/</span><span>Blog</span></div><p class="eyebrow">The blog / Fintech & technology</p><h1>Notes from the systems<br>that <span class="green">move money.</span></h1><p class="lead">Practical perspectives on payments, architecture, security, AI, and engineering leadership. Explore my articles, LinkedIn writing, and original visual guides.</p></div></section><section class="container blog-list-section"><div class="blog-toolbar"><div class="field"><label for="blog-search">Find an article</label><input id="blog-search" type="search" placeholder="Search payments, ledgers, AI…"></div><div class="field"><label for="blog-category">Explore a topic</label><select id="blog-category"><option value="all">All topics</option>';
foreach(array_unique(array_column($posts,'category')) as $c)echo '<option>'.bp_h($c).'</option>';
echo '</select></div></div><p class="blog-count" id="blog-count" role="status">Showing '.count($posts).' articles</p><div class="blog-grid">';
foreach($posts as $i=>$post){
    $url='/blog/'.$post['slug'].'.html';$assets=$post['assets'];$cover=null;
    foreach($assets as $a)if($a['is_cover'] && str_starts_with($a['mime_type'],'image/')){$cover=bp_asset_url($a,true);break;}
    if(!$cover)foreach($assets as $a)if(str_starts_with($a['mime_type'],'image/')){$cover=bp_asset_url($a,true);break;}
    echo '<article class="blog-card" data-category="'.bp_h($post['category']).'" data-search="'.bp_h(strtolower($post['title'].' '.$post['excerpt'].' '.$post['category'].' '.$post['slug'])).'"><a class="blog-cover" href="'.bp_h($url).'">';
    if($cover)echo '<img src="'.bp_h($cover).'" alt="'.bp_h($post['title']).'" width="800" height="800" loading="lazy">';
    else echo '<div class="blog-type-art tone-'.($i%4).'"><span>BP / FIELD NOTES</span><strong>'.bp_h($post['category']).'</strong><b aria-hidden="true">✳</b></div>';
    echo '</a><div class="blog-card-meta"><span>'.bp_h($post['category']).'</span><span>'.(count($assets)?'Visual guide':'Article').'</span></div><h2><a href="'.bp_h($url).'">'.bp_h($post['title']).'</a></h2><p>'.bp_h($post['excerpt']).'</p><a class="blog-read" href="'.bp_h($url).'">Read article <span aria-hidden="true">↗</span></a></article>';
}
echo '</div><p id="blog-empty" class="blog-empty" hidden>No articles match. Try another keyword or topic.</p></section></main><script defer src="/assets/js/blog.js"></script>';bp_page_end();

    return ob_get_clean();
    });
    if(!bp_etag($html))echo $html;
}catch(OutOfBoundsException $e){http_response_code(404);readfile($public.'/404.html');}
catch(Throwable $e){if(ob_get_level())ob_end_clean();error_log('Portfolio blog unavailable.');http_response_code(503);header('Retry-After: 60');echo 'The blog is temporarily unavailable. Please try again shortly.';}
