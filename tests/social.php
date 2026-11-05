<?php
declare(strict_types=1);
require __DIR__.'/../hosting/bkpandey-private/core.php';
require __DIR__.'/../hosting/bkpandey-private/social.php';

function verify(bool $pass, string $message): void {
    if (!$pass) {
        throw new RuntimeException($message);
    }
}

$job = ['platform' => 'instagram', 'slug' => 'portfolio-architecture', 'title' => 'Portfolio architecture',
    'excerpt' => str_repeat('Public article summary ', 50), 'channel_id' => 'test-channel'];
$input = bp_social_post_input($job);
verify($input['metadata']['instagram']['type'] === 'post' && count($input['assets']) === 1, 'Instagram receives a feed image');
verify(str_ends_with($input['text'], 'utm_source=instagram&utm_medium=social&utm_campaign=article'), 'Use a canonical source-tagged link');
$job['platform'] = 'threads';
$input = bp_social_post_input($job);
verify(strlen($input['text']) <= 502 && $input['assets'] === [], 'Fit the Threads post and send a link');
verify(bp_social_result(200, ['data'=>['createPost'=>['post'=>['id'=>'buffer-post-1']]]]) === ['submitted', 'buffer-post-1'], 'Store confirmed remote acceptance');
verify(bp_social_result(429, null)[0] === 'retry' && bp_social_result(403, null)[0] === 'failed', 'Distinguish definite provider responses');
verify(bp_social_result(500, null)[0] === 'review' && bp_social_result(200, null)[0] === 'review', 'Ambiguous delivery requires review');

$cache = sys_get_temp_dir().'/bp-social-test-'.bin2hex(random_bytes(6));
putenv('BP_CACHE_DIR='.$cache);
$db = new Pdo\Sqlite('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->createFunction('UTC_TIMESTAMP', 'bp_now');
$db->exec("CREATE TABLE bp_blog_categories(id INTEGER PRIMARY KEY);
CREATE TABLE bp_blog_posts(id INTEGER PRIMARY KEY, category_id INTEGER, slug TEXT, title TEXT,
 excerpt TEXT, body TEXT, status TEXT, source_url TEXT, origin TEXT, published_at TEXT,
 created_at TEXT, updated_at TEXT, pdf_display TEXT, slider_interval INTEGER, version INTEGER DEFAULT 1);
INSERT INTO bp_blog_categories VALUES (1);
CREATE TABLE bp_social_jobs (post_id INTEGER, platform TEXT, channel_id TEXT, run_after TEXT, updated_at TEXT,
 PRIMARY KEY (post_id, platform))");
$article = ['title' => 'Example article', 'slug' => 'example-article', 'excerpt' => 'An excerpt for this example.',
    'body' => 'A private fixture for testing published articles.', 'status' => 'draft', 'category_id' => 1];
$channels = ['linkedin' => 'channel-one', 'facebook' => 'channel-two'];
try {
    $id = bp_social_save_post($db, $article, null, null, $channels);
    verify((int) $db->query('SELECT COUNT(*) FROM bp_social_jobs')->fetchColumn() === 0, 'Draft save has no side effect');
    $article['status'] = 'published';
    bp_social_save_post($db, $article, $id, 1, $channels);
    verify((int) $db->query('SELECT COUNT(*) FROM bp_social_jobs')->fetchColumn() === 2, 'First publish queues each configured channel');
    bp_social_save_post($db, $article, $id, 2, $channels);
    verify((int) $db->query('SELECT COUNT(*) FROM bp_social_jobs')->fetchColumn() === 2, 'Editing a published article never queues a duplicate');
    $db->exec('DROP TABLE bp_social_jobs');
    $article['slug'] = 'fail-queue';
    try {
        bp_social_save_post($db, $article, null, null, $channels);
        throw new RuntimeException('Expected queue creation to fail.');
    } catch (PDOException $e) {
        verify((int) $db->query('SELECT COUNT(*) FROM bp_blog_posts')->fetchColumn() === 1, 'Roll back article when queue insertion fails');
    }
} finally {
    foreach (glob($cache.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($cache);
}
echo "Social publishing queue and delivery checks passed.\n";
