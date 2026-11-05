<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/core.php';
require __DIR__.'/traffic.php';
require __DIR__.'/ga4.php';
require __DIR__.'/migration-runner.php';

function bp_traffic_collect_archive(PDO $db, string $path, string $key): int {
    $name = basename($path);
    $stat = stat($path);
    if (!$stat) {
        throw new RuntimeException('Archive metadata unavailable.');
    }
    $query = $db->prepare('SELECT * FROM bp_traffic_cursors WHERE log_name=?');
    $query->execute([$name]);
    $cursor = $query->fetch();
    if ($cursor && $cursor['finished'] && $cursor['archive_mtime'] == $stat['mtime'] && $cursor['archive_size'] == $stat['size']) {
        return 0;
    }

    $file = gzopen($path, 'rb');
    if (!$file) {
        throw new RuntimeException('Archive unavailable.');
    }
    $offset = (int) ($cursor['byte_offset'] ?? 0);
    try {
        // Check the processed prefix boundary before resuming an appended archive.
        $checkpoint = static function ($file, int $offset): string {
            $start = max(0, $offset - 512);
            if (gzseek($file, $start) !== 0) {
                throw new RuntimeException('Archive cursor unavailable.');
            }
            $bytes = $offset > $start ? gzread($file, $offset - $start) : '';
            if ($bytes === false || gztell($file) !== $offset) {
                throw new RuntimeException('Archive was truncated.');
            }
            return hash('sha256', $bytes, true);
        };
        if ($cursor && !hash_equals($cursor['boundary_hash'], $checkpoint($file, $offset))) {
            throw new RuntimeException('An imported archive was rewritten.');
        }
        $daily = [];
        $visitors = [];
        $records = 0;
        $lines = 0;
        while ($lines < 15000 && ($line = gzgets($file)) !== false) {
            if (!str_ends_with($line, "\n")) {
                // Leave the cursor before a partially appended final record.
                break;
            }
            $lines++;
            $offset = gztell($file);
            $row = bp_traffic_record($line, $key);
            if ($row === null) {
                continue;
            }
            $records++;
            $day = $row['day'];
            $daily[$day] ??= [0, 0, 0, 0, 0];
            $daily[$day][0]++;
            $daily[$day][1] += (int) $row['attempt'];
            $daily[$day][2] += (int) $row['view'];
            $daily[$day][3] += (int) $row['failed'];
            $daily[$day][4] += (int) $row['bot'];
            if ($row['fingerprint'] === null) {
                continue;
            }
            $kind = $row['bot'] ? 'b' : 'v';
            $id = $day.$kind.$row['fingerprint'];
            if (!isset($visitors[$id])) {
                $visitors[$id] = [$day, $kind, $row['fingerprint'], $row['source'], $row['source_at']];
            }
            if ($row['source'] !== 'direct' && ($visitors[$id][3] === 'direct' || $row['source_at'] < $visitors[$id][4])) {
                $visitors[$id][3] = $row['source'];
                $visitors[$id][4] = $row['source_at'];
            }
        }
        $finished = (int) (gzeof($file) && $offset === gztell($file));
        $boundary = $checkpoint($file, $offset);
    } finally {
        gzclose($file);
    }

    $db->beginTransaction();
    try {
        $query = $db->prepare('INSERT INTO bp_traffic_daily(day, requests, page_attempts, page_views, failed_attempts, bot_requests, collected_at)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE
            requests=requests+VALUES(requests), page_attempts=page_attempts+VALUES(page_attempts),
            page_views=page_views+VALUES(page_views), failed_attempts=failed_attempts+VALUES(failed_attempts),
            bot_requests=bot_requests+VALUES(bot_requests), collected_at=VALUES(collected_at)');
        foreach ($daily as $day => $counts) {
            $query->execute([$day, ...$counts]);
        }
        $query = $db->prepare("INSERT INTO bp_traffic_visitors(day, kind, fingerprint, source, source_at)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
            source_at=IF(VALUES(source)<>'direct' AND (source='direct' OR VALUES(source_at)<source_at), VALUES(source_at), source_at),
            source=IF(VALUES(source)<>'direct' AND VALUES(source_at)=source_at, VALUES(source), source)");
        foreach ($visitors as $row) {
            $query->execute($row);
        }
        $query = $db->prepare('INSERT INTO bp_traffic_cursors(log_name, byte_offset, boundary_hash, archive_mtime, archive_size, finished)
            VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE byte_offset=VALUES(byte_offset),
            boundary_hash=VALUES(boundary_hash), archive_mtime=VALUES(archive_mtime), archive_size=VALUES(archive_size), finished=VALUES(finished)');
        $query->execute([$name, $offset, $boundary, $stat['mtime'], $stat['size'], $finished]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return $records;
}

function bp_traffic_mail(PDO $db, string $day): void {
    $report = bp_traffic_report($db, $day);
    if (!$report || $report['mail_status'] !== 'pending') {
        return;
    }
    $recipient = bp_public_site_values()['contact_email'];
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\x00-\x20\x7f]/', $recipient)) {
        throw new RuntimeException('Invalid report recipient.');
    }
    $claim = $db->prepare("UPDATE bp_traffic_daily SET mail_status='attempted', mail_attempted_at=UTC_TIMESTAMP()
        WHERE day=? AND mail_status='pending'");
    $claim->execute([$day]);
    if (!$claim->rowCount()) {
        return;
    }
    $headers = [
        'From' => 'Brajkishor Pandey Website <contact@bkpandey.com>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    $digest = bp_traffic_digest($report);
    $settings = bp_site_values($db);
    $property = bp_ga4_property_id($settings['ga4_property_id'] ?? '');
    $measurement = bp_ga4_measurement_id($settings['ga4_measurement_id'] ?? '');
    if ($measurement !== '' && $property !== '' && is_readable(bp_ga4_credentials_path())) {
        $digest .= "\n\nGoogle Analytics 4 for ".$day."\n";
        try {
            $gaReport = bp_ga4_report($property, $day);
            foreach (BP_GA4_METRICS as $key => $label) {
                $digest .= $label.': '.number_format($gaReport['metrics'][$key])."\n";
            }
            $digest .= $gaReport['has_rows'] ? 'Recent Google data can still change.' : 'Google returned no activity for this date yet.';
        } catch (Throwable $e) {
            $digest .= 'The Google report was unavailable at send time. See Traffic reports in the admin dashboard.';
        }
    }
    $accepted = mail($recipient, 'bkpandey.com traffic report '.$day, $digest, $headers, '-fcontact@bkpandey.com');
    $query = $db->prepare('UPDATE bp_traffic_daily SET mail_status=? WHERE day=?');
    $query->execute([$accepted ? 'queued' : 'failed', $day]);
}

umask(0077);
$state = __DIR__.'/traffic-state';
try {
    if (!is_dir($state) && !mkdir($state, 0700, true) && !is_dir($state)) {
        throw new RuntimeException('Traffic state directory unavailable.');
    }
    $lock = fopen($state.'/collect.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        exit;
    }
    $keyFile = $state.'/visitor.key';
    if (!is_file($keyFile) && file_put_contents($keyFile, random_bytes(32)) !== 32) {
        throw new RuntimeException('Traffic key could not be created.');
    }
    $key = file_get_contents($keyFile);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Traffic key invalid.');
    }
    $db = bp_db();
    if (in_array('--migrate', $argv, true)) {
        bp_migrate($db);
    }
    $logDir = dirname(__DIR__).'/logs';
    $paths = array_filter(glob($logDir.'/bkpandey.com*-20*.gz') ?: [], static fn ($path) =>
        preg_match('/^bkpandey\.com(?:-ssl_log)?-[A-Z][a-z]{2}-20\d{2}\.gz$/D', basename($path)) && is_readable($path));
    if (!$paths) {
        throw new RuntimeException('No readable cPanel access archives.');
    }
    $records = 0;
    foreach ($paths as $path) {
        $records += bp_traffic_collect_archive($db, $path, $key);
    }
    if ($records) {
        echo 'Imported '.$records." access records.\n";
    }
    $today = bp_traffic_today();
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--send-date=')) {
            $day = substr($argument, strlen('--send-date='));
            if ($day !== bp_traffic_date($day, $today) || $day >= $today->format('Y-m-d')) {
                throw new InvalidArgumentException('Choose a completed report date.');
            }
            bp_traffic_mail($db, $day);
        }
    }
    if (in_array('--mail', $argv, true)) {
        $yesterday = $today->modify('-1 day')->format('Y-m-d');
        // Wait for both vhost archives to finish importing after the report date closes.
        $query = $db->prepare("SELECT COUNT(DISTINCT IF(log_name LIKE '%-ssl_log-%', 'ssl', 'http'))
            FROM bp_traffic_cursors WHERE archive_mtime>=? AND finished=1");
        $query->execute([$today->getTimestamp()]);
        if ((int) $query->fetchColumn() === 2) {
            bp_traffic_mail($db, $yesterday);
        }
    }
} catch (Throwable $e) {
    // Avoid logging connection settings, visitor information, or untrusted log text.
    fwrite(STDERR, 'Traffic collection failed. Check the private logs, archive access, and database migration status.'."\n");
    exit(1);
}
