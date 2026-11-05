<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/core.php';
require __DIR__.'/social.php';

umask(0077);
try {
    $config = bp_social_config();
    if (!$config['channels']) {
        exit;
    }
    $lock = fopen(bp_cache_dir().'/social-worker.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        exit;
    }
    $db = bp_db();
    $db->exec("UPDATE bp_social_jobs SET status='review',updated_at=UTC_TIMESTAMP() WHERE status='sending'");
    $jobs = $db->query("SELECT j.*,p.slug,p.title,p.excerpt,p.status AS article_status FROM bp_social_jobs j
        JOIN bp_blog_posts p ON p.id=j.post_id WHERE j.status='queued' AND j.run_after<=UTC_TIMESTAMP()
        ORDER BY j.run_after,j.post_id,j.platform LIMIT 5")->fetchAll();
    $claim = $db->prepare("UPDATE bp_social_jobs SET status='sending',attempts=attempts+1,updated_at=UTC_TIMESTAMP()
        WHERE post_id=? AND platform=? AND status='queued'");
    $finish = $db->prepare('UPDATE bp_social_jobs SET status=?,remote_id=?,run_after=?,updated_at=UTC_TIMESTAMP()
        WHERE post_id=? AND platform=?');
    foreach ($jobs as $job) {
        $id = [(int) $job['post_id'], $job['platform']];
        $target = $config['channels'][$job['platform']] ?? '';
        if ($job['article_status'] !== 'published' || $target !== $job['channel_id']) {
            $finish->execute(['cancelled', null, bp_now(), ...$id]);
            continue;
        }
        $cover = null;
        if ($job['platform'] === 'instagram') {
            foreach (bp_assets($db, $id[0]) as $asset) {
                if ($asset['is_cover'] && in_array($asset['mime_type'], ['image/jpeg','image/png'], true)) {
                    $cover = 'https://bkpandey.com'.bp_asset_url($asset, true);
                    break;
                }
            }
        }
        $claim->execute($id);
        if (!$claim->rowCount()) {
            continue;
        }
        [$status, $remote] = bp_social_send($config['api_key'], bp_social_post_input($job, $cover));
        $retry = $status === 'retry' && (int) $job['attempts'] < 2;
        $finish->execute([$retry ? 'queued' : ($status === 'retry' ? 'failed' : $status), $remote,
            $retry ? gmdate('Y-m-d H:i:s', time() + 300 * (1 + (int) $job['attempts'])) : bp_now(), ...$id]);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Social publishing worker could not complete. Check the private setup and migration status.\n");
    exit(1);
}
