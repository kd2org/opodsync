<?php

namespace OPodSync;

use KD2\ErrorManager;
use KD2\Smartyer;

const ROOT = __DIR__;

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    require_once ROOT . '/lib/' . $class . '.php';
});

// Enable exception handler in dev mode before we load the config file
ErrorManager::enable(ErrorManager::DEVELOPMENT);

ErrorManager::setLogFile(ROOT . '/error.log');

class UserException extends \Exception {}

$cfg_file = (getenv('DATA_ROOT') ?: ROOT . '/data') . '/config.local.php';

if (file_exists($cfg_file)) {
	require $cfg_file;
}

$data_root = defined(__NAMESPACE__ . '\DATA_ROOT') ? constant(__NAMESPACE__ . '\DATA_ROOT') : (getenv('DATA_ROOT') ?: ROOT . '/data');

// Default configuration constants
$defaults = [
	'ENABLE_SUBSCRIPTIONS'         => false,
	'ENABLE_SUBSCRIPTION_CAPTCHA'  => true,
	'DISABLE_USER_METADATA_UPDATE' => false,
	'KARADAV_URL'                  => null,
	'DATA_ROOT'                    => $data_root,
	'CACHE_ROOT'                   => $data_root . '/cache',
	'DB_FILE'                      => $data_root . '/data.sqlite',
	'SQLITE_JOURNAL_MODE'          => 'TRUNCATE',
	'ERRORS_SHOW'                  => true,
	'ERRORS_EMAIL'                 => null,
	'ERRORS_LOG'                   => $data_root . '/error.log',
	'ERRORS_REPORT_URL'            => null,
	'TITLE'                        => 'My oPodSync server',
	'DEBUG_LOG'                    => null,
	'HTTP_SCHEME'                  => !empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http',
];

foreach ($defaults as $const => $value) {
	if (defined(__NAMESPACE__ . '\\' . $const)) {
		continue;
	}

	define(__NAMESPACE__ . '\\' . $const, getenv($const) ?: $value);
}

if (!defined(__NAMESPACE__ . '\BASE_URL')) {
	$name = $_SERVER['SERVER_NAME'];
	$port = !in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '';
	$root = '/';

	define(__NAMESPACE__ . '\BASE_URL', sprintf('%s://%s%s%s', HTTP_SCHEME, $name, $port, $root));
}

if (!ERRORS_SHOW) {
	ErrorManager::setEnvironment(ErrorManager::PRODUCTION);
}

if (ERRORS_EMAIL) {
	ErrorManager::setEmail(ERRORS_EMAIL);
}

if (ERRORS_LOG) {
	ErrorManager::setLogFile(ERRORS_LOG);
}
elseif (is_writeable(ROOT . 'data/error.log')) {
	ErrorManager::setLogFile(ROOT . 'data/error.log');
}

if (ERRORS_REPORT_URL) {
	ErrorManager::setRemoteReporting(ERRORS_REPORT_URL, true);
}

if (!is_dir(DATA_ROOT)) {
	if (!@mkdir(DATA_ROOT, fileperms(ROOT), true)) {
		throw new \RuntimeException('Unable to create directory, please create it and allow this program to write inside: ' . DATA_ROOT);
	}
}

// Fix issues with badly configured web servers
if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
	@list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
}

$gpodder = new GPodder;

$tpl = new Smartyer;
$tpl->setNamespace(__NAMESPACE__);
$tpl->setCompiledDir(CACHE_ROOT . '/templates');
$tpl->setTemplatesDir(ROOT . '/templates');
$tpl->assign('title', TITLE);
$tpl->assign('can_update_feeds', !DISABLE_USER_METADATA_UPDATE);
$tpl->assign('user', $gpodder->user);
$tpl->assign('url', BASE_URL);
$tpl->register_modifier('format_description', [Utils::class, 'format_description']);


ErrorManager::setCustomExceptionHandler(__NAMESPACE__. '\\UserException', function ($e) use ($tpl) {
	$tpl->assign('message', $e->getMessage());
	$tpl->display('error.tpl');
	exit;
});
