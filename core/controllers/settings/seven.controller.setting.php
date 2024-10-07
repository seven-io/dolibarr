<?php

class SevenBaseSettingController {
	public $notification_messages;

	public function add_notification_message($message, $style = 'ok') {
		$this->notification_messages[] = [
			'message' => $message,
			'style' => $style,
		];
	}
}
