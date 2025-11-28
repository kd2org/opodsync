<?php

use KD2\Test;

$r = $http->GET('/');

Test::equals(200, $r->status);

Test::assert(str_contains($r->body, 'href="register.php"'), 'no link to register page for fresh install');
