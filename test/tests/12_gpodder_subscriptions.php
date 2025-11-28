<?php

use KD2\HTTP;
use KD2\Test;

$fp = fopen('php://temp', 'r+');
fwrite($fp, 'invalid');
fseek($fp, 0);

$r = $http->PUT('/subscriptions/demo/test-device.opml', $fp);
Test::equals(501, $r->status, $r);

$r = $http->PUT('/subscriptions/demo/test-device.json', $fp);
Test::equals(400, $r->status, $r);
fclose($fp);

$r = $http->PUT('/subscriptions/demo/test-device.json', __DIR__ . '/../subscriptions.json');
Test::equals(200, $r->status, $r);

$r = $http->GET('/subscriptions/demo/test-device.opml');

Test::equals(200, $r->status, $r);
Test::assert(str_contains($r->body, 'xmlUrl="https://april.org/lav.xml"'));

