<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	ini_set('session.use_strict_mode', '1');
	ini_set('session.use_only_cookies', '1');
	$cookie_params = session_get_cookie_params();
	session_set_cookie_params(array(
		'lifetime' => $cookie_params['lifetime'],
		'path' => $cookie_params['path'],
		'domain' => $cookie_params['domain'],
		'secure' => !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off',
		'httponly' => true,
		'samesite' => 'Lax'
	));
	session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token()
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function valid_csrf_token($token)
{
	return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

class login{

	var $logged_in = false;
	var $errMsg = '';
	var $mysql;
	var $user_table = '';
	var $allow_registration = false;

	public function __construct($mysql,$post){

		$this->mysql=$mysql;
		$this->user_table = $mysql->user_table;
		$this->allow_registration = $mysql->allow_registration;

		if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
			$this->check_post_data($post);
		} else {
			$this->logged_in = true;
		}
		
	}

	private function check_post_data($post){
		if (isset($post['txtUserid'])) {
			$username = is_string($post['txtUserid']) ? trim($post['txtUserid']) : '';
			$password = is_string($post['txtUserpw'] ?? null) ? $post['txtUserpw'] : '';
			$stored_password = $username !== '' && $password !== ''
				? $this->mysql->get_user_password($this->user_table, $username)
				: 'denied';
			if ($stored_password !== 'denied') {
				$password_valid = password_verify($password, $stored_password);
				if (!$password_valid && strlen($stored_password) === 40) {
					$password_valid = hash_equals($stored_password, sha1($password));
				}
				if ($password_valid) {
					session_regenerate_id(true);
					$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
					$_SESSION['logged_in'] = true;
					$_SESSION['user'] = $this->mysql->user_name;
					$_SESSION['user_id'] = $this->mysql->user_id;
					if (strlen($stored_password) === 40) {
						$this->mysql->update_user_password($this->user_table, $this->mysql->user_id, $password);
					}
		        	$this->logged_in = true;
		    	} else {
		    	} 	
			}
			if (!$this->logged_in) {
				$this->errMsg = 'Invalid username or password.';
			}
		}
		if (isset($post['setuser'])) {
			$username = is_string($post['setuser']) ? trim($post['setuser']) : '';
			$password = is_string($post['setpw1'] ?? null) ? $post['setpw1'] : '';
			$confirmation = is_string($post['setpw2'] ?? null) ? $post['setpw2'] : '';
			if (!$this->allow_registration) {
				$this->errMsg = 'registration is disabled';
			} elseif (strlen($username) < 1 || strlen($username) > 36 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
				$this->errMsg = 'Username must be 1 to 36 characters and contain no control characters.';
			} elseif (strlen($password) < 12 || strlen($password) > 72) {
				$this->errMsg = 'Password must be between 12 and 72 bytes.';
			} elseif ($password === $confirmation) {
				$this->mysql->create_user($this->user_table, $username, $password, 1);
			}else{
				$this->errMsg = 'The passwords do not match.';
			}
		}
	}

	public function get_login_page(){
		$page='';
		$setup=$this->mysql->table_exists($this->user_table);
		if(!$setup && $this->allow_registration){
			$page.= '<div >You are new to Link, and have not set up a user.';
			$page.= ' Please take the time now to enter a user name and password so that you can have access to';
			$page.= ' the Link interface.</div>';

			$page.='<div class="login_form">
 			register new user:
 			<form action="" method="post" name="login_form_new_user" id="login_form_new_user">
			<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">
 			<input name="setuser" type="text" id="setuser" value="" placeholder="user name" class="login_input">
 			<input name="setpw1" type="password" id="setpw1" value="" placeholder="password" class="login_input">
 			<input name="setpw2" type="password" id="setpw2" value="" placeholder="confirm password" class="login_input">
 			<input type="button" name="Submit" value="Submit" onclick="process_login(\'login_form_new_user\')">
 			</form>
 			</div>';
		}else{
			$page.='<div class="login_form">
 			<form action="" method="post" name="login_form" id="login_form">
			<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">
 			<input name="txtUserid" type="text" id="txtUserid" value="" placeholder="user name" class="login_input">
 			<input name="txtUserpw" type="password" id="txtUserpw" value="" placeholder="password" class="login_input">
 			<input type="button" name="Submit" value="Submit" onclick="process_login(\'login_form\')">
 			</form>
 			</div>';

			if($this->allow_registration)
 			{

	 				$page.='<div class="login_form">
	 			register new user:
	 			<form action="" method="post" name="login_form_new_user" id="login_form_new_user">
			<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">
	 			<input name="setuser" type="text" id="setuser" value="" placeholder="user name" class="login_input">
	 			<input name="setpw1" type="password" id="setpw1" value="" placeholder="password" class="login_input">
	 			<input name="setpw2" type="password" id="setpw2" value="" placeholder="confirm password" class="login_input">
	 			<input type="button" name="Submit" value="Submit" onclick="process_login(\'login_form_new_user\')">
	 			</form>
	 			</div>';
 			}
		}
		return $page;
	}
}

class logout{
	public function __construct(){
		$_SESSION = array();
		if (ini_get('session.use_cookies')) {
			$cookie_params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure'], $cookie_params['httponly']);
		}
		session_destroy();
	}
}

?>