<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
require_once 'mysql_link.php';
require_once 'backend/login.php';

function html_value($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safe_link_url($url)
{
	$parts = parse_url($url);
	if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) {
		return '#';
	}
	return html_value($url);
}

function logout_button()
{
	return '<div>
		<div id="user_id" class="invisible">'.html_value($_SESSION['user_id']).'</div>
		<div id="user_name">'.html_value($_SESSION['user']).'</div>
		<input type="hidden" id="csrf_token" value="'.html_value(csrf_token()).'">
		<button onclick="logout()">logout</button>
	</div>';
}

function new_link_page()
{
	return '<div id="container_new_link">
		<form name="new_link_form" id="new_link_form">
			<input type="hidden" name="csrf_token" value="'.html_value(csrf_token()).'">
			<input name="new_link" type="text" id="new_link" placeholder="http://" class="new_link_input">
			<textarea name="new_desc" id="new_desc" placeholder="description" class="new_desc_input"></textarea>
			<input name="new_tags" type="text" id="new_tags" placeholder="tag1, tag2" class="new_tags_input">
			<input type="button" name="Submit" value="Submit" onclick="process_new_link()">
		</form>
	</div>';
}

function link_html($link_data, $tag_data=array(), $can_delete=false)
{
	$stags = '';
	foreach ($tag_data as $tag) {
		$stags .= '<div class="link_tag" onclick="load_tagid_page('.(int)$tag->id.')">'.html_value($tag->name).'</div>';
	}
	$safe_url = safe_link_url($link_data['url']);
	return '<div class="container_link">
		<a class="link_ahref" href="'.$safe_url.'" target="_blank" rel="noopener noreferrer"><div class="link_ahref_bg">'.html_value($link_data['url']).'</div></a>
		<div id="link_description">'.html_value($link_data['description']).'</div>
		<div class="container_link_tags">'.$stags.'</div>
		<div id="link_posttime">'.html_value($link_data['posttime']).'</div>
		'.($can_delete ? '<div class="link_card_actions"><button type="button" class="link_delete_button" onclick="delete_link('.(int)$link_data['link_id'].')">Delete</button></div>' : '').'
	</div>';
}

function tag_html($tag_data)
{
	return '<div class="container_tag" onclick="load_tagid_page('.(int)$tag_data['tag_id'].')">'.html_value($tag_data['tag']).'</div>';
}

function paging_info($total_count, $begin, $end)
{
	$d = new stdClass();
	$d->total_count = $total_count;
	$d->begin = $begin;
	$d->end = $end;
	return $d;
}

function get_focused_links($focus, $payload)
{
	$payload = is_object($payload) ? $payload : new stdClass();
	$begin = isset($payload->begin) ? max(0, (int)$payload->begin) : 0;
	$limit = isset($payload->limit) ? min(50, max(1, (int)$payload->limit)) : 10;
	$tag = isset($payload->tag) && $payload->tag !== null ? (int)$payload->tag : null;
	$mysql = new mysql_link();
	$fetched = $focus ? $mysql->get_all_personal_links($begin, $limit, $tag) : $mysql->get_all_public_links($begin, $limit, $tag);
	$data = new stdClass();
	$data->html = $mysql->errMsg;
	if (count($fetched->links) < 1) {
		$data->html .= 'Unbelievable, there are no links here.';
	} else {
		foreach ($fetched->links as $i => $link) {
			$data->html .= link_html($link, $fetched->tags[$i] ?? array(), $focus);
		}
	}
	$data->paging = paging_info($fetched->total_count, $fetched->start_offset, $fetched->end_offset);
	echo json_encode($data);
}

function get_tags()
{
	$mysql = new mysql_link();
	$tags = $mysql->get_tags();
	$html = $mysql->errMsg;
	foreach ($tags as $tag) {
		$html .= tag_html($tag);
	}
	echo $html ?: 'Unbelievable, there are no tags here.';
}

function attemp_login($payload)
{
	$mysql = new mysql_link();
	$login = new login($mysql, $payload);
	return $login->logged_in ? logout_button() : $mysql->errMsg.$login->errMsg.$login->get_login_page();
}

function process_new_link($payload)
{
	if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
		return 'You must be logged in to add a link.';
	}
	$url = trim($payload['new_link'] ?? '');
	$parts = parse_url($url);
	if (strlen($url) > 2048 || !$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) {
		return 'The URL is invalid. It was not added.';
	}
	$mysql = new mysql_link();
	$mysql->add_link($url, substr(trim($payload['new_desc'] ?? ''), 0, 2000), 'fake image link');
	$link_id = $mysql->last_insert_id();
	foreach (explode(',', $payload['new_tags'] ?? '') as $tag) {
		$tag = substr(trim($tag), 0, 36);
		if ($tag !== '') {
			$mysql->add_tag($tag, $link_id);
		}
	}
	return $mysql->errMsg;
}

function process_delete_link($payload)
{
	if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
		return 'You must be logged in to delete a link.';
	}
	$link_id = filter_var($payload['link_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
	if ($link_id === false) {
		return 'Invalid link.';
	}
	$mysql = new mysql_link();
	return $mysql->delete_link($link_id, $_SESSION['user_id']) ? 'ok' : 'Unable to delete this link.';
}

if (isset($_GET['q'])) {
	if ($_GET['q'] === 'login') {
		echo isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true ? logout_button() : attemp_login(array());
	}
	if ($_GET['q'] === 'links_page') {
		get_focused_links(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true, json_decode($_GET['payload'] ?? ''));
	}
	if ($_GET['q'] === 'tags_page') {
		get_tags();
	}
	if ($_GET['q'] === 'new_link_page' && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
		echo new_link_page();
	}
}

if (isset($_POST['q'])) {
	if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
		echo 'Invalid request.';
		exit;
	}
	if ($_POST['q'] === 'logout') {
		new logout();
		echo attemp_login($_POST);
	}
	if ($_POST['q'] === 'login') {
		echo attemp_login($_POST);
	}
	if ($_POST['q'] === 'process_new_link') {
		echo process_new_link($_POST);
	}
	if ($_POST['q'] === 'delete_link') {
		echo process_delete_link($_POST);
	}
}
?>
