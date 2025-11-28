{include file="_head.tpl"}

{if $error}
	<p class="error center">{$error}</p>
{/if}

<form method="post" action="">
	<fieldset>
		<legend>Create an account</legend>
		<dl>
			<dt><label for="login">Username</label></dt>
			<dd><input type="text" name="login" required id="login" /></dd>
			<dt><label for="password">Password (minimum 8 characters)</label></dt>
			<dd><input type="password" minlength="8" required name="password" id="password" /></dd>
			<dt>Captcha</dt>
			<dd class="ca"><label for="captcha">Please enter this number: {$captcha|raw}</label></dd>
			<dd><input type="text" name="captcha" required id="captcha" /></dd>
		</dl>
		<p><button type="submit" class="btn">Create account <svg aria-hidden="true" width="40px" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="32" fill="#4bd37b" r="30"/><path d="m46 14-21 21.6-7-7.2-7 7.2 14 14.4 28-28.8z" fill="#fff"/></svg></button></p>
	</fieldset>
</form>

{include file="_foot.tpl"}