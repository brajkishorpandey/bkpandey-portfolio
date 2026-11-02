<?php
declare(strict_types=1);
require dirname(__DIR__).'/core.php';bp_security_headers();
header('Content-Type: application/json; charset=utf-8');
try{$json=json_encode(bp_public_site_values(),JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);if(!bp_etag($json))echo $json;}
catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"Temporarily unavailable"}';}
