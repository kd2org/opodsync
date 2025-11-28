<?php

use KD2\Test;
use KD2\HTTP;

// reset HTTP state (eg. cookies)
$http = new HTTP;
$http->url_prefix = $url;
$http->http_options['timeout'] = 2;

// Make sure we can't login with a wrong password
$r = $http->POST('/api/2/auth/demo/login.json', [], HTTP::FORM, ['Authorization' => 'Basic ' . base64_encode('demo:falsepassword')]);

Test::equals(401, $r->status, $r);

$r = $http->POST('/api/2/auth/demo/login.json', [], HTTP::FORM, ['Authorization' => 'Basic ' . base64_encode('demo:demodemo')]);

Test::equals(200, $r->status, $r);
Test::assert(count($r->cookies) === 1);
Test::assert(!empty($r->cookies['sessionid']));
