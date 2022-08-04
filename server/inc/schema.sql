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
	data TEXT
);

CREATE UNIQUE INDEX deviceid ON devices (deviceid);

CREATE TABLE subscriptions (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	url TEXT NOT NULL,
	deleted INTEGER NOT NULL DEFAULT 0,
	changed INTEGER NOT NULL,
	data TEXT
);

CREATE UNIQUE INDEX subscription_url ON subscriptions (url);

CREATE TABLE episodes_actions (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	subscription INTEGER NOT NULL REFERENCES subscriptions (id) ON DELETE CASCADE,
	url TEXT NOT NULL,
	changed INTEGER NOT NULL,
	action TEXT NOT NULL,
	data TEXT
);

CREATE INDEX episodes_idx ON episodes_actions (user, action, changed);