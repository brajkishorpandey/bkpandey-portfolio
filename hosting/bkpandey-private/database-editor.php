<?php
declare(strict_types=1);
// Included only after the admin controller has verified the owner session.
$migrations=bp_migration_status($db);
echo '<section class="admin-editor"><h2>Database migrations.</h2><p>Apply trusted database updates included in a release. Back up the database before updating. Existing content and owner passwords are preserved by the initial migration.</p><table class="admin-list"><thead><tr><th>Version</th><th>Status</th><th>Applied (UTC)</th></tr></thead><tbody>';
foreach($migrations as $migration)echo '<tr><td>'.bp_h($migration['version']).'</td><td>'.bp_h($migration['state']).'</td><td>'.bp_h($migration['applied_at']??'—').'</td></tr>';
echo '</tbody></table><form method="post"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><button class="btn btn-primary" name="action" value="migrate">Apply pending migrations</button></form><p class="form-note">Changed, missing, or interrupted migrations require operator review. Uploads cannot add or execute migrations.</p></section>';
