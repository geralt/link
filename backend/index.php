<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once 'mysql_link.php';
require_once 'backend/login.php';
$mysql = new mysql_link();

if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
	echo 'Invalid request.';
} else {
	$login = new login($mysql,$_POST);
	if(!$login->logged_in){
		echo $login->get_login_page();
	}else{
		echo "logged in";
	}
}

?>