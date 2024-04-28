<?php

class DB extends \SQLite3
{
	protected $statements = [];

	public function __construct(string $file)
	{
		$setup = !file_exists($file);

		parent::__construct($file);

		if ($setup) {
			$this->install();
		}
		else {
			$this->migrate();
		}
	}

	public function install() {
		$this->exec(file_get_contents(__DIR__ . '/schema.sql'));
	}

	public function migrate() {
		$v = $this->firstColumn('PRAGMA user_version;');

		if (!$v) {
			$this->exec(file_get_contents(__DIR__ . '/migration_20240428.sql'));
			$v = 20240428;
		}

		$this->simple(sprintf('PRAGMA user_version = %d;', $v));
	}

	public function upsert(string $table, array $params)
	{
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT DO UPDATE SET %s;',
			$table,
			implode(', ', array_keys($params)),
			':' . implode(', :', array_keys($params)),
			implode(', ', array_map(fn($a) => $a . ' = :' . $a, array_keys($params)))
		);

		return $this->simple($sql, ...$params);
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
