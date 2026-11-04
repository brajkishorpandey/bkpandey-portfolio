<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__,2).'/bkpandey-private';
$discoveryMode='feed';require $private.'/controllers/discovery.php';
