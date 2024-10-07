<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

dol_include_once('/seven/lib/SevenSMS.class.php');
dol_include_once('/seven/core/helpers/seven_message_status.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven.db.php');
dol_include_once('/seven/core/controllers/invoice.class.php');
dol_include_once('/seven/core/controllers/thirdparty.class.php');
dol_include_once('/seven/core/controllers/supplier_invoice.class.php');
dol_include_once('/seven/core/controllers/supplier_order.class.php');
dol_include_once('/seven/core/controllers/project.class.php');
dol_include_once('/seven/core/controllers/settings/third_party.setting.php');
dol_include_once('/seven/core/helpers/EventLogger.php');

function sevenSendSms(string $from, $to, string $message, string $source, array $args = [], string $medium = 'dolibarr') {
	global $conf, $db;

	$sevenDatabase = new SevenDatabase($db);
	$log = new Seven_Logger;

	try {
		$apiKey = property_exists($conf->global, 'SEVEN_API_KEY') ? $conf->global->SEVEN_API_KEY : '';
		$from = empty($from) ? $conf->global->SEVEN_FROM : $from;

		if (empty($to)) {
			$log->add('Seven', 'Mobile number is empty, exiting...');
			throw new Exception('Mobile number cannot be empty');
		}

		$sevenClient = new SevenSMS($apiKey);
		$resp = $sevenClient->sendSMS($from, $to, $message, $args, $medium);
		$resp = json_decode($resp, 1);
		$log->add('Seven', 'SMS Response');
		$log->add('Seven', print_r($resp, 1));
		$msgStatus = $resp['messages'][0]['status'];
		$msgId = $resp['messages'][0]['msgid'];

		if (!empty($args['delay'])) {
			$realScheduledDatetime = new DateTime($args['seven-schedule'], new DateTimeZone('Asia/Kuala_Lumpur'));
			$realScheduledDatetime->setTimezone(new DateTimeZone('UTC'));
			$msgStatus = Seven_Message_Status::SCHEDULED;
			$sevenDatabase->insert($msgId, $from, $to, $message, $msgStatus, $source, 'sms', $realScheduledDatetime->format('Y-m-d H:i:s'));
		} else {
			$sevenDatabase->insert($msgId, $from, $to, $message, $msgStatus, $source, 'sms');
		}

		return $resp;
	} catch (Exception $e) {
		$sevenDatabase->insert(NULL, $from, $to, $message, 1, $source, 'sms');
		$log->add('Seven', print_r($e->getMessage(), 1));
		return [
			'messages' => [
				[
					'status' => 1,
				],
			],
		];
	}
}

function displayView(string $domain, string $fileName, array $templateVariables): void {
	$filePath = '/seven/core/controllers/templates/' . $domain . '/' . $fileName . '.php';
	include_once DOL_DOCUMENT_ROOT . '/custom/' . $filePath;
}
