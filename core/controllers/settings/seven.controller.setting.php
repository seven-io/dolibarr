<?php
dol_include_once('/seven/core/class/seven_logger.class.php');

class SevenBaseSettingController {
	protected Form $form;
	protected Seven_Logger $log;
	public array $notificationMessages;

	function __construct(public readonly DoliDB $db) {
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
	}

	public function addNotificationMessage(string $message, string $style = 'ok'): void {
		$this->notificationMessages[] = [
			'message' => $message,
			'style' => $style,
		];
	}
}
