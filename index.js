var ajaxClient = new rad.ajax();
var isLoading = false;
var selectedTag = null;

function logout() {
	var token = document.getElementById('csrf_token').value;
	ajaxClient.post('link.php', {q: 'logout', csrf_token: token}, function(response) {
		document.getElementById('login').innerHTML = response;
	});
	document.getElementById('new_link').innerHTML = '';
}

function process_login(formName) {
	var form = document.getElementById(formName);
	var data = {q: 'login'};

	Array.prototype.forEach.call(form.elements, function(element) {
		data[element.name] = element.value;
	});

	ajaxClient.post('link.php', data, function(response) {
		document.getElementById('login').innerHTML = response;
		loadLinks(true);
	});
}

function loadNewLinkForm() {
	ajaxClient.get('link.php', 'q=new_link_page', function(response) {
		document.getElementById('new_link').innerHTML = response;
	});
}

function process_new_link() {
	var form = document.getElementById('new_link_form');
	var data = {q: 'process_new_link'};
	var url = '';

	Array.prototype.forEach.call(form.elements, function(element) {
		data[element.name] = element.value;
		if (element.name === 'new_link') {
			url = element.value.trim();
		}
	});

	if (!url) {
		alert('The link field cannot be empty.');
		return;
	}

	ajaxClient.post('link.php', data, function(response) {
		loadLinks(true);
		if (response.length > 0) {
			alert(response);
		}
	});
}

function delete_link(linkId) {
	if (!window.confirm('Delete this saved link?')) {
		return;
	}
	var token = document.getElementById('csrf_token');
	if (!token) {
		return;
	}
	ajaxClient.post('link.php', {q: 'delete_link', link_id: linkId, csrf_token: token.value}, function(response) {
		if (response !== 'ok') {
			alert(response || 'Unable to delete this link.');
			return;
		}
		selectedTag = null;
		window.history.replaceState(null, '', window.location.pathname);
		loadLinks(true);
	});
}

function loadLinks(refresh, begin, limit) {
	var offset = begin || 0;
	var pageSize = limit || 10;
	var payload = JSON.stringify({begin: offset, limit: pageSize, tag: selectedTag});

	ajaxClient.get('link.php', 'q=links_page&payload=' + encodeURIComponent(payload), function(response) {
		var data = JSON.parse(response);
		var links = document.getElementById('links');

		if (refresh) {
			links.innerHTML = data.html;
		} else {
			links.innerHTML += data.html;
		}
		document.getElementById('total_links_count').textContent = data.paging.total_count;
		document.getElementById('start_offset').textContent = data.paging.begin;
		document.getElementById('end_offset').textContent = data.paging.end;
		loadTags();
		isLoading = false;
	});
}

function loadTags() {
	ajaxClient.get('link.php', 'q=tags_page', function(response) {
		document.getElementById('tags').innerHTML = response;
		loadNewLinkForm();
	});
}

function load_tagid_page(tagId) {
	window.location.search = 'tagid=' + encodeURIComponent(tagId);
}

function initialize() {
	ajaxClient.get('link.php', 'q=login', function(response) {
		document.getElementById('login').innerHTML = response;
		var tagId = parseInt(new URLSearchParams(window.location.search).get('tagid'), 10);
		selectedTag = Number.isNaN(tagId) ? null : tagId;
		loadLinks(true);
	});
}

window.addEventListener('load', initialize);
window.addEventListener('scroll', function() {
	var atPageEnd = window.innerHeight + window.pageYOffset >= document.body.offsetHeight;
	if (!atPageEnd || isLoading) {
		return;
	}

	var total = parseInt(document.getElementById('total_links_count').textContent, 10);
	var end = parseInt(document.getElementById('end_offset').textContent, 10);
	var pageSize = Math.min(10, total - end);
	if (pageSize > 0) {
		isLoading = true;
		loadLinks(false, end, pageSize);
	}
});
