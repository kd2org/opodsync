## 0.5.0 (11 December 2025)

* Add URL of instance on homepage, with copy button
* Add support for being used as an external app inside KaraDAV

## 0.4.7 (29 November 2025)

* Add some client tests (still incomplete)
* Refactor API error and route handling

## 0.4.6 (28 November 2025)

* Fix register button was hidden when no user existed
* Fix login after registration
* Fix various warnings for PHP 8.5 compatibility (please report any issue)

## 0.4.5 (November 2025)

* Implement various small cosmetic PRs

## 0.4.4

* Fix login.php redirect (thanks @mx1up)

## 0.4.3

* Fix GPodder token parsing (thanks @sezuan)

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
