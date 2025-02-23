<?php

require_once __DIR__ .'/../../config/config.php';

function explode_or_empty(string $separator, string $string): array
{
	return empty($string) ? [] : explode($separator, $string);
}

class cm_db
{
	public PDO $connection;

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
	}

	public function translate_query(string $query): string
	{
		$dbtype = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
		if($dbtype === 'mysql')
		{
			return $query;
		}
		return str_replace('`', '"', $query);
	}

	public function query(string $query): PDOStatement
	{
		return $this->connection->query($this->translate_query($query));
	}

	public function prepare(string $query): PDOStatement
	{
		return $this->connection->prepare($this->translate_query($query));
	}

	public function execute(string $query, ?array $params = null): PDOStatement
	{
		$stmt = $this->prepare($query);
		$stmt->execute($params);
		return $stmt;
	}

	// The stuff calling this needs to be moved elsewhere, perhaps a separate database-init page.
	// We shouldn't try to create the tables *every time a page is loaded*!
	public function table_def(string $table, string $def): void
	{
		$this->query("CREATE TABLE IF NOT EXISTS `$table` ($def)");
	}

	public function table_is_empty(string $table): bool
	{
		return 0 === $this->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
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
