<?php
declare(strict_types=1);
require_once __DIR__.'/social.php';
$social = bp_social_config();
$jobs = [];
$queueReady = true;
try {
    $jobs = $db->query('SELECT j.post_id,j.platform,j.status,j.attempts,j.updated_at,p.title
        FROM bp_social_jobs j JOIN bp_blog_posts p ON p.id=j.post_id ORDER BY j.updated_at DESC,j.post_id DESC LIMIT 30')->fetchAll();
} catch (PDOException $e) {
    $queueReady = false;
}
?>
<section class="admin-section traffic-panel" aria-label="Social publishing destinations">
    <h2>Destinations</h2>
    <p>On first publication, new articles are shared to the channels enabled in the private Buffer configuration.</p>
    <ul>
        <?php foreach (BP_SOCIAL_PLATFORMS as $platform => $name): ?>
        <li><?= bp_h($name) ?> · <?= isset($social['channels'][$platform]) ? 'Configured' : 'Connection needed' ?></li>
        <?php endforeach; ?>
    </ul>
    <p>Instagram shares the article cover or the portfolio image. Other channels share a link to the article. Each link identifies its source in traffic reports.</p>
    <p><a class="btn" href="https://buffer.com/" target="_blank" rel="noopener">Open Buffer ↗</a></p>
</section>
<section class="admin-section traffic-panel" aria-label="Recent social publishing jobs">
    <h2>Recent jobs</h2>
    <?php if (!$queueReady): ?>
        <p>Apply the social publishing migration from the <a href="/admin?database=1">Database</a> section.</p>
    <?php elseif (!$jobs): ?>
        <p>No social jobs yet.</p>
    <?php else: ?>
        <p>For a job marked <strong>Check Buffer</strong>, verify the destination before sharing again.</p>
        <div class="admin-list-wrap">
            <table class="admin-list" aria-label="Recent social publishing jobs">
                <thead><tr><th scope="col">Article</th><th scope="col">Destination</th><th scope="col">Status</th><th scope="col">Updated (UTC)</th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr><th scope="row"><a href="/admin?edit=<?= (int) $job['post_id'] ?>"><?= bp_h($job['title']) ?></a></th>
                        <td><?= bp_h(BP_SOCIAL_PLATFORMS[$job['platform']] ?? $job['platform']) ?></td>
                        <td><?= bp_h(BP_SOCIAL_STATUS[$job['status']] ?? $job['status']) ?></td>
                        <td><?= bp_h($job['updated_at']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
