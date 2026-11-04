<?php
declare(strict_types=1);
// This directory belongs outside the public document root.
require_once __DIR__.'/seo.php';
function bp_settings(): array {
    $stored=[];$path=__DIR__.'/database.php';
    if(is_file($path)){$stored=require $path;if(!is_array($stored))$stored=[];}
    foreach(['db_host'=>'BP_DB_HOST','db_name'=>'BP_DB_NAME','db_user'=>'BP_DB_USER','db_password'=>'BP_DB_PASSWORD'] as $key=>$env){
        $value=getenv($env);if($value!==false)$stored[$key]=$value;
    }
    return $stored;
}
function bp_configured(): bool {
    $s=bp_settings();return isset($s['db_name'],$s['db_user'],$s['db_password']);
}
function bp_db(): PDO {
    static $db=null;
    if ($db instanceof PDO) return $db;
    $s=bp_settings();if (!isset($s['db_name'],$s['db_user'],$s['db_password'])) throw new RuntimeException('Blog environment is not configured.');
    $host=$s['db_host']??'localhost';$name=$s['db_name'];
    if (!preg_match('/^[A-Za-z0-9_]+$/',$name) || !preg_match('/^[A-Za-z0-9._:-]+$/',$host)) throw new RuntimeException('Invalid database configuration.');
    $db=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$s['db_user'],$s['db_password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $db;
}
function bp_h(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function bp_now(): string {return gmdate('Y-m-d H:i:s');}
function bp_source_url(?string $s): ?string {
    $s=trim($s ?? '');if ($s==='') return null;
    if (strlen($s)>2000 || !filter_var($s,FILTER_VALIDATE_URL) || strtolower((string)parse_url($s,PHP_URL_SCHEME))!=='https' || !parse_url($s,PHP_URL_HOST)) throw new InvalidArgumentException('Use a valid HTTPS source URL.');
    return $s;
}
function bp_inline_text(string $text): string {
    $parts=preg_split('~(https://[^\s<>"\x27]+)~u',$text,-1,PREG_SPLIT_DELIM_CAPTURE);$out='';
    foreach($parts as $i=>$part){
        if($i%2===0){$out.=bp_h($part);continue;}
        $url=rtrim($part,'.),;');$suffix=substr($part,strlen($url));
        try{bp_source_url($url);$out.='<a href="'.bp_h($url).'" target="_blank" rel="noopener noreferrer">'.bp_h($url).'</a>'.bp_h($suffix);}
        catch(InvalidArgumentException $e){$out.=bp_h($part);}
    }
    return $out;
}
function bp_markdown(string $text): string {
    $out='';$list=false;
    foreach (preg_split('/\R/u',$text) as $line) {
        if (str_starts_with($line,'- ')) {if(!$list){$out.='<ul>';$list=true;}$out.='<li>'.bp_inline_text(substr($line,2)).'</li>';continue;}
        if($list){$out.='</ul>';$list=false;}
        if(str_starts_with($line,'## ')) $out.='<h2>'.bp_h(substr($line,3)).'</h2>';
        elseif(str_starts_with($line,'### ')) $out.='<h3>'.bp_h(substr($line,4)).'</h3>';
        elseif(trim($line)!=='') $out.='<p>'.bp_inline_text($line).'</p>';
    }
    return $out.($list?'</ul>':'');
}
function bp_security_headers(bool $admin=false): void {
    header_remove('X-Powered-By');ini_set('display_errors','0');
    header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: SAMEORIGIN');
    if($admin){header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');}
}
function bp_session(): void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('bp_publisher');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);session_start();
    $now=time();
    if(isset($_SESSION['admin_id']) && ($now-($_SESSION['last_seen']??0)>1800 || $now-($_SESSION['signed_in']??0)>28800)){
        $_SESSION=[];session_regenerate_id(true);
    }
    $_SESSION['last_seen']=$now;
}
function bp_admin(): bool {
    bp_session();if(empty($_SESSION['admin_id']))return false;
    $q=bp_db()->prepare('SELECT password_hash FROM bp_blog_admins WHERE id=?');$q->execute([(int)$_SESSION['admin_id']]);$hash=$q->fetchColumn();
    if(!$hash || empty($_SESSION['auth_stamp']) || !hash_equals(hash('sha256',$hash),$_SESSION['auth_stamp'])){$_SESSION=[];session_regenerate_id(true);return false;}return true;
}
function bp_csrf(): string {bp_session();if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function bp_check_csrf(?string $token): void {
    bp_session();if(!$token || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],$token)) throw new RuntimeException('The form expired. Refresh and try again.');
}
function bp_try_login(PDO $db,string $email,string $password,string $ip): bool {
    $email=strtolower(trim($email));
    // Limits are keyed separately by address and account to resist targeted and broad guessing.
    $keys=[hash('sha256','ip:'.$ip),hash('sha256','email:'.$email)];$now=time();
    $db->beginTransaction();
    try {
        foreach($keys as $key){
            $q=$db->prepare('SELECT attempts,window_started FROM bp_blog_login_limits WHERE attempt_key=?'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$key]);$row=$q->fetch();
            if($row && $now-(int)$row['window_started']<900 && (int)$row['attempts']>=6){$db->rollBack();return false;}
            if(!$row){$q=$db->prepare('INSERT INTO bp_blog_login_limits (attempt_key,attempts,window_started) VALUES (?,1,?)');$q->execute([$key,$now]);}
            elseif($now-(int)$row['window_started']>=900){$q=$db->prepare('UPDATE bp_blog_login_limits SET attempts=1,window_started=? WHERE attempt_key=?');$q->execute([$now,$key]);}
            else{$q=$db->prepare('UPDATE bp_blog_login_limits SET attempts=attempts+1 WHERE attempt_key=?');$q->execute([$key]);}
        }
        $db->commit();
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    $q=$db->prepare('SELECT id,password_hash FROM bp_blog_admins WHERE email=?');$q->execute([$email]);$user=$q->fetch();
    // Valid bcrypt dummy for an absent account keeps the hash-verification path consistent.
    $valid=password_verify($password,$user['password_hash']??'$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if(!$user || !$valid)return false;
    foreach($keys as $key){$q=$db->prepare('DELETE FROM bp_blog_login_limits WHERE attempt_key=?');$q->execute([$key]);}
    if(password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)){$q=$db->prepare('UPDATE bp_blog_admins SET password_hash=? WHERE id=?');$hash=password_hash($password,PASSWORD_DEFAULT);$q->execute([$hash,$user['id']]);$user['password_hash']=$hash;}
    bp_session();session_regenerate_id(true);$_SESSION['admin_id']=(int)$user['id'];$_SESSION['auth_stamp']=hash('sha256',$user['password_hash']);$_SESSION['signed_in']=time();$_SESSION['last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));return true;
}
function bp_categories(PDO $db): array {return $db->query('SELECT id,name FROM bp_blog_categories ORDER BY name')->fetchAll();}
function bp_post(PDO $db,string $slug,bool $public=true): ?array {
    $sql='SELECT p.*,c.name AS category FROM bp_blog_posts p JOIN bp_blog_categories c ON c.id=p.category_id WHERE p.slug=?';
    if($public)$sql.=" AND p.status='published'";
    $q=$db->prepare($sql);$q->execute([$slug]);return $q->fetch()?:null;
}
function bp_assets(PDO $db,int $post): array {
    $q=$db->prepare('SELECT a.*,pa.is_cover,pa.sort_order FROM bp_blog_assets a JOIN bp_blog_post_assets pa ON pa.asset_id=a.id WHERE pa.post_id=? ORDER BY pa.sort_order,a.id');$q->execute([$post]);return $q->fetchAll();
}
function bp_public_asset(PDO $db,int $id): ?array {
    // Bind the setting value as data. Comparing two derived string columns can
    // otherwise fail when the connection and table use different collations.
    $q=$db->prepare("SELECT a.* FROM bp_blog_assets a WHERE a.id=? AND (EXISTS(SELECT 1 FROM bp_blog_post_assets pa JOIN bp_blog_posts p ON p.id=pa.post_id WHERE pa.asset_id=a.id AND p.status='published') OR EXISTS(SELECT 1 FROM bp_site_settings s WHERE s.setting_key IN ('profile_asset','resume_asset') AND s.setting_value=?))");
    $q->execute([$id,(string)$id]);return $q->fetch()?:null;
}
function bp_asset_url(array $asset,bool $inline=false): string {
    if(!empty($asset['public_path']) && preg_match('~^/assets/(?:blog|images/blog|documents/blog)/[A-Za-z0-9_.-]+\.(?:pdf|webp|jpg|png)$~',$asset['public_path']))return $asset['public_path'];
    return '/api/media/'.(int)$asset['id'].($inline?'/view':'/download');
}
function bp_validate_post(array $data): array {
    $data['title']=trim((string)($data['title']??''));$data['slug']=trim((string)($data['slug']??''));$data['excerpt']=trim((string)($data['excerpt']??''));$data['body']=trim((string)($data['body']??''));
    if(strlen($data['title'])<3 || strlen($data['title'])>220)throw new InvalidArgumentException('Title must be 3-220 characters.');
    if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$data['slug']) || strlen($data['slug'])>160)throw new InvalidArgumentException('Use a short lowercase slug with letters, numbers, and single hyphens.');
    if(strlen($data['excerpt'])<10 || strlen($data['excerpt'])>700)throw new InvalidArgumentException('Summary must be 10-700 characters.');
    if(strlen($data['body'])<30 || strlen($data['body'])>100000)throw new InvalidArgumentException('Article must be 30-100,000 characters.');
    if(!in_array($data['status']??'',['draft','published','archived'],true))throw new InvalidArgumentException('Choose a valid publication state.');
    $data['category_id']=(int)($data['category_id']??0);if($data['category_id']<1)throw new InvalidArgumentException('Choose a category.');
    $data['source_url']=bp_source_url($data['source_url']??null);
    $data['pdf_display']=$data['pdf_display']??'file';if(!in_array($data['pdf_display'],['file','carousel'],true))throw new InvalidArgumentException('Choose PDF file or PDF carousel.');
    $data['slider_interval']=max(3,min(15,(int)($data['slider_interval']??6)));return $data;
}
function bp_save_post(PDO $db,array $data,?int $id=null,?int $version=null): int {
    $d=bp_validate_post($data);$now=bp_now();$pub=$d['status']==='published'?$now:null;
    $q=$db->prepare('SELECT id FROM bp_blog_categories WHERE id=?');$q->execute([$d['category_id']]);if(!$q->fetch())throw new InvalidArgumentException('Choose an existing category.');
    if($id){
        $q=$db->prepare('UPDATE bp_blog_posts SET category_id=?,slug=?,title=?,excerpt=?,body=?,status=?,source_url=?,published_at=COALESCE(published_at,?),updated_at=?,pdf_display=?,slider_interval=?,version=version+1 WHERE id=? AND version=?');
        $q->execute([$d['category_id'],$d['slug'],$d['title'],$d['excerpt'],$d['body'],$d['status'],$d['source_url'],$pub,$now,$d['pdf_display'],$d['slider_interval'],$id,$version]);
        if($q->rowCount()!==1)throw new RuntimeException('This article changed in another session. Reload before saving.');bp_cache_invalidate();return $id;
    }
    $q=$db->prepare('INSERT INTO bp_blog_posts(category_id,slug,title,excerpt,body,status,source_url,origin,published_at,created_at,updated_at,pdf_display,slider_interval) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$d['category_id'],$d['slug'],$d['title'],$d['excerpt'],$d['body'],$d['status'],$d['source_url'],'Blog article',$pub,$now,$now,$d['pdf_display'],$d['slider_interval']]);$id=(int)$db->lastInsertId();bp_cache_invalidate();return $id;
}
function bp_check_upload(string $path,string $name,int $size): array {
    if($size<1 || $size>10*1024*1024)throw new InvalidArgumentException('Attachments must be under 10 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowed=['application/pdf'=>['pdf'],'image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];
    if(preg_match('/\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|bash|js|html?|svg|exe|dll)(?:\.|$)/i',$name))throw new InvalidArgumentException('Script and double-extension filenames are not accepted.');
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(!isset($allowed[$mime]) || !in_array($ext,$allowed[$mime],true))throw new InvalidArgumentException('Upload PDF, JPEG, PNG, or WebP files with matching content.');
    if(str_starts_with($mime,'image/')){$dimensions=getimagesize($path);if(!$dimensions || $dimensions[0]*$dimensions[1]>40000000)throw new InvalidArgumentException('Use a valid image under 40 megapixels.');}
    if(preg_match('/<\?(?:php\b|=)/i',(string)file_get_contents($path)))throw new InvalidArgumentException('Files containing executable script markers are not accepted.');
    return ['mime'=>$mime,'extension'=>$ext];
}
function bp_store_upload(PDO $db,array $file,string $kind='any'): ?int {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($file['error']??-1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload failed. Check the hosting upload limit and try a smaller file.');
    $info=bp_check_upload($file['tmp_name'],$file['name'],(int)$file['size']);
    if($kind==='image' && !str_starts_with($info['mime'],'image/'))throw new InvalidArgumentException('Choose a JPEG, PNG, or WebP image.');
    if($kind==='pdf' && $info['mime']!=='application/pdf')throw new InvalidArgumentException('The résumé must be a PDF.');
    $dir=__DIR__.'/uploads';if(!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('The upload directory is not writable.');
    if(!is_file($dir.'/.htaccess')){file_put_contents($dir.'/.htaccess',"Require all denied\nOptions -Indexes -ExecCGI -Includes\n");chmod($dir.'/.htaccess',0600);}
    $key=bin2hex(random_bytes(24)).'.'.$info['extension'];$path=$dir.'/'.$key;
    if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Could not store the uploaded file.');chmod($path,0600);
    try{
        $q=$db->prepare('INSERT INTO bp_blog_assets(original_name,storage_key,mime_type,size_bytes) VALUES (?,?,?,?)');
        $q->execute([substr(basename($file['name']),0,255),$key,$info['mime'],$file['size']]);return (int)$db->lastInsertId();
    }catch(Throwable $e){@unlink($path);throw $e;}
}
function bp_upload(PDO $db,int $post,array $file,bool $cover=false,string $kind='any'): void {
    $aid=bp_store_upload($db,$file,$cover?'image':$kind);if(!$aid)return;
    try{
        $db->beginTransaction();
        if($cover){$q=$db->prepare('UPDATE bp_blog_post_assets SET is_cover=0 WHERE post_id=?');$q->execute([$post]);}
        $q=$db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM bp_blog_post_assets WHERE post_id=?');$q->execute([$post]);$order=(int)$q->fetchColumn();
        $q=$db->prepare('INSERT INTO bp_blog_post_assets(post_id,asset_id,is_cover,sort_order) VALUES (?,?,?,?)');$q->execute([$post,$aid,$cover?1:0,$order]);$db->commit();bp_cache_invalidate();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function bp_multiple_files(array $files): array {
    if(!is_array($files['name']??null))return [$files];$out=[];
    if(count($files['name'])>12)throw new InvalidArgumentException('Upload up to 12 files at a time.');
    foreach($files['name'] as $i=>$name)$out[]=['name'=>$name,'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];return $out;
}
function bp_page_start(string $title,string $description,string $canonical='/blog/',array $seo=[]): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.bp_seo_tags($title,$description,$canonical,$seo).'<link rel="icon" href="/assets/images/favicon.svg"><link rel="stylesheet" href="/assets/css/site.css?v=3"><link rel="stylesheet" href="/assets/css/blog.css"><script defer src="/assets/js/site.js?v=3"></script><script type="module" src="/assets/js/media.js?v=2"></script><link rel="stylesheet" href="/assets/css/media.css?v=2"></head><body><a href="#main" class="skip">Skip to content</a><div class="progress" aria-hidden="true"></div>';
    readfile(__DIR__.'/templates/header.html');
}
function bp_page_end(): void {readfile(__DIR__.'/templates/footer.html');}

// Small, private file cache: never cache sessions, account details, or DB credentials.
function bp_cache_dir(): string {
    $dir=getenv('BP_CACHE_DIR')?:__DIR__.'/cache';
    if(!is_dir($dir) && !@mkdir($dir,0700,true))throw new RuntimeException('The private cache directory is not writable.');return $dir;
}
function bp_cache_generation(): string {
    $file=bp_cache_dir().'/generation';return is_file($file)?trim((string)file_get_contents($file)):'initial';
}
function bp_cache_invalidate(): void {
    $dir=bp_cache_dir();$temp=tempnam($dir,'generation-');
    file_put_contents($temp,bin2hex(random_bytes(12)),LOCK_EX);chmod($temp,0600);rename($temp,$dir.'/generation');
    // Files from earlier generations are no longer reachable; keep disk use bounded.
    foreach(glob($dir.'/*.json')?:[] as $file)if(filemtime($file)<time()-3600)@unlink($file);
}
function bp_cached(string $key,callable $produce,int $ttl=86400): mixed {
    $path=bp_cache_dir().'/'.hash('sha256',bp_cache_generation().':'.$key).'.json';
    if(is_file($path) && filemtime($path)>time()-$ttl){$data=json_decode((string)file_get_contents($path),true);if(is_array($data) && array_key_exists('value',$data))return $data['value'];}
    $value=$produce();$json=json_encode(['value'=>$value],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    $temp=tempnam(bp_cache_dir(),'cache-');if(file_put_contents($temp,$json,LOCK_EX)!==false){chmod($temp,0600);rename($temp,$path);}else @unlink($temp);return $value;
}
function bp_site_values(PDO $db): array {
    $values=[];foreach($db->query('SELECT setting_key,setting_value FROM bp_site_settings')->fetchAll() as $r)$values[$r['setting_key']]=$r['setting_value'];return $values;
}
function bp_set_site_value(PDO $db,string $key,string $value): void {
    if(!in_array($key,array_merge(['profile_asset','resume_asset'],array_keys(bp_site_fields())),true))throw new InvalidArgumentException('Unknown website setting.');
    $q=$db->prepare('SELECT setting_key FROM bp_site_settings WHERE setting_key=?');$q->execute([$key]);
    if($q->fetch()){$q=$db->prepare('UPDATE bp_site_settings SET setting_value=?,updated_at=? WHERE setting_key=?');$q->execute([$value,bp_now(),$key]);}
    else{$q=$db->prepare('INSERT INTO bp_site_settings(setting_key,setting_value,updated_at) VALUES (?,?,?)');$q->execute([$key,$value,bp_now()]);}
    bp_cache_invalidate();
}
function bp_public_catalog(PDO $db): array {
    // Two bounded queries; no per-article attachment query or body in the listing.
    $posts=$db->query("SELECT p.id,p.slug,p.title,p.excerpt,p.origin,p.published_at,c.name AS category FROM bp_blog_posts p JOIN bp_blog_categories c ON c.id=p.category_id WHERE p.status='published' ORDER BY p.published_at DESC,p.id DESC LIMIT 500")->fetchAll();
    $assets=$db->query("SELECT a.*,pa.post_id,pa.is_cover,pa.sort_order FROM bp_blog_assets a JOIN bp_blog_post_assets pa ON pa.asset_id=a.id JOIN bp_blog_posts p ON p.id=pa.post_id WHERE p.status='published' ORDER BY pa.sort_order,a.id")->fetchAll();$map=[];
    foreach($assets as $a)$map[(int)$a['post_id']][]=$a;
    foreach($posts as &$p)$p['assets']=$map[(int)$p['id']]??[];unset($p);return $posts;
}
function bp_media_markup(array $assets,string $pdfMode='file',int $interval=6): string {
    $images=array_values(array_filter($assets,fn($a)=>str_starts_with($a['mime_type'],'image/')));
    $pdfs=array_values(array_filter($assets,fn($a)=>$a['mime_type']==='application/pdf'));$out='';
    if($images){
        $out.='<section class="media-carousel image-carousel" data-image-carousel data-interval="'.($interval*1000).'" role="region" aria-roledescription="carousel" aria-label="Article images" tabindex="0"><div class="media-stage">';
        foreach($images as $i=>$a)$out.='<figure class="image-slide" '.($i?'hidden':'').'><img src="'.bp_h(bp_asset_url($a,true)).'" alt="'.bp_h($a['original_name']).'" loading="lazy" decoding="async"><figcaption>'.bp_h($a['original_name']).'</figcaption></figure>';
        $out.='</div>';
        if(count($images)>1)$out.='<div class="media-controls"><button type="button" data-prev aria-label="Previous image">←</button><span data-counter aria-live="polite">1 / '.count($images).'</span><button type="button" data-next aria-label="Next image">→</button><button type="button" data-pause aria-pressed="false">Pause slideshow</button></div>';
        $out.='</section>';
    }
    foreach($pdfs as $a){$url=bp_asset_url($a,true);$download=bp_asset_url($a);
        if($pdfMode==='carousel')$out.='<section class="media-carousel pdf-carousel" data-pdf-carousel data-src="'.bp_h($url).'" role="region" aria-roledescription="carousel" aria-label="'.bp_h($a['original_name']).'" tabindex="0"><div class="media-stage pdf-stage"><canvas aria-label="PDF page preview" hidden></canvas><p data-pdf-status role="status">PDF preview loads when you reach this guide.</p></div><div class="media-controls"><button type="button" data-prev disabled aria-label="Previous PDF page">←</button><span data-counter aria-live="polite">PDF guide</span><button type="button" data-next disabled aria-label="Next PDF page">→</button><a class="btn btn-small" href="'.bp_h($download).'" download>Download PDF ↓</a></div><p class="media-caption">'.bp_h($a['original_name']).' · Use the arrows or your keyboard to browse.</p></section>';
        else $out.='<aside class="pdf-download"><span class="pdf-badge">PDF</span><div><strong>'.bp_h($a['original_name']).'</strong><p>Original visual guide</p></div><a class="btn" href="'.bp_h($download).'" download>Download ↓</a><a class="btn" href="'.bp_h($url).'" target="_blank" rel="noopener">Open ↗</a></aside>';
    }
    return $out;
}
function bp_etag(string $body): bool {
    $etag='"'.hash('sha256',$body).'"';header('ETag: '.$etag);header('Cache-Control: public, max-age=0, must-revalidate');
    if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);return true;}return false;
}
function bp_byte_range(?string $range,int $size): ?array {
    if(!$range)return null;
    if(!preg_match('/^bytes=(\d*)-(\d*)$/',$range,$m) || ($m[1]==='' && $m[2]==='') || $size<1)throw new InvalidArgumentException('Unsatisfiable range.');
    if($m[1]===''){$suffix=(int)$m[2];if($suffix<1)throw new InvalidArgumentException('Unsatisfiable range.');return [max(0,$size-$suffix),$size-1];}
    $start=(int)$m[1];$end=$m[2]===''?$size-1:min((int)$m[2],$size-1);
    if($start>=$size || $end<$start)throw new InvalidArgumentException('Unsatisfiable range.');return [$start,$end];
}
function bp_serve_file(string $path,string $mime,string $name,bool $inline,bool $public=true,bool $alias=false): void {
    $size=filesize($path);$mtime=filemtime($path);$etag='"'.hash('sha256',basename($path).':'.$size.':'.$mtime).'"';
    header('Content-Type: '.$mime);header('Accept-Ranges: bytes');header('ETag: '.$etag);header('Last-Modified: '.gmdate('D, d M Y H:i:s',$mtime).' GMT');
    header('Cache-Control: '.($public?($alias?'public, max-age=0, must-revalidate':'public, max-age=86400, immutable'):'private, no-store'));
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Content-Disposition: '.($inline?'inline':'attachment')."; filename*=UTF-8''".rawurlencode(basename($name)));
    if($public && trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);return;}
    $range=$_SERVER['HTTP_RANGE']??null;
    if(isset($_SERVER['HTTP_IF_RANGE']) && $_SERVER['HTTP_IF_RANGE']!==$etag && $_SERVER['HTTP_IF_RANGE']!==gmdate('D, d M Y H:i:s',$mtime).' GMT')$range=null;
    try{$r=bp_byte_range($range,$size);}catch(InvalidArgumentException $e){http_response_code(416);header('Content-Range: bytes */'.$size);header('Content-Length: 0');return;}
    [$start,$end]=$r??[0,$size-1];if($r){http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}header('Content-Length: '.($end-$start+1));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')return;
    $fp=fopen($path,'rb');fseek($fp,$start);$left=$end-$start+1;
    while($left>0 && !feof($fp)){ $chunk=fread($fp,min(65536,$left));if($chunk===false)break;echo $chunk;$left-=strlen($chunk);}fclose($fp);
}

function bp_site_fields(): array {
    return [
        'seo_home_title'=>['SEO — homepage title','Fintech Systems Architect & Engineering Leader',100,'text'],
        'seo_home_description'=>['SEO — homepage description','Brajkishor Pandey: 18+ years in fintech engineering, cross-border payments, API platforms, software architecture, and team leadership. Explore projects and download my résumé.',320,'text'],
        'seo_profile_title'=>['SEO — professional profile title','Brajkishor Pandey | Fintech Architect & Engineering Leader',100,'text'],
        'seo_profile_description'=>['SEO — professional profile description','Brajkishor Pandey’s professional profile: 18+ years in engineering leadership, cross-border payments, software architecture, and full-stack development.',320,'text'],
        'hero_title'=>['Homepage headline',"I build systems\nthat move money.\nAnd businesses\nforward.",180,'text'],
        'hero_summary'=>['Homepage introduction','18+ years turning complex ideas into dependable platforms. Fintech architecture, hands-on engineering, and teams built to deliver.',450,'text'],
        'resume_summary'=>['Brief résumé','Engineering leader with 18+ years of experience building, improving, and operating fintech, mobility, commerce, enterprise, and data-driven platforms. Hands-on across architecture, backend delivery, payment APIs, production infrastructure, and team leadership.',1200,'text'],
        'contact_title'=>['Contact heading',"Have a complex\nchallenge?\nLet’s talk.",180,'text'],
        'contact_intro'=>['Contact introduction','Engineering leadership, fintech architecture, a new platform, or a system that needs a stronger foundation. Tell me where you are and what you need to change.',900,'text'],
        'contact_email'=>['Public email address','brajkishorpandey@gmail.com',254,'email'],
        'contact_phone'=>['Telephone number','+91 95698 43767',40,'phone'],
        'contact_whatsapp'=>['WhatsApp number with country code','919569843767',20,'digits'],
        'contact_linkedin'=>['LinkedIn profile','https://www.linkedin.com/in/brajkishorpandey/',300,'url'],
        'contact_location'=>['Location / collaboration note','Based in Chandigarh, India. Collaborating with teams globally.',250,'text'],
        'email_subject'=>['Enquiry email subject','Portfolio enquiry: {service}',180,'text'],
        'email_body'=>['Enquiry email content',"Hi Braj,\n\nI’m {name}.\nEmail: {email}\nInterested in: {service}\n\n{message}\n\nBest,\n{name}",4000,'text']
    ];
}
function bp_validate_site_values(array $input): array {
    $values=[];
    foreach(bp_site_fields() as $key=>[$label,$default,$max,$type]){
        $v=trim((string)($input[$key]??$default));
        if($v==='' || strlen($v)>$max)throw new InvalidArgumentException($label.' is required and must be under '.$max.' characters.');
        if($type==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid public email address.');
        if($type==='phone' && !preg_match('/^\+?[0-9 ()-]{7,40}$/',$v))throw new InvalidArgumentException('Enter a valid telephone number.');
        if($type==='digits' && !preg_match('/^[1-9][0-9]{6,19}$/',$v))throw new InvalidArgumentException('Use the WhatsApp number with country code and digits only.');
        if($type==='url' && (!filter_var($v,FILTER_VALIDATE_URL) || parse_url($v,PHP_URL_SCHEME)!=='https' || !in_array(strtolower((string)parse_url($v,PHP_URL_HOST)),['linkedin.com','www.linkedin.com'],true)))throw new InvalidArgumentException('Use an HTTPS LinkedIn profile URL.');
        $values[$key]=$v;
    }
    return $values;
}
function bp_public_site_values(): array {
    return bp_cached('site-public:v3',function(){
        $v=[];foreach(bp_site_fields() as $key=>$field)$v[$key]=$field[1];
        if(bp_configured())foreach(bp_site_values(bp_db()) as $key=>$value)if(array_key_exists($key,$v))$v[$key]=$value;
        return $v;
    });
}

function bp_apply_site_html(string $html,array $v): string {
    foreach([
        '~(<div class="hero-copy">.*?<h1>).*?(</h1>)~s'=>'hero_title',
        '~(<p class="hero-description">).*?(</p>)~s'=>'hero_summary',
        '~(<section class="section contact".*?<h2>).*?(</h2>)~s'=>'contact_title',
        '~(<p class="contact-intro">).*?(</p>)~s'=>'contact_intro',
        '~(<p class="tiny-caption">)Based in Chandigarh, India\. Collaborating with teams globally\.(</p>)~s'=>'contact_location',
        '~(<p data-resume-summary>).*?(</p>)~s'=>'resume_summary'
    ] as $pattern=>$key)if(isset($v[$key]) && !(in_array($key,['hero_title','contact_title'],true) && $v[$key]===bp_site_fields()[$key][1]))$html=preg_replace_callback($pattern,fn($m)=>$m[1].nl2br(bp_h($v[$key]),false).$m[2],$html,1);
    $html=str_replace('mailto:brajkishorpandey@gmail.com','mailto:'.rawurlencode($v['contact_email']),$html);
    $html=str_replace('brajkishorpandey@gmail.com',bp_h($v['contact_email']),$html);
    $html=str_replace('tel:+919569843767','tel:'.preg_replace('/[^+0-9]/','',$v['contact_phone']),$html);
    $html=str_replace('+91 95698 43767',bp_h($v['contact_phone']),$html);
    $html=str_replace('https://wa.me/919569843767','https://wa.me/'.$v['contact_whatsapp'],$html);
    return str_replace('https://www.linkedin.com/in/brajkishorpandey/',bp_h($v['contact_linkedin']),$html);
}

function bp_change_password(PDO $db,int $id,string $current,string $next,string $confirmation): void {
    if(strlen($next)<14 || strlen($next)>512 || !hash_equals($next,$confirmation))throw new InvalidArgumentException('Use at least 14 characters and confirm the same new password.');
    $q=$db->prepare('SELECT password_hash FROM bp_blog_admins WHERE id=?');$q->execute([$id]);$hash=$q->fetchColumn();
    if(!$hash || !password_verify($current,$hash))throw new RuntimeException('The current password is incorrect.');
    $q=$db->prepare('UPDATE bp_blog_admins SET password_hash=? WHERE id=? AND password_hash=?');$q->execute([password_hash($next,PASSWORD_DEFAULT),$id,$hash]);
    if($q->rowCount()!==1)throw new RuntimeException('The account changed. Sign in again before updating it.');
}
