<?php

use KD2\HTTP;
use KD2\Test;

$data = json_decode(file_get_contents(__DIR__ . '/../episodes.json'), true);

$r = $http->POST('/api/2/episodes/demo.json', $data, HTTP::JSON);
Test::equals(200, $r->status, $r);

$r = $http->GET('/api/2/episodes/demo.json');
Test::equals(200, $r->status, $r);

$r = json_decode($r->body);
Test::assert(is_object($r));
Test::assert(isset($r->actions));
Test::assert(count($r->actions) === 2);

$db = new SQLite3($data_root . '/data.sqlite');
$res = $db->query('SELECT a.device, d.deviceid, a.user, d.user AS device_user
	FROM episodes_actions a
	LEFT JOIN devices d ON d.id = a.device
	ORDER BY a.id;');
$rows = [];

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
	$rows[] = $row;
}

Test::assert(count($rows) === 2);

foreach ($rows as $row) {
	Test::assert(!empty($row['device']));
	Test::equals('test-device', $row['deviceid']);
	Test::equals($row['user'], $row['device_user']);
}
