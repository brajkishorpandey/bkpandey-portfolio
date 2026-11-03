<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__);$public=dirname($private).'/public_html';
require $private.'/core.php';bp_security_headers();
try{$html=bp_cached('html:home:v8',fn()=>bp_apply_site_html(file_get_contents($public.'/index.html'),bp_public_site_values()));if(!bp_etag($html))echo $html;}
catch(Throwable $e){readfile($public.'/index.html');}
