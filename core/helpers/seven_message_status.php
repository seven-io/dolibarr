<?php

class Seven_Message_Status {
	const SUCCESS = 0;
	const FAILED = 1;
	const SCHEDULED = 100;

	static function get_msg_status($msg_status) {
		$msg_statuses = [
			self::SUCCESS => 'success',
			self::FAILED => 'failed',
			self::SCHEDULED => 'scheduled'
		];

		return array_key_exists($msg_status, $msg_statuses) ? $msg_statuses[$msg_status] : $msg_statuses[self::FAILED];
	}
}
