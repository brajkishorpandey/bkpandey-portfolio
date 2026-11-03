<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';require __DIR__.'/migration-runner.php';
try{
    $db=bp_db();
    bp_migrate($db);
    $email=strtolower(trim(getenv('BP_ADMIN_EMAIL')?:''));$password=getenv('BP_ADMIN_PASSWORD')?:'';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<14)throw new RuntimeException('Set BP_ADMIN_EMAIL and a BP_ADMIN_PASSWORD of at least 14 characters.');
    $db->beginTransaction();
    $q=$db->prepare('SELECT id FROM bp_blog_admins WHERE email=?');$q->execute([$email]);
    if(!$q->fetch()){$q=$db->prepare('INSERT INTO bp_blog_admins(email,password_hash) VALUES (?,?)');$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);}
    $db->commit();require __DIR__.'/seed-import.php';$added=bp_import_initial_articles($db);bp_cache_invalidate();echo 'Publishing setup complete. Imported '.$added.' new articles. Existing articles and admin passwords were preserved.'.PHP_EOL;
}catch(Throwable $e){if(isset($db) && $db->inTransaction())$db->rollBack();fwrite(STDERR,"Setup could not complete. Check the database environment, schema permissions, and admin configuration.\n");exit(1);}
