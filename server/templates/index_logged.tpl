{include file="_head.tpl"}

{if $oktoken}
	<p class="success center">You are logged in, you can close this and go back to the app.</p>
{/if}

<p class="center"><img src="icon.svg" width="150" alt="" /></p>
<h2 class="center">Logged in as {$user.name}</h2>
<p class="center">You have {$subscriptions_count} active subscriptions.</p>
<nav class="center">
	<ul>
		<li><a href="subscriptions.php" class="btn sm">List my subscriptions</a></li>
		{if !$user.external_user_id}
		<li><a href="login.php?logout" class="btn sm">Logout</a></li>
		{/if}
	</ul>
</nav>

<form method="post" action="">
	<fieldset>
		<legend>Secret GPodder username</legend>
	{if $gpodder_token}
		<h3 class="center">Your secret GPodder username: <code>{$gpodder_token}</code></h3>
		<p class="center help">(Use this username in GPodder desktop, as it does not support passwords.)</p>
		<input type="submit" name="disable_token" value="Disable GPodder username" class="btn sm" />
	{else}
		<p class="center help">GPodder desktop app has a bug, it does not support passwords.<br />
			Click below to create a secret unique username that can be used in GPodder:
		</p>
		<input type="submit" name="enable_token" value="Enable GPodder username" class="btn sm" />
	{/if}
	</fieldset>

	<fieldset>
		<legend>Sync URL</legend>
		<p class="center help">Use this address in your podcast application:</p>
		<p class="center"><input type="text" class="url" value="{$url}" style="field-sizing: content;" /> <button class="btn sm" onclick="var i = this.parentNode.firstChild; i.select(); document.execCommand('copy');">Copy</button></p>
	</fieldset>
</form>


{include file="_foot.tpl"}
