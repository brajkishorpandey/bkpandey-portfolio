<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__);
$public=dirname($private).'/public_html';
if(!is_file($private.'/core.php')){http_response_code(503);exit('Publishing files are not yet available.');}
require $private.'/core.php';require $private.'/seed-import.php';require $private.'/migration-runner.php';
bp_security_headers(true);
if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'){http_response_code(426);exit('Use HTTPS for database setup.');}
bp_session();
$error='';$db=null;$ready=false;
try{if(bp_configured()){$db=bp_db();$ready=true;if((int)$db->query('SELECT COUNT(*) FROM bp_blog_admins')->fetchColumn()>0){http_response_code(404);exit('Setup is complete.');}}}catch(Throwable $e){$ready=false;}
$owner=isset($_SESSION['db_setup_owner']) && time()-(int)$_SESSION['db_setup_owner']<1200;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        bp_check_csrf($_POST['csrf']??null);$action=(string)($_POST['action']??'');
        if($action==='connect'){
            // The owner-authorized database identity is fixed; this is not a general DB proxy.
            $limitFile=$private.'/setup-attempts.json';$attempts=is_file($limitFile)?json_decode(file_get_contents($limitFile),true):[];
            if(!is_array($attempts))$attempts=[];$now=time();
            if($now-(int)($attempts['start']??0)>=900)$attempts=['start'=>$now,'count'=>0];
            if((int)($attempts['count']??0)>=6)throw new RuntimeException('Database sign-in is temporarily limited. Try again later.');
            $attempts['count']=(int)($attempts['count']??0)+1;file_put_contents($limitFile,json_encode($attempts),LOCK_EX);chmod($limitFile,0600);
            if(!in_array('mysql',PDO::getAvailableDrivers(),true))throw new RuntimeException('Enable PDO MySQL for this domain in cPanel before connecting.');
            $password=(string)($_POST['db_password']??'');
            if($password==='' || strlen($password)>1024)throw new RuntimeException('Enter the database password.');
            try{
                $db=new PDO('mysql:host=localhost;dbname=bkpandey_braj;charset=utf8mb4','bkpandey_braj',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
            }catch(PDOException $e){$code=(int)($e->errorInfo[1]??0);throw new RuntimeException(in_array($code,[1044,1045],true)?'Database access was rejected. Use the database-user password, which may differ from the cPanel password.':'The database connection is unavailable. Check the database host and hosting service.');}
            bp_migrate($db);
            bp_import_initial_articles($db);bp_cache_invalidate();
            $settings=['db_host'=>'localhost','db_name'=>'bkpandey_braj','db_user'=>'bkpandey_braj','db_password'=>$password];
            $temp=$private.'/database-'.bin2hex(random_bytes(8)).'.tmp';
            if(file_put_contents($temp,"<?php\nreturn ".var_export($settings,true).";\n",LOCK_EX)===false)throw new RuntimeException('The private configuration directory is not writable.');
            chmod($temp,0600);if(!rename($temp,$private.'/database.php')){@unlink($temp);throw new RuntimeException('Could not activate the private database configuration.');}
            unset($password,$settings);@unlink($limitFile);session_regenerate_id(true);$_SESSION['db_setup_owner']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
            header('Location: /admin/setup',true,303);exit;
        }
        if($action==='create_owner'){
            if(!$owner || !$ready || !$db)throw new RuntimeException('Authenticate the database connection before creating the owner account.');
            $email=strtolower(trim((string)($_POST['admin_email']??'')));$password=(string)($_POST['admin_password']??'');$confirm=(string)($_POST['admin_confirm']??'');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid owner email.');
            if(strlen($password)<14 || strlen($password)>512 || !hash_equals($password,$confirm))throw new RuntimeException('Use at least 14 characters and enter the same password twice.');
            if((int)$db->query('SELECT COUNT(*) FROM bp_blog_admins')->fetchColumn()>0)throw new RuntimeException('An owner account is already configured.');
            $q=$db->prepare('INSERT INTO bp_blog_admins(email,password_hash) VALUES (?,?)');$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);unset($password,$confirm);
            unset($_SESSION['db_setup_owner']);$_SESSION['notice']='Owner account created. Sign in to publish.';@unlink(__FILE__);
            header('Location: /admin',true,303);exit;
        }
    }catch(RuntimeException $e){$error=$e instanceof PDOException?'The setup could not complete. Check the database privileges.':$e->getMessage();}
    catch(Throwable $e){$error='Setup could not complete. Please check the hosting configuration.';}
}
bp_page_start('Owner setup','Private publishing setup.');
echo '<main id="main" class="container"><div class="admin-login"><p class="eyebrow">Owner setup / bkpandey.com</p>';
if($error)echo '<p class="admin-notice admin-error">'.bp_h($error).'</p>';
if(!$owner){
    echo '<h1 style="margin-top:20px">Connect MySQL.</h1><p class="muted" style="font-size:12px">Authenticate the existing database account to initialize the blog. The connection settings stay outside the public website.</p><dl class="case-meta" style="display:block"><dt>Database host</dt><dd>localhost</dd><dt style="margin-top:15px">Database</dt><dd>bkpandey_braj</dd><dt style="margin-top:15px">Database account</dt><dd>bkpandey_braj</dd></dl><form method="post"><input type="hidden" name="action" value="connect"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><div class="field"><label for="db-password">Database password</label><input id="db-password" name="db_password" type="password" autocomplete="current-password" required></div><button class="btn btn-primary" type="submit">Connect database ↗</button></form>';
}else{
    $total=$db?(int)$db->query('SELECT COUNT(*) FROM bp_blog_posts')->fetchColumn():0;
    echo '<h1 style="margin-top:20px">Database connected.</h1><p class="admin-notice">MySQL is active. '.$total.' articles are available in the blog.</p><p class="muted" style="font-size:12px">Create your owner account to edit articles, publish drafts, and upload attachments. Choose a new password for this publishing area.</p><form method="post"><input type="hidden" name="action" value="create_owner"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><div class="field"><label for="admin-email">Owner email</label><input id="admin-email" type="email" name="admin_email" value="brajkishorpandey@gmail.com" autocomplete="username" required></div><div class="field"><label for="admin-password">New publisher password</label><input id="admin-password" type="password" name="admin_password" minlength="14" maxlength="512" autocomplete="new-password" required></div><div class="field"><label for="admin-confirm">Confirm publisher password</label><input id="admin-confirm" type="password" name="admin_confirm" minlength="14" maxlength="512" autocomplete="new-password" required></div><button class="btn btn-primary" type="submit">Create owner account ↗</button></form><p class="form-note" style="font-size:10px;margin-top:18px">Setup expires after 20 minutes. This setup page is removed after the account is created.</p>';
}
echo '</div></main>';bp_page_end();
