# Micro GPodder server

This is a minimalist GPodder server to self-host your podcast synchronization data.

This allows you to keep track of which episodes have been listened to.

Requires PHP 7.4+ and SQLite3 with JSON1 extension.

## Features

* Stores history of subscriptions and episodes (plays, downloads, etc.)
* Sync between devices
* Compatible with gPodder desktop client
* Self-registration
* See subscriptions and history on web interface
* Fetch feeds and episodes metadata and store them locally (optional)

In the future, this will target compatibility with the [Open Podcast API](https://openpodcastapi.org) as well when it is released.

## Screenshots

<img src="https://github.com/bohwaz/micro-gpodder-server/assets/584819/016b835d-2afe-47ef-86f0-dd8acc51aa89" height=300 /> <img src="https://github.com/bohwaz/micro-gpodder-server/assets/584819/45da98da-ded1-44b3-9607-c114c3fd7dbc" height=300 />

## Installation

Just copy the files from the `server` directory into a new directory of your webserver.

This should work in most cases. Exceptions are:

* If you are not using Apache, but Caddy, or nginx, make sure to adapt the rules from the `.htaccess` file to your own server.
* If you are using Apache, but have set-up this server in a sub-folder of your document root, then you will have to adapt the `.htaccess` to your configuration.

### First account

When installed, the server will allow to create a first account. Just go to the server URL and you will be able to create an account and login.

After that first account, account creation is disabled by default.

If you want to allow more accounts, you'll have to configure the server (see "Configuration" below).

### Docker

In order to run micro-gpodder-server with Docker you only need to build the `Dockerfile` and run it while binding the `data` directory for persistence and setting the hostname. An example `docker-compose.yml` is provided.

### Configuration

You can create a `config.local.php` in the `data` directory (overridable with the `DATA_ROOT` environment variable), defining configuration constants:

```
<?php

// Enable or disable subscriptions (boolean)
// By default the server allows to create one account
// and then disables subscriptions
const ENABLE_SUBSCRIPTIONS = true;

// Set to a file path to enable the debug log
// Set to NULL (default) to disable the debug log
const DEBUG = __DIR__ . '/debug.log';

// Set to change the instance name
const TITLE = 'My awesome GPodder server';

// Set to the URL where the server is hosted
const BASE_URL = 'https://gpodder.mydomain.tld/me/';

// Set this to TRUE to forbid users from updating feed metadata,
// as this may add some load on your server
// (default is FALSE)
const DISABLE_USER_METADATA_UPDATE = true;
```

### Fetching and updating feeds metadata

Version 0.3.0 brings support for fetching metadata for feed subscriptions.

Feeds will only be fetched/updated if an action has been created on the linked subscription since the last fetch.

To update feeds metadata, users can click the **Update all feeds metadata** button in the subscription list, unless you did set `DISABLE_USER_METADATA_UPDATE` to `TRUE`.

You can also just set a crontab to run `index.php` every hour for example:

```
@hourly php /var/www/micro-gpodder-server/server/index.php
```

This requires to set the `BASE_URL` either in `config.local.php` or in an environment variable.


*Note: episodes titles may not appear in the list of actions, as the media URL may differ between what your podcast apps reports and what the RSS feed providers. This is because some podcast providers will provide a different URL for each user/app, for adding tracking or advertisement.*

## Configuring your podcast client

Just use the domain name where you installed the server, and the login and password you have chosen.

### gPodder (desktop client)

gPodder (the [desktop client](https://gpodder.github.io), not the gpodder.net service) doesn't support any kind of authentication (!!), see this [bug report](https://github.com/gpodder/gpodder/issues/1358) for details.

This means that you have to use a unique secret token as the username.

This token is displayed when you log in. Use it as the username in gPodder configuration.

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
* gPodder 3.10.17 - Debian (requires a specific token, see above!)

Please report if apps work (or not) with other clients.

It doesn't work with:

* Clementine 1.4.0rc1 - Debian (not possible to choose the server: [bug report](https://github.com/clementine-player/Clementine/issues/7202))

## License

GNU AGPLv3

## Author

* [BohwaZ](https://bohwaz.net/)
