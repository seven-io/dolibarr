<?php

class SevenBaseController {

	public $id;
	public $notification_messages;

	function __construct($id) {
		$this->id = $id;
		$this->notification_messages = [];
	}

	public function add_notification_message($message, $style = 'ok') {
		$this->notification_messages[] = [
			'message' => $message,
			'style' => $style,
		];
	}

}
