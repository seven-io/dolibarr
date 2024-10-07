<?php

class Seven_Message_Status {
	const SUCCESS = 0;
	const FAILED = 1;
	const SCHEDULED = 100;

	static function getMessageStatus($status): string {
		$statuses = [
			self::SUCCESS => 'success',
			self::FAILED => 'failed',
			self::SCHEDULED => 'scheduled'
		];

		return array_key_exists($status, $statuses) ? $statuses[$status] : $statuses[self::FAILED];
	}
}
