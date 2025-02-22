<?php

require_once __DIR__ .'/../../config/config.php';

function explode_or_empty(string $separator, string $string): array
{
	return empty($string) ? [] : explode($separator, $string);
}

class cm_db
{
	public PDO $connection;
	public array $known_tables; // This is effectively a set. Consider Ds\Set.

	public function __construct()
	{
		$config = $GLOBALS['cm_config']['database'];

		// Connect to database
		$host = $config['host'];
		$dbname = $config['database'];
		// The charset must be utf8mb4 for full Unicode® support
		$this->connection = new PDO(
			"mysql:host=$host;dbname=$dbname;charset=utf8mb4",
			$config['username'], $config['password']
		);

		// Set the time zone
		$this->connection->prepare('SET time_zone = ?')
			->execute([$config['timezone']]);

		// Create the set of known tables
		$stmt = $this->connection->prepare(
			'SELECT table_name FROM information_schema.tables WHERE table_schema = ?'
		);
		$stmt->execute([$dbname]);
		$db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$this->known_tables = array_fill_keys($db_tables, true);
	}

	public function table_def(string $table, string $def): void
	{
		if(!isset($this->known_tables[$table]))
		{
			$this->known_tables[$table] = true;
			$this->connection->query("CREATE TABLE IF NOT EXISTS \"$table\" ($def)");
		}
	}

	public function table_is_empty(string $table): bool
	{
		return 0 === $this->connection->query("SELECT COUNT(*) FROM $table")->fetchColumn();
	}

	public function now(): string
	{
		return $this->connection->query('SELECT NOW()')->fetchColumn();
	}

	public function uuid(): string
	{
		return $this->connection->query('SELECT UUID()')->fetchColumn();
	}

	public function curdatetime(): array
	{
		return $this->connection->query('SELECT CURDATE(), CURTIME()')->fetch(PDO::FETCH_NUM);
	}

	public function timezone(): array
	{
		return $this->connection->query('SELECT @@global.time_zone, @@session.time_zone')
			->fetch(PDO::FETCH_NUM);
	}

	public function characterset(): array
	{
		return $this->connection->query('SHOW VARIABLES LIKE \'character\\_set\\_%\'')
			->fetchAll(PDO::FETCH_KEY_PAIR);
	}
}
