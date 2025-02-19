## 0.4.2

* Fix DATA_ROOT env

## 0.4.1

* Fix CSS loading issue with php-cli webserver
* Fix session_destroy call during migration

## 0.4.0

Warning: If you had an old `config.local.php` from pre-0.4 version, it will not work anymore (the namespace has changed). You should edit it to suit the new format, see `config.dist.php` for an example.

* Refactor code of HTML display to use Smartyer templates
* Add more configuration constants
* Allow to enable/disable the secret gpodder username
* Add feed title, description and website URL to OPML (if metadata fetching was done)
