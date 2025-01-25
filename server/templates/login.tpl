{include file="_head.tpl"}

{if $error}
	<p class="error center">{$error}</p>
{/if}

{if $token}
	<p class="center">An app is asking to access your account.</p>
{/if}

<form method="post" action="">
	<fieldset>
		<legend>Please login</legend>
		<dl>
			<dt><label for="login">Login</label></dt>
			<dd><input type="text" required name="login" id="login" /></dd>
			<dt><label for="password">Password</label></dt>
			<dd><input type="password" required name="password" id="password" /></dd>
		</dl>
		<p><button type="submit" class="btn">Login <svg aria-hidden="true" width="40" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#ffdd67" r="30"/><g fill="#664e27"><circle cx="20.5" cy="26.6" r="5"/><circle cx="43.5" cy="26.6" r="5"/><path d="m44.6 40.3c-8.1 5.7-17.1 5.6-25.2 0-1-.7-1.8.5-1.2 1.6 2.5 4 7.4 7.7 13.8 7.7s11.3-3.6 13.8-7.7c.6-1.1-.2-2.3-1.2-1.6"/></g></svg></button></p>
	</fieldset>
</form>

{include file="_foot.tpl"}
