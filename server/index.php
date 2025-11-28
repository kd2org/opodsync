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

try {
	// Try to handle API requests first
	$api = new API;
	$uri = $api->getRequestURI();

	if ($api->handleRequest($uri)) {
		return;
	}
}
catch (APIException $e) {
	$api->error($e);
	return;
}

if (PHP_SAPI === 'cli') {
	$gpodder->updateAllFeeds(true);
	exit(0);
}

$uri = trim($uri, '/');

// Return 404 is URI is invalid
if (!in_array($uri, ['', 'index.php'], true)) {
	http_response_code(404);
	echo '<h1>404 Not Found</h1>';
	exit;
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
	$tpl->assign('can_subscribe', $gpodder->canSubscribe());
	$tpl->display('index.tpl');
}

