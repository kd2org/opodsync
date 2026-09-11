CREATE INDEX episodes_media_url ON episodes (media_url);
CREATE INDEX episodes_actions_url ON episodes_actions (url);

CREATE INDEX episodes_actions_subscription_action ON episodes_actions (subscription, action);
CREATE INDEX episodes_feed_pubdate ON episodes (feed, pubdate DESC);
