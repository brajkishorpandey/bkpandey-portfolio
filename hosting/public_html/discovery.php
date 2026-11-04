<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__).'/bkpandey-private';
$discoveryMode=($_GET['mode']??'')==='feed'?'feed':'sitemap';
require $private.'/controllers/discovery.php';
