<?php
declare(strict_types=1);

// Only trusted, version-controlled files from this private directory can run.
function bp_migration_files(?string $directory=null): array {
    $directory=$directory??__DIR__.'/migrations';$files=[];
    foreach(glob($directory.'/*.php')?:[] as $path){
        $version=basename($path,'.php');
        if(strlen($version)>96 || !preg_match('/^[0-9]{3,}_[a-z0-9_]+$/',$version))throw new RuntimeException('Invalid migration filename.');
        $files[$version]=$path;
    }
    ksort($files,SORT_STRING);return $files;
}
function bp_migration_history(PDO $db): array {
    try{$rows=$db->query('SELECT version,checksum,state,completed_statements,applied_at FROM bp_schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);}
    catch(PDOException $e){
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(($driver==='mysql' && (int)($e->errorInfo[1]??0)===1146) || ($driver==='sqlite' && str_contains($e->getMessage(),'no such table: bp_schema_migrations')))return [];
        throw $e;
    }
    $history=[];foreach($rows as $row)$history[$row['version']]=$row;return $history;
}
function bp_migration_status(PDO $db,?string $directory=null): array {
    $history=bp_migration_history($db);$rows=[];
    foreach(bp_migration_files($directory) as $version=>$file){
        $stored=$history[$version]??null;$checksum=hash_file('sha256',$file);
        $state=$stored?(!hash_equals($stored['checksum'],$checksum)?'changed':$stored['state']):'pending';
        $rows[]=['version'=>$version,'state'=>$state,'checksum'=>$checksum,'completed_statements'=>(int)($stored['completed_statements']??0),'applied_at'=>$stored['applied_at']??null];
        unset($history[$version]);
    }
    foreach($history as $row){$row['state']='missing';$rows[]=$row;}
    return $rows;
}
function bp_migrate(PDO $db,?string $directory=null): array {
    if($db->inTransaction())throw new RuntimeException('Finish the current transaction before applying migrations.');
    $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if(!in_array($driver,['mysql','sqlite'],true))throw new RuntimeException('Unsupported migration database.');
    $lock=null;$applied=[];
    try{
        if($driver==='mysql'){
            $name=(string)$db->query('SELECT DATABASE()')->fetchColumn();$lock='bp:migrate:'.substr(hash('sha256',$name),0,40);
            $q=$db->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lock]);
            if((int)$q->fetchColumn()!==1){$lock=null;throw new RuntimeException('Another database migration is in progress. Try again later.');}
        }
        $db->exec("CREATE TABLE IF NOT EXISTS bp_schema_migrations (
            version VARCHAR(96) PRIMARY KEY, checksum CHAR(64) NOT NULL,
            state VARCHAR(16) NOT NULL, completed_statements INTEGER NOT NULL DEFAULT 0,
            applied_at DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )".($driver==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
        // SQLite is used for local runner tests; the production lock is connection-scoped.
        if($driver==='sqlite'){$db->exec('PRAGMA busy_timeout=10000');$db->beginTransaction();$db->exec('UPDATE bp_schema_migrations SET state=state WHERE 1=0');}
        $status=bp_migration_status($db,$directory);$files=bp_migration_files($directory);$lastApplied='';
        foreach($status as $row){
            if(!in_array($row['state'],['pending','applied'],true))throw new RuntimeException('Migration '.$row['version'].' needs operator review ('.$row['state'].'). Restore the original file or repair the database before retrying.');
            if($row['state']==='applied')$lastApplied=max($lastApplied,$row['version']);
        }
        foreach($status as $row)if($row['state']==='pending' && strcmp($row['version'],$lastApplied)<0)throw new RuntimeException('New migrations must follow the last applied version.');
        foreach($status as $row){
            if($row['state']!=='pending')continue;$version=$row['version'];$file=$files[$version];
            $statements=require $file;
            if(!is_array($statements) || !$statements || !hash_equals($row['checksum'],hash_file('sha256',$file)))throw new RuntimeException('Invalid migration '.$version.'.');
            foreach($statements as $sql)if(!is_string($sql) || trim($sql)==='')throw new RuntimeException('Invalid migration statement in '.$version.'.');
            $q=$db->prepare("INSERT INTO bp_schema_migrations(version,checksum,state) VALUES (?,?,'running')");$q->execute([$version,$row['checksum']]);
            try{
                foreach($statements as $index=>$sql){
                    $db->exec($sql);
                    $q=$db->prepare('UPDATE bp_schema_migrations SET completed_statements=?,updated_at=CURRENT_TIMESTAMP WHERE version=?');$q->execute([$index+1,$version]);
                }
                $q=$db->prepare("UPDATE bp_schema_migrations SET state='applied',applied_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE version=?");$q->execute([$version]);$applied[]=$version;
            }catch(Throwable $e){
                // MySQL DDL may already be committed. Never silently replay a partial migration.
                $q=$db->prepare("UPDATE bp_schema_migrations SET state='failed',updated_at=CURRENT_TIMESTAMP WHERE version=?");$q->execute([$version]);
                throw new RuntimeException('Migration '.$version.' stopped. Review its recorded progress and restore a database backup if necessary.',0,$e);
            }
        }
        if($driver==='sqlite' && $db->inTransaction())$db->commit();return $applied;
    }catch(Throwable $e){if($driver==='sqlite' && $db->inTransaction())$db->rollBack();throw $e;}
    finally{if($lock!==null){$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}}
}
