<?php

abstract class Notification_Transport {

	public static function factory($transport_name)
	{
		$class_name = 'Notification_Transport_' . ucfirst($transport_name);
		return new $class_name();
	}

	abstract public function send($args = array(), $when = null);

	abstract public function send_async($args = array(), $when = null);

	abstract public function perform();

}
