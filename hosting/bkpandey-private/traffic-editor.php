<?php
declare(strict_types=1);
require_once __DIR__.'/traffic-history.php';

$today = bp_traffic_today();
$filterError = '';
try {
    $filters = bp_traffic_history_filters($_GET, $today);
} catch (InvalidArgumentException $e) {
    $filterError = $e->getMessage();
    $filters = bp_traffic_history_filters([], $today);
}
$day = bp_traffic_valid_day($_GET['day'] ?? null, $today);
if (isset($_GET['day']) && $day === null) {
    $filterError = 'Choose a valid report date.';
}

try {
    $report = $day !== null ? bp_traffic_report($db, $day) : null;
    $history = $day === null && $filterError === '' ? bp_traffic_history($db, $filters) : null;
} catch (PDOException $e) {
    error_log('Portfolio traffic report unavailable.');
    echo '<p class="admin-notice admin-error" role="alert">Traffic reports are unavailable. Check the Database section and scheduled collector.</p>';
    return;
}
?>
<?php if ($day !== null): ?>
<section class="admin-section traffic-panel" aria-labelledby="traffic-day-heading">
    <a class="admin-back" href="<?= bp_h(bp_traffic_history_url($filters, $filters['page'])) ?>">← All daily reports</a>
    <h2 id="traffic-day-heading"><time datetime="<?= bp_h($day) ?>"><?= bp_h($day) ?></time></h2>
    <?php if (!$report): ?>
        <p>No server-log records have been imported for this date.</p>
    <?php else: ?>
        <dl class="traffic-metrics">
            <?php foreach (BP_TRAFFIC_METRICS as $field => $label): ?>
            <div><dt><?= bp_h($label) ?></dt><dd><?= number_format((int) $report[$field]) ?></dd></div>
            <?php endforeach; ?>
        </dl>
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
        <p class="muted">Last imported <?= bp_h($report['collected_at']) ?> UTC. Email status
            <strong><?= bp_h(BP_TRAFFIC_MAIL_LABELS[$report['mail_status']] ?? 'Unknown') ?></strong>.
        </p>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="admin-section traffic-panel" aria-labelledby="traffic-history-heading">
    <h2 id="traffic-history-heading">Daily history</h2>
    <p class="muted">Browse daily totals from the hosting server’s archived access logs. Dates use India time.</p>
    <form method="get" action="/admin" class="traffic-filters" aria-label="Filter daily traffic reports">
        <input type="hidden" name="traffic" value="1">
        <div class="field">
            <label for="traffic-from">From date</label>
            <input id="traffic-from" name="from" type="date" value="<?= bp_h($filters['from']) ?>" max="<?= bp_h($today->format('Y-m-d')) ?>">
        </div>
        <div class="field">
            <label for="traffic-to">To date</label>
            <input id="traffic-to" name="to" type="date" value="<?= bp_h($filters['to']) ?>" max="<?= bp_h($today->format('Y-m-d')) ?>">
        </div>
        <div class="field">
            <label for="traffic-email">Email status</label>
            <select id="traffic-email" name="email">
                <option value="">Any status</option>
                <?php foreach (BP_TRAFFIC_MAIL_LABELS as $status => $label): ?>
                <option value="<?= bp_h($status) ?>"<?= $filters['email'] === $status ? ' selected' : '' ?>><?= bp_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="traffic-size">Rows per page</label>
            <select id="traffic-size" name="size">
                <?php foreach ([10, 25, 50] as $size): ?>
                <option<?= $filters['size'] === $size ? ' selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="traffic-filter-actions">
            <button type="submit" class="btn btn-primary">Apply filters</button>
            <a class="btn" href="/admin?traffic=1">Reset</a>
        </div>
    </form>
    <?php if ($filterError !== ''): ?>
        <p class="admin-notice admin-error" role="alert"><?= bp_h($filterError) ?></p>
    <?php elseif (!$history['rows']): ?>
        <p>No daily reports match these filters.</p>
    <?php else: ?>
        <p class="traffic-results muted">Showing <?= number_format($history['offset'] + 1) ?>–<?= number_format($history['offset'] + count($history['rows'])) ?> of <?= number_format($history['total']) ?> daily reports.</p>
        <div class="admin-list-wrap traffic-table-wrap">
            <table class="traffic-table" role="table" aria-label="Daily traffic reports">
                <thead role="rowgroup"><tr role="row">
                    <th scope="col" role="columnheader">Date</th>
                    <th scope="col" role="columnheader">Est. visitors</th>
                    <th scope="col" role="columnheader">Page views</th>
                    <th scope="col" role="columnheader">Bot requests</th>
                    <th scope="col" role="columnheader">Email status</th>
                    <th scope="col" role="columnheader">Report</th>
                </tr></thead>
                <tbody role="rowgroup">
                <?php foreach ($history['rows'] as $row): ?>
                    <tr role="row">
                        <th scope="row" role="rowheader"><time datetime="<?= bp_h($row['day']) ?>"><?= bp_h($row['day']) ?></time></th>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Est. visitors</span><?= number_format((int) $row['visitors']) ?></td>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Page views</span><?= number_format((int) $row['page_views']) ?></td>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Bot requests</span><?= number_format((int) $row['bot_requests']) ?></td>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Email status</span><span class="traffic-status"><?= bp_h(BP_TRAFFIC_MAIL_LABELS[$row['mail_status']] ?? 'Unknown') ?></span></td>
                        <td role="cell"><a class="traffic-open" href="<?= bp_h(bp_traffic_history_url($filters, $history['page'], $row['day'])) ?>" aria-label="View report for <?= bp_h($row['day']) ?>">View report →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($history['pages'] > 1): ?>
        <nav class="traffic-pager" aria-label="Traffic report pages">
            <?php if ($history['page'] > 1): ?>
                <a class="btn" rel="prev" href="<?= bp_h(bp_traffic_history_url($filters, $history['page'] - 1)) ?>">← Previous</a>
            <?php endif; ?>
            <span>Page <?= $history['page'] ?> of <?= $history['pages'] ?></span>
            <?php if ($history['page'] < $history['pages']): ?>
                <a class="btn" rel="next" href="<?= bp_h(bp_traffic_history_url($filters, $history['page'] + 1)) ?>">Next →</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<p class="traffic-method muted">Visitor and bot counts are estimates from hosting logs. Requests handled entirely at Cloudflare’s edge are outside these reports.</p>
<?php require __DIR__.'/ga4-editor.php'; ?>
