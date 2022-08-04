<?php

class GPodder
{
	protected DB $db;
	public ?string $user = null;
	public ?int $user_id = null;

	public function __construct(DB $db)
	{
		$this->db = $db;
	}

	public function auth(): ?string {
		if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
			return null;
		}

		$user = $this->db->firstRow('SELECT id, name, password FROM users WHERE name = ?;', $_SERVER['PHP_AUTH_USER']);

		if (!password_verify($_SERVER['PHP_AUTH_PW'], $user->password ?? '')) {
			return 'Invalid username/password';
		}

		$this->user = $user->name;
		$this->user_id = $user->id;
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
