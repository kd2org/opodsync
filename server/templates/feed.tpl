{include file="_head.tpl"}

<p class="center">
	<a href="./subscriptions.php" class="btn sm" aria-label="Go Back">&larr; Back</a>
</p>

{if isset($feed->url, $feed->title, $feed->description)}
	<article class="feed">
		{if $feed.image_url}
			<figure><img src="{$feed.image_url}" alt="" /></figure>
		{/if}
		<h2><a href="{$feed.url}">{$feed.title}</a></h2>
		<p>{$feed.description|raw|format_description}</p>
	</article>
{else}
	<p class="help">No information is available on this feed.</p>
{/if}

{if count($episodes)}
<h2>Episodes ({$episodes|count})</h2>
<table>
	<thead>
		<tr>
			<th scope="col">Title</th>
			<th scope="col">Date</th>
			<th scope="col">Duration</th>
		</tr>
	</thead>
	<tbody>
		{foreach from=$episodes item="episode"}
			<?php
			$title = $episode->title ?: basename(parse_url($episode->media_url, PHP_URL_PATH));
			$iso_date = $episode->pubdate ? date(DATE_ISO8601, strtotime($episode->pubdate)) : '';
			$date = $episode->pubdate ? date('d/m/Y', strtotime($episode->pubdate)) : '';
			$duration = $episode->duration ? sprintf('%d:%02d', floor($episode->duration / 60), $episode->duration % 60) : '';
			?>
			<tr>
				<th scope="row"><a href="{$episode.media_url}">{$title}</a></th>
				<td><time datetime="{$iso_date}">{$date}</time></td>
				<td>{$duration}</td>
			</tr>
		{/foreach}
	</tbody>
</table>
{/if}

<h2>Actions</h2>
<p class="help">Note: episodes titles might be missing because of trackers/ads used by some podcast providers.</p>
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
			$url = basename(parse_url($row->url, PHP_URL_PATH));
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
