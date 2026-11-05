<?php
declare(strict_types=1);
require_once __DIR__.'/ga4.php';

$gaSettings = bp_site_values($db);
$measurementId = bp_ga4_measurement_id($gaSettings['ga4_measurement_id'] ?? '');
$propertyId = bp_ga4_property_id($gaSettings['ga4_property_id'] ?? '');
$gaReport = null;
$gaError = '';
if ($measurementId !== '' && $propertyId !== '' && is_readable(bp_ga4_credentials_path())) {
    try {
        $gaReport = bp_ga4_report($propertyId, $day);
    } catch (Throwable $e) {
        error_log('Portfolio Google Analytics reporting request failed.');
        $gaError = 'Google Analytics could not load this report. Check the property, read-only access, and enabled Data API.';
    }
}
?>
<section class="admin-section" aria-labelledby="ga4-heading">
    <h2 id="ga4-heading">Google Analytics 4</h2>
    <p>Google Analytics counts visitors who allow analytics. The server-log report above includes separate request and bot estimates.</p>
    <?php if ($gaReport): ?>
        <h3>Google Analytics on <?= bp_h($day) ?></h3>
        <?php if (!$gaReport['has_rows']): ?><p>Google returned no activity for this date. Recent activity can take time to appear.</p><?php endif; ?>
        <div class="admin-list-wrap">
            <table class="admin-list">
                <thead><tr><th scope="col">Metric</th><th scope="col">Total</th></tr></thead>
                <tbody>
                    <?php foreach (BP_GA4_METRICS as $field => $label): ?>
                    <tr><th scope="row"><?= bp_h($label) ?></th><td><?= number_format($gaReport['metrics'][$field]) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($gaReport['sources']): ?>
        <h3>Top sources by sessions</h3>
        <div class="admin-list-wrap">
            <table class="admin-list">
                <thead><tr><th scope="col">Source / medium</th><th scope="col">Sessions</th></tr></thead>
                <tbody>
                <?php foreach ($gaReport['sources'] as $source): ?>
                    <tr><th scope="row"><?= bp_h($source['name']) ?></th><td><?= number_format($source['sessions']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <p class="muted">Retrieved <?= bp_h($gaReport['fetched_at']) ?> UTC.<?php if ($gaReport['timezone'] !== ''): ?>
            Property time zone <?= bp_h($gaReport['timezone']) ?>.<?php endif; ?></p>
    <?php elseif ($gaError !== ''): ?>
        <p class="admin-notice admin-error"><?= bp_h($gaError) ?></p>
    <?php else: ?>
        <p>Reports appear here after a GA4 stream and read-only Data API access are connected.</p>
    <?php endif; ?>
    <details>
        <summary>Google Analytics connection</summary>
        <form action="/admin?traffic=1" method="post" class="admin-editor">
            <input type="hidden" name="csrf" value="<?= bp_h(bp_csrf()) ?>">
            <input type="hidden" name="action" value="ga4_config">
            <div class="editor-grid">
                <div class="field">
                    <label for="ga4-measurement-id">Web stream measurement ID</label>
                    <input id="ga4-measurement-id" name="ga4_measurement_id" value="<?= bp_h($measurementId) ?>" placeholder="G-…" maxlength="22" pattern="G-[A-Z0-9]{4,20}">
                </div>
                <div class="field">
                    <label for="ga4-property-id">Numeric GA4 property ID</label>
                    <input id="ga4-property-id" name="ga4_property_id" value="<?= bp_h($propertyId) ?>" inputmode="numeric" maxlength="20" pattern="[1-9][0-9]{4,19}">
                </div>
            </div>
            <p class="muted">Leave the measurement ID blank to disable the tag. Install the read-only reporting credential in the private hosting folder.</p>
            <button type="submit" class="btn btn-primary">Save Analytics connection</button>
        </form>
    </details>
</section>
