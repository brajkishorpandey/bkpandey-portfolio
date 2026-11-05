<?php
declare(strict_types=1);

const BP_TRAFFIC_SOURCES = [
    'direct' => 'Direct / unknown', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook',
    'instagram' => 'Instagram', 'google' => 'Google', 'bing' => 'Bing', 'x' => 'X / Twitter',
    'whatsapp' => 'WhatsApp', 'other' => 'Other websites',
];

function bp_traffic_today(): DateTimeImmutable {
    return new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
}

function bp_traffic_valid_day(mixed $input, DateTimeImmutable $today): ?string {
    if (is_string($input) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $input)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input, $today->getTimezone());
        if ($date && $date->format('Y-m-d') === $input && $date <= $today) {
            return $input;
        }
    }
    return null;
}

function bp_traffic_date(mixed $input, DateTimeImmutable $today): string {
    return bp_traffic_valid_day($input, $today) ?? $today->format('Y-m-d');
}

function bp_traffic_source(string $referrer, string $target): string {
    $host = strtolower(rtrim((string) parse_url($referrer, PHP_URL_HOST), '.'));
    if ($host === '' || $host === 'bkpandey.com' || str_ends_with($host, '.bkpandey.com')) {
        // Apps often strip referrers. Recognize only explicitly supported campaign sources.
        $query = (string) parse_url($target, PHP_URL_QUERY);
        if (preg_match('/(?:^|&)utm_source=([a-zA-Z0-9_-]{1,24})(?:&|$)/', $query, $utm)) {
            $source = strtolower($utm[1]);
            if (isset(BP_TRAFFIC_SOURCES[$source]) && $source !== 'other') {
                return $source;
            }
            if ($source === 'twitter') {
                return 'x';
            }
        }
        return 'direct';
    }
    foreach ([
        'linkedin.com' => 'linkedin', 'lnkd.in' => 'linkedin',
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook',
        'instagram.com' => 'instagram', 'bing.com' => 'bing', 'x.com' => 'x',
        'twitter.com' => 'x', 't.co' => 'x', 'whatsapp.com' => 'whatsapp', 'wa.me' => 'whatsapp',
    ] as $domain => $source) {
        if ($host === $domain || str_ends_with($host, '.'.$domain)) {
            return $source;
        }
    }
    if (preg_match('/(?:^|\.)google\.(?:com|[a-z]{2}|(?:co|com)\.[a-z]{2})$/D', $host)) {
        return 'google';
    }
    return 'other';
}

function bp_traffic_record(string $line, string $key): ?array {
    static $zone;
    $zone ??= new DateTimeZone('Asia/Kolkata');
    $pattern = '~^(\S+) \S+ \S+ \[([^\]]+)\] "([^"]*)" (\d{3}) \S+ "([^"]*)" "([^"]*)"~';
    if (!preg_match($pattern, $line, $parts) || !filter_var($parts[1], FILTER_VALIDATE_IP)) {
        return null;
    }
    $time = DateTimeImmutable::createFromFormat('!d/M/Y:H:i:s O', $parts[2]);
    if (!$time) {
        return null;
    }
    $day = $time->setTimezone($zone)->format('Y-m-d');
    $request = explode(' ', $parts[3], 3);
    $target = $request[1] ?? '';
    $path = parse_url($target, PHP_URL_PATH);
    $publicPage = in_array($request[0], ['GET', 'HEAD'], true) && is_string($path)
        && preg_match('~^/(?:$|index\.html$|(?:resume|archive|privacy)\.html$|blog/?$|blog/[a-z0-9-]+\.html$)~D', $path);
    $agent = $parts[6];
    $bot = $agent === '-' || $agent === '' || preg_match(
        '/bot|crawler|spider|slurp|headless|facebookexternalhit|facebookcatalog|linkedinbot|whatsapp|curl\/|wget\/|python-requests|go-http-client|uptime|monitor|semrush|ahrefs|bytespider|scrapy|scanner/i',
        $agent
    );
    $status = (int) $parts[4];
    $view = !$bot && $publicPage && $request[0] === 'GET'
        && (($status >= 200 && $status < 300) || $status === 304);
    return [
        'day' => $day, 'bot' => (bool) $bot, 'view' => $view, 'attempt' => !$bot && $publicPage,
        'failed' => !$bot && $publicPage && $status >= 400,
        'source' => $view ? bp_traffic_source($parts[5], $target) : 'direct',
        'source_at' => $time->getTimestamp(),
        'fingerprint' => $view || $bot ? hash_hmac('sha256', $day."\0".inet_pton($parts[1])."\0".$agent, $key, true) : null,
    ];
}

function bp_traffic_report(PDO $db, string $day): ?array {
    $query = $db->prepare('SELECT * FROM bp_traffic_daily WHERE day=?');
    $query->execute([$day]);
    $report = $query->fetch();
    if (!$report) {
        return null;
    }

    $report['visitors'] = 0;
    $report['bot_clients'] = 0;
    $report['sources'] = array_fill_keys(array_keys(BP_TRAFFIC_SOURCES), 0);
    $query = $db->prepare('SELECT kind, source, COUNT(*) AS total FROM bp_traffic_visitors WHERE day=? GROUP BY kind, source');
    $query->execute([$day]);
    foreach ($query as $row) {
        $total = (int) $row['total'];
        if ($row['kind'] === 'b') {
            $report['bot_clients'] += $total;
        } else {
            $report['visitors'] += $total;
            $report['sources'][$row['source']] = $total;
        }
    }
    arsort($report['sources']);
    return $report;
}

const BP_TRAFFIC_METRICS = [
    'visitors' => 'Estimated unique visitors',
    'page_views' => 'Browser page views',
    'page_attempts' => 'Browser page requests',
    'failed_attempts' => 'Browser page errors',
    'bot_requests' => 'Detected bot requests',
    'bot_clients' => 'Estimated unique bot clients',
    'requests' => 'All logged requests',
];

function bp_traffic_digest(array $report): string {
    $lines = ['bkpandey.com traffic report for '.$report['day'].' (India time)', ''];
    foreach (BP_TRAFFIC_METRICS as $field => $label) {
        $lines[] = $label.': '.number_format((int) $report[$field]);
    }
    $lines[] = '';
    $lines[] = 'Estimated unique visitors by source';
    foreach ($report['sources'] as $source => $count) {
        $lines[] = BP_TRAFFIC_SOURCES[$source].': '.number_format($count);
    }
    $lines[] = '';
    $lines[] = 'Based on cPanel origin logs. Browser and bot counts are estimates.';
    $lines[] = 'https://bkpandey.com/admin?traffic=1&day='.$report['day'];
    return implode("\n", $lines);
}
