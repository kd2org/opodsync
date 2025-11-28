<?php

use KD2\Test;
use KD2\HTTP;
use KD2\HTMLDocument;

require '../../kd2fw/src/lib/KD2/Test.php';
require '../../kd2fw/src/lib/KD2/HTTP.php';
require '../../kd2fw/src/lib/KD2/HTMLDocument.php';

$server = 'localhost:8099';
$url = 'http://' . $server;

$data_root = __DIR__ . '/data';

if (file_exists($data_root)) {
	passthru('rm -rf ' . escapeshellarg($data_root));
}

mkdir($data_root);
//file_put_contents($data_root . '/config.local.php', '<?php namespace OPodSync;');

$root = realpath(__DIR__ . '/../server');

$cmd = sprintf(
	'DATA_ROOT=%s php -S %s -d variables_order=EGPCS -t %s %s > /dev/null 2>&1 & echo $!',
	escapeshellarg($data_root),
	escapeshellarg($server),
	escapeshellarg($root),
	escapeshellarg($root . '/index.php')
);

$pid = shell_exec($cmd);

sleep(1);

declare(ticks = 1);

pcntl_signal(SIGINT, function() use ($pid) {
	shell_exec('kill ' . $pid);
	exit;
});

$http = new HTTP;
$http->url_prefix = $url;
$http->http_options['timeout'] = 2;
$list = glob(__DIR__ . '/tests/*.php');
natcasesort($list);

try {
	foreach ($list as $file) {
		require $file;
	}
}
finally {
	shell_exec('kill ' . $pid);
}

function dom(string $html) {
	$doc = new HTMLDocument;
	$doc->loadHTML($html);
	return $doc;
}
