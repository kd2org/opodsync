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

CREATE UNIQUE INDEX episodes_unique ON episodes (feed, media_url);

CREATE TABLE users (
	id INTEGER NOT NULL PRIMARY KEY,
	name TEXT NOT NULL,
	password TEXT NOT NULL
);

CREATE UNIQUE INDEX users_name ON users (name);

CREATE TABLE devices (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	deviceid TEXT NOT NULL,
	name TEXT NULL,
	data TEXT
);

CREATE UNIQUE INDEX deviceid ON devices (deviceid, user);

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
CREATE INDEX subscription_feed ON subscriptions (feed);

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
CREATE INDEX episodes_actions_link ON episodes_actions (episode);

PRAGMA user_version = 20240428;
