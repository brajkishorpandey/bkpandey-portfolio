<?php
declare(strict_types=1);
require_once __DIR__.'/traffic.php';

const BP_TRAFFIC_MAIL_LABELS = [
    'pending' => 'Pending',
    'attempted' => 'Unconfirmed',
    'queued' => 'Accepted by mail server',
    'failed' => 'Failed',
];

function bp_traffic_history_filters(array $input, DateTimeImmutable $today): array {
    $dates = [];
    foreach (['from', 'to'] as $key) {
        $raw = $input[$key] ?? '';
        $dates[$key] = bp_traffic_valid_day($raw, $today) ?? '';
        if ($raw !== '' && $dates[$key] === '') {
            throw new InvalidArgumentException('Choose valid dates up to today.');
        }
    }
    if ($dates['from'] !== '' && $dates['to'] !== '' && $dates['from'] > $dates['to']) {
        throw new InvalidArgumentException('The start date must be on or before the end date.');
    }
    $email = $input['email'] ?? '';
    $size = $input['size'] ?? '';
    $page = $input['page'] ?? '';
    return $dates + [
        'email' => is_string($email) && isset(BP_TRAFFIC_MAIL_LABELS[$email]) ? $email : '',
        'size' => in_array($size, ['10', '25', '50'], true) ? (int) $size : 10,
        'page' => is_string($page) && ctype_digit($page) ? max(1, (int) $page) : 1,
    ];
}

function bp_traffic_history_url(array $filters, int $page = 1, ?string $day = null): string {
    $query = ['traffic' => '1'];
    foreach (['from', 'to', 'email'] as $key) {
        if ($filters[$key] !== '') {
            $query[$key] = $filters[$key];
        }
    }
    if ($filters['size'] !== 10) {
        $query['size'] = $filters['size'];
    }
    if ($page > 1) {
        $query['page'] = $page;
    }
    if ($day !== null) {
        $query['day'] = $day;
    }
    return '/admin?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function bp_traffic_history(PDO $db, array $filters): array {
    $conditions = [];
    $values = [];
    foreach (['from' => 'd.day >= ?', 'to' => 'd.day <= ?', 'email' => 'd.mail_status = ?'] as $key => $condition) {
        if ($filters[$key] !== '') {
            $conditions[] = $condition;
            $values[] = $filters[$key];
        }
    }
    $where = $conditions ? ' WHERE '.implode(' AND ', $conditions) : '';
    $count = $db->prepare('SELECT COUNT(*) FROM bp_traffic_daily d'.$where);
    $count->execute($values);
    $total = (int) $count->fetchColumn();
    $size = $filters['size'];
    $pages = max(1, (int) ceil($total / $size));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $size;
    $query = $db->prepare(
        "SELECT d.day, d.page_views, d.bot_requests, d.mail_status,
            (SELECT COUNT(*) FROM bp_traffic_visitors v WHERE v.day=d.day AND v.kind='v') AS visitors
        FROM bp_traffic_daily d".$where.' ORDER BY d.day DESC LIMIT ? OFFSET ?'
    );
    foreach ($values as $index => $value) {
        $query->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $query->bindValue(count($values) + 1, $size, PDO::PARAM_INT);
    $query->bindValue(count($values) + 2, $offset, PDO::PARAM_INT);
    $query->execute();
    return ['rows' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'offset' => $offset];
}
