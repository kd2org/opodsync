<?php

namespace OPodSync;

$uri = strtok($_SERVER['REQUEST_URI'], '?');
strtok('');

// Stop here if we are using CLI server and the requested resource exists,
// it will be served by PHP HTTP server
if (PHP_SAPI === 'cli-server'
	&& file_exists(__DIR__ . $uri)
	&& !is_dir(__DIR__ . $uri)) {
	return false;
}

require_once __DIR__ . '/_inc.php';

// Try to handle API requests first
$api = new API;

try {
	if ($api->handleRequest()) {
		return;
	}
} catch (JsonException $e) {
	return;
}

if (PHP_SAPI === 'cli') {
	$gpodder->updateAllFeeds(true);
	exit(0);
}

if ($gpodder->user) {
	if (!empty($_POST['enable_token'])) {
		$gpodder->enableToken();
		header('Location: ./');
		exit;
	}
	elseif (!empty($_POST['disable_token'])) {
		$gpodder->disableToken();
		header('Location: ./');
		exit;
	}

	$tpl->assign('oktoken', isset($_GET['oktoken']));
	$tpl->assign('gpodder_token', $gpodder->getUserToken());
	$tpl->assign('subscriptions_count', $gpodder->countActiveSubscriptions());
	$tpl->display('index_logged.tpl');
}
else {
	$tpl->assign('canSubscribe', $gpodder->canSubscribe());
	$tpl->display('index.tpl');
}

