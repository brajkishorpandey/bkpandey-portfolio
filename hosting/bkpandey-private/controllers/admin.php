<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__);
$public=dirname($private).'/public_html';
if(!is_file($private.'/core.php')){http_response_code(503);exit('Publishing service is being configured.');}
require $private.'/core.php';require $private.'/migration-runner.php';require $private.'/admin-navigation.php';require $private.'/contact.php';bp_security_headers(true);
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){http_response_code(426);exit('Use HTTPS to access the publishing area.');}
bp_session();$error='';$notice='';
if(!bp_configured()){
    http_response_code(503);bp_page_start('Publishing setup','Owner publishing area.');echo '<main id="main" class="container"><div class="admin-login"><h1>Publishing setup.</h1><p class="muted">The public blog is available. <a href="/admin/setup">Connect your database and create the owner account</a> to activate publishing.</p></div></main>';bp_page_end();exit;
}
try{$db=bp_db();}catch(Throwable $e){error_log('Portfolio admin database unavailable.');http_response_code(503);exit('Publishing is temporarily unavailable.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        bp_check_csrf($_POST['csrf']??null);$action=(string)($_POST['action']??'');
        if($action==='login'){
            if(!bp_try_login($db,(string)($_POST['email']??''),(string)($_POST['password']??''),(string)($_SERVER['REMOTE_ADDR']??'unknown')))throw new RuntimeException('Sign-in failed or temporarily limited. Check your details and try again later.');
            header('Location: '.bp_admin_return_path($_GET),true,303);exit;
        }
        if(!bp_admin())throw new RuntimeException('Please sign in before making changes.');
        if($action==='logout'){
            $_SESSION=[];session_destroy();setcookie(session_name(),'',time()-3600,'/','',true,true);header('Location: /admin',true,303);exit;
        }
        if($action==='save'){
            $id=!empty($_POST['id'])?(int)$_POST['id']:null;
            $version=isset($_POST['version'])?(int)$_POST['version']:null;
            $id=bp_save_post($db,$_POST,$id,$version);
            $uploadErrors=[];
            foreach(['cover'=>true,'attachment'=>false] as $key=>$cover){if(isset($_FILES[$key]))try{bp_upload($db,$id,$_FILES[$key],$cover);}catch(Throwable $e){$uploadErrors[]=$e->getMessage();}}
            if(isset($_FILES['images']))foreach(bp_multiple_files($_FILES['images']) as $file)try{bp_upload($db,$id,$file,false,'image');}catch(Throwable $e){$uploadErrors[]=$e->getMessage();}
            foreach(bp_assets($db,$id) as $asset){
                $aid=(int)$asset['id'];
                if(in_array((string)$aid,array_map('strval',$_POST['remove_assets']??[]),true)){$q=$db->prepare('DELETE FROM bp_blog_post_assets WHERE post_id=? AND asset_id=?');$q->execute([$id,$aid]);}
                else{$order=max(0,min(9999,(int)($_POST['asset_order'][$aid]??$asset['sort_order'])));$q=$db->prepare('UPDATE bp_blog_post_assets SET sort_order=? WHERE post_id=? AND asset_id=?');$q->execute([$order,$id,$aid]);}
            }
            if(!empty($_POST['cover_asset']) && ($_FILES['cover']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$aid=(int)$_POST['cover_asset'];$q=$db->prepare("SELECT a.id FROM bp_blog_assets a JOIN bp_blog_post_assets pa ON pa.asset_id=a.id WHERE pa.post_id=? AND a.id=? AND a.mime_type LIKE 'image/%'");$q->execute([$id,$aid]);if($q->fetch()){$q=$db->prepare('UPDATE bp_blog_post_assets SET is_cover=CASE WHEN asset_id=? THEN 1 ELSE 0 END WHERE post_id=?');$q->execute([$aid,$id]);}}
            bp_cache_invalidate();
            $_SESSION['notice']=$uploadErrors?'Article saved. Attachment: '.implode(' ',$uploadErrors):'Article saved.';
            header('Location: /admin?edit='.$id,true,303);exit;
        }
        if($action==='password'){
            bp_change_password($db,(int)$_SESSION['admin_id'],(string)($_POST['current_password']??''),(string)($_POST['new_password']??''),(string)($_POST['confirm_password']??''));
            $_SESSION=[];session_regenerate_id(true);$_SESSION['notice']='Password changed. Sign in with your new password.';header('Location: /admin',true,303);exit;
        }
        if($action==='site'){
            $values=bp_validate_site_values($_POST);
            foreach($values as $key=>$value)bp_set_site_value($db,$key,$value);
            $uploadErrors=[];
            foreach(['profile'=>'image','resume'=>'pdf'] as $key=>$kind)if(isset($_FILES[$key]))try{$aid=bp_store_upload($db,$_FILES[$key],$kind);if($aid)bp_set_site_value($db,$key.'_asset',(string)$aid);}catch(Throwable $e){$uploadErrors[]=$e->getMessage();}
            $_SESSION['notice']=$uploadErrors?'Text saved. Upload: '.implode(' ',$uploadErrors):'Website updated. The public cache has been refreshed.';
            header('Location: /admin?site=1',true,303);exit;
        }
        if($action==='ga4_config'){
            require_once $private.'/ga4.php';
            $measurement=strtoupper(trim((string)($_POST['ga4_measurement_id']??'')));
            $property=trim((string)($_POST['ga4_property_id']??''));
            if(($measurement!=='' && bp_ga4_measurement_id($measurement)==='') ||
                ($property!=='' && bp_ga4_property_id($property)==='')){
                throw new InvalidArgumentException('Enter a valid Google Analytics measurement ID and numeric property ID.');
            }
            bp_set_site_value($db,'ga4_property_id',$property);
            bp_set_site_value($db,'ga4_measurement_id',$measurement);
            $_SESSION['notice']='Google Analytics settings saved.';
            header('Location: /admin?traffic=1',true,303);exit;
        }
        if($action==='contact_config'){bp_contact_save_config($_POST);$_SESSION['notice']='Contact email settings saved.';header('Location: /admin?contact=1',true,303);exit;}
        if($action==='contact_test'){
            bp_contact_limit(bp_contact_state_dir(),[['owner-test',3,3600]],time());
            $site=bp_public_site_values();$test=['name'=>'Website delivery test','email'=>$site['contact_email'],'service'=>'Other collaboration','message'=>'This is a delivery test from your authenticated owner dashboard. No action is required.'];
            $site['email_subject']='bkpandey.com contact delivery test';$site['email_body']='{message}';
            if(!bp_contact_deliver($test,$site))throw new RuntimeException('The hosting mail service did not accept the test message.');
            $notice='The hosting mail service accepted the test email for delivery. Check your inbox and spam folder.';
        }
        if($action==='migrate'){$applied=bp_migrate($db);bp_cache_invalidate();$notice=count($applied).' database migrations applied.';}
        if($action==='cache'){bp_cache_invalidate();$notice='Public cache refreshed.';}
        if($action==='category'){
            $name=trim((string)($_POST['category_name']??''));if(strlen($name)<2 || strlen($name)>80)throw new InvalidArgumentException('Category must be 2-80 characters.');
            $q=$db->prepare('INSERT INTO bp_blog_categories(name) VALUES (?)');$q->execute([$name]);bp_cache_invalidate();$notice='Category added.';
        }
    }catch(PDOException $e){error_log('Portfolio database change failed.');$error='The change could not be saved. Check that the slug or category is unique and try again.';}
    catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Portfolio publishing operation failed.');$error='The change could not be saved. Check the slug and try again.';}
}
if(!bp_admin()){
    bp_admin_page_start('Sign in');
    echo '<main id="main" class="admin-login-wrap"><form class="admin-login" method="post"><p class="eyebrow">Owner access</p><h1 style="margin-top:20px">Welcome back.</h1>';
    if($error)echo '<p class="admin-notice admin-error">'.bp_h($error).'</p>';
    echo '<input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><div class="field"><label for="admin-email">Email</label><input id="admin-email" type="email" name="email" required autocomplete="username" maxlength="254"></div><div class="field"><label for="admin-password">Password</label><input id="admin-password" type="password" name="password" required autocomplete="current-password" maxlength="512"></div><button class="btn btn-primary" type="submit">Sign in ↗</button></form></main></body></html>';exit;
}
$notice=$notice?:($_SESSION['notice']??'');unset($_SESSION['notice']);
$section=bp_admin_section($_GET);
$title=$section==='articles' && isset($_GET['edit']) ? 'Edit article' : BP_ADMIN_SECTIONS[$section][0];
bp_admin_page_start($title, true);
echo '<div class="admin-layout">'.bp_admin_navigation($_GET).'<main id="main" class="admin-shell admin-content">'
    .'<h1>'.bp_h($title).'</h1>';
if($error)echo '<p class="admin-notice admin-error">'.bp_h($error).'</p>';if($notice)echo '<p class="admin-notice" role="status">'.bp_h($notice).'</p>';
$edit=isset($_GET['edit'])?(int)$_GET['edit']:0;$post=null;
if($edit){$q=$db->prepare('SELECT * FROM bp_blog_posts WHERE id=?');$q->execute([$edit]);$post=$q->fetch();}
if(isset($_GET['account'])){
    echo '<form method="post" class="admin-editor"><h2>Change your password.</h2><p>Changing the password signs out existing sessions.</p><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><input type="hidden" name="action" value="password"><div class="field"><label for="current-password">Current password</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required></div><div class="field"><label for="new-password">New password — at least 14 characters</label><input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="14" maxlength="512" required></div><div class="field"><label for="confirm-password">Confirm new password</label><input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="14" maxlength="512" required></div><button type="submit" class="btn btn-primary">Change password</button></form>';
}
elseif(isset($_GET['database'])){require $private.'/database-editor.php';}
elseif(isset($_GET['site'])){require $private.'/site-editor.php';}
elseif(isset($_GET['contact'])){require $private.'/contact-editor.php';}
elseif(isset($_GET['traffic'])){require $private.'/traffic-editor.php';}
elseif(isset($_GET['new']) || $post){
    $p=$post?:['id'=>'','version'=>'','title'=>'','slug'=>'','excerpt'=>'','body'=>'','category_id'=>'','status'=>'draft','source_url'=>'','pdf_display'=>'carousel','slider_interval'=>6];
    if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save' && $error)$p=array_merge($p,array_intersect_key($_POST,$p));
    echo '<form method="post" enctype="multipart/form-data" class="admin-editor"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.bp_h($p['id']).'"><input type="hidden" name="version" value="'.bp_h($p['version']).'"><div class="field"><label for="post-title">Title</label><input id="post-title" name="title" required maxlength="220" value="'.bp_h($p['title']).'"></div><div class="editor-grid"><div class="field"><label for="post-slug">URL slug</label><input id="post-slug" name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" required maxlength="160" value="'.bp_h($p['slug']).'"></div><div class="field"><label for="post-category">Category</label><select id="post-category" name="category_id" required><option value="">Choose a category</option>';
    foreach(bp_categories($db) as $c)echo '<option value="'.(int)$c['id'].'" '.((string)$p['category_id']===(string)$c['id']?'selected':'').'>'.bp_h($c['name']).'</option>';
    echo '</select></div></div><div class="field"><label for="post-excerpt">Summary</label><textarea class="excerpt-input" id="post-excerpt" name="excerpt" required minlength="10" maxlength="700">'.bp_h($p['excerpt']).'</textarea></div><div class="field"><label for="post-body">Article - plain text, ## headings, ### subheadings, and - list items</label><textarea id="post-body" name="body" required minlength="30" maxlength="100000">'.bp_h($p['body']).'</textarea></div><div class="editor-grid"><div class="field"><label for="post-status">Publication</label><select id="post-status" name="status">';
    foreach(['draft','published','archived'] as $s)echo '<option value="'.$s.'" '.($p['status']===$s?'selected':'').'>'.ucfirst($s).'</option>';
    echo '</select></div><div class="field"><label for="source-url">Original source URL (optional)</label><input id="source-url" name="source_url" type="url" maxlength="2000" value="'.bp_h($p['source_url']).'" placeholder="https://www.linkedin.com/…"></div><div class="field"><label for="cover-file">Cover image - JPEG, PNG, WebP; up to 10 MB</label><input id="cover-file" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></div><div class="field"><label for="attachment-file">Attach a PDF or image; up to 10 MB</label><input id="attachment-file" type="file" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp"></div></div><div class="admin-section"><h2>Visuals & sliders.</h2><p class="admin-subtitle">Upload several images together to create an animated slideshow automatically. For PDFs, choose a page carousel or a downloadable file.</p><div class="editor-grid"><div class="field"><label for="gallery-files">Slideshow images — up to 12 at a time, 10 MB each</label><input id="gallery-files" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG, WebP. Smaller, web-sized images load faster.</small></div><div class="field"><label for="pdf-display">PDF display on the website</label><select id="pdf-display" name="pdf_display"><option value="carousel" '.($p['pdf_display']==='carousel'?'selected':'').'>PDF page carousel + download</option><option value="file" '.($p['pdf_display']==='file'?'selected':'').'>PDF download / open file</option></select></div><div class="field"><label for="slider-interval">Image slide duration (seconds)</label><input id="slider-interval" name="slider_interval" type="number" min="3" max="15" value="'.(int)$p['slider_interval'].'"><small>Sliders pause for keyboard interaction and respect reduced motion.</small></div></div></div>';
    if($post){echo '<div class="admin-section"><h2>Manage attached media.</h2><p>Set the order, choose a cover, or remove an attachment from this article.</p>';
        foreach(bp_assets($db,(int)$post['id']) as $a){$aid=(int)$a['id'];$isImage=str_starts_with($a['mime_type'],'image/');echo '<div class="admin-media-row">'.($isImage?'<img loading="lazy" src="'.bp_h(bp_asset_url($a,true)).'" alt="">':'<span class="pdf-badge">PDF</span>').'<a href="'.bp_h(bp_asset_url($a)).'" target="_blank" rel="noopener">'.bp_h($a['original_name']).'</a><label>Order<input type="number" min="0" max="9999" name="asset_order['.$aid.']" value="'.(int)$a['sort_order'].'"></label>'.($isImage?'<label><input type="radio" name="cover_asset" value="'.$aid.'" '.($a['is_cover']?'checked':'').'>Cover</label>':'').'<label><input type="checkbox" name="remove_assets[]" value="'.$aid.'">Remove from article</label></div>';}
        echo '</div><p><a class="btn" href="/blog/'.bp_h($p['slug']).'.html" target="_blank" rel="noopener">View public article ↗</a></p>';
    }
    echo '<button class="btn btn-primary" type="submit">Save article ↗</button></form>';
}else{
    $rows=$db->query('SELECT p.id,p.title,p.slug,p.status,p.updated_at,c.name AS category FROM bp_blog_posts p JOIN bp_blog_categories c ON c.id=p.category_id ORDER BY p.updated_at DESC,p.id DESC')->fetchAll();
    echo '<div class="admin-list-wrap"><table class="admin-list"><thead><tr><th>Article</th><th>Topic</th><th>State</th><th>Updated (UTC)</th><th></th></tr></thead><tbody>';
    foreach($rows as $r)echo '<tr><td>'.bp_h($r['title']).'</td><td>'.bp_h($r['category']).'</td><td>'.bp_h($r['status']).'</td><td>'.bp_h($r['updated_at']).'</td><td><a href="/admin?edit='.(int)$r['id'].'">Edit ↗</a></td></tr>';
    echo '</tbody></table></div><h2>Add a category.</h2><form method="post" class="admin-toolbar"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><input name="category_name" aria-label="New category name" required minlength="2" maxlength="80" style="max-width:300px"><button class="btn" name="action" value="category">Add category +</button></form>';
}
echo '</main></div></body></html>';
