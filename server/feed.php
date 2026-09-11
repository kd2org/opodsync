<?php

namespace OPodSync;

require_once __DIR__ . '/_inc.php';

if (!$gpodder->user) {
	header('Location: ./login.php');
	exit;
}

$id = intval($_GET['id'] ?? null);

$tpl->assign(compact('id'));

if (isset($_GET['actions'])) {
	$actions = $gpodder->listActions($id);

	$tpl->assign(compact('actions'));
	$tpl->display('feed_actions.tpl');
}
else {
	$feed = $gpodder->getFeedForSubscription($id);
	$episodes = $gpodder->listEpisodes($id);

	if (!$feed) {
		throw new UserException('Feed not found or empty');
	}

	$tpl->assign(compact('feed', 'episodes'));
	$tpl->display('feed.tpl');
}

