<?php
declare(strict_types=1);

const BP_SOCIAL_PLATFORMS = [
    'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'threads' => 'Threads',
];
const BP_SOCIAL_STATUS = [
    'queued' => 'Queued', 'sending' => 'Sending', 'submitted' => 'Accepted by Buffer',
    'failed' => 'Rejected', 'review' => 'Check Buffer', 'cancelled' => 'Cancelled',
];

function bp_social_config(): array {
    $path = __DIR__.'/social-buffer.json';
    $disabled = ['api_key' => '', 'channels' => []];
    if (!is_readable($path)) {
        return $disabled;
    }
    $raw = file_get_contents($path, false, null, 0, 16385);
    $config = is_string($raw) && strlen($raw) <= 16384 ? json_decode($raw, true, 12) : null;
    $key = $config['api_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[A-Za-z0-9._~-]{12,1024}$/D', $key)) {
        return $disabled;
    }
    $channels = [];
    foreach (BP_SOCIAL_PLATFORMS as $platform => $label) {
        $id = $config['channels'][$platform] ?? null;
        if (is_string($id) && preg_match('/^[A-Za-z0-9:_-]{3,100}$/D', $id) && !in_array($id, $channels, true)) {
            $channels[$platform] = $id;
        }
    }
    return ['api_key' => $key, 'channels' => $channels];
}

function bp_social_save_post(PDO $db, array $post, ?int $id, ?int $version, array $channels): int {
    if (($post['status'] ?? '') !== 'published' || !$channels) {
        return bp_save_post($db, $post, $id, $version);
    }

    $db->beginTransaction();
    try {
        $first = true;
        if ($id) {
            $query = $db->prepare('SELECT published_at FROM bp_blog_posts WHERE id=?');
            $query->execute([$id]);
            $first = $query->fetchColumn() === null;
        }
        $id = bp_save_post($db, $post, $id, $version);
        if ($first) {
            $enqueue = $db->prepare("INSERT INTO bp_social_jobs (post_id, platform, channel_id, run_after, updated_at)
                VALUES (?, ?, ?, ?, UTC_TIMESTAMP())");
            foreach ($channels as $platform => $channel) {
                $enqueue->execute([$id, $platform, $channel, gmdate('Y-m-d H:i:s', time() + 60)]);
            }
        }
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function bp_social_post_input(array $job, ?string $image = null): array {
    $platform = $job['platform'];
    $url = 'https://bkpandey.com/blog/'.$job['slug'].'.html?utm_source='.$platform
        .'&utm_medium=social&utm_campaign=article';
    $summary = trim(strip_tags($job['title']))."\n\n".trim(strip_tags($job['excerpt']));
    $limit = $platform === 'threads' ? 500 : 650;
    $budget = max(1, $limit - strlen($url) - 3);
    preg_match('/^.{0,'.$budget.'}/su', $summary, $part);
    $text = rtrim($part[0] ?? '');
    if ($text !== $summary) {
        $text .= '…';
    }
    $input = [
        'channelId' => $job['channel_id'], 'schedulingType' => 'automatic', 'mode' => 'shareNow',
        'text' => $text."\n\n".$url, 'assets' => [], 'needsApproval' => false,
    ];
    if ($platform === 'instagram') {
        $input['assets'] = [['image' => ['url' => $image ?? 'https://bkpandey.com/assets/images/social-card.png']]];
        $input['metadata'] = ['instagram' => ['type' => 'post']];
    }
    return $input;
}

function bp_social_result(int $http, ?array $response): array {
    if ($http === 429) {
        return ['retry', null];
    }
    $post = $response['data']['createPost']['post']['id'] ?? null;
    if ($http === 200 && is_string($post) && preg_match('/^[A-Za-z0-9:_-]{1,160}$/D', $post)) {
        return ['submitted', $post];
    }
    if (in_array($http, [400, 401, 403, 404, 422], true)
        || ($http === 200 && isset($response['data']['createPost']['message']) && empty($response['errors']))) {
        return ['failed', null];
    }
    // An ambiguous response may follow a successful remote mutation. Review it before sending again.
    return ['review', null];
}

function bp_social_send(string $key, array $input): array {
    $mutation = 'mutation Publish($input: CreatePostInput!) {
        createPost(input: $input) {
            ... on PostActionSuccess { post { id } }
            ... on MutationError { message }
        }
    }';
    $body = json_encode(['query' => $mutation, 'variables' => ['input' => $input]], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!function_exists('curl_init') || ($curl = curl_init('https://api.buffer.com')) === false) {
        return ['failed', null];
    }
    $raw = '';
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$key, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
            if (strlen($raw) + strlen($chunk) > 65536) {
                return 0;
            }
            $raw .= $chunk;
            return strlen($chunk);
        },
    ]);
    if (curl_exec($curl) === false) {
        return ['review', null];
    }
    $response = json_decode($raw, true, 24);
    return bp_social_result((int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), is_array($response) ? $response : null);
}
