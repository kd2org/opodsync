{include file="_head.tpl"}

<p class="center">
	<a href="./subscriptions.php" class="btn sm" aria-label="Go Back">&larr; Back</a>
</p>

{if isset($feed->url, $feed->title, $feed->description)}
	<article class="feed">
		<h2><a href="{$feed.url}">{$feed.title}</a></h2>
		<p>{$feed.description|raw|format_description}</p>
	</article>
	<p class="help">Note: episodes titles might be missing because of trackers/ads used by some podcast providers.</p>
{else}
	<p class="help">No information is available on this feed.</p>
{/if}

<table>
	<thead>
		<tr>
			<th scope="col">Action</th>
			<th scope="col">Device</th>
			<th scope="col">Date</th>
			<th scope="col">Episode</th>
		</tr>
	</thead>
	<tbody>
		{foreach from=$actions item="row"}
			<?php
			$url = strtok(basename($row->url), '?');
			strtok('');
			$title = $row->title ?? $url;
			$iso_date = date(DATE_ISO8601, $row->changed);
			$date = date('d/m/Y H:i', $row->changed);
			?>
			<tr>
				<th scope="row">{$row.action}</th>
				<td>{$row.device_name}</td>
				<td><time datetime="{$iso_date}">{$date}</time></td>
				<td><a href="{$row.url}">{$title}</a></td>
			</tr>
		{/foreach}
	</tbody>
</table>

{include file="_foot.tpl"}