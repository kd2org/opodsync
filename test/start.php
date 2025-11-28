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
	'DATA_ROOT=%s php -S %s -d variables_order=EGPCS -t %s %s &',
	escapeshellarg($data_root),
	escapeshellarg($server),
	escapeshellarg($root),
	escapeshellarg($root . '/index.php')
);

declare(ticks = 1);
$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$proc = proc_open($cmd, $descriptorspec, $pipes);
$proc_details = proc_get_status($proc);
$pid = $proc_details['pid'];

pcntl_signal(SIGINT, function() use ($proc) {
	proc_close($proc);
	exit;
});

echo $pid;
sleep(1);

$http = new HTTP;
$http->url_prefix = $url;
$http->http_options['timeout'] = 2;
$list = glob(__DIR__ . '/tests/*.php');
natcasesort($list);

foreach ($list as $file) {
	require $file;
}


function dom(string $html) {
	$doc = new HTMLDocument;
	$doc->loadHTML($html);
	return $doc;
}
