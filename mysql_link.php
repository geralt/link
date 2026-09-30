<?php
require_once 'backend/mysql.php';
class mysql_link extends mysql{

	public function __construct(){
		parent::__construct();
		$this->init_tables($this->user_table);
	}

	public function init_tables($users_table){
		$this->create_users_table($users_table);
		$this->create_link_table();
		$this->ensure_link_title_column();
		$this->create_tag_table();
		$this->create_tag_rel_table();
	}

	function create_link_table(){
		$this->db_query("CREATE TABLE IF NOT EXISTS $this->mysql_link_table (
			link_id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			url TEXT NOT NULL,
			description TEXT NOT NULL,
			imagelink TEXT NOT NULL,
			posttime TEXT,
			titulo TEXT NOT NULL DEFAULT '',
			FOREIGN KEY (user_id) REFERENCES $this->user_table(user_id) ON DELETE CASCADE
		)");
	}
	private function ensure_link_title_column(){
		$columns = $this->db_query("PRAGMA table_info($this->mysql_link_table)")->fetchAll(PDO::FETCH_COLUMN, 1);
		if (!in_array('titulo', $columns, true)) {
			$this->db_query("ALTER TABLE $this->mysql_link_table ADD COLUMN titulo TEXT NOT NULL DEFAULT ''");
		}
	}
	function create_tag_table(){
		$this->db_query("CREATE TABLE IF NOT EXISTS $this->mysql_tag_table (
			tag_id INTEGER PRIMARY KEY AUTOINCREMENT,
			tag TEXT NOT NULL UNIQUE,
			user_id INTEGER NOT NULL,
			posttime TEXT
		)");
	}
	function create_tag_rel_table(){
		$this->db_query("CREATE TABLE IF NOT EXISTS $this->mysql_link_tag_table (
			rel_id INTEGER PRIMARY KEY AUTOINCREMENT,
			link_id INTEGER NOT NULL,
			tag_id INTEGER NOT NULL,
			FOREIGN KEY (tag_id) REFERENCES $this->mysql_tag_table(tag_id) ON DELETE CASCADE,
			FOREIGN KEY (link_id) REFERENCES $this->mysql_link_table(link_id) ON DELETE CASCADE
		)");
	}
	private function get_tag_id($tag)
	{
		$stmt = $this->db_query("SELECT tag_id FROM $this->mysql_tag_table WHERE tag = ? LIMIT 1", array($tag));
		return (int)$stmt->fetchColumn();
	}
	private function get_tag_name($tag_id)
	{
		$tag_id = (int)$tag_id;
		$stmt = $this->db_query("SELECT tag FROM $this->mysql_tag_table WHERE tag_id = ? LIMIT 1", array($tag_id));
		return (string)$stmt->fetchColumn();
	}

	public function add_link($url,$description,$imagelink,$titulo = ''){
		$user_id = (int)$_SESSION['user_id'];
		$posttime = date("Y-m-d H:i:s");
		$this->db_query("INSERT INTO $this->mysql_link_table (user_id, url, description, imagelink, posttime, titulo) VALUES (?, ?, ?, ?, ?, ?)", array($user_id, $url, $description, $imagelink, $posttime, $titulo));
	}

	public function add_tag($tag,$link_id){
		$user_id = (int)$_SESSION['user_id'];
		$posttime = date("Y-m-d H:i:s");
		$tag_id = -1;

		$this->db_query("INSERT OR IGNORE INTO $this->mysql_tag_table (tag, user_id, posttime) VALUES (?, ?, ?)", array($tag, $user_id, $posttime));
		$tag_id = $this->get_tag_id($tag);

		$link_id = (int)$link_id;
		$this->db_query("INSERT INTO $this->mysql_link_tag_table (link_id, tag_id) VALUES (?, ?)", array($link_id, $tag_id));
	}

	public function delete_link($link_id, $user_id){
		$this->conn->beginTransaction();
		$tag_ids = $this->db_query(
			"SELECT tag_id FROM $this->mysql_link_tag_table WHERE link_id = ?",
			array((int)$link_id)
		)->fetchAll(PDO::FETCH_COLUMN);
		$deleted = $this->db_query(
			"DELETE FROM $this->mysql_link_table WHERE link_id = ? AND user_id = ?",
			array((int)$link_id, (int)$user_id)
		)->rowCount();
		if ($deleted !== 1) {
			$this->conn->rollBack();
			return false;
		}
		foreach ($tag_ids as $tag_id) {
			$this->db_query("DELETE FROM $this->mysql_tag_table
				WHERE tag_id = ? AND NOT EXISTS (
					SELECT 1 FROM $this->mysql_link_tag_table WHERE tag_id = ?
				)", array((int)$tag_id, (int)$tag_id));
		}
		$this->conn->commit();
		return true;
	}

	private function get_link_tag_relationship($link_id)
	{
		$link_id = (int)$link_id;
		$query = $this->db_query("SELECT tag_id FROM $this->mysql_link_tag_table WHERE link_id = ?", array($link_id));
		$tags = array();
		while($info = $query->fetch())
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
		$where = is_null($tag) ? '' : "WHERE link_id IN (SELECT link_id FROM $this->mysql_link_tag_table WHERE tag_id = ?)";
		return $this->fetch_links($where, is_null($tag) ? array() : array($tag), $begin, $limit);
	}

	public function get_all_personal_links($begin,$limit,$tag)
	{
		$begin = max(0, (int)$begin);
		$limit = min(50, max(1, (int)$limit));
		$tag = is_null($tag) ? null : (int)$tag;
		$user_id = (int)$_SESSION['user_id'];
		$where = is_null($tag)
			? 'WHERE user_id = ?'
			: "WHERE user_id = ? AND link_id IN (SELECT link_id FROM $this->mysql_link_tag_table WHERE tag_id = ?)";
		$params = is_null($tag) ? array($user_id) : array($user_id, $tag);
		return $this->fetch_links($where, $params, $begin, $limit);
	}

	private function fetch_links($where, $parameters, $begin, $limit)
	{
		$obj = new stdClass();
		$obj->start_offset = $begin;
		$obj->end_offset = $begin + $limit;
		$count = $this->db_query("SELECT COUNT(*) FROM $this->mysql_link_table $where", $parameters);
		$obj->total_count = (int)$count->fetchColumn();
		$raw = $this->db_query("SELECT * FROM $this->mysql_link_table $where ORDER BY link_id DESC LIMIT ? OFFSET ?", array_merge($parameters, array($limit, $begin)));
		$obj->links = $raw->fetchAll();
		$obj->tags = array();
		foreach ($obj->links as $link) {
			$obj->tags[] = $this->get_link_tag_relationship($link['link_id']);
		}
		return $obj;
	}

	public function get_tags()
	{
		$raw = $this->db_query("SELECT * FROM $this->mysql_tag_table ORDER BY tag_id DESC");
		return $raw->fetchAll();
	}
}
?>