UPDATE episodes_actions
SET device = (
	SELECT d.id
	FROM devices d
	WHERE d.deviceid = json_extract(episodes_actions.data, '$.device')
		AND d.user = episodes_actions.user
)
WHERE json_extract(data, '$.device') IS NOT NULL;
