<?php
class mysql{

	var $conn;
	var $user_table = '';
	var $mysql_link_table = '';
	var $mysql_tag_table = '';
	var $mysql_link_tag_table = '';
	var $user_name = '';
	var $user_id = -1;
	var $errMsg = '';
	var $allow_registration = false;

	public function __construct(){
		include(__DIR__ . '/mysql_login.ini.php');
		$this->user_table = $mysql_user_table;
		$this->mysql_link_table = $mysql_link_table;
		$this->mysql_tag_table = $mysql_tag_table;
		$this->mysql_link_tag_table = $mysql_link_tag_table;
		$registration_setting = $mysql_settings_allow_registration ?? ($mysql_settings_allow_multi_user ?? false);
		$this->allow_registration = filter_var($registration_setting, FILTER_VALIDATE_BOOLEAN);
		foreach (array($this->user_table, $this->mysql_link_table, $this->mysql_tag_table, $this->mysql_link_tag_table) as $table) {
			if (!preg_match('/\\A[A-Za-z_][A-Za-z0-9_]*\\z/', $table)) {
				$this->database_failure('configuration', 'Invalid table name.');
			}
		}
		if (!is_dir(dirname($sqlite_database_file)) || !is_writable(dirname($sqlite_database_file))) {
			$this->database_failure('database path', 'Database directory does not exist or is not writable.');
		}
		try {
			$this->conn = new PDO('sqlite:' . $sqlite_database_file, null, null, array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false
			));
			$this->conn->exec('PRAGMA foreign_keys = ON');
		} catch (PDOException $error) {
			error_log('Database initialization failed: ' . $error->getMessage());
			http_response_code(500);
			exit('A database error occurred.');
		}
	}

	protected function db_query($sql, $parameters = array()){
		try {
			$statement = $this->conn->prepare($sql);
			$statement->execute($parameters);
			return $statement;
		} catch (PDOException $error) {
			$this->database_failure('query', $error->getMessage());
		}
	}

	public function table_exists($table){
		$statement = $this->db_query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1", array($table));
		return $statement->fetchColumn() !== false;
	}
	protected function database_failure($operation, $error){
		error_log('Database ' . $operation . ' failed: ' . $error);
		http_response_code(500);
		exit('A database error occurred.');
	}
	public function init_tables($users_table){
		$this->create_users_table($users_table);
	}
	public function create_user($table,$user,$password,$permission){
		if(!$this->table_exists($table)) {
			$this->init_tables($table);
		}
		$passwordhashed = password_hash($password, PASSWORD_DEFAULT);
		$created = date("Y-m-d H:i:s");
		$query = "INSERT INTO $table (user, password, created, permission) VALUES (?, ?, ?, ?)";
		$this->db_query($query, array($user, $passwordhashed, $created, $permission));
	}
	public function get_user_password($table,$user){
		$stmt = $this->db_query("SELECT user_id, user, password FROM $table WHERE user = ? LIMIT 1", array($user));
		$au = $stmt->fetch();
		if ($au) {
			$this->user_id = $au['user_id'];
			$this->user_name = $au['user'];
			return $au['password'];
		}
		return 'denied';
	}
	public function update_user_password($table, $user_id, $password){
		$passwordhashed = password_hash($password, PASSWORD_DEFAULT);
		$this->db_query("UPDATE $table SET password = ? WHERE user_id = ?", array($passwordhashed, $user_id));
	}
	public function last_insert_id(){
		return (int)$this->conn->lastInsertId();
	}

	function create_users_table($table){
		$this->db_query("CREATE TABLE IF NOT EXISTS $table (
			user_id INTEGER PRIMARY KEY AUTOINCREMENT,
			user TEXT NOT NULL UNIQUE,
			password TEXT NOT NULL,
			created TEXT,
			permission INTEGER NOT NULL
		)");
	}
}
?>