<?php
declare(strict_types=1);
require_once __DIR__.'/traffic.php';

$today = bp_traffic_today();
$requested = $_GET['day'] ?? null;
try {
    $range = $db->query('SELECT MIN(day) AS first_day, MAX(day) AS last_day FROM bp_traffic_daily')->fetch();
    $day = bp_traffic_date($requested ?? $range['last_day'], $today);
    $report = bp_traffic_report($db, $day);
} catch (PDOException $e) {
    error_log('Portfolio traffic report unavailable.');
    echo '<section class="admin-section"><h2>Traffic reports</h2><p>Traffic reports are unavailable. Check the Database tab and scheduled collector.</p></section>';
    return;
}
?>
<section class="admin-section" aria-labelledby="traffic-heading">
    <h2 id="traffic-heading">Traffic reports</h2>
    <p>Daily reports use India time and the hosting server’s archived access logs. Totals update when cPanel archives new logs.</p>
    <form method="get" action="/admin" class="admin-toolbar">
        <input type="hidden" name="traffic" value="1">
        <div class="field">
            <label for="traffic-day">Report date</label>
            <input id="traffic-day" name="day" type="date" value="<?= bp_h($day) ?>" max="<?= bp_h($today->format('Y-m-d')) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">View report</button>
    </form>
    <?php if ($range['first_day']): ?>
    <p class="muted">Available log dates run from <?= bp_h($range['first_day']) ?> through <?= bp_h($range['last_day']) ?>.</p>
    <?php endif; ?>
    <?php if (!$report): ?>
    <p>No log records have been imported for this date.</p>
    <?php else: ?>
    <h3>Traffic on <?= bp_h($day) ?></h3>
    <div class="admin-list-wrap">
        <table class="admin-list">
            <thead><tr><th scope="col">Metric</th><th scope="col">Total</th></tr></thead>
            <tbody>
            <?php foreach (BP_TRAFFIC_METRICS as $field => $label): ?>
                <tr><th scope="row"><?= bp_h($label) ?></th><td><?= number_format((int) $report[$field]) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h3>Estimated unique visitors by source</h3>
    <p class="muted">Each visitor is assigned to their first known external source. Direct / unknown includes visits with no known referral.</p>
    <div class="admin-list-wrap">
        <table class="admin-list">
            <thead><tr><th scope="col">Source</th><th scope="col">Visitors</th></tr></thead>
            <tbody>
            <?php foreach ($report['sources'] as $source => $count): ?>
                <tr><th scope="row"><?= bp_h(BP_TRAFFIC_SOURCES[$source]) ?></th><td><?= number_format($count) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted">Last imported <?= bp_h($report['collected_at']) ?> UTC. Email <?= bp_h(match ($report['mail_status']) {
        'queued' => 'accepted by the hosting mail service',
        'failed' => 'rejected by the hosting mail service',
        'attempted' => 'delivery status unconfirmed',
        default => 'pending or disabled',
    }) ?>.</p>
    <?php endif; ?>
    <p class="muted">Visitor counts use daily, keyed estimates. Automated traffic is identified from user agents. Cloudflare requests served or blocked at the edge are outside this report.</p>
</section>

<?php require __DIR__.'/ga4-editor.php'; ?>
