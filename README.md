# Micro GPodder server

This is a minimalist GPodder server to self-host your podcast data.

Requires PHP 8.0+ and SQLite3 with JSON1 extension.

## Installation

Just copy the files from the `server` directory into a new directory of your webserver.

If you are not using Apache, make sure to replicate the rules from the `.htaccess` file to your own server.

### Docker

Alternatively, just use [vivab0rg/docker-php-nginx](https://github.com/vivab0rg/docker-php-nginx) Docker image and see theincluded `Makefile` task to start a container for this project.

NOTE: mind your permissions for the `server/` directory or the container will no be able to write the `data.sqlite` file.

## Configuring your podcast client

Just use the domain name where you installed the server, and the login and password you have chosen.

## Accounts

When installed, the server will allow to create a first account. Just go to the server URL and you will be able to create an account and login.

After that first account, account creation is disabled by default.

If you want to allow more accounts, you'll have to create a file named `config.local.php` containing this:

```
<?php

const ENABLE_SUBSCRIPTIONS = true;
```

## APIs

This server supports the following APIs:

* [Authentication](https://gpoddernet.readthedocs.io/en/latest/api/reference/auth.html)
* [Episodes actions](https://gpoddernet.readthedocs.io/en/latest/api/reference/events.html)
* [Subscriptions](https://gpoddernet.readthedocs.io/en/latest/api/reference/subscriptions.html)
* [Device synchronization](https://gpoddernet.readthedocs.io/en/latest/api/reference/sync.html)
* [Devices API](https://gpoddernet.readthedocs.io/en/latest/api/reference/devices.html)

It also supports endpoints defined by the [NextCloud GPodder app](https://github.com/thrillfall/nextcloud-gpodder).

The endpoint `/api/2/updates/(username)/(deviceid).json` (from the Devices API) is not implemented.

### API implementation differences

This server only supports JSON format, except:

* `PUT /subscriptions/(username)/(deviceid).txt`
* `GET /subscriptions/(username)/(deviceid).opml`
* `GET /subscriptions/(username).opml`

Trying to use a different format on other endpoints will result in a 501 error. JSONP is not supported either.

Please also note: the username "current" always points to the currently logged-in user. The deviceid "default" points to the first deviceid found.

## Compatible apps

This server has been tested so far with:

* AntennaPod 2.6.1 (both GPodder API and NextCloud API) - Android

Please report if apps work (or not).

It doesn't work with:

* gPodder 3.10.17 - Debian ([bug report](https://github.com/gpodder/gpodder/issues/1358))
* Clementine 1.4.0rc1 - Debian (not possible to choose the server: [bug report](https://github.com/clementine-player/Clementine/issues/7202))

## License

GNU AGPLv3

## Author

* [BohwaZ](https://bohwaz.net/)
