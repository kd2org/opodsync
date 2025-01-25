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

ErrorManager::setLogFile(ROOT . 'error.log');

class UserException extends \Exception {}

$cfg_file = (getenv('DATA_ROOT') ?: ROOT . '/data') . '/config.local.php';

if (file_exists($cfg_file)) {
	require $cfg_file;
}

// Default configuration constants
$defaults = [
	'ENABLE_SUBSCRIPTIONS'         => false,
	'DISABLE_USER_METADATA_UPDATE' => false,
	'DATA_ROOT'                    => getenv('DATA_ROOT') ?: ROOT . '/data',
	'CACHE_ROOT'                   => ROOT . '/data/cache',
	'DB_FILE'                      => ROOT . '/data/data.sqlite',
	'SQLITE_JOURNAL_MODE'          => 'TRUNCATE',
	'ERRORS_SHOW'                  => true,
	'ERRORS_EMAIL'                 => null,
	'ERRORS_LOG'                   => ROOT . '/data/error.log',
	'ERRORS_REPORT_URL'            => null,
	'TITLE'                        => 'My oPodSync server',
	'DEBUG_LOG'                    => null,
];

foreach ($defaults as $const => $value) {
	if (!defined(__NAMESPACE__ . '\\' . $const)) {
		define(__NAMESPACE__ . '\\' . $const, $value);
	}
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

// Fix issues with badly configured web servers
if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
	@list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
}

DB::getInstance();
$gpodder = new GPodder;

$tpl = new Smartyer;
$tpl->setCompiledDir(CACHE_ROOT . '/templates');
$tpl->setTemplatesDir(ROOT . '/templates');
$tpl->assign('title', TITLE);
$tpl->assign('can_update_feeds', !DISABLE_USER_METADATA_UPDATE);
$tpl->assign('user', $gpodder->user);
$tpl->register_modifier('format_description', [Utils::class, 'format_description']);


ErrorManager::setCustomExceptionHandler(__NAMESPACE__. '\\UserException', function ($e) use ($tpl) {
	$tpl->assign('message', $e->getMessage());
	$tpl->display('error.tpl');
	exit;
});
