var CACHE_NAME = 'link-shell-v4';
var APP_SHELL = [
	'./index.html',
	'./index.css',
	'./index.js',
	'./ajax.js',
	'./manifest.json',
	'./icon.svg'
];

self.addEventListener('install', function(event) {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function(cache) {
			return cache.addAll(APP_SHELL.map(function(path) {
				return new Request(path, {cache: 'reload'});
			}));
		}).then(function() {
			return self.skipWaiting();
		})
	);
});

self.addEventListener('activate', function(event) {
	event.waitUntil(
		caches.keys().then(function(cacheNames) {
			return Promise.all(cacheNames.map(function(cacheName) {
				if (cacheName !== CACHE_NAME && cacheName.indexOf('link-shell-') === 0) {
					return caches.delete(cacheName);
				}
			}));
		}).then(function() {
			return self.clients.claim();
		})
	);
});

self.addEventListener('fetch', function(event) {
	var request = event.request;
	var url = new URL(request.url);
	var scope = new URL(self.registration.scope);
	var shellUrl = new URL('./index.html', scope);

	if (request.method !== 'GET' || url.origin !== scope.origin || !url.pathname.startsWith(scope.pathname)) {
		return;
	}

	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(function() {
				return caches.match(shellUrl);
			})
		);
		return;
	}

	if (APP_SHELL.indexOf('./' + url.pathname.slice(scope.pathname.length)) === -1) {
		return;
	}

	event.respondWith(
		caches.match(request).then(function(cachedResponse) {
			if (cachedResponse) {
				return cachedResponse;
			}
			return fetch(request).then(function(response) {
				if (response.ok) {
					var responseCopy = response.clone();
					caches.open(CACHE_NAME).then(function(cache) {
						cache.put(request, responseCopy);
					});
				}
				return response;
			});
		})
	);
});