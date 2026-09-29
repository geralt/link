var rad = rad || {};

rad.ajax = function() {
	return this;
};

rad.ajax.prototype.get = function(script, data, callback) {
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
		if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200 && callback) {
			callback(xhr.responseText);
		}
	};
	xhr.open('GET', script + (data ? '?' + data : ''), true);
	xhr.send();
	return xhr;
};

rad.ajax.prototype.post = function(script, data, callback) {
	var params = typeof data === 'string'
		? data
		: Object.keys(data).map(function(key) {
			return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
		}).join('&');
	var xhr = new XMLHttpRequest();

	xhr.onreadystatechange = function() {
		if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200 && callback) {
			callback(xhr.responseText);
		}
	};
	xhr.open('POST', script, true);
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.send(params);
	return xhr;
};
