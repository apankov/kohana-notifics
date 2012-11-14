<?php

return array(
	'send_email' => true,

	'smtp' => array(
		'default' => array(
			'host' => 'smtp.gmail.com',
			'auth' => 'login',
			'ssl' => 'ssl',
			'port' => '465',
			'username' => 'foo@example.com',
			'password' => 'secRetPwd',
			'return-path' => 'admin@example.com',
		),
	),

	'jabber-rules' => array(
	),

	'email-rules' => array(
	),
);
