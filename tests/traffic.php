<?php
declare(strict_types=1);
require __DIR__.'/../hosting/bkpandey-private/traffic.php';

function verify(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$key = str_repeat('k', 32);
$parse = static fn (string $timestamp, string $request, int $status, string $referer, string $agent) =>
    bp_traffic_record(sprintf('192.0.2.12 - - [%s] "%s" %d 300 "%s" "%s"', $timestamp, $request, $status, $referer, $agent), $key);
$first = $parse('04/Nov/2026:18:29:59 +0000', 'GET /blog/test.html HTTP/2', 200, 'https://www.linkedin.com/feed/?private=1', 'Mozilla');
$next = $parse('04/Nov/2026:18:30:00 +0000', 'GET /blog/test.html HTTP/2', 304, 'https://m.facebook.com/', 'Mozilla');
verify($first['view'] && $first['day'] === '2026-11-04' && $first['source'] === 'linkedin', 'External referrer and date boundary');
verify($next['view'] && $next['day'] === '2026-11-05' && $next['source'] === 'facebook', 'Cached browser view and date boundary');
verify($first['fingerprint'] !== $next['fingerprint'] && strlen($first['fingerprint']) === 32, 'Daily keyed identifier');
$direct = $parse('04/Nov/2026:18:30:12 +0000', 'GET / HTTP/2', 200, '-', 'Mozilla');
verify($direct['fingerprint'] === $next['fingerprint'] && $direct['source'] === 'direct', 'Same visitor during one day');
$bot = $parse('04/Nov/2026:18:30:00 +0000', 'GET /robots.txt HTTP/2', 200, '-', 'Googlebot');
verify($bot['bot'] && !$bot['view'] && !$bot['attempt'], 'Bot requests separate from browser visits');
$failed = $parse('04/Nov/2026:18:30:00 +0000', 'GET /blog/missing.html HTTP/2', 404, '-', 'Mozilla');
verify($failed['attempt'] && $failed['failed'] && !$failed['view'], 'Failed page attempts');
$asset = $parse('04/Nov/2026:18:30:00 +0000', 'GET /assets/image.jpg HTTP/2', 200, '-', 'Mozilla');
verify(!$asset['attempt'] && !$asset['view'], 'Static assets excluded from browser page metrics');
verify(bp_traffic_source('-', '/?utm_source=whatsapp') === 'whatsapp', 'Campaign source without a referrer');
verify(bp_traffic_source('https://linkedin.com.example.com', '/') === 'other', 'Domain suffix boundary');
verify(bp_traffic_date('2026-02-30', new DateTimeImmutable('2026-11-05')) === '2026-11-05', 'Invalid dates fall back');
echo "Traffic classification checks passed.\n";

require __DIR__.'/../hosting/bkpandey-private/traffic-history.php';
$today = new DateTimeImmutable('2026-11-05');
$db = new PDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE bp_traffic_daily (day TEXT PRIMARY KEY, page_views INTEGER, bot_requests INTEGER, mail_status TEXT);
          CREATE TABLE bp_traffic_visitors (day TEXT, kind TEXT)');
for ($day = new DateTimeImmutable('2026-10-01'); $day <= $today; $day = $day->modify('+1 day')) {
    $id = $day->format('Y-m-d');
    $db->prepare('INSERT INTO bp_traffic_daily VALUES (?, 9, 3, ?)')->execute([$id, $id < '2026-11-01' ? 'queued' : 'pending']);
    $db->prepare("INSERT INTO bp_traffic_visitors VALUES (?, 'v'), (?, 'v'), (?, 'b')")->execute([$id, $id, $id]);
}
$filters = bp_traffic_history_filters([], $today);
$result = bp_traffic_history($db, $filters);
verify($result['total'] === 36 && $result['pages'] === 4 && count($result['rows']) === 10, 'Default daily history is paginated');
verify($result['rows'][0]['day'] === '2026-11-05' && $result['rows'][0]['visitors'] === 2, 'Newest first and excludes bot identities');
$filters = bp_traffic_history_filters(['from' => '2026-10-03', 'to' => '2026-11-03', 'email' => 'queued', 'size' => '25', 'page' => '9999'], $today);
$result = bp_traffic_history($db, $filters);
verify($result['total'] === 29 && $result['page'] === 2 && count($result['rows']) === 4, 'Filter before paginating and clamp out-of-range pages');
verify($result['rows'][3]['day'] === '2026-10-03', 'Include the lower date boundary');
verify(bp_traffic_history_url($filters, 2, '2026-10-03') === '/admin?traffic=1&from=2026-10-03&to=2026-11-03&email=queued&size=25&page=2&day=2026-10-03',
    'Keep filter and pagination context in daily links');
foreach ([['from' => '2026-02-30'], ['to' => '2026-11-06'], ['from' => '2026-11-05', 'to' => '2026-11-01']] as $input) {
    try {
        bp_traffic_history_filters($input, $today);
        throw new RuntimeException('An invalid range should not be accepted.');
    } catch (InvalidArgumentException $e) {
        continue;
    }
}
echo "Traffic history filter and pagination checks passed.\n";
