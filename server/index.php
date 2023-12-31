<?php

require_once __DIR__ . '/inc/DB.php';
require_once __DIR__ . '/inc/API.php';
require_once __DIR__ . '/inc/GPodder.php';

error_reporting(E_ALL);

if (PHP_SAPI === 'cli-server' && file_exists(__DIR__ . $_SERVER['REQUEST_URI']) && !is_dir(__DIR__ . $_SERVER['REQUEST_URI'])) {
	return false;
}

set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		// Don't report this error (for example @unlink)
		return;
	}

	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
	http_response_code(500);
	error_log((string)$e);
	echo '<pre style="background: #fdd; padding: 20px; border: 5px solid darkred; margin: 10px;"><h2>Internal error</h2>';
	echo $e;
	echo '</pre>';
	exit;
});

ini_set('error_log', __DIR__ . '/../error.log');

if (file_exists(__DIR__ . '/config.local.php')) {
	require __DIR__ . '/config.local.php';
}

if (!defined('ENABLE_SUBSCRIPTIONS')) {
	define('ENABLE_SUBSCRIPTIONS', false);
}

if (!defined('DEBUG')) {
	define('DEBUG', null);
}

$db = new DB(__DIR__ . '/data.sqlite');
$api = new API($db);

try {
	if ($api->handleRequest()) {
		return;
	}
} catch (JsonException $e) {
	return;
}

$gpodder = new GPodder($db);

echo '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<link rel="stylesheet" type="text/css" href="style.css" />
	<title>GPodder</title>
</head>

<body>
<h1>GPodder micro</h1>
<main>';

if ($api->url === 'login') {
	if ($error = $gpodder->auth()) {
		printf('<p class="error">%s</p>', htmlspecialchars($error));
	}

	if ($gpodder->user) {
		printf('<h1 class="center">Logged in as %s</h1>', $gpodder->user->name);
		printf('<p class="center"><a href="subscriptions/%s.opml" class="btn sm">Subscriptions (OPML)</a></p>', $gpodder->user->name);
	}
	else {
		echo '
		<form method="post" action="">
			<fieldset>
				<legend>Please login</legend>
				<dl>
					<dt>Login</dt>
					<dd><input type="text" required name="login" /></dd>
					<dt>Password</dt>
					<dd><input type="password" required name="password" /></dd>
				</dl>
				<p><button type="submit" class="btn">Login <svg width="40" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#ffdd67" r="30"/><g fill="#664e27"><circle cx="20.5" cy="26.6" r="5"/><circle cx="43.5" cy="26.6" r="5"/><path d="m44.6 40.3c-8.1 5.7-17.1 5.6-25.2 0-1-.7-1.8.5-1.2 1.6 2.5 4 7.4 7.7 13.8 7.7s11.3-3.6 13.8-7.7c.6-1.1-.2-2.3-1.2-1.6"/></g></svg></button></p>
			</fieldset>
		</form>';
	}
}
elseif ($api->url === 'register' && !$gpodder->canSubscribe()) {
	echo '<p class="center">Subscriptions are disabled.</p>';
}
elseif ($api->url === 'register') {
	if (!empty($_POST)) {
		if (!$gpodder->checkCaptcha($_POST['captcha'] ?? '', $_POST['cc'] ?? '')) {
			echo '<p class="error center">Invalid captcha.</p>';
		}
		elseif ($error = $gpodder->subscribe($_POST['username'] ?? '', $_POST['password'] ?? '')) {
			printf('<p class="error center">%s</p>', htmlspecialchars($error));
		}
		else {
			echo '<p class="success">Your account is registered.</p>';
			echo '<p class=""><a href="login" class="btn sm">Login</a></p>';
		}
	}

	echo '
	<form method="post" action="">
		<fieldset>
			<legend>Create an account</legend>
			<dl>
				<dt>Username</dt>
				<dd><input type="text" name="username" required /></dd>
				<dt>Password (minimum 8 characters)</dt>
				<dd><input type="password" minlength="8" required name="password" /></dd>
				<dt>Captcha</dt>
				<dd class="ca">Please enter this number: '.$gpodder->generateCaptcha().'</dd>
				<dd><input type="text" name="captcha" required /></dd>
			</dl>
			<p><button type="submit" class="btn">Create account <svg width="40px" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#4bd37b" r="30"/><path d="m46 14-21 21.6-7-7.2-7 7.2 14 14.4 28-28.8z" fill="#fff"/></svg></button></p>
		</fieldset>
	</form>';
}
else {
	echo '<p class="center">
		<a href="login" class="btn">Login</a>
		<a href="register" class="btn">Create account</a>
	</p>';
}

echo '
</main>
</body>
</html>';
