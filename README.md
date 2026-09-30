# link
***Link*** is a simple Delicious (del.icio.us) replacement. Meant as a simple central repository for bookmarking links. Another feature from Delicious that has been implement is **Tagging** of posted links.

# setup
Requires PHP with PDO SQLite enabled. The SQLite file is created at `data/link.sqlite`; Apache must be able to write to the `data` directory. Requests to that directory are denied by `data/.htaccess`. Set the database table names and registration option in `backend/mysql_login.ini.php`.

The schema is created automatically the first time the application connects. Registration is disabled by default. To create the first account, temporarily enable registration in `backend/mysql_login.ini.php`:
```php
$mysql_settings_allow_registration = true;
```
After registering the initial account, set the value back to `false`. Ensure the Apache configuration allows `.htaccess` overrides for the project directory (`AllowOverride AuthConfig` or `AllowOverride All`).

# Progressive Web App
Serve the application from `localhost` or over HTTPS to enable service workers. On supported browsers, use the browser's install option to add Link to the home screen or desktop. The interface shell is cached for offline startup; login, saved links, and other server data still require a connection.

# todo
* Editing posted links
* Delete posted links
* Mark posted links as dead
* Automatic image linking to dress up link
