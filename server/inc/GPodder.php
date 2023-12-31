<?php

class GPodder
{
	protected DB $db;
	public ?\stdClass $user = null;

	public function __construct(DB $db)
	{
		$this->db = $db;

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

		$user = $this->db->firstRow('SELECT * FROM users WHERE name = ?;', trim($_POST['login']));

		if (!$user || !password_verify(trim($_POST['password']), $user->password ?? '')) {
			return 'Invalid username/password';
		}

		$_SESSION['user'] = $this->user = $user;

		if (!empty($_GET['token'])) {
			$_SESSION['app_password'] = sprintf('%s:%s', $_GET['token'], sha1($user->password . $_GET['token']));
		}

		return null;
	}

	public function isLogged(): bool
	{
		return !empty($_SESSION['user']);
	}

	public function logout(): void
	{
		session_destroy();
	}

	public function getUserToken(): string
	{
		return $this->user->name . '__' . substr(sha1($this->user->password), 0, 10);
	}

	public function validateToken(string $username): bool
	{
		$login = strtok($username, '__');
		$token = strtok('');

		$this->user = $this->db->firstRow('SELECT * FROM users WHERE name = ?;', $login);

		if (!$this->user) {
			return false;
		}

		return $username === $this->getUserToken();
	}

	public function canSubscribe(): bool
	{
		if (ENABLE_SUBSCRIPTIONS) {
			return true;
		}

		if (!$this->db->firstColumn('SELECT COUNT(*) FROM users;')) {
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

		if (strlen($password) < 8) {
			return 'Password is too short';
		}

		if ($this->db->firstColumn('SELECT 1 FROM users WHERE name = ?;', $name)) {
			return 'Username already exists';
		}

		$this->db->simple('INSERT INTO users (name, password) VALUES (?, ?);', trim($name), password_hash($password, null));
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
		return $this->db->firstColumn('SELECT COUNT(*) FROM subscriptions WHERE user = ? AND deleted = 0;', $this->user->id);
	}

	public function listActiveSubscriptions(): array
	{
		return $this->db->all('SELECT s.*, COUNT(*) AS count
			FROM subscriptions s LEFT JOIN episodes_actions a ON a.subscription = s.id
			WHERE s.user = ? AND s.deleted = 0
			GROUP BY s.id ORDER BY s.changed DESC;', $this->user->id);
	}

	public function listActions(int $subscription): array
	{
		return $this->db->all('SELECT *, json_extract(data, \'$.device\') AS device,
			json_extract(data, \'$.timestamp\') AS timestamp
			FROM episodes_actions
			WHERE user = ? AND subscription = ?
			ORDER BY changed DESC;', $this->user->id, $subscription);
	}
}
