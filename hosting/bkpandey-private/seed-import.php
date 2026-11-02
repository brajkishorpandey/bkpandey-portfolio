<?php
declare(strict_types=1);
function bp_import_initial_articles(PDO $db): int {
    $seed=json_decode(file_get_contents(__DIR__.'/seed.json'),true,512,JSON_THROW_ON_ERROR);$added=0;
    $db->beginTransaction();
    try{
        foreach($seed as $i=>$post){
            $q=$db->prepare('SELECT id FROM bp_blog_posts WHERE slug=?');$q->execute([$post['slug']]);if($q->fetch())continue;
            $q=$db->prepare('SELECT id FROM bp_blog_categories WHERE name=?');$q->execute([$post['category']]);$category=$q->fetchColumn();
            if(!$category){$q=$db->prepare('INSERT INTO bp_blog_categories(name) VALUES (?)');$q->execute([$post['category']]);$category=$db->lastInsertId();}
            $q=$db->prepare('INSERT INTO bp_blog_posts(category_id,slug,title,excerpt,body,status,source_url,origin,published_at,created_at,updated_at,pdf_display) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $now=gmdate('Y-m-d H:i:s',time()-$i);$published=$post['published_at']?$post['published_at'].' 00:00:00':null;
            $q->execute([$category,$post['slug'],$post['title'],$post['excerpt'],$post['body'],'published',bp_source_url($post['source_url']),$post['origin'],$published,$now,$now,$post['attachment']?'carousel':'file']);$id=(int)$db->lastInsertId();
            if($post['attachment']){
                $name=basename($post['attachment']);
                foreach([[$name,'application/pdf',0],[substr($name,0,-4).'.webp','image/webp',1]] as [$asset,$mime,$cover]){
                    $q=$db->prepare('INSERT INTO bp_blog_assets(original_name,public_path,mime_type) VALUES (?,?,?)');$q->execute([$asset,'/assets/blog/'.$asset,$mime]);$aid=$db->lastInsertId();
                    $q=$db->prepare('INSERT INTO bp_blog_post_assets(post_id,asset_id,is_cover) VALUES (?,?,?)');$q->execute([$id,$aid,$cover]);
                }
            }
            $added++;
        }
        $db->commit();return $added;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
