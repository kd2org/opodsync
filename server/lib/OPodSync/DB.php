<?php

namespace OPodSync;

class DB extends \SQLite3
{
	const VERSION = 20250125;

	protected $statements = [];

	static protected $instance;

	static public function getInstance(): self
	{
		if (!isset(self::$instance)) {
			self::$instance = new self(DB_FILE);
		}

		return self::$instance;
	}

	public function __construct(string $file)
	{
		$setup = !file_exists($file);

		parent::__construct($file);

		$mode = strtoupper(SQLITE_JOURNAL_MODE);
		$set_mode = $this->querySingle('PRAGMA journal_mode;');
		$set_mode = strtoupper($set_mode);

		if ($set_mode !== $mode) {
			// WAL = performance enhancement
			// see https://www.cs.utexas.edu/~jaya/slides/apsys17-sqlite-slides.pdf
			// https://ericdraken.com/sqlite-performance-testing/
			$this->exec(sprintf(
				'PRAGMA journal_mode = %s; PRAGMA synchronous = NORMAL; PRAGMA journal_size_limit = %d;',
				$mode,
				32 * 1024 * 1024
			));
		}

		if ($setup) {
			$this->install();
		}
		else {
			$this->migrate();
		}
	}

	public function install() {
		$this->exec(file_get_contents(ROOT . '/sql/schema.sql'));
		$this->simple(sprintf('PRAGMA user_version = %d;', self::VERSION));
	}

	public function migrate() {
		$v = $this->firstColumn('PRAGMA user_version;');

		if ($v < self::VERSION) {
			$list = glob(ROOT . '/sql/migration_*.sql');
			sort($list);

			foreach ($list as $file) {
				if (!preg_match('!/migration_(.*?)\.sql$!', $file, $match)) {
					continue;
				}

				$file_version = $match[1];

				if ($file_version && $file_version <= self::VERSION && $file_version > $v) {
					$this->exec(file_get_contents($file));
				}
			}

			// Destroy session, just to make sure that there is no bug between user data in session
			// and user data in DB
			$gpodder = new GPodder;
			$gpodder->logout();
		}

		$this->simple(sprintf('PRAGMA user_version = %d;', self::VERSION));
	}

	public function upsert(string $table, array $params, array $conflict_columns)
	{
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s;',
			$table,
			implode(', ', array_keys($params)),
			':' . implode(', :', array_keys($params)),
			implode(', ', $conflict_columns),
			implode(', ', array_map(fn($a) => $a . ' = :' . $a, array_keys($params)))
		);

		return $this->simple($sql, $params);
	}

	public function prepare2(string $sql, ...$params)
	{
		$hash = md5($sql);

		if (!array_key_exists($hash, $this->statements)) {
			$st = $this->statements[$hash] = $this->prepare($sql);
		}
		else {
			$st = $this->statements[$hash];
			$st->reset();
			$st->clear();
		}

		if (isset($params[0]) && is_array($params[0])) {
			$params = $params[0];
		}

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

	public function simple(string $sql, ...$params): ?\SQLite3Result
	{
		$res = $this->prepare2($sql, ...$params)->execute();

		if (is_bool($res)) {
			return null;
		}

		return $res;
	}

	public function firstRow(string $sql, ...$params): ?\stdClass
	{
		$row = $this->simple($sql, ...$params)->fetchArray(\SQLITE3_ASSOC);
		return $row ? (object) $row : null;
	}

	public function firstColumn(string $sql, ...$params)
	{
		return $this->simple($sql, ...$params)->fetchArray(\SQLITE3_NUM)[0] ?? null;
	}

	public function rowsFirstColumn(string $sql, ...$params): array
	{
		$res = $this->simple($sql, ...$params);
		$out = [];

		while ($row = $res->fetchArray(\SQLITE3_NUM)) {
			$out[] = $row[0];
		}

		$res->finalize();
		return $out;
	}

	public function iterate(string $sql, ...$params): \Generator
	{
		$res = $this->simple($sql, ...$params);

		while ($row = $res->fetchArray(\SQLITE3_ASSOC)) {
			yield (object) $row;
		}

		$res->finalize();
	}

	public function all(string $sql, ...$params): array
	{
		return iterator_to_array($this->iterate($sql, ...$params));
	}
}
