<?php

abstract class Notification {

	abstract protected function get_active_transports($param = null);

}
