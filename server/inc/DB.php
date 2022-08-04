<?php

class DB extends \SQLite3
{
	public function __construct(string $file)
	{
		$setup = !file_exists($file);

		parent::__construct($file);

		if ($setup) {
			$this->install();
		}
	}

	public function install() {
		$this->exec(file_get_contents(__DIR__ . '/schema.sql'));
	}

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
