<?php
declare(strict_types=1);
require __DIR__.'/../hosting/bkpandey-private/migration-runner.php';
$checks=0;
function check_migration(bool $condition,string $message): void {global $checks;$checks++;if(!$condition)throw new RuntimeException($message);}
function reject_migration(callable $run,string $message): void {try{$run();}catch(RuntimeException $e){check_migration(true,$message);return;}check_migration(false,$message);}
$dir=sys_get_temp_dir().'/bp-migration-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
try{
    file_put_contents($dir.'/001_initial.php',"<?php return ['CREATE TABLE IF NOT EXISTS items(id INTEGER PRIMARY KEY,value TEXT)'];");
    $db->exec("CREATE TABLE items(id INTEGER PRIMARY KEY,value TEXT);INSERT INTO items VALUES(1,'Owner content')");
    check_migration(bp_migration_status($db,$dir)[0]['state']==='pending','Status before initialization is read-only');
    check_migration(bp_migrate($db,$dir)===['001_initial'],'Adopt a pre-existing schema');
    check_migration($db->query('SELECT value FROM items')->fetchColumn()==='Owner content','Existing data survives initial migration');
    check_migration(bp_migrate($db,$dir)===[],'Repeated migration is a no-op');
    file_put_contents($dir.'/002_add_column.php',"<?php return ['ALTER TABLE items ADD COLUMN label TEXT'];");
    check_migration(bp_migrate($db,$dir)===['002_add_column'],'Apply only the next migration');
    check_migration(bp_migrate($db,$dir)===[],'Do not replay ALTER TABLE');
    file_put_contents($dir.'/001_initial.php',"\n// changed",FILE_APPEND);
    reject_migration(fn()=>bp_migrate($db,$dir),'Reject checksum drift before executing new SQL');
    unlink($dir.'/001_initial.php');
    reject_migration(fn()=>bp_migrate($db,$dir),'Reject missing applied migration');
    foreach(glob($dir.'/*.php') as $file)unlink($file);
    $db->exec('DROP TABLE bp_schema_migrations');
    file_put_contents($dir.'/001_fails.php',"<?php return ['CREATE TABLE partial_test(id INTEGER)','INVALID SQL'];");
    reject_migration(fn()=>bp_migrate($db,$dir),'Stop on a failed statement');
    check_migration((int)$db->query("SELECT COUNT(*) FROM bp_schema_migrations WHERE state='applied'")->fetchColumn()===0,'A failed migration is never marked applied');
    $q=$db->prepare("INSERT INTO bp_schema_migrations(version,checksum,state) VALUES (?,?,'running')");$q->execute(['001_fails',hash_file('sha256',$dir.'/001_fails.php')]);
    reject_migration(fn()=>bp_migrate($db,$dir),'Interrupted MySQL-like history requires review');
    // The versioned schema and manual SQL reference must agree.
    $statements=require __DIR__.'/../hosting/bkpandey-private/migrations/001_initial_schema.php';
    $schema=file_get_contents(__DIR__.'/../hosting/bkpandey-private/schema.sql');
    check_migration(preg_replace('/\s+/','',implode(';',$statements).';')===preg_replace('/\s+/','',$schema),'Manual schema matches immutable baseline');
}finally{foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
if(getenv('BP_TEST_MYSQL')==='1'){
    $db=new PDO('mysql:host=127.0.0.1;dbname=portfolio_test;charset=utf8mb4','root','ci-test-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    check_migration(bp_migrate($db)===['001_initial_schema'],'Create all tables on MySQL');
    $db->exec("INSERT INTO bp_site_settings(setting_key,setting_value) VALUES ('hero_title','Owner content')");
    check_migration(bp_migrate($db)===[],'MySQL repeated run is a no-op');
    $db->exec('DELETE FROM bp_schema_migrations');
    check_migration(bp_migrate($db)===['001_initial_schema'],'MySQL adopts existing installation');
    check_migration($db->query("SELECT setting_value FROM bp_site_settings WHERE setting_key='hero_title'")->fetchColumn()==='Owner content','MySQL preserves existing settings');
    $row=bp_migration_status($db)[0];check_migration($row['state']==='applied' && $row['completed_statements']===7,'MySQL records all seven baseline statements');
    $lock='bp:migrate:'.substr(hash('sha256','portfolio_test'),0,40);
    $q=$db->prepare('SELECT IS_FREE_LOCK(?)');$q->execute([$lock]);check_migration((int)$q->fetchColumn()===1,'Migration lock is released');
    $dir=sys_get_temp_dir().'/bp-mysql-failure-'.bin2hex(random_bytes(8));mkdir($dir,0700);
    try{
        copy(__DIR__.'/../hosting/bkpandey-private/migrations/001_initial_schema.php',$dir.'/001_initial_schema.php');
        file_put_contents($dir.'/002_fails.php',"<?php return ['CREATE TABLE bp_partial_test(id INTEGER)','INVALID SQL'];");
        reject_migration(fn()=>bp_migrate($db,$dir),'MySQL stops after a partially committed migration');
        $row=$db->query("SELECT state,completed_statements FROM bp_schema_migrations WHERE version='002_fails'")->fetch();
        check_migration($row['state']==='failed' && (int)$row['completed_statements']===1,'MySQL persists failed state and statement progress');
        reject_migration(fn()=>bp_migrate($db,$dir),'MySQL refuses automatic replay after partial failure');
    }finally{foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
}
echo $checks.' migration checks passed.'.PHP_EOL;
