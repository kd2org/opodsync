<?php

namespace OPodSync;

use stdClass;

class API
{
	protected ?string $method;
	protected ?stdClass $user;
	protected ?string $section;
	public ?string $url;
	public ?string $base_url;
	public ?string $base_path;
	protected ?string $path;
	protected ?string $format = null;
	protected string $log = '';

	public function __construct()
	{
		$url = defined(__NAMESPACE__ . '\\BASE_URL') ? BASE_URL : null;
		$url ??= getenv('BASE_URL', true) ?: null;

		if (!$url) {
			if (!isset($_SERVER['SERVER_PORT'], $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT'])) {
				echo "Unable to auto-detect application URL, please set BASE_URL constant or environment variable.\n";
				exit(1);
			}

			$url = 'http';

			if (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] === 443) {
				$url .= 's';
			}

			$url .= '://' . $_SERVER['SERVER_NAME'];

			if (!in_array($_SERVER['SERVER_PORT'], [80, 443])) {
				$url .= ':' . $_SERVER['SERVER_PORT'];
			}

			$path = substr(dirname($_SERVER['SCRIPT_FILENAME']), strlen($_SERVER['DOCUMENT_ROOT']));
			$path = trim($path, '/');
			$url .= $path ? '/' . $path . '/' : '/';
		}

		$this->base_path = parse_url($url, PHP_URL_PATH) ?? '';
		$this->base_url = $url;
	}

	public function url(string $path = ''): string
	{
		return $this->base_url . $path;
	}

	public function debug(string $message, ...$params): void
	{
		if (!DEBUG_LOG) {
			return;
		}

		$this->log .= date('Y-m-d H:i:s ') . vsprintf($message, $params) . PHP_EOL;
	}

	public function __destruct()
	{
		if (!DEBUG_LOG || $this->log === '') {
			return;
		}

		file_put_contents(DEBUG_LOG, $this->log, FILE_APPEND);
	}

	public function queryWithData(string $sql, ...$params): array {
		$db = DB::getInstance();
		$result = $db->iterate($sql, ...$params);
		$out = [];

		foreach ($result as $row) {
			$row = array_merge(json_decode($row->data, true, 512, JSON_THROW_ON_ERROR), (array) $row);
			unset($row['data']);
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * @throws JsonException
	 */
	public function error(int $code, string $message): void {
		$this->debug('RETURN: %d - %s', $code, $message);

		http_response_code($code);
		header('Content-Type: application/json', true);
		echo json_encode(compact('code', 'message'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
		exit;
	}

	/**
	 * @throws JsonException
	 */
	public function requireMethod(string $method): void {
		if ($method !== $this->method) {
			$this->error(405, 'Invalid HTTP method: ' . $this->method);
		}
	}

	/**
	 * @throws JsonException
	 */
	public function validateURL(string $url): void {
		if (!preg_match('!^https?://[^/]+!', $url)) {
			$this->error(400, 'Invalid URL: ' . $url);
		}
	}

	/**
	 * @throws JsonException
	 */
	public function getInput()
	{
		if ($this->format === 'txt') {
			return array_filter(file('php://input'), 'trim');
		}

		$input = file_get_contents('php://input');
		return json_decode($input, false, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @see https://gpoddernet.readthedocs.io/en/latest/api/reference/auth.html
	 * @throws JsonException
	 */
	public function handleAuth(): void
	{
		$this->requireMethod('POST');

		strtok($this->path, '/');
		$action = strtok('');

		if ($action === 'logout') {
			$_SESSION = [];
			@session_destroy();
			$this->error(200, 'Logged out');
		}
		elseif ($action !== 'login') {
			$this->error(404, 'Unknown login action: ' . $action);
		}

		if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
			$this->error(401, 'No username or password provided');
		}

		$this->requireAuth();

		$this->error(200, 'Logged in!');
	}

	public function login()
	{
		$login = $_SERVER['PHP_AUTH_USER'];
		list($login) = explode('__', $login, 2);

		$db = DB::getInstance();
		$user = $db->firstRow('SELECT id, password FROM users WHERE name = ?;', $login);

		if(!$user) {
			$this->error(401, 'Invalid username');
		}

		if (!password_verify($_SERVER['PHP_AUTH_PW'], $user->password ?? '')) {
			$this->error(401, 'Invalid username/password');
		}

		$this->debug('Logged user: %s', $login);

		@session_start();
		$_SESSION['user'] = $user;
	}

	/**
	 * @throws JsonException
	 */
	public function requireAuth(?string $username = null): void
	{
		if (isset($this->user)) {
			return;
		}

		// For gPodder desktop
		if ($username && false !== strpos($username, '__')) {
			$gpodder = new GPodder;
			if (!$gpodder->validateToken($username)) {
				$this->error(401, 'Invalid gpodder token');
			}

			$this->user = $gpodder->user;
			return;
		}

		if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
			$this->login();
			$this->user = $_SESSION['user'];
			return;
		}

		if (empty($_COOKIE['sessionid'])) {
			$this->error(401, 'session cookie is required');
		}

		@session_start();

		if (empty($_SESSION['user'])) {
			$this->error(401, 'Expired sessionid cookie, and no Authorization header was provided');
		}

		$db = DB::getInstance();

		if (!$db->firstColumn('SELECT 1 FROM users WHERE id = ?;', $_SESSION['user']->id)) {
			$this->error(401, 'User does not exist');
		}

		$this->user = $_SESSION['user'];
		$this->debug('Cookie user ID: %s', $this->user->id);
	}

	/**
	 * @throws JsonException
	 */
	public function route()
	{
		switch ($this->section) {
			// Not implemented
			case 'tag':
			case 'tags':
			case 'data':
			case 'toplist':
			case 'suggestions':
			case 'favorites':
				return [];
			case 'devices':
				return $this->devices();
			case 'updates':
				return $this->updates();
			case 'subscriptions':
				return $this->subscriptions();
			case 'episodes':
				return $this->episodes();
			case 'settings':
			case 'lists':
			case 'sync-device':
				$this->error(503, 'Not implemented');
			default:
				return null;
		}
	}

	/**
	 * Map NextCloud endpoints to GPodder
	 * @see https://github.com/thrillfall/nextcloud-gpodder
	 * @throws JsonException
	 */
	public function handleNextCloud(): ?array
	{
		if ($this->url === 'index.php/login/v2') {
			$this->requireMethod('POST');

			$id = sha1(random_bytes(16));

			return [
				'poll' => [
					'token' => $id,
					'endpoint' => $this->url('index.php/login/v2/poll'),
				],
				'login' => $this->url('login.php?token=' . $id),
			];
		}

		if ($this->url === 'index.php/login/v2/poll') {
			$this->requireMethod('POST');

			if (empty($_POST['token']) || !ctype_alnum($_POST['token'])) {
				$this->error(400, 'Invalid token');
			}

			session_id($_POST['token']);
			session_start();

			if (empty($_SESSION['user']) || empty($_SESSION['app_password'])) {
				$this->error(404, 'Not logged in yet, using token: ' . $_POST['token']);
			}

			return [
				'server' => $this->url(),
				'loginName' => $_SESSION['user']->name,
				'appPassword' => $_SESSION['app_password'], // FIXME provide a real app-password here
			];
		}

		$nextcloud_path = 'index.php/apps/gpoddersync/';

		if (0 !== strpos($this->url, $nextcloud_path)) {
			return null;
		}

		if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
			$this->error(401, 'No username or password provided');
		}

		$this->debug('Nextcloud compatibility: %s / %s', $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		$db = DB::getInstance();
		$user = $db->firstRow('SELECT id, password FROM users WHERE name = ?;', $_SERVER['PHP_AUTH_USER']);

		if (!$user) {
			$this->error(401, 'Invalid username');
		}

		// FIXME store a real app password instead of this hack
		$token = strtok($_SERVER['PHP_AUTH_PW'], ':');
		$password = strtok('');
		$app_password = sha1($user->password . $token);

		if ($app_password !== $password) {
			$this->error(401, 'Invalid username/password');
		}

		$this->user = $_SESSION['user'] = $user;

		$path = substr($this->url, strlen($nextcloud_path));

		if ($path === 'subscriptions') {
			$this->url = 'api/2/subscriptions/current/default.json';
		}
		elseif ($path === 'subscription_change/create') {
			$this->url = 'api/2/subscriptions/current/default.json';
		}
		elseif ($path === 'episode_action' || $path === 'episode_action/create') {
			$this->url = 'api/2/episodes/current.json';
		}
		else {
			$this->error(404, 'Undefined Nextcloud API endpoint');
		}

		return null;
	}

	/**
	 * @throws JsonException
	 */
	public function handleRequest(): void
	{
		$this->method = $_SERVER['REQUEST_METHOD'] ?? null;
		$url = '/' . trim($_SERVER['REQUEST_URI'] ?? '', '/');
		$url = substr($url, strlen($this->base_path));
		$this->url = strtok($url, '?');

		$this->debug('Got a %s request on %s', $this->method, $this->url);

		$return = $this->handleNextCloud();

		if ($return) {
			echo json_encode($return, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
			exit;
		}

		if (!preg_match('!^(suggestions|subscriptions|toplist|api/2/(auth|subscriptions|devices|updates|episodes|favorites|settings|lists|sync-devices|tags?|data))/!', $this->url, $match)) {
			return;
		}

		$this->section = $match[2] ?? $match[1];
		$this->path = substr($this->url, strlen($match[0]));
		$username = null;

		if (preg_match('/\.(json|opml|txt|jsonp|xml)$/', $this->url, $match)) {
			$this->format = $match[1];
			$this->path = substr($this->path, 0, -strlen($match[0]));
		}

		if (!in_array($this->format, ['json', 'opml', 'txt'])) {
			$this->error(501, 'output format is not implemented');
		}

		// For gPodder
		if (preg_match('!(\w+__\w{10})!i', $this->path, $match)) {
			$username = $match[1];
		}

		if ($this->section === 'auth') {
			$this->handleAuth();
			return;
		}

		$this->requireAuth($username);

		$return = $this->route();

		$this->debug("RETURN:\n%s", json_encode($return, JSON_PRETTY_PRINT));

		if ($this->format === 'opml') {
			if ($this->section !== 'subscriptions') {
				$this->error(501, 'output format is not implemented');
			}

			header('Content-Type: text/x-opml; charset=utf-8');
			echo $this->opml($return);
		}
		else {
			header('Content-Type: application/json');

			if ($return !== null) {
				echo json_encode($return, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
			}
		}

		exit;
	}

	/**
	 * @throws JsonException
	 */
	public function devices(): array
	{
		if ($this->method === 'GET') {
			return $this->queryWithData('SELECT deviceid as id, user, deviceid, name, data FROM devices WHERE user = ?;', $this->user->id);
		}

		if ($this->method === 'POST') {
			$deviceid = explode('/', $this->path)[1] ?? null;

			if (!$deviceid || !preg_match('/^[\w.-]+$/', $deviceid)) {
				$this->error(400, 'Invalid device ID');
			}

			$json = $this->getInput();
			$json ??= [];
			$json->subscriptions = 0;

			$params = [
				'deviceid' => $deviceid,
				'data'     => json_encode($json),
				'name'     => $json->caption ?? null,
				'user'     => $this->user->id,
			];

			$db = DB::getInstance();
			$db->upsert('devices', $params, ['deviceid', 'user']);
			$this->error(200, 'Device updated');
		}
		$this->error(400, 'Wrong request method');
		exit;
	}

	/**
	 * @throws JsonException
	 */
	public function subscriptions()
	{
		$db = DB::getInstance();
		$v2 = strpos($this->url, 'api/2/') !== false;

		// We don't care about deviceid yet (FIXME)
		$deviceid = explode('/', $this->path)[1] ?? null;

		if ($this->method === 'GET' && !$v2) {
			return $db->all('SELECT s.url AS feed, f.title, f.url AS website, f.description
				FROM subscriptions s
				LEFT JOIN feeds f ON f.id = s.feed
				WHERE s.user = ?;', $this->user->id);
		}

		if (!$deviceid || !preg_match('/^[\w.-]+$/', $deviceid)) {
			$this->error(400, 'Invalid device ID');
		}

		// Get Subscription Changes
		if ($v2 && $this->method === 'GET') {
			$timestamp = (int)($_GET['since'] ?? 0);

			return [
				'add' => $db->rowsFirstColumn('SELECT url FROM subscriptions WHERE user = ? AND deleted = 0 AND changed >= ?;', $this->user->id, $timestamp),
				'remove' => $db->rowsFirstColumn('SELECT url FROM subscriptions WHERE user = ? AND deleted = 1 AND changed >= ?;', $this->user->id, $timestamp),
				'update_urls' => [],
				'timestamp' => time(),
			];
		}

		if ($this->method === 'PUT') {
			$lines = $this->getInput();

			if (!is_array($lines)) {
				$this->error(400, 'Invalid input: requires an array with one line per feed');
			}

			$db->exec('BEGIN;');
			$st = $db->prepare('INSERT OR IGNORE INTO subscriptions (user, url, changed) VALUES (:user, :url, strftime(\'%s\', \'now\'));');

			foreach ($lines as $url) {
				$this->validateURL($url);

				$st->bindValue(':url', $url);
				$st->bindValue(':user', $this->user->id);
				$st->execute();
				$st->reset();
				$st->clear();
			}

			$db->exec('END;');
			return null;
		}

		if ($this->method === 'POST') {
			$input = $this->getInput();

			$db->exec('BEGIN;');

			$ts = time();

			if (!empty($input->add) && is_array($input->add)) {
				foreach ($input->add as $url) {
					$this->validateURL($url);

					$db->upsert('subscriptions', [
						'user'    => $this->user->id,
						'url'     => $url,
						'changed' => $ts,
						'deleted' => 0,
					], ['user', 'url']);
				}
			}

			if (!empty($input->remove) && is_array($input->remove)) {
				foreach ($input->remove as $url) {
					$this->validateURL($url);

					$db->upsert('subscriptions', [
						'user'    => $this->user->id,
						'url'     => $url,
						'changed' => $ts,
						'deleted' => 1,
					], ['user', 'url']);
				}
			}

			$db->exec('END;');
			return ['timestamp' => $ts, 'update_urls' => []];
		}

		$this->error(501, 'Not implemented yet');
		exit;
	}

	/**
	 * @throws JsonException
	 */
	public function updates(): mixed
	{
		$this->error(501, 'Not implemented yet');
		exit;
	}

	/**
	 * @throws JsonException
	 */
	public function episodes(): array
	{
		if ($this->method === 'GET') {
			$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

			return [
				'timestamp' => time(),
				'actions' => $this->queryWithData('SELECT e.url AS episode, e.action, e.data, s.url AS podcast,
					strftime(\'%Y-%m-%dT%H:%M:%SZ\', e.changed, \'unixepoch\') AS timestamp
					FROM episodes_actions e
					INNER JOIN subscriptions s ON s.id = e.subscription
					WHERE e.user = ? AND e.changed >= ?;', $this->user->id, $since)
			];
		}

		$this->requireMethod('POST');

		$input = $this->getInput();

		if (!is_array($input)) {
			$this->error(400, 'No valid array found');
		}

		$db = DB::getInstance();
		$db->exec('BEGIN;');

		$timestamp = time();
		$st = $db->prepare('INSERT INTO episodes_actions (user, subscription, url, changed, action, data) VALUES (:user, :subscription, :url, :changed, :action, :data);');

		foreach ($input as $action) {
			if (!isset($action->podcast, $action->action, $action->episode)) {
				$this->error(400, 'Missing required key in action');
			}

			$this->validateURL($action->podcast);
			$this->validateURL($action->episode);

			$id = $db->firstColumn('SELECT id FROM subscriptions WHERE url = ? AND user = ?;', $action->podcast, $this->user->id);

			if (!$id) {
				$db->simple('INSERT OR IGNORE INTO subscriptions (user, url, changed) VALUES (?, ?, ?);', $this->user->id, $action->podcast, $timestamp);
				$id = $db->lastInsertRowID();
			}

			if (!empty($action->timestamp)) {
				$changed = new \DateTime($action->timestamp, new \DateTimeZone('UTC'));
				$changed = $changed->getTimestamp();
			}
			else {
				$changed = null;
			}

			$st->bindValue(':user', $this->user->id);
			$st->bindValue(':subscription', $id);
			$st->bindValue(':url', $action->episode);
			$st->bindValue(':changed', $changed ?? $timestamp);
			$st->bindValue(':action', strtolower($action->action));
			unset($action->action, $action->episode, $action->podcast);
			$st->bindValue(':data', json_encode($action, JSON_THROW_ON_ERROR));
			$st->execute();
			$st->reset();
			$st->clear();
		}

		$db->exec('END;');

		return compact('timestamp') + ['update_urls' => []];
	}

	public function opml(array $data): string
	{
		$out = '<?xml version="1.0" encoding="utf-8"?>';
		$out .= PHP_EOL . '<opml version="1.0"><head><title>My Feeds</title></head><body>';

		foreach ($data as $row) {
			$url = $row->website ?? $row->feed;
			$out .= PHP_EOL . sprintf('<outline type="rss" xmlUrl="%s" title="%s" text="%2$s" htmlUrl="%s" description="%s" />',
					htmlspecialchars($row->feed, ENT_XML1 | ENT_QUOTES),
					htmlspecialchars($row->title ?? $url, ENT_XML1 | ENT_QUOTES),
					htmlspecialchars($url, ENT_XML1 | ENT_QUOTES),
					htmlspecialchars($row->description ?? '', ENT_XML1 | ENT_QUOTES),
				);
		}

		$out .= PHP_EOL . '</body></opml>';
		return $out;
	}
}
