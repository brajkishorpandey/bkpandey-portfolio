<?php
declare(strict_types=1);
$private=getenv('BP_PRIVATE_DIR')?:dirname(__DIR__,2).'/bkpandey-private';
require $private.'/controllers/contact.php';
