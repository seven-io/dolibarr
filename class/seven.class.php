<?php /** @noinspection PhpUnused */

class Seven extends CommonObject {
	var $class = '';
	var $deferred = '';
	var $dest = '';
	var $error;
	var $expe = '';
	var $message = '';
	var $priority = '';
	var $timeDrift = 0;

	function __construct($DB) {
	}

	function SmsSenderList() {
		global $conf;

		$from = new stdClass;
		$from->number = $conf->global->SEVEN_API_KEY_SMS_FROM ?: 'Dolibarr';
		return [$from];
	}

	function SmsSend() {
		global $langs;

		$langs->load('seven@seven');

		dol_include_once('/seven/class/SevenApi.class.php');
		$api = new SevenApi;

		$result = $api->sms([
			'delay' => (new DateTime)
				->modify('+' . $this->deferred ?: 0 . ' minutes')
				->getTimestamp(),
			'flash' => $this->class == '0',
			'from' => $this->expe,
			'text' => $this->message,
			'to' => $this->dest,
		]);
		$success = $result->success;

		if ((int)$success !== 100) {
			$this->error = $result;
			dol_syslog(get_class($this) . '::SmsSend ' . print_r($success, true), LOG_ERR);
			return 0;
		}

		return 1;
	}
}

?>
