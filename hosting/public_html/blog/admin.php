<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR') ?: dirname(__DIR__,2).'/bkpandey-private';
$controller=$private.'/controllers/admin.php';
if(!is_file($controller)){http_response_code(404);exit;}
require $controller;
