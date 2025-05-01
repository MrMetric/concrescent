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
			// TODO!!!: test this branch
			$config = $GLOBALS['cm_config']['default_admin'];
			if ($config['name'] && $config['username'] && $config['password']) {
				$this->cm_db->execute(
					'INSERT INTO `admin_users` SET '.
					'`name` = ?, `username` = ?, `password` = ?, `active` = 1, `permissions` = "*"'
				, [
					$config['name'],
					$config['username'],
					password_hash($config['password'], PASSWORD_DEFAULT),
				]);
			}
		}
	}

	public function logged_in_user(): array|false
	{
		$username = $_SESSION['admin_username'] ?? false;
		$password = $_SESSION['admin_password'] ?? false;
		if(!$username || !$password) { return false; }

		$stmt = $this->cm_db->execute(
			'SELECT `name`, `username`, `password`, `permissions`'.
			' FROM `admin_users`' .
			' WHERE `username` = ? AND `active`'
		, [$username]);
		$stmt->bind_result($name, $username, $hash, $permissions);
		// NOTE: This leaves no way to distinguish between a lookup failure and
		// an incorrect password.
		if(!$stmt->fetch()
		|| !password_verify($password, $hash))
		{
			return false;
		}
		return [
			'name' => $name,
			'username' => $username,
			'permissions' => explode(',', $permissions)
		];
	}

	public function log_in($username, $password): array|false {
		$_SESSION['admin_username'] = $username;
		$_SESSION['admin_password'] = $password;
		return $this->logged_in_user();
	}

	public function log_out(): void {
		unset($_SESSION['admin_username']);
		unset($_SESSION['admin_password']);
		session_destroy();
	}

	public function log_access(): void {
		$this->cm_db->execute(
			'INSERT INTO `admin_access_log` SET '.
			'`timestamp` = NOW(), `username` = ?, '.
			'`remote_addr` = ?, `remote_host` = ?, '.
			'`request_method` = ?, `request_uri` = ?, '.
			'`http_referer` = ?, `http_user_agent` = ?'
		, [
			$_SESSION['admin_username'] ?? '',
			$_SERVER['REMOTE_ADDR'    ] ?? '',
			$_SERVER['REMOTE_HOST'    ] ?? '',
			$_SERVER['REQUEST_METHOD' ] ?? '',
			$_SERVER['REQUEST_URI'    ] ?? '',
			$_SERVER['HTTP_REFERER'   ] ?? '',
			$_SERVER['HTTP_USER_AGENT'] ?? '',
		]);
	}

	// NOTE: most uses have `$permission` as a string
	public function user_has_permission(array $user, array|string $permission): bool {
		if (!is_array($permission)) {
			return ($user && $user['permissions'] && (
				in_array('*', $user['permissions']) ||
				in_array($permission, $user['permissions'])
			));
		}

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
		}

		return false;
	}

	// TODO!!!: test
	public function get_user(string $username): array|false {
		if(!$username) { return false; }

		$stmt = $this->cm_db->execute(
			'SELECT `name`, `username`, `active`, `permissions`'.
			' FROM `admin_users`' .
			' WHERE `username` = ?'
			, [$username]
		);
		$stmt->bind_result($name, $username, $active, $permissions);
		if(!$stmt->fetch()) { return false; }
		return [
			'name' => $name,
			'username' => $username,
			'active' => !!$active,
			'permissions' => ($permissions ? explode(',', $permissions) : []),
			'search-content' => [$name, $username],
		];
	}

	public function list_users(): array {
		$stmt = $this->cm_db->execute(
			'SELECT `name`, `username`, `active`, `permissions`'.
			' FROM `admin_users`' .
			' ORDER BY `name`'
		);
		$stmt->bind_result($name, $username, $active, $permissions);
		// TODO!!!: investigate `fetchAll`
		$users = [];
		while ($stmt->fetch()) {
			$users[] = [
				'name' => $name,
				'username' => $username,
				'active' => !!$active,
				'permissions' => ($permissions ? explode(',', $permissions) : []),
				'search-content' => [$name, $username],
			];
		}
		return $users;
	}

	// TODO!!!: test function
	public function create_user(array $user): bool {
		if(!$user
		|| !isset($user['username']) || !$user['username']
		|| !isset($user['password']) || !$user['password'])
		{
			return false;
		}

		$name = $user['name'] ?? '';
		$username = $user['username'];
		$password = password_hash($user['password'], PASSWORD_DEFAULT);
		$active = (isset($user['active']) ? (int)$user['active'] : 1);
		$permissions = (
			(isset($user['permissions']) && $user['permissions'])
			? implode(',', $user['permissions'])
			: ''
		);

		$stmt = $this->cm_db->prepare(
			'INSERT INTO `admin_users` SET '.
			'`name` = ?, `username` = ?, `password` = ?, `active` = ?, `permissions` = ?'
		);
		return $stmt->execute([
			$name,
			$username,
			$password,
			$active,
			$permissions,
		]);
	}

	public function update_user(string $username, array $user): bool {
		if(!$username || !$user) { return false; }

		$new_password = '';
		$new_active = 1;
		$new_permissions = '';
		$query_params = [];
		$bind_params = [];
		if (isset($user['name']) && $user['name']) {
			$query_params[] = '`name` = ?';
			$bind_params[] = &$user['name'];
		}
		if (isset($user['username']) && $user['username']) {
			$query_params[] = '`username` = ?';
			$bind_params[] = &$user['username'];
		}
		if (isset($user['password']) && $user['password']) {
			$new_password = password_hash($user['password'], PASSWORD_DEFAULT);
			$query_params[] = '`password` = ?';
			$bind_params[] = &$new_password;
		}
		if (isset($user['active'])) {
			$new_active = (int)$user['active'];
			$query_params[] = '`active` = ?';
			$bind_params[] = &$new_active;
		}
		if (isset($user['permissions']) && $user['permissions']) {
			$new_permissions = implode(',', $user['permissions']);
			$query_params[] = '`permissions` = ?';
			$bind_params[] = &$new_permissions;
		}
		$bind_params[] = &$username;

		$stmt = $this->cm_db->prepare(
			'UPDATE `admin_users` SET '.
			implode(', ', $query_params).' WHERE `username` = ?'
		);
		return $stmt->execute([$bind_params]);
	}

	// TODO!!!: double-check that `LIMIT 1` isn't important
	// TODO!!!: test with `$username === ''`
	public function delete_user(string $username): bool {
		$stmt = $this->cm_db->prepare(
			'DELETE FROM `admin_users` WHERE `username` = ?'
		);
		return $stmt->execute([$username]);
	}

	public function activate_user(string $username, bool $active) {
		$stmt = $this->cm_db->prepare(
			'UPDATE `admin_users`' .
			' SET `active` = ? WHERE `username` = ?'
		);
		return $stmt->execute([(int)$active, $username]);
	}
}
