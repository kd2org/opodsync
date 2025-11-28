<?php

namespace OPodSync;

require_once __DIR__ . '/_inc.php';

if (!$gpodder->canSubscribe()) {
	throw new UserException('Subscriptions are disabled.');
}

$error = null;

if (!empty($_POST)) {
	if (!$gpodder->checkCaptcha($_POST['captcha'] ?? '', $_POST['cc'] ?? '')) {
		$error = 'Invalid captcha';
	}
	else {
		$error = $gpodder->subscribe($_POST['login'] ?? '', $_POST['password'] ?? '');

		if (!$error) {
			$gpodder->login();
			header('Location: ./');
			exit;
		}
	}
}

$captcha = $gpodder->generateCaptcha();
$tpl->assign(compact('error', 'captcha'));
$tpl->display('register.tpl');
