{include file="_head.tpl"}

<nav class="center">
	<ul>
		<li><a href="./" class="btn sm" aria-label="Go Back">&larr; Back</a></li>
		<li><a href="./subscriptions/{$user.name}.opml" class="btn sm">OPML</a></li>
		{if $can_update_feeds}
			<li><a href="./update.php" class="btn sm">Update all feeds metadata</a></li>
		{/if}
	</ul>
</nav>

<table>
	<thead>
		<tr>
			<th scope="col">Podcast URL</th>
			<th scope="col">Last action</th>
			<th scope="col">Actions</th>
		</tr>
	</thead>
	<tbody>

	{foreach from=$subscriptions item="row"}
		<?php
		$iso_date = date(DATE_ISO8601, $row->last_change);
		$date = date('d/m/Y H:i', $row->last_change);
		$title = $row->title ?? str_replace(['http://', 'https://'], '', $row->url);
		?>
		<tr>
			<th scope="row"><a href="./feed.php?id={$row.id}">{$title}</a></th>
			<td><time datetime="{$iso_date}">{$date}</time></td>
			<td>{$row.count}</td>
		</tr>
	{/foreach}
	</tbody>
</table>

{include file="_foot.tpl"}