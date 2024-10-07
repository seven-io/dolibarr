<?php

class SevenBaseController {
	public array $notificationMessages;

	function __construct(public $id) {
		$this->notificationMessages = [];
	}

	public function addNotificationMessage(string $message, string $style = 'ok'): void {
		$this->notificationMessages[] = [
			'message' => $message,
			'style' => $style,
		];
	}

}
