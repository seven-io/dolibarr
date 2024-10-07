<?php

class SevenBaseSettingController {
	public array $notificationMessages;

	public function addNotificationMessage(string $message, string $style = 'ok'): void {
		$this->notificationMessages[] = [
			'message' => $message,
			'style' => $style,
		];
	}
}
