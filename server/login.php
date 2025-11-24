<?php

namespace OPodSync;

require_once __DIR__ . '/_inc.php';

if (isset($_GET['logout'])) {
	$gpodder->logout();
	header('Location: ./');
	exit;
}

$token = isset($_GET['token']) ? '?oktoken' : '';
$error = $gpodder->login();

if ($gpodder->isLogged()) {
	header('Location: ./' . $token);
	exit;
}

if ($error) {
	http_response_code(401);
}

$token = isset($_GET['token']) ? true : false;

$tpl->assign(compact('error', 'token'));
$tpl->display('login.tpl');
