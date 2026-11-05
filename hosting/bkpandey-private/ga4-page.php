<?php
declare(strict_types=1);
require_once __DIR__.'/traffic.php';
$today = bp_traffic_today();
$day = bp_traffic_valid_day($_GET['day'] ?? null, $today) ?? $today->modify('-1 day')->format('Y-m-d');
?>
<section class="admin-section traffic-panel" aria-label="Google Analytics date">
    <p>Review consented visits by India reporting date. Recent Google totals can change as events are processed.</p>
    <form action="/admin" method="get" class="traffic-filters">
        <input type="hidden" name="analytics" value="1">
        <div class="field">
            <label for="ga4-day">Report date</label>
            <input id="ga4-day" type="date" name="day" value="<?= bp_h($day) ?>" max="<?= bp_h($today->format('Y-m-d')) ?>" required>
        </div>
        <div class="traffic-filter-actions">
            <button class="btn btn-primary" type="submit">View report</button>
            <a class="btn" href="https://analytics.google.com/" target="_blank" rel="noopener">Open Google Analytics ↗</a>
        </div>
    </form>
</section>
<?php require __DIR__.'/ga4-editor.php'; ?>
