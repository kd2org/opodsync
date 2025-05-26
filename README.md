# oPodSync - a minimalist GPodder-compatible server

*(Previously known as Micro GPodder server)*

This is a minimalist podcast synchronization server, for self-hosting your podcast listening / download history.

This allows you to keep track of which episodes have been listened to.

Requires PHP 7.4+ and SQLite3 with JSON1 extension.

* Main development happens on Fossil: <https://fossil.kd2.org/opodsync/>
* Github mirror: <https://github.com/kd2org/opodsync> (PR and issues accepted)

## Features

* Compatible with [GPodder](https://gpoddernet.readthedocs.io/en/latest/api/reference/) and NextCloud [gPodder Sync](https://apps.nextcloud.com/apps/gpoddersync) APIs
* Stores history of subscriptions and episodes (plays, downloads, etc.)
* Sync between devices
* Compatible with gPodder desktop client
* Self-registration
* See subscriptions and history on web interface
* Fetch feeds and episodes metadata and store them locally (optional)

## Roadmap

* Support [Podcasting 2.0 GUID](https://podcasting2.org/podcast-namespace/tags/guid)
* Unit tests
* Implement the [Open Podcast API](https://openpodcastapi.org)
* Download, archive and listen to podcasts from the web UI (optional feature)

## Screenshots

<img src="https://github.com/kd2org/opodsync/assets/584819/016b835d-2afe-47ef-86f0-dd8acc51aa89" height=300 /> <img src="https://github.com/kd2org/opodsync/assets/584819/45da98da-ded1-44b3-9607-c114c3fd7dbc" height=300 />

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

There is an unofficial [Docker image available](https://hub.docker.com/r/ganeshlab/opodsync). Please report any Docker issue there.

If this image stops being maintained, see [Docker Hub](https://hub.docker.com/search?q=opodsync) to find other community distribution for Docker.

**Please don't report Docker issues here, this repository is only for software development.**

### Configuration

You can copy the `config.dist.php` to `data/config.local.php`, and edit it to suit your needs.

### Fetching and updating feeds metadata

Version 0.3.0 brings support for fetching metadata for feed subscriptions.

Feeds will only be fetched/updated if an action has been created on the linked subscription since the last fetch.

To update feeds metadata, users can click the **Update all feeds metadata** button in the subscription list, unless you did set `DISABLE_USER_METADATA_UPDATE` to `TRUE`.

You can also just set a crontab to run `index.php` every hour for example:

```
@hourly php /var/www/opodsync/server/index.php
```

This requires to set the `BASE_URL` either in `config.local.php` or in an environment variable.


*Note: episodes titles may not appear in the list of actions, as the media URL may differ between what your podcast apps reports and what the RSS feed providers. This is because some podcast providers will provide a different URL for each user/app, for adding tracking or advertisement.*

## Configuring your podcast client

Just use the domain name where you installed the server, and the login and password you have chosen.

### gPodder (desktop client)

gPodder (the [desktop client](https://gpodder.github.io), not the gpodder.net service) doesn't support any kind of authentication (!!), see this [bug report](https://github.com/gpodder/gpodder/issues/1358) for details.

This means that you have to use a unique secret token as the username.

This token can be created when you log in. Use it as the username in gPodder configuration. Be warned that this username is replacing your password, so it lowers the security of your account.

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

* [AntennaPod](https://github.com/AntennaPod/AntennaPod) 2.6.1 (both GPodder API and NextCloud API) - Android
* [gPodder](https://gpodder.github.io/) 3.10.17 - Debian (requires a specific token, see above!)
* [Kasts](https://invent.kde.org/multimedia/kasts) 21.08 - Linux/Windows/Android
* [PinePods](https://github.com/madeofpendletonwool/PinePods) 0.6.1 - WebServer

Please report if apps work (or not) with other clients.

It doesn't work with:

* Clementine 1.4.0rc1 - Debian (not possible to choose the server: [bug report](https://github.com/clementine-player/Clementine/issues/7202))

## Other interesting resources

* [Podcast Index](https://podcastindex.org/) to find podcasts and apps
* [Castopod](https://castopod.org/) to share your podcast in the Fediverse
* [Podcasting 2.0](https://podcasting2.org/) to find modern listening apps and update your podcast feeds to the latest features

## License

GNU AGPLv3

## Author

* [BohwaZ](https://bohwaz.net/)
