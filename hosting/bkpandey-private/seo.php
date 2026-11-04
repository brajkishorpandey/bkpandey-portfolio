<?php
declare(strict_types=1);

function bp_seo_date(?string $value): ?string {
    if(!$value)return null;
    foreach(['!Y-m-d H:i:s','!Y-m-d'] as $format){
        $date=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('UTC'));
        $errors=DateTimeImmutable::getLastErrors();
        if($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])))return $date->format('Y-m-d\TH:i:s\Z');
    }
    return null;
}
function bp_seo_image(array $assets): string {
    foreach([true,false] as $cover)foreach($assets as $a)if(str_starts_with($a['mime_type'],'image/') && (!$cover || !empty($a['is_cover'])))return 'https://bkpandey.com'.bp_asset_url($a,true);
    return 'https://bkpandey.com/assets/images/social-card.png';
}
function bp_seo_tags(string $title,string $description,string $path='/',array $options=[],?array $values=null): string {
    $domain='https://bkpandey.com';$url=$domain.$path;$personId=$domain.'/resume.html#person';
    $v=$values??bp_public_site_values();
    $full=str_contains($title,'Brajkishor Pandey')?$title:$title.' | Brajkishor Pandey';
    $person=['@type'=>'Person','@id'=>$personId,'name'=>'Brajkishor Pandey','alternateName'=>'Braj','url'=>$domain.'/resume.html','image'=>$domain.'/media/profile','jobTitle'=>'Head of Engineering & Fintech Systems Architect','sameAs'=>[$v['contact_linkedin']],'knowsAbout'=>['Cross-border payments','Fintech architecture','Payment APIs','Double-entry ledgers','Software engineering','Engineering leadership']];
    if($path==='/resume.html')$person['description']=$v['resume_summary'];
    $page=['@type'=>$path==='/resume.html'?'ProfilePage':'WebPage','@id'=>$url.'#page','url'=>$url,'name'=>$full,'description'=>$description,'inLanguage'=>'en','isPartOf'=>['@id'=>$domain.'/#website']];
    if(in_array($path,['/','/resume.html'],true))$page['mainEntity']=['@id'=>$personId];
    $image=$options['image']??($domain.'/assets/images/social-card.png');
    $graph=[$person,['@type'=>'WebSite','@id'=>$domain.'/#website','url'=>$domain.'/','name'=>'Brajkishor Pandey','publisher'=>['@id'=>$personId],'inLanguage'=>'en']];
    $post=$options['article']??null;
    if($post){
        $page['mainEntity']=['@id'=>$url.'#article'];
        $article=['@type'=>'BlogPosting','@id'=>$url.'#article','headline'=>$title,'description'=>$description,'url'=>$url,'mainEntityOfPage'=>['@id'=>$url.'#page'],'author'=>['@id'=>$personId],'publisher'=>['@id'=>$personId],'image'=>[$image],'inLanguage'=>'en','articleSection'=>$post['category']];
        if($date=bp_seo_date($post['published_at']??null))$article['datePublished']=$date;
        if($date=bp_seo_date($post['updated_at']??null))$article['dateModified']=$date;
        if(!empty($post['source_url']))$article['isBasedOn']=$post['source_url'];
        $graph[]=$article;
    }elseif($path==='/blog/')$page['@type']='CollectionPage';
    $graph[]=$page;
    if($path!=='/'){
        $crumbs=[['Home',$domain.'/']];if($post)$crumbs[]=['Blog',$domain.'/blog/'];$crumbs[]=[$title,$url];$items=[];
        foreach($crumbs as $i=>[$name,$link])$items[]=['@type'=>'ListItem','position'=>$i+1,'name'=>$name,'item'=>$link];
        $graph[]=['@type'=>'BreadcrumbList','itemListElement'=>$items];
    }
    $out='<!-- SEO:start --><title>'.bp_h($full).'</title><meta name="description" content="'.bp_h($description).'"><link rel="canonical" href="'.bp_h($url).'">';
    foreach(['og:type'=>$post?'article':'website','og:site_name'=>'Brajkishor Pandey','og:title'=>$full,'og:description'=>$description,'og:url'=>$url,'og:image'=>$image,'og:image:alt'=>$title,'og:locale'=>'en_IN'] as $key=>$value)$out.='<meta property="'.$key.'" content="'.bp_h($value).'">';
    foreach(['twitter:card'=>'summary_large_image','twitter:title'=>$full,'twitter:description'=>$description,'twitter:image'=>$image,'twitter:image:alt'=>$title,'author'=>'Brajkishor Pandey'] as $key=>$value)$out.='<meta name="'.$key.'" content="'.bp_h($value).'">';
    if($post)foreach(['article:published_time'=>'published_at','article:modified_time'=>'updated_at'] as $key=>$field)if($date=bp_seo_date($post[$field]??null))$out.='<meta property="'.$key.'" content="'.$date.'">';
    $out.='<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    return $out.'<link rel="alternate" type="application/rss+xml" title="Brajkishor Pandey — Blog" href="/blog/feed.xml"><!-- SEO:end -->';
}
function bp_apply_seo_html(string $html,array $v,string $path): string {
    $prefix=$path==='/'?'seo_home_':'seo_profile_';
    // Also handles older deployed HTML, without leaving duplicate metadata behind.
    $html=preg_replace('~<!-- SEO:start -->.*?<!-- SEO:end -->~s','',$html);
    $html=preg_replace('~<title>.*?</title>|<meta\s+(?:name="(?:description|author|twitter:[^"]+)"|property="(?:og:|article:)[^"]+")[^>]*>|<link\s+rel="(?:canonical|alternate)"[^>]*>|<script\s+type="application/ld\+json">.*?</script>~is','',$html);
    return str_replace('</head>',bp_seo_tags($v[$prefix.'title'],$v[$prefix.'description'],$path,[],$v).'</head>',$html);
}
function bp_sitemap_rows(PDO $db,array $paths): array {
    $rows=[];foreach($paths as $path)if(is_string($path) && preg_match('~^/(?:[a-z0-9/-]+\.html)?$~',$path))$rows[$path]=null;
    $latest=null;
    foreach($db->query("SELECT slug,updated_at FROM bp_blog_posts WHERE status='published' ORDER BY id LIMIT 49900")->fetchAll() as $post){
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$post['slug']))continue;
        $date=bp_seo_date($post['updated_at']);$rows['/blog/'.$post['slug'].'.html']=$date;
        if($date && (!$latest || strcmp($date,$latest)>0))$latest=$date;
    }
    $rows['/blog/']=$latest;
    $changed=$db->query('SELECT MAX(updated_at) FROM bp_site_settings')->fetchColumn();
    if($date=bp_seo_date($changed?:null))foreach(['/','/resume.html'] as $path)$rows[$path]=$date;
    return $rows;
}
function bp_sitemap_xml(array $rows): string {
    $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach($rows as $path=>$date)$xml.='<url><loc>'.bp_h('https://bkpandey.com'.$path).'</loc>'.($date?'<lastmod>'.bp_h($date).'</lastmod>':'').'</url>';
    return $xml.'</urlset>';
}
function bp_feed_xml(array $posts): string {
    $xml='<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel><title>Brajkishor Pandey — Blog</title><link>https://bkpandey.com/blog/</link><description>Payment architecture, technology, and engineering leadership.</description><language>en</language><atom:link href="https://bkpandey.com/blog/feed.xml" rel="self" type="application/rss+xml"/>';
    foreach($posts as $p){$url='https://bkpandey.com/blog/'.$p['slug'].'.html';$xml.='<item><title>'.bp_h($p['title']).'</title><link>'.bp_h($url).'</link><guid isPermaLink="true">'.bp_h($url).'</guid><description>'.bp_h($p['excerpt']).'</description>';
        if($date=bp_seo_date($p['published_at']??null))$xml.='<pubDate>'.(new DateTimeImmutable($date))->format(DATE_RSS).'</pubDate>';
        $xml.='</item>';
    }
    return $xml.'</channel></rss>';
}
