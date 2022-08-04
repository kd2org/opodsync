# Micro GPodder server

This is a minimalist GPodder server to self-host your podcast data.

Requires PHP 8.0+ and SQLite3 with JSON1 extension.

## Installation

Just copy the files into a new directory of your webserver.

If you are not using Apache, make sure to replicate the rules from .htaccess

## Accounts

When installed, the server will allow to create a first account. Just go to the server URL and you will be able to create an account and login.

After that if you want to allow more accounts, you'll have to create a file named `config.local.php` containing this:

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

Please fill-in when tested.

## License

GNU AGPLv3