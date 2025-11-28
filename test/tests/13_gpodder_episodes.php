<?php

use KD2\HTTP;
use KD2\Test;

$data = json_decode(file_get_contents(__DIR__ . '/../episodes.json'), true);

$r = $http->POST('/api/2/episodes/demo.json', $data, HTTP::JSON);
Test::equals(200, $r->status, $r);

$r = $http->GET('/api/2/episodes/demo.json');
Test::equals(200, $r->status, $r);

$r = json_decode($r);
Test::assert(is_object($r));
Test::assert(isset($r->actions));
Test::assert(count($r->actions) === 2);
