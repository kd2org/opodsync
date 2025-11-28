<?php

use KD2\Test;

$r = $http->GET('/register.php');

Test::equals(200, $r->status, $r);

$dom = dom($r->body);
$cc = $dom->querySelector('input[name="cc"]');
$codes = $dom->querySelectorAll('label[for="captcha"] i');

Test::assert($cc);
Test::assert($codes->length === 4);

$code = '';

foreach ($codes as $c) {
	$code .= $c->textContent;
}

$form = ['login' => 'demo', 'password' => 'demodemo', 'captcha' => $code, 'cc' => $cc->getAttribute('value')];

$r = $http->POST('/register.php', $form);

Test::equals(200, $r->status);
Test::assert(str_contains($r->body, 'Logged in as demo'));
