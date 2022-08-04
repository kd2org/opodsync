<?php

class DB extends \SQLite3
{
	public function prepare2(string $sql, ...$params)
	{
		$st = $this->prepare($sql);

		foreach ($params as $key => $value) {
			if (is_int($key)) {
				$st->bindValue($key + 1, $value);
			}
			else {
				$st->bindValue(':' . $key, $value);
			}
		}

		return $st;
	}

	public function simple(string $sql, ...$params): \SQLite3Result
	{
		return $this->prepare2($sql, ...$params)->execute();
	}

	public function firstRow(string $sql, ...$params): ?\stdClass
	{
		$row = $this->simple($sql, ...$params)->fetchArray(\SQLITE3_ASSOC);
		return $row ? (object) $row : null;
	}

	public function firstColumn(string $sql, ...$params)
	{
		return $this->simple($sql, ...$params)->fetchArray(\SQLITE3_NUM)[0] ?: null;
	}

	public function iterate(string $sql, ...$params): \Generator
	{
		$res = $this->simple($sql, ...$params)->fetchArray(\SQLITE3_ASSOC);

		while ($row = $this->simple($sql, ...$params)->fetchArray(\SQLITE3_ASSOC)) {
			yield (object) $row;
		}

		$res->finalize();
	}
}

class GPodder
{
	protected ?string $method;
	protected ?int $user;
	protected ?string $section;
	protected ?string $url;
	protected DB $db;

	public function __construct()
	{
		$setup = !file_exists(DB_FILE);
		session_name('sessionid');

		$this->db = new DB(DB_FILE);

		if ($setup) {
			$this->install();
		}
	}

	public function install() {
		$this->db->exec('
			CREATE TABLE users (
				id INTEGER NOT NULL PRIMARY KEY,
				name TEXT NOT NULL,
				password TEXT NOT NULL
			);

			CREATE UNIQUE INDEX users_name ON users (name);

			CREATE TABLE devices (
				id INTEGER NOT NULL PRIMARY KEY,
				user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
				deviceid TEXT NOT NULL
				data TEXT
			);

			CREATE UNIQUE INDEX deviceid ON devices (deviceid);

			CREATE TABLE subscriptions (
				id INTEGER NOT NULL PRIMARY KEY,
				user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
				url TEXT NOT NULL,
				deleted INTEGER NOT NULL DEFAULT 0,
				changed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				data TEXT
			);

			CREATE UNIQUE INDEX subscription_url ON subscriptions (url);

			CREATE TABLE episodes_actions (
				id INTEGER NOT NULL PRIMARY KEY,
				user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
				subscription INTEGER NOT NULL REFERENCES subscriptions (id) ON DELETE CASCADE,
				url TEXT NOT NULL,
				changed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				action TEXT NOT NULL,
				data TEXT
			);

			CREATE INDEX episodes_idx ON episodes_actions (user, , action, changed);
		');
	}

	public function queryWithData(string $sql, ...$params) {
		$result = $this->db->iterate($sql, ...$params);

		foreach ($result as &$row) {
			$row = json_decode($row->data);
		}

		unset($row);

		return $result;
	}

	public function error(int $code, string $message) {
		http_response_code($code);
		echo json_encode(compact('code', 'message'));
		exit;
	}

	public function requireMethod(string $method) {
		if ($method != $this->method) {
			$this->error(400, 'Invalid HTTP method');
		}
	}

	/**
	 * https://gpoddernet.readthedocs.io/en/latest/api/reference/auth.html
	 */
	public function handleAuth(): void
	{
		if (isset($_COOKIE['sessionid'])) {
			@session_start();

			if (!empty($_SESSION['user'])) {
				$this->error(400, 'Invalid sessionid cookie');
			}

			if (!$this->db->firstColumn('SELECT 1 FROM users WHERE name = ?;', $_SESSION['user'])) {
				$this->error(400, 'User does not exist');
			}

			$this->user = $_SESSION['user'];
			return;
		}

		$this->requireMethod('POST');

		$username = strtok($this->url, '/');
		$action = strtok('');

		if ($action == 'logout.json') {
			$_SESSION = [];
			session_destroy();
			$this->error(200, 'Logged out');
		}
		elseif ($action != 'login.json') {
			$this->error(404, 'Unknown login action');
		}

		if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
			$this->error(401, 'No username or password provided');
		}

		if ($username != $_SERVER['PHP_AUTH_USER']) {
			$this->error(400, 'Specified username in Auth Basic is different than username specified in URL');
		}

		$user = $this->db->firstColumn('SELECT id, password FROM users WHERE name = ?;', $username);

		if (!password_verify($_SERVER['PHP_AUTH_PW'], $user->password ?? '')) {
			$this->error(401, 'Invalid username/password');
		}

		$_SESSION['user'] = $user->id;
		$this->error(200, 'Logged in!');
	}

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
		}
	}

	public function handleRequest()
	{
		$this->method = $_SERVER['METHOD'] ?? null;
		$this->url = strtok(trim($_SERVER['REQUEST_URI'] ?? ''), '?');

		if (!preg_match('!^(suggestions|subscriptions|toplist|api/2/(auth|subscriptions|devices|updates|episodes|favorites|settings|lists|sync-devices|tags?|data))/!', $this->url, $match)) {
			$this->error(404, 'Unknown API action');
		}

		$this->section = $match[2] ?: $match[1];
		$this->path = substr($this->url, strlen($match[0]) + 1);

		$this->handleAuth();

		$return = $this->route();
		echo json_encode($return);
		exit;
	}

	public function devices()
	{
		if ($this->method == 'GET') {
			return $this->queryWithData('SELECT * FROM devices WHERE user = ?;', $this->user);
		}
		elseif ($this->method == 'POST') {
			$deviceid = explode('/', $this->path)[1] ?? null;

			if (!$deviceid || !preg_match('/^[\w.-]+$/', $deviceid)) {
				$this->error(400, 'Invalid device ID');
			}

			$this->db->simple('REPLACE INTO devices (user, deviceid) VALUES (?, ?);', $this->user, $deviceid);
			$this->db->simple('UPDATE devices SET data = json_patch(data, ?);', json_encode($_POST));
			$this->error(200, 'Device updated');
		}

		$this->error(400, 'Wrong request method');
	}

	public function subscriptions()
	{
		if ($this->method == 'GET') {
			// We don't care about deviceid yet (FIXME)
			return $this->queryWithData('SELECT * FROM subscriptions WHERE user = ?;', $this->user);
		}

		$deviceid = explode('/', $this->path)[1] ?? null;

		if (!$deviceid || !preg_match('/^[\w.-]+$/', $deviceid)) {
			$this->error(400, 'Invalid device ID');
		}


	}

	public function updates()
	{

	}

	public function episodes()
	{
	}
}

$g = new GPodder;
$g->handleRequest();
