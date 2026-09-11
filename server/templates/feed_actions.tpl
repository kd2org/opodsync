{include file="_head.tpl"}

<p class="center">
	<a href="./subscriptions.php" class="btn sm" aria-label="Go Back">&larr; Back</a>
</p>

<h2>Actions</h2>
<p class="help">Note: episodes titles might be missing because of trackers/ads used by some podcast providers.</p>
<table>
	<thead>
		<tr>
			<th scope="col">Action</th>
			<th scope="col">Device</th>
			<th scope="col">Date</th>
			<th scope="col">Episode</th>
			<th scope="col">Details</th>
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
				<td>{if $row.action === 'play'}Position: {$row.position|format_duration}{/if}</td>
			</tr>
		{/foreach}
	</tbody>
</table>

{include file="_foot.tpl"}
