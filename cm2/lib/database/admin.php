<?php

require_once __DIR__ .'/../../config/config.php';
require_once __DIR__ .'/database.php';

class cm_admin_db {

	public cm_db $cm_db;

	public function __construct(cm_db $cm_db) {
		$this->cm_db = $cm_db;
		$this->cm_db->table_def('admin_users', (
			'`name` VARCHAR(255) NOT NULL,'.
			'`username` VARCHAR(255) NOT NULL PRIMARY KEY,'.
			'`password` VARCHAR(255) NOT NULL,'.
			'`active` BOOLEAN NOT NULL,'.
			'`permissions` TEXT NOT NULL'
		));
		$this->cm_db->table_def('admin_access_log', (
			'`timestamp` DATETIME NOT NULL,'.
			'`username` VARCHAR(255) NOT NULL,'.
			'`remote_addr` VARCHAR(255) NOT NULL,'.
			'`remote_host` VARCHAR(255) NOT NULL,'.
			'`request_method` VARCHAR(255) NOT NULL,'.
			'`request_uri` VARCHAR(255) NOT NULL,'.
			'`http_referer` VARCHAR(255) NOT NULL,'.
			'`http_user_agent` VARCHAR(255) NOT NULL'
		));
		if ($this->cm_db->table_is_empty('admin_users')) {
			$config = $GLOBALS['cm_config']['default_admin'];
			if ($config['name'] && $config['username'] && $config['password']) {
				$password = password_hash($config['password'], PASSWORD_DEFAULT);
				$active = 1;
				$permissions = '*';
				$this->cm_db->execute(
					'INSERT INTO `admin_users`'
					.' (`name`, `username`, `password`, `active`, `permissions`)'
					.' VALUES (?, ?, ?, ?, ?)'
					, [
						$config['name'],
						$config['username'],
						$password,
						$active,
						$permissions,
					]
				);
			}
		}
	}

	public function logged_in_user() {
		$username = isset($_SESSION['admin_username']);
		$password = isset($_SESSION['admin_password']);
		if (!$username || !$password) return false;
		$username = $_SESSION['admin_username'];
		$password = $_SESSION['admin_password'];
		if (!$username || !$password) return false;
		$stmt = $this->cm_db->execute(
			'SELECT `name`, `username`, `password`, `permissions`'
			.' FROM `admin_users`'
			.' WHERE `username` = ? AND `active`'
			, [$username]
		);

		$row = $stmt->fetch(PDO::FETCH_NUM);
		if ($row === false) {
			return false;
		}
		list($name, $username, $hash, $permissions) = $row;
		if (!password_verify($password, $hash)) {
			return false;
		}
		return [
			'name' => $name,
			'username' => $username,
			'permissions' => explode(',', $permissions)
		];
	}

	public function log_in($username, $password) {
		$_SESSION['admin_username'] = $username;
		$_SESSION['admin_password'] = $password;
		return $this->logged_in_user();
	}

	public function log_out() {
		unset($_SESSION['admin_username']);
		unset($_SESSION['admin_password']);
		session_destroy();
	}

	public function log_access(): void
	{
		$username        = $_SESSION['admin_username'] ?? '';
		$remote_addr     = $_SERVER['REMOTE_ADDR'    ] ?? '';
		$remote_host     = $_SERVER['REMOTE_HOST'    ] ?? '';
		$request_method  = $_SERVER['REQUEST_METHOD' ] ?? '';
		$request_uri     = $_SERVER['REQUEST_URI'    ] ?? '';
		$http_referer    = $_SERVER['HTTP_REFERER'   ] ?? '';
		$http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$this->cm_db->execute(
			'INSERT INTO `admin_access_log` '
			.'(`timestamp`, `username`, `remote_addr`, `remote_host`,'
			.' `request_method`, `request_uri`, `http_referer`, `http_user_agent`)'
			.' VALUES(NOW(), ?, ?, ?, ?, ?, ?, ?)'
		, [
			$username, $remote_addr, $remote_host,
			$request_method, $request_uri,
			$http_referer, $http_user_agent
		]);
	}

	// TODO (Mr. Metric): gut feeling says this function should be rewritten
	public function user_has_permission($user, $permission) {
		if (is_array($permission)) {
			switch ($permission[0]) {
				case '|': case '||':
					for ($i = 1, $n = count($permission); $i < $n; $i++) {
						if ($this->user_has_permission($user, $permission[$i])) {
							return true;
						}
					}
					return false;
				case '!': case '!!':
					for ($i = 1, $n = count($permission); $i < $n; $i++) {
						if ($this->user_has_permission($user, $permission[$i])) {
							return false;
						}
					}
					return true;
				case '&': case '&&':
					for ($i = 1, $n = count($permission); $i < $n; $i++) {
						if (!$this->user_has_permission($user, $permission[$i])) {
							return false;
						}
					}
					return true;
				default:
					return false;
			}
		} else {
			return ($user && $user['permissions'] && (
				in_array('*', $user['permissions']) ||
				in_array($permission, $user['permissions'])
			));
		}
	}

	public function get_user($username): array
	{
		if(!$username) { return false; }
		$stmt = $this->cm_db->execute(
			'SELECT `name`, `username`, `active`, `permissions`'
			.' FROM `admin_users`'
			.' WHERE `username` = ?'
			, [$username]
		);

		$row = $stmt->fetch(PDO::FETCH_NUM);
		if ($row === false) {
			return false;
		}

		list($name, $username, $active, $permissions) = $row;
		return [
			'name' => $name,
			'username' => $username,
			'active' => !!$active,
			'permissions' => explode_or_empty(',', $permissions),
			'search-content' => [$name, $username],
		];
	}

	public function list_users(): array
	{
		$stmt = $this->cm_db->query(
			'SELECT `name`, `username`, `active`, `permissions`'
			.' FROM `admin_users`'
			.' ORDER BY `name`'
		);
		$users = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
			$users[] = [
				'name' => $row['name'],
				'username' => $row['username'],
				'active' => !!$row['active'],
				'permissions' => explode_or_empty(',', $row['permissions']),
				'search-content' => [$row['name'], $row['username']],
			];
		}
		return $users;
	}

	public function create_user($user): bool
	{
		if(!$user
		|| !isset($user['username']) || !$user['username']
		|| !isset($user['password']) || !$user['password'])
		{
			return false;
		}

		$name = $user['name'] ?? '';
		$username = $user['username'];
		$password = password_hash($user['password'], PASSWORD_DEFAULT);
		$active = (isset($user['active']) ? ($user['active'] ? 1 : 0) : 1);
		$permissions = (
			(isset($user['permissions']) && $user['permissions'])
			? implode(',', $user['permissions'])
			: ''
		);

		return $this->cm_db->prepare(
			'INSERT INTO `admin_users`'
			.' (`name`, `username`, `password`, `active`, `permissions`)'
			.' VALUES (?, ?, ?, ?, ?)'
		)->execute([ $name, $username, $password, $active, $permissions ]);
	}

	public function update_user(string $username, array $user): bool
	{
		if (!$username || !$user) return false;

		$exec_params = [];
		$bind_params = [];
		if (isset($user['name']) && $user['name']) {
			$exec_params[] = '`name` = ?';
			$bind_params[] = $user['name'];
		}
		if (isset($user['username']) && $user['username']) {
			$exec_params[] = '`username` = ?';
			$bind_params[] = $user['username'];
		}
		if (isset($user['password']) && $user['password']) {
			$exec_params[] = '`password` = ?';
			$bind_params[] = password_hash($user['password'], PASSWORD_DEFAULT);
		}
		if (isset($user['active'])) {
			$exec_params[] = '`active` = ?';
			$bind_params[] = ($user['active'] ? 1 : 0);
		}
		if (isset($user['permissions']) && $user['permissions']) {
			$exec_params[] = '`permissions` = ?';
			$bind_params[] = implode(',', $user['permissions']);
		}
		$bind_params[] = $username;

		return $this->cm_db->prepare(
			'UPDATE `admin_users` SET '.
			implode(',', $exec_params).' WHERE `username` = ?'
		)->execute($bind_params);
	}

	public function delete_user($username): bool
	{
		if (!$username) return false;
		return $this->cm_db->prepare(
			'DELETE FROM `admin_users` WHERE `username` = ?'
		)->execute([$username]);
	}

	public function activate_user($username, $active): bool
	{
		if (!$username) return false;
		$active = $active ? 1 : 0;
		return $this->cm_db->prepare(
			'UPDATE `admin_users` SET `active` = ? WHERE `username` = ?'
		)->execute([$active, $username]);
	}
}
