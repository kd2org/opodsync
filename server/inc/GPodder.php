<?php

class GPodder
{
	protected DB $db;
	public ?\stdClass $user = null;

	public function __construct(DB $db)
	{
		$this->db = $db;

		if (isset($_COOKIE[session_name()])) {
			@session_start($_POST['token'] ?? null);

			if (!empty($_SESSION['user'])) {
				$this->user = $_SESSION['user'];
			}
		}
	}

	public function auth(): ?string {
		if (empty($_POST['login']) || empty($_POST['password'])) {
			return null;
		}

		$user = $this->db->firstRow('SELECT * FROM users WHERE name = ?;', trim($_POST['login']));

		if (!password_verify(trim($_POST['password']), $user->password ?? '')) {
			return 'Invalid username/password';
		}

		@session_start($_POST['token'] ?? null);
		$_SESSION['user'] = $this->user = $user;

		if (!empty($_GET['token'])) {
			$_SESSION['app_password'] = sprintf('%s:%s', $_GET['token'], sha1($user->password . $_GET['token']));
		}

		return null;
	}


	public function canSubscribe(): bool {
		if (ENABLE_SUBSCRIPTIONS) {
			return true;
		}

		if (!$this->db->firstColumn('SELECT COUNT(*) FROM users;')) {
			return true;
		}

		return false;
	}

	public function subscribe(string $name, string $password): ?string {
		if (trim($name) === '' || !preg_match('/^\w[\w\d_-]+$/', $name)) {
			return 'Invalid username. Allowed is: \w[\w\d_-]+';
		}

		if ($name == 'current') {
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
		return sha1($captcha . __DIR__) == $check;
	}
}
