<?php
declare(strict_types=1);

const BP_GA4_METRICS = [
    'activeUsers' => 'Active users',
    'sessions' => 'Sessions',
    'engagedSessions' => 'Engaged sessions',
    'screenPageViews' => 'Page views',
];

function bp_ga4_measurement_id(mixed $value): string {
    return is_string($value) && preg_match('/^G-[A-Z0-9]{4,20}$/D', $value) ? $value : '';
}

function bp_ga4_property_id(mixed $value): string {
    return is_string($value) && preg_match('/^[1-9][0-9]{4,19}$/D', $value) ? $value : '';
}

function bp_ga4_credentials_path(): string {
    return __DIR__.'/ga4-credentials.json';
}

function bp_ga4_base64url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function bp_ga4_assertion(array $credentials, int $now): string {
    $email = $credentials['client_email'] ?? '';
    $key = $credentials['private_key'] ?? '';
    if (($credentials['type'] ?? '') !== 'service_account' || !is_string($email)
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '.iam.gserviceaccount.com')
        || !is_string($key) || !str_starts_with($key, '-----BEGIN PRIVATE KEY-----')) {
        throw new RuntimeException('Install a valid Google service account credential.');
    }
    $claims = [
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 300,
    ];
    $unsigned = bp_ga4_base64url('{"alg":"RS256","typ":"JWT"}').'.'.bp_ga4_base64url(json_encode($claims, JSON_THROW_ON_ERROR));
    if (!@openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('The Google credential could not sign the reporting request.');
    }
    return $unsigned.'.'.bp_ga4_base64url($signature);
}

function bp_ga4_request(string $url, string $body, array $headers): array {
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Google Analytics could not be reached.');
    }
    $raw = '';
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
            if (strlen($raw) + strlen($chunk) > 65536) {
                return 0;
            }
            $raw .= $chunk;
            return strlen($chunk);
        },
    ]);
    if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
        throw new RuntimeException('Google Analytics rejected or could not complete the request.');
    }
    $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Google Analytics returned an invalid response.');
    }
    return $data;
}

function bp_ga4_report(string $property, string $day): array {
    if (bp_ga4_property_id($property) === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) {
        throw new InvalidArgumentException('Choose a valid Google Analytics property and date.');
    }
    $path = bp_ga4_credentials_path();
    if (!is_readable($path) || !is_int($size = filesize($path)) || $size > 16384) {
        throw new RuntimeException('The private Google Analytics credential is missing or invalid.');
    }
    // Only the report is cached. OAuth tokens and the key stay out of caches and public responses.
    return bp_cached('ga4:v1:'.$property.':'.$day.':'.hash_file('sha256', $path), function () use ($path, $property, $day): array {
        $credentials = json_decode((string) file_get_contents($path), true, 12, JSON_THROW_ON_ERROR);
        $assertion = bp_ga4_assertion(is_array($credentials) ? $credentials : [], time());
        $token = bp_ga4_request('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]), ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'])['access_token'] ?? '';
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9._~-]{1,4096}$/D', $token)) {
            throw new RuntimeException('Google Analytics authorization failed.');
        }

        $date = [['startDate' => $day, 'endDate' => $day]];
        // Limit results to this website even if its property has additional streams.
        $hostFilter = ['filter' => ['fieldName' => 'hostName', 'stringFilter' => [
            'matchType' => 'FULL_REGEXP', 'value' => '^(www\\.)?bkpandey\\.com$', 'caseSensitive' => false,
        ]]];
        $result = bp_ga4_request('https://analyticsdata.googleapis.com/v1beta/properties/'.$property.':batchRunReports',
            json_encode(['requests' => [
                ['dateRanges' => $date, 'dimensionFilter' => $hostFilter,
                    'metrics' => array_map(static fn ($name) => ['name' => $name], array_keys(BP_GA4_METRICS))],
                ['dateRanges' => $date, 'dimensionFilter' => $hostFilter,
                    'dimensions' => [['name' => 'sessionSourceMedium']], 'metrics' => [['name' => 'sessions']],
                    'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => '10'],
            ]], JSON_THROW_ON_ERROR),
            ['Authorization: Bearer '.$token, 'Content-Type: application/json', 'Accept: application/json']);
        $totals = $result['reports'][0] ?? [];
        $metrics = [];
        foreach (array_keys(BP_GA4_METRICS) as $index => $field) {
            $metrics[$field] = max(0, (int) ($totals['rows'][0]['metricValues'][$index]['value'] ?? 0));
        }
        $sources = [];
        foreach ($result['reports'][1]['rows'] ?? [] as $row) {
            $sources[] = [
                'name' => (string) ($row['dimensionValues'][0]['value'] ?? '(not set)'),
                'sessions' => max(0, (int) ($row['metricValues'][0]['value'] ?? 0)),
            ];
        }
        return ['metrics' => $metrics, 'sources' => $sources, 'has_rows' => !empty($totals['rows']),
            'timezone' => (string) ($totals['metadata']['timeZone'] ?? ''), 'fetched_at' => bp_now()];
    }, 3600);
}
