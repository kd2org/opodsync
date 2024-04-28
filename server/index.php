<?php

require_once __DIR__ . '/inc/DB.php';
require_once __DIR__ . '/inc/API.php';
require_once __DIR__ . '/inc/GPodder.php';
require_once __DIR__ . '/inc/Feed.php';

error_reporting(E_ALL);
$backtrace = null;

if (PHP_SAPI === 'cli-server' && file_exists(__DIR__ . $_SERVER['REQUEST_URI']) && !is_dir(__DIR__ . $_SERVER['REQUEST_URI'])) {
	return false;
}

set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		// Don't report this error (for example @unlink)
		return;
	}

	global $backtrace;
	$backtrace = debug_backtrace();

	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
	@http_response_code(500);
	error_log((string)$e);
	echo '<pre style="background: #fdd; padding: 20px; border: 5px solid darkred; position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: auto; white-space: pre-wrap;"><h1>Internal error</h1>';

	error_log((string) $e);

	if (DEBUG) {
		echo $e;

		global $backtrace;
		$backtrace ??= debug_backtrace();

		echo '<hr style="margin: 30px 0; border: none; border-top: 5px solid darkred; background: none;" />';
		print_r($backtrace);
	}
	else {
		echo 'An error happened and has been logged to data/error.log<br />Enable DEBUG constant to see errors directly.';
	}

	echo '</pre>';
	exit;
});

define('DATA_ROOT', getenv('DATA_ROOT') ?: __DIR__ . '/data');

if (!file_exists(DATA_ROOT)) {
	mkdir(DATA_ROOT);
}

ini_set('error_log', DATA_ROOT . '/error.log');

if (file_exists(DATA_ROOT . '/config.local.php')) {
	require DATA_ROOT . '/config.local.php';
}

if (!defined('ENABLE_SUBSCRIPTIONS')) {
	define('ENABLE_SUBSCRIPTIONS', false);
}

if (!defined('DEBUG')) {
	define('DEBUG', null);
}

$db = new DB(DATA_ROOT . '/data.sqlite');
$api = new API($db);

try {
	if ($api->handleRequest()) {
		return;
	}
} catch (JsonException $e) {
	return;
}

$gpodder = new GPodder($db);

if (PHP_SAPI === 'cli') {
	$gpodder->updateAllFeeds(true);
	exit(0);
}

function html_head() {
	$title = defined('TITLE') ? TITLE : 'My micro podcast server';

	echo '<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, target-densitydpi=device-dpi" />
		<link rel="stylesheet" type="text/css" href="style.css" />
		<title>' . htmlspecialchars($title) . '</title>
		<link rel="icon" href="icon.svg" />
	</head>

	<body>
	<h1>' . htmlspecialchars($title) . ' <img src="icon.svg" alt="" /></h1>
	<main>';
}

function html_foot() {
	echo '
	</main>
	</body>
	</html>';
}

function format_description(string $str): string {
	$str = str_replace('</p>', "\n\n", $str);
	$str = preg_replace_callback('!<a[^>]*href=(".*?"|\'.*?\'|\S+)[^>]*>(.*?)</a>!i', function ($match) {
		$url = trim($match[1], '"\'');
		if ($url === $match[2]) {
			return $match[1];
		}
		else {
			return '[' . $match[2] . '](' . $url . ')';
		}
	}, $str);
	$str = htmlspecialchars(strip_tags($str));
	$str = preg_replace("!(?:\r?\n){3,}!", "\n\n", $str);
	$str = preg_replace('!\[([^\]]+)\]\(([^\)]+)\)!', '<a href="$2">$1</a>', $str);
	$str = preg_replace(';(?<!")https?://[^<\s]+(?!");', '<a href="$0">$0</a>', $str);
	$str = nl2br($str);
	return $str;
}

if ($api->url === 'logout') {
	$gpodder->logout();
	header('Location: ./');
	exit;
}
elseif ($gpodder->user && $api->url === 'subscriptions') {
	html_head();

	if (isset($_POST['update'])) {
		echo '<p class="center"><a href="./subscriptions" class="btn sm" aria-label="Go Back">&larr; Back</a></p>';
		$gpodder->updateAllFeeds();
		exit;
	}
	elseif (isset($_GET['id'])) {
		echo '<form method="post" action=""><p class="center">
			<a href="./subscriptions" class="btn sm" aria-label="Go Back">&larr; Back</a>
			<button type="submit" class="btn sm" name="fetch" value=1>Update feed metadata</button>
		</p></form>';

		if (!empty($_POST['fetch'])) {
			$feed = $gpodder->updateFeedForSubscription((int)$_GET['id']);
		}
		else {
			$feed = $gpodder->getFeedForSubscription((int)$_GET['id']);
		}

		if (!$feed) {
			echo '<p>No information is available on this feed.</p>';
		}
		else {
			printf('<article class="feed"><h2><a href="%s">%s</a></h2><p>%s</p></article>',
				htmlspecialchars($feed->url),
				htmlspecialchars($feed->title),
				format_description($feed->description)
			);

			echo '<p class="help">Note: episodes titles might be missing because of trackers/ads used by some podcast providers.</p>';
		}

		echo '<table><thead><tr><th scope="col">Action</th><th scope="col">Device</th><th scope="col">Date</th><th scope="col">Episode</td></tr></thead><tbody>';

		foreach ($gpodder->listActions((int)$_GET['id']) as $row) {
			$url = strtok(basename($row->url), '?');
			strtok('');
			$title = $row->title ?? $url;
			printf('<tr><th scope="row">%s</th><td>%s</td><td><time datetime="%s">%s</time></td><td><a href="%s">%s</a></td></tr>',
				htmlspecialchars($row->action),
				htmlspecialchars($row->device_name ?? '?'),
				date(DATE_ISO8601, $row->changed),
				date('d/m/Y H:i', $row->changed),
				htmlspecialchars($row->url),
				htmlspecialchars($title),
			);
		}
	}
	else {
		printf('<form method="post" action=""><p class="center">
			<a href="./" class="btn sm" aria-label="Go Back">&larr; Back</a>
			<a href="./subscriptions/%s.opml" class="btn sm">OPML</a>
			<button type="submit" class="btn sm" name="update" value=1>Update all feeds metadata</button>
		</p></form>',
			htmlspecialchars($gpodder->user->name)
		);

		echo '<table><thead><tr><th scope="col">Podcast URL</th><th scope="col">Last change</th><th scope="col">Actions</th></tr></thead><tbody>';

		foreach ($gpodder->listActiveSubscriptions() as $row) {
			$title = $row->title ?? str_replace(['http://', 'https://'], '', $row->url);
			printf('<tr><th scope="row"><a href="?id=%d">%s</a></th><td><time datetime="%s">%s</time></td><td>%d</td></tr>',
				$row->id,
				htmlspecialchars($title),
				date(DATE_ISO8601, $row->changed),
				date('d/m/Y H:i', $row->changed),
				$row->count
			);
		}
	}

	echo '</tbody></table>';
	html_foot();
}
elseif ($gpodder->user) {
	html_head();

	if (isset($_GET['oktoken'])) {
		echo '<p class="success center">You are logged in, you can close this and go back to the app.</p>';
	}

	echo '<p class="center"><img src="icon.svg" width="150" alt="" /></p>';
	printf('<h2 class="center">Logged in as %s</h2>', $gpodder->user->name);
	printf('<h3 class="center">GPodder secret username: %s</h2>', $gpodder->getUserToken());
	echo '<p class="center"><small>(Use this username in GPodder desktop, as it does not support passwords.)</small></p>';
	printf('<p class="center">You have %d active subscriptions.</p><p class="center"><a href="subscriptions" class="btn sm">List my subscriptions</a></p>', $gpodder->countActiveSubscriptions());

	echo '<p class="center"><a href="logout" class="btn sm">Logout</a></p>';
	html_foot();
}
elseif ($api->url === 'login') {
	$error = $gpodder->login();

	if ($gpodder->isLogged()) {
		$token = isset($_GET['token']) ? '?oktoken' : '';
		header('Location: ./' . $token);
		exit;
	}

	html_head();

	if ($error) {
		printf('<p class="error center">%s</p>', htmlspecialchars($error));
	}

	if (isset($_GET['token'])) {
		printf('<p class="center">An app is asking to access your account.</p>');
	}

	echo '
	<form method="post" action="">
		<fieldset>
			<legend>Please login</legend>
			<dl>
				<dt><label for="login">Login</label></dt>
				<dd><input type="text" required name="login" id="login" /></dd>
				<dt><label for="password">Password</label></dt>
				<dd><input type="password" required name="password" id="password" /></dd>
			</dl>
			<p><button type="submit" class="btn">Login <svg aria-hidden="true" width="40" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#ffdd67" r="30"/><g fill="#664e27"><circle cx="20.5" cy="26.6" r="5"/><circle cx="43.5" cy="26.6" r="5"/><path d="m44.6 40.3c-8.1 5.7-17.1 5.6-25.2 0-1-.7-1.8.5-1.2 1.6 2.5 4 7.4 7.7 13.8 7.7s11.3-3.6 13.8-7.7c.6-1.1-.2-2.3-1.2-1.6"/></g></svg></button></p>
		</fieldset>
	</form>';
	html_foot();
}
elseif ($api->url === 'register' && !$gpodder->canSubscribe()) {
	html_head();
	echo '<p class="center">Subscriptions are disabled.</p>';
	html_foot();
}
elseif ($api->url === 'register') {
	html_head();

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
				<dt><label for="username">Username</label></dt>
				<dd><input type="text" name="username" required id="username" /></dd>
				<dt><label for="password">Password (minimum 8 characters)</label></dt>
				<dd><input type="password" minlength="8" required name="password" id="password" /></dd>
				<dt>Captcha</dt>
				<dd class="ca"><label for="captcha">Please enter this number: '.$gpodder->generateCaptcha().'</label></dd>
				<dd><input type="text" name="captcha" required id="captcha" /></dd>
			</dl>
			<p><button type="submit" class="btn">Create account <svg aria-hidden="true" width="40px" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#4bd37b" r="30"/><path d="m46 14-21 21.6-7-7.2-7 7.2 14 14.4 28-28.8z" fill="#fff"/></svg></button></p>
		</fieldset>
	</form>';

	html_foot();
}
else {
	html_head();

	echo '<p class="center" aria-hidden="true"><img src="icon.svg" width="150" /></p>';
	echo '<p class="center">
		<a href="login" class="btn">Login</a>
		<a href="register" class="btn">Create account</a>
	</p>';

	html_foot();
}
