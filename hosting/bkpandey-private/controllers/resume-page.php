<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__);$public=dirname($private).'/public_html';
require $private.'/core.php';bp_security_headers();
try{$html=bp_cached('html:resume:v4',function()use($public){$v=bp_public_site_values();return bp_apply_seo_html(bp_apply_site_html(file_get_contents($public.'/resume.html'),$v),$v,'/resume.html');});if(!bp_etag($html))echo $html;}
catch(Throwable $e){readfile($public.'/resume.html');}
