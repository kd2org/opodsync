ALTER TABLE users ADD COLUMN external_user_id INTEGER NULL;

DROP INDEX users_name;
CREATE UNIQUE INDEX users_unique ON users (name, external_user_id);

