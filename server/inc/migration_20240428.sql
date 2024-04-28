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
DROP TABLE devices_old;

ALTER TABLE episodes_actions RENAME TO episodes_actions_old;
DROP INDEX episodes_idx;

-- Add new column for device
CREATE TABLE episodes_actions (
	id INTEGER NOT NULL PRIMARY KEY,
	user INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
	subscription INTEGER NOT NULL REFERENCES subscriptions (id) ON DELETE CASCADE,
	device INTEGER NULL REFERENCES devices (id) ON DELETE SET NULL,
	url TEXT NOT NULL,
	changed INTEGER NOT NULL,
	action TEXT NOT NULL,
	data TEXT
);

CREATE INDEX episodes_idx ON episodes_actions (user, action, changed);

INSERT INTO episodes_actions
	SELECT a.id, a.user, a.subscription, d.id, a.url, a.changed, a.action, a.data
	FROM episodes_actions_old a
		LEFT JOIN devices d ON d.deviceid = json_extract(a.data, '$.device');

DROP TABLE episodes_actions_old;
