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
