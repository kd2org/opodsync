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

{if $error}
	<p class="error center">{$error}</p>
{/if}

{if $success}
	<p class="success center">{$success}</p>
{/if}

<form method="post" action="">
	<fieldset>
		<legend>Subscribe to a new podcast</legend>
		<p class="center help">Enter the RSS feed URL of the podcast:</p>
		<p class="center"><input type="url" name="feed_url" class="url" placeholder="https://example.com/feed.xml" required /> <button type="submit" class="btn sm">Subscribe</button></p>
	</fieldset>
</form>

<table>
	<thead>
		<tr>
			<th scope="col">Podcast URL</th>
			<th scope="col">Last action</th>
			<th scope="col">Actions</th>
			<th scope="col"></th>
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
			<td>
				<form method="post" action="" class="inline-form" onsubmit="return confirm('Unsubscribe from this podcast?');">
					<input type="hidden" name="unsubscribe" value="{$row.id}" />
					<button type="submit" class="btn sm btn-danger" title="Unsubscribe">✕</button>
				</form>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>

{include file="_foot.tpl"}
