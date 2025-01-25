<?php

namespace OPodSync;

require_once __DIR__ . '/_inc.php';

if (!$gpodder->user) {
	header('Location: ./login.php');
	exit;
}

$subscriptions = $gpodder->listActiveSubscriptions();

$tpl->assign(compact('subscriptions'));
$tpl->display('subscriptions.tpl');
