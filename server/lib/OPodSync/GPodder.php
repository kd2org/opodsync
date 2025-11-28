<?php

namespace OPodSync;

use stdClass;

class GPodder
{
	public ?stdClass $user = null;

	public function __construct()
	{
		session_name('sessionid');

		if (!empty($_POST['login']) || isset($_COOKIE[session_name()])) {
			if (isset($_GET['token']) && ctype_alnum($_GET['token'])) {
				session_id($_GET['token']);
			}

			@session_start();

			if (!empty($_SESSION['user'])) {
				$this->user = $_SESSION['user'];
			}
		}
	}

	public function login(): ?string
	{
		if (empty($_POST['login']) || empty($_POST['password'])) {
			return null;
		}

		$db = DB::getInstance();
		$user = $db->firstRow('SELECT * FROM users WHERE name = ?;', trim($_POST['login']));

		if (!$user || !password_verify(trim($_POST['password']), $user->password ?? '')) {
			return 'Invalid username/password';
		}

		$_SESSION['user'] = $this->user = $user;

		if (!empty($_GET['token'])) {
			$_SESSION['app_password'] = sprintf('%s:%s', $_GET['token'], sha1($user->password . $_GET['token']));
		}

		return null;
	}

	protected function refreshSession(): void
	{
		$_SESSION['user'] = $this->user = DB::getInstance()->firstRow('SELECT * FROM users WHERE id = ?;', $this->user->id);
	}

	public function isLogged(): bool
	{
		return !empty($_SESSION['user']);
	}

	public function logout(): void
	{
		@session_destroy();
	}

	public function enableToken(): void
	{
		$token = substr(sha1(random_bytes(16)), 0, 10);
		DB::getInstance()->simple('UPDATE users SET token = ? WHERE id = ?;', $token, $this->user->id);
		$this->refreshSession();
	}

	public function disableToken(): void
	{
		DB::getInstance()->simple('UPDATE users SET token = NULL WHERE id = ?;', $this->user->id);
		$this->refreshSession();
	}

	public function getUserToken(): ?string
	{
		if (null === $this->user->token) {
			return null;
		}

		return $this->user->name . '__' . $this->user->token;
	}

	public function validateToken(string $username): bool
	{
		$pos = strrpos($username, '__');

		if ($pos === false) {
			return false;
		}

		$login = substr($username, 0, $pos);
		$token = substr($username, $pos+2);

		$db = DB::getInstance();
		$this->user = $db->firstRow('SELECT * FROM users WHERE name = ? AND token = ?;', $login, $token);

		return $this->user !== null;
	}

	public function canSubscribe(): bool
	{
		if (ENABLE_SUBSCRIPTIONS) {
			return true;
		}

		$db = DB::getInstance();
		if (!$db->firstColumn('SELECT COUNT(*) FROM users;')) {
			return true;
		}

		return false;
	}

	public function subscribe(string $name, string $password): ?string
	{
		if (trim($name) === '' || !preg_match('/^\w[\w_-]+$/', $name)) {
			return 'Invalid username. Allowed is: \w[\w\d_-]+';
		}

		if ($name === 'current') {
			return 'This username is locked, please choose another one.';
		}

		$password = trim($password);
		$db = DB::getInstance();

		if (strlen($password) < 8) {
			return 'Password is too short';
		}

		if ($db->firstColumn('SELECT 1 FROM users WHERE name = ?;', $name)) {
			return 'Username already exists';
		}

		$db->simple('INSERT INTO users (name, password) VALUES (?, ?);', trim($name), password_hash($password, PASSWORD_DEFAULT));
		return null;
	}

	/**
	 * @throws Exception
	 */
	public function generateCaptcha(): string
	{
		$n = '';
		$c = '';

		for ($i = 0; $i < 4; $i++) {
			$j = random_int(0, 9);
			$c .= $j;
			$n .= sprintf('<b>%d</b><i>%d</i>', random_int(0, 9), $j);
		}

		$n .= sprintf('<input type="hidden" name="cc" value="%s" />', sha1($c . __DIR__));

		return $n;
	}

	public function checkCaptcha(string $captcha, string $check): bool
	{
		$captcha = trim($captcha);
		return sha1($captcha . __DIR__) === $check;
	}

	public function countActiveSubscriptions(): int
	{
		$db = DB::getInstance();
		return $db->firstColumn('SELECT COUNT(*) FROM subscriptions WHERE user = ? AND deleted = 0;', $this->user->id);
	}

	public function listActiveSubscriptions(): array
	{
		$db = DB::getInstance();
		return $db->all('SELECT s.*, COUNT(a.rowid) AS count, f.title, COALESCE(MAX(a.changed), s.changed) AS last_change
			FROM subscriptions s
				LEFT JOIN episodes_actions a ON a.subscription = s.id
				LEFT JOIN feeds f ON f.id = s.feed
			WHERE s.user = ? AND s.deleted = 0
			GROUP BY s.id
			ORDER BY last_change DESC;', $this->user->id);
	}

	public function listActions(int $subscription): array
	{
		$db = DB::getInstance();
		return $db->all('SELECT a.*,
				d.name AS device_name,
				e.title,
				e.url AS episode_url
			FROM episodes_actions a
				LEFT JOIN devices d ON d.id = a.device AND a.user = d.user
				LEFT JOIN episodes e ON e.id = a.episode
			WHERE a.user = ? AND a.subscription = ?
			ORDER BY changed DESC;', $this->user->id, $subscription);
	}

	public function updateFeedForSubscription(int $subscription): ?Feed
	{
		$db = DB::getInstance();
		$url = $db->firstColumn('SELECT url FROM subscriptions WHERE id = ?;', $subscription);

		if (!$url) {
			return null;
		}

		$feed = new Feed($url);

		if (!$feed->fetch()) {
			return null;
		}

		$feed->sync();

		return $feed;
	}

	public function getFeedForSubscription(int $subscription): ?Feed
	{
		$db = DB::getInstance();
		$data = $db->firstRow('SELECT f.*
			FROM subscriptions s INNER JOIN feeds f ON f.id = s.feed
			WHERE s.id = ?;', $subscription);

		if (!$data) {
			return null;
		}

		$feed = new Feed($data->feed_url);
		$feed->load($data);
		return $feed;
	}

	public function updateAllFeeds(bool $cli = false): void
	{
		$sql = 'SELECT s.id AS subscription, s.url, MAX(a.changed) AS changed
			FROM subscriptions s
				LEFT JOIN episodes_actions a ON a.subscription = s.id
				LEFT JOIN feeds f ON f.id = s.feed
			WHERE f.last_fetch IS NULL OR f.last_fetch < s.changed OR f.last_fetch < a.changed
			GROUP BY s.id';

		@ini_set('max_execution_time', 3600);
		@ob_end_flush();
		@ob_implicit_flush(true);
		$i = 0;

		$db = DB::getInstance();

		foreach ($db->iterate($sql) as $row) {
			@set_time_limit(30); // Extend running time;

			if ($cli) {
				printf("Updating %s\n", $row->url);
			}
			else {
				printf("<h4>Updating %s</h4>", $row->url);
				echo str_pad(' ', 4096);
				flush();
			}

			$this->updateFeedForSubscription($row->subscription);
			$i++;
		}

		if (!$i) {
			echo "Nothing to update\n";
		}
	}
}
