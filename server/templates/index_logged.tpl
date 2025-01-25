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
		<li><a href="login.php?logout" class="btn sm">Logout</a></li>
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
</form>

{include file="_foot.tpl"}
