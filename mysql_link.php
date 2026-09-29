<?php
require_once 'backend/mysql.php';
class mysql_link extends mysql{

	public function __construct(){
		parent::__construct();
		
		include('backend/mysql_login.php');

		$this->mysql_link_table = $mysql_link_table;
		$this->mysql_tag_table = $mysql_tag_table;
		$this->mysql_link_tag_table = $mysql_link_tag_table;
	}

	public function init_tables($users_table){
		$this->create_users_table($users_table);
		$this->create_link_table();
		$this->create_tag_table();
		$this->create_tag_rel_table();
	}

	function create_link_table(){
		if(!$this->table_exists($this->mysql_link_table))
		{
			if (!mysqli_query($this->conn,"CREATE TABLE $this->mysql_link_table(
				link_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				user_id INT(11) NOT NULL,
				url TEXT NOT NULL,
				description TEXT NOT NULL,
				imagelink VARCHAR(255) NOT NULL,
				posttime DATETIME,
				FOREIGN KEY (user_id) REFERENCES $this->user_table(user_id) ON DELETE CASCADE
				)")) $this->database_failure('link table creation', mysqli_error($this->conn));
		}
	}
	function create_tag_table(){
		if(!$this->table_exists($this->mysql_tag_table))
		{
			if (!mysqli_query($this->conn,"CREATE TABLE $this->mysql_tag_table(
				tag_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				tag VARCHAR(36) NOT NULL UNIQUE KEY,
				user_id INT(11) NOT NULL,
				posttime DATETIME
				)")) $this->database_failure('tag table creation', mysqli_error($this->conn));
		}
	}
	function create_tag_rel_table(){
		if(!$this->table_exists($this->mysql_link_tag_table))
		{
			if (!mysqli_query($this->conn,"CREATE TABLE $this->mysql_link_tag_table(
				rel_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				link_id INT(11) NOT NULL,
				tag_id INT(11) NOT NULL,
				FOREIGN KEY (tag_id) REFERENCES $this->mysql_tag_table(tag_id) ON DELETE CASCADE,
				FOREIGN KEY (link_id) REFERENCES $this->mysql_link_table(link_id) ON DELETE CASCADE
				)")) $this->database_failure('link-tag table creation', mysqli_error($this->conn));
		}
	}
	private function get_tag_id($tag)
	{
		$stmt = mysqli_prepare($this->conn, "SELECT tag_id FROM $this->mysql_tag_table WHERE tag = ? LIMIT 1");
		mysqli_stmt_bind_param($stmt, 's', $tag);
		mysqli_stmt_execute($stmt);
		$query = mysqli_stmt_get_result($stmt);
		$id=-1;
		if ($info = mysqli_fetch_assoc($query)) {
			$id = $info['tag_id'];
		}
		return $id;
	}
	private function get_tag_name($tag_id)
	{
		$tag_id = (int)$tag_id;
		$stmt = mysqli_prepare($this->conn, "SELECT tag FROM $this->mysql_tag_table WHERE tag_id = ? LIMIT 1");
		mysqli_stmt_bind_param($stmt, 'i', $tag_id);
		mysqli_stmt_execute($stmt);
		$query = mysqli_stmt_get_result($stmt);
		$name='';
		if ($info = mysqli_fetch_assoc($query)) {
			$name = $info['tag'];
		}
		return $name;
	}

	public function add_link($url,$description,$imagelink){
		$user_id = (int)$_SESSION['user_id'];
		$posttime = date("Y-m-d H:i:s");
		$stmt = mysqli_prepare($this->conn, "INSERT INTO $this->mysql_link_table (user_id, url, description, imagelink, posttime) VALUES (?, ?, ?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'issss', $user_id, $url, $description, $imagelink, $posttime);
		if (!mysqli_stmt_execute($stmt)) $this->database_failure('link insert', mysqli_stmt_error($stmt));
	}

	public function add_tag($tag,$link_id){
		$user_id = (int)$_SESSION['user_id'];
		$posttime = date("Y-m-d H:i:s");
		$tag_id = -1;

		$stmt = mysqli_prepare($this->conn, "INSERT IGNORE INTO $this->mysql_tag_table (tag, user_id, posttime) VALUES (?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'sis', $tag, $user_id, $posttime);
		if (!mysqli_stmt_execute($stmt)) $this->database_failure('tag insert', mysqli_stmt_error($stmt));
		$tag_id = $this->get_tag_id($tag);

		$link_id = (int)$link_id;
		$stmt = mysqli_prepare($this->conn, "INSERT INTO $this->mysql_link_tag_table (link_id, tag_id) VALUES (?, ?)");
		mysqli_stmt_bind_param($stmt, 'ii', $link_id, $tag_id);
		if (!mysqli_stmt_execute($stmt)) $this->database_failure('link-tag relationship insert', mysqli_stmt_error($stmt));
	}

	private function get_link_tag_relationship($link_id)
	{
		$link_id = (int)$link_id;
		$stmt = mysqli_prepare($this->conn, "SELECT tag_id FROM $this->mysql_link_tag_table WHERE link_id = ?");
		mysqli_stmt_bind_param($stmt, 'i', $link_id);
		mysqli_stmt_execute($stmt);
		$query = mysqli_stmt_get_result($stmt);
		$tags = array();
		while($info = mysqli_fetch_assoc($query))
		{
			$tag = new stdClass();
			$tag->name = $this->get_tag_name($info['tag_id']);
			$tag->id = $info['tag_id'];
			$tags[] = $tag;
		}
		return $tags;
	}
	public function get_all_public_links($begin,$limit,$tag)
	{
		$begin = max(0, (int)$begin);
		$limit = min(50, max(1, (int)$limit));
		$tag = is_null($tag) ? null : (int)$tag;
		$obj = new stdClass();
		$obj->start_offset=$begin;
		$obj->end_offset=$begin+$limit;
		
		if(is_null($tag))
		{
			$allRaw =  mysqli_query($this->conn,"SELECT link_id 
				FROM $this->mysql_link_table 
				ORDER BY link_id") or $this->database_failure('public link count', mysqli_error($this->conn));
		}
		else
		{
			$allRaw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table
				WHERE link_id IN (
					SELECT link_id
					FROM $this->mysql_link_tag_table
					WHERE tag_id LIKE $tag
				) 
				ORDER BY link_id") or $this->database_failure('public tagged link count', mysqli_error($this->conn));

		}
		$obj->total_count = mysqli_num_rows($allRaw);

		if(is_null($tag))
		{
			$raw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table 
				ORDER BY link_id 
				DESC LIMIT $begin, $limit") or $this->database_failure('public link query', mysqli_error($this->conn));
		}
		else
		{
			$raw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table 
				WHERE link_id IN (
					SELECT link_id
					FROM $this->mysql_link_tag_table
					WHERE tag_id LIKE $tag
				) 
				ORDER BY link_id 
				DESC LIMIT $begin, $limit") or $this->database_failure('public tagged link query', mysqli_error($this->conn));
		}
		$count=0;
		$obj->links=array();
		$obj->tags=array();
		while($info = mysqli_fetch_array( $raw ))
		{
			$obj->links[$count] = $info;
			$obj->tags[$count] = $this->get_link_tag_relationship($info['link_id']);

			$count++;
		}
		return $obj;
	}

	public function get_all_personal_links($begin,$limit,$tag)
	{
		$begin = max(0, (int)$begin);
		$limit = min(50, max(1, (int)$limit));
		$tag = is_null($tag) ? null : (int)$tag;
		$obj = new stdClass();
		$obj->start_offset=$begin;
		$obj->end_offset=$begin+$limit;
		
		$user_id = (int)$_SESSION['user_id'];

		if(is_null($tag))
		{
			$allRaw =  mysqli_query($this->conn,"SELECT link_id 
				FROM $this->mysql_link_table 
				WHERE user_id LIKE $user_id 
				ORDER BY link_id") or $this->database_failure('personal link count', mysqli_error($this->conn));
		}
		else
		{
			$allRaw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table
				WHERE link_id IN (
					SELECT link_id
					FROM $this->mysql_link_tag_table
					WHERE tag_id LIKE $tag
				)
				AND user_id LIKE $user_id 
				ORDER BY link_id") or $this->database_failure('personal tagged link count', mysqli_error($this->conn));
		}
		$obj->total_count = mysqli_num_rows($allRaw);


		if(is_null($tag))
		{
			$raw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table 
				WHERE user_id LIKE $user_id 
				ORDER BY link_id 
				DESC LIMIT $begin, $limit") or $this->database_failure('personal link query', mysqli_error($this->conn));
		}
		else
		{
			$raw =  mysqli_query($this->conn,"SELECT * 
				FROM $this->mysql_link_table 
				WHERE link_id IN (
					SELECT link_id
					FROM $this->mysql_link_tag_table
					WHERE tag_id LIKE $tag
				)
				AND user_id LIKE $user_id
				ORDER BY link_id 
				DESC LIMIT $begin, $limit") or $this->database_failure('personal tagged link query', mysqli_error($this->conn));
		}
		$count=0;
		$obj->links=array();
		$obj->tags=array();
		while($info = mysqli_fetch_array( $raw ))
		{
			$obj->links[$count] = $info;
			$obj->tags[$count] = $this->get_link_tag_relationship($info['link_id']);
			$count++;
		}
		return $obj;
	}

	public function get_tags()
	{
		$raw =  mysqli_query($this->conn,"SELECT * FROM $this->mysql_tag_table ORDER BY tag_id DESC ") or $this->database_failure('tag query', mysqli_error($this->conn));
		$count=0;
		$arr=array();
		while($info = mysqli_fetch_array( $raw ))
		{
			$arr[$count] = $info;
			$count++;
		}
		return $arr;
	}
}
?>