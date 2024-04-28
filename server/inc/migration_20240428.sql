CREATE TABLE feeds (
	id INTEGER NOT NULL PRIMARY KEY,
	feed_url TEXT NOT NULL,
	image_url TEXT NULL,
	url TEXT NULL,
	language TEXT NULL CHECK (language IS NULL OR LENGTH(language) = 2),
	title TEXT NULL,
	description TEXT NULL,
	pubdate TEXT NULL DEFAULT CURRENT_TIMESTAMP CHECK (pubdate IS NULL OR datetime(pubdate) = pubdate),
	last_fetch INTEGER NOT NULL
);

CREATE UNIQUE INDEX feed_url ON feeds (feed_url);

CREATE TABLE episodes (
	id INTEGER NOT NULL PRIMARY KEY,
	feed INTEGER NOT NULL REFERENCES feeds (id) ON DELETE CASCADE,
	media_url TEXT NOT NULL,
	url TEXT NULL,
	image_url TEXT NULL,
	duration INTEGER NULL,
	title TEXT NULL,
	description TEXT NULL,
	pubdate TEXT NULL DEFAULT CURRENT_TIMESTAMP CHECK (pubdate IS NULL OR datetime(pubdate) = pubdate)
);

CREATE INDEX episodes_unique ON episodes (feed, media_url);

ALTER TABLE subscriptions RENAME TO subscriptions_old;
DROP INDEX subscription_url;

CREATE TABLE subscriptions (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	feed INTEGER NULL REFERENCES feeds (id) ON DELETE SET NULL,
	url TEXT NOT NULL,
	deleted INTEGER NOT NULL DEFAULT 0,
	changed INTEGER NOT NULL,
	data TEXT
);

CREATE UNIQUE INDEX subscription_url ON subscriptions (url, user);

INSERT INTO subscriptions SELECT id, user, NULL, url, deleted, changed, data FROM subscriptions_old;

ALTER TABLE devices RENAME TO devices_old;
DROP INDEX deviceid;

-- Add new column for device name
CREATE TABLE devices (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	deviceid TEXT NOT NULL,
	name TEXT NULL,
	data TEXT
);

CREATE UNIQUE INDEX deviceid ON devices (deviceid, user);

INSERT INTO devices SELECT id, user, deviceid, json_extract(data, '$.caption'), data FROM devices_old;

ALTER TABLE episodes_actions RENAME TO episodes_actions_old;
DROP INDEX episodes_idx;

-- Add new column for device
CREATE TABLE episodes_actions (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	subscription INTEGER NOT NULL REFERENCES subscriptions (id) ON DELETE CASCADE,
	episode INTEGER NULL REFERENCES episodes (id) ON DELETE SET NULL,
	device INTEGER NULL REFERENCES devices (id) ON DELETE SET NULL,
	url TEXT NOT NULL,
	changed INTEGER NOT NULL,
	action TEXT NOT NULL,
	data TEXT
);

CREATE INDEX episodes_idx ON episodes_actions (user, action, changed);

INSERT INTO episodes_actions
	SELECT a.id, a.user, a.subscription, e.id, d.id, a.url, a.changed, a.action, a.data
	FROM episodes_actions_old a
		LEFT JOIN episodes e ON e.media_url = a.url
		LEFT JOIN devices d ON d.deviceid = json_extract(a.data, '$.device');

DROP TABLE episodes_actions_old;
DROP TABLE devices_old;
DROP TABLE subscriptions_old;

CREATE INDEX subscription_feed ON subscriptions (feed);
CREATE INDEX episodes_actions_link ON episodes_actions (episode);