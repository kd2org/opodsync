<?php

use KD2\HTTP;
use KD2\Test;

$r = $http->GET('/api/2/devices/demo.json');

Test::equals(200, $r->status, $r);
Test::assert(json_decode($r->body, true) === []);

$r = $http->POST('/api/2/devices/demo/test-device.json', ['caption' => 'My device', 'type' => 'mobile'], HTTP::JSON);

Test::equals(200, $r->status, $r);

$r = $http->GET('/api/2/devices/demo.json');

Test::equals(200, $r->status, $r);
$r = json_decode($r->body);
Test::assert(is_array($r));
$r = $r[0];
Test::assert(is_object($r));
Test::assert($r->type === 'mobile');
Test::assert($r->caption === 'My device');

