<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';require __DIR__.'/migration-runner.php';
$command=$argv[1]??'status';
if(!in_array($command,['status','up'],true)){fwrite(STDERR,"Usage: php migrate.php [status|up]\n");exit(2);}
try{
    $db=bp_db();
    if($command==='up')foreach(bp_migrate($db) as $version)echo 'Applied '.$version.PHP_EOL;
    foreach(bp_migration_status($db) as $row)echo $row['version'].'  '.$row['state'].PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Database migration could not complete. Check private configuration, permissions, and migration status. No credentials are printed.\n");exit(1);}
