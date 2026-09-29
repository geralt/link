<?php
class mysql{

	var $conn;
	var $user_table = '';
	var $user_name = '';
	var $user_id = -1;
	var $errMsg = '';
	var $allow_registration = false;

	public function __construct(){
		include('mysql_login.php');
		$this->user_table = $mysql_user_table;
		$registration_setting = $mysql_settings_allow_registration ?? ($mysql_settings_allow_multi_user ?? false);
		$this->allow_registration = filter_var($registration_setting, FILTER_VALIDATE_BOOLEAN);
		$this->conn = mysqli_connect($mysql_host, $mysql_user, $mysql_pass);
		if (!$this->conn) {
			error_log('Database connection failed: ' . mysqli_connect_error());
			http_response_code(500);
			exit('A database error occurred.');
		}
		if (!mysqli_select_db($this->conn, $mysql_database_name) || !mysqli_set_charset($this->conn, 'utf8mb4')) {
			error_log('Database initialization failed: ' . mysqli_error($this->conn));
			http_response_code(500);
			exit('A database error occurred.');
		}
	}

	public function table_exists($table){
		$exists=0;
		$table = mysqli_real_escape_string($this->conn, $table);
		$result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
		if (!$result) {
			error_log('Database table lookup failed: ' . mysqli_error($this->conn));
			http_response_code(500);
			exit('A database error occurred.');
		}
		if (mysqli_num_rows ($result)>0)$exists=1;
		
		return $exists;
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
		$stmt = mysqli_prepare($this->conn, $query);
		mysqli_stmt_bind_param($stmt, 'sssi', $user, $passwordhashed, $created, $permission);
		if (!mysqli_stmt_execute($stmt)) {
			error_log('Database user creation failed: ' . mysqli_stmt_error($stmt));
			http_response_code(500);
			exit('Unable to create user.');
		}
	}
	public function get_user_password($table,$user){
		$stmt = mysqli_prepare($this->conn,"SELECT user_id, user, password FROM $table WHERE user = ? LIMIT 1");
		mysqli_stmt_bind_param($stmt, 's', $user);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$au = mysqli_fetch_assoc($result);
		if ($au) {
			$this->user_id = $au['user_id'];
			$this->user_name = $au['user'];
			return $au['password'];
		}
		return 'denied';
	}
	public function update_user_password($table, $user_id, $password){
		$passwordhashed = password_hash($password, PASSWORD_DEFAULT);
		$stmt = mysqli_prepare($this->conn, "UPDATE $table SET password = ? WHERE user_id = ?");
		mysqli_stmt_bind_param($stmt, 'si', $passwordhashed, $user_id);
		if (!mysqli_stmt_execute($stmt)) {
			error_log('Database password update failed: ' . mysqli_stmt_error($stmt));
			http_response_code(500);
			exit('Unable to update password.');
		}
	}

	function create_users_table($table){
		if(!$this->table_exists($table))
		{
			if (!mysqli_query($this->conn,"CREATE TABLE $table(
				user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				user VARCHAR(36) NOT NULL UNIQUE KEY,
				password VARCHAR(255) NOT NULL,
				created DATETIME,
				permission TINYINT(1) NOT NULL
				)")) {
				$this->database_failure('user table creation', mysqli_error($this->conn));
			}
		}
	}
}
?>