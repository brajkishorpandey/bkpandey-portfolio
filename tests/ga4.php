<?php
declare(strict_types=1);
require __DIR__.'/../hosting/bkpandey-private/ga4.php';

function verify(bool $result, string $message): void {
    if (!$result) {
        throw new RuntimeException($message);
    }
}

verify(bp_ga4_measurement_id('G-123ABCDE') === 'G-123ABCDE', 'Web stream ID');
verify(bp_ga4_measurement_id('G-123ABCDE?x=1') === '', 'Reject an injected tag parameter');
verify(bp_ga4_property_id('307363654') === '307363654', 'Numeric property ID');
verify(bp_ga4_property_id('307363654/reports') === '', 'Reject an injected API path');

$config = tempnam(sys_get_temp_dir(), 'bp-ga4-test-');
try {
    file_put_contents($config, "[req]\ndistinguished_name = req_dn\n[req_dn]\n");
    $options = ['config' => $config, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
    $key = openssl_pkey_new($options);
    verify($key !== false && openssl_pkey_export($key, $private, null, $options), 'Test key');
} finally {
    unlink($config);
}
$now = 1700000000;
$assertion = bp_ga4_assertion([
    'type' => 'service_account',
    'client_email' => 'portfolio-reports@project-test.iam.gserviceaccount.com',
    'private_key' => $private,
], $now);
[$header, $payload, $signature] = explode('.', $assertion);
$decode = static fn (string $value) => base64_decode(strtr($value, '-_', '+/'), true);
$claims = json_decode($decode($payload), true, 8, JSON_THROW_ON_ERROR);
verify($claims['scope'] === 'https://www.googleapis.com/auth/analytics.readonly', 'Read-only scope');
verify($claims['aud'] === 'https://oauth2.googleapis.com/token', 'Fixed token endpoint');
verify($claims['exp'] - $claims['iat'] === 300, 'Short assertion expiration');
verify(openssl_verify($header.'.'.$payload, $decode($signature), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256) === 1, 'JWT signature');
echo "Google Analytics credential checks passed.\n";
