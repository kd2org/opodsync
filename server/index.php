<?php

require __DIR__ . '/inc/DB.php';
require __DIR__ . '/inc/API.php';
require __DIR__ . '/inc/GPodder.php';

error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		// Don't report this error (for example @unlink)
		return;
	}

	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
	echo '<pre style="background: #fdd; padding: 20px; border: 5px solid darkred; margin: 10px;">';
	echo $e;
	echo '</pre>';
	exit;
});

error_log(__DIR__ . '/../error.log');

if (file_exists(__DIR__ . '/config.local.php')) {
	require __DIR__ . '/config.local.php';
}

if (!defined('ENABLE_SUBSCRIPTIONS')) {
	define('ENABLE_SUBSCRIPTIONS', false);
}

$db = new DB(__DIR__ . '/data.sqlite');
$api = new API($db);

if ($api->handleRequest()) {
	return;
}

$gpodder = new GPodder($db);

$login_error = $gpodder->auth();

if (isset($_GET['login']) && !$gpodder->user) {
	http_response_code(401);
	header(sprintf('WWW-Authenticate: Basic realm="%s"', 'Please login'));
	exit;
}

echo '<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<title>GPodder</title>
</head>

<body>';

if ($gpodder->user) {
	printf('<h1>Logged in as %s</h1>', $gpodder->user);
}
else {
	echo '<p><a href="?login">Login</a></p>';

	if ($login_error) {
		printf('<p class="error">%s</p>', htmlspecialchars($login_error));
	}

	if ($gpodder->canSubscribe()) {
		if (!empty($_POST)) {
			if (!$gpodder->checkCaptcha($_POST['captcha'] ?? '', $_POST['cc'] ?? '')) {
				echo '<p class="error">Invalid captcha.</p>';
			}
			elseif ($error = $gpodder->subscribe($_POST['username'] ?? '', $_POST['password'] ?? '')) {
				printf('<p class="error">%s</p>', htmlspecialchars($error));
			}
			else {
				echo '<p class="success">Account has been created.</p>';
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
				<p><input type="submit" /></p>
			</fieldset>
		</form>';
	}
}

echo '
</body>
</html>';
