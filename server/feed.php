<?php

namespace OPodSync;

require_once __DIR__ . '/_inc.php';

if (!$gpodder->user) {
	header('Location: ./login.php');
	exit;
}

$id = intval($_GET['id'] ?? null);
$feed = $gpodder->getFeedForSubscription($id);
$actions = $gpodder->listActions($id);
$episodes = $gpodder->listEpisodes($id);

if (!$feed && !$actions && !$episodes) {
	throw new UserException('Feed not found or empty');
}

$tpl->assign(compact('feed', 'actions', 'episodes'));
$tpl->display('feed.tpl');
