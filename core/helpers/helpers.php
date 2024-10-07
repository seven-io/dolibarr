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
dol_include_once('/seven/core/controllers/contact.class.php');
dol_include_once('/seven/core/controllers/project.class.php');
dol_include_once('/seven/core/controllers/settings/third_party.setting.php');
dol_include_once('/seven/core/controllers/settings/contact.setting.php');
dol_include_once('/seven/core/helpers/EventLogger.php');

function process_send_sms_data(): array {
	global $db;

	$log = new Seven_Logger;
	$object_id = GETPOST('object_id');
	$send_context = GETPOST('send_context');
	$sms_from = GETPOST('sms_from');
	$sms_contact_ids = GETPOST('sms_contact_ids');
	$sms_thirdparty_id = GETPOST('thirdparty_id');
	$send_sms_to_thirdparty_flag = GETPOST('send_sms_to_thirdparty_flag') == 'on' ? true : false;
	$sms_message = GETPOST('sms_message');
	$sms_scheduled_datetime = GETPOST('sms_scheduled_datetime');
	$sms_args = [];

	if (isset($sms_scheduled_datetime) && !empty($sms_scheduled_datetime)) {
		$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone(getServerTimeZoneString()));
		$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

		$sms_args['seven-schedule'] = $real_scheduled_dt->format('Y-m-d H:i:s'); // TODO?
	}

	$totalSmsResponses = [];

	if (!empty($sms_thirdparty_id)) {
		if (empty($sms_contact_ids) || $send_sms_to_thirdparty_flag) {
			$societe = (new Societe($db))->fetch($sms_thirdparty_id);
			$message = (new SMS_ThirdParty_Setting($db))->fillKeywordsWithValues($societe, $sms_message); // TODO??!
			$to = (new ThirdPartyController($sms_thirdparty_id))->getThirdPartyMobileNumber();
			$resp = seven_send_sms($sms_from, $to, $message, 'Tab', $sms_args);
			$totalSmsResponses[] = $resp;
			if ($resp['messages'][0]['status'] == 0) {
				EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS sent to third party');
			} else {
				EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $resp['messages'][0]['err_msg']);
			}
		}
	}

	foreach ($sms_contact_ids ?? [] as $contactId) {
		switch ($send_context) {
			case 'invoice':
				$obj = new InvoiceController($object_id);
				$sms_to = (new ContactController($contactId))->get_contact_mobile_number();
				$contactSettingObj = new SMS_Contact_Setting($db);
				$contactObj = new Contact($db);
				$contactObj->fetch($contactId);

				$message = $contactSettingObj->fillKeywordsWithValues($contactObj, $sms_message);

				$resp = seven_send_sms($sms_from, $sms_to, $message, 'Tab', $sms_args);
				$totalSmsResponses[] = $resp;
				if ($resp['messages'][0]['status'] == 0) {
					EventLogger::create($contactId, 'contact', 'SMS sent to contact');
				} else {
					$err_msg = $resp['messages'][0]['err_msg'];
					EventLogger::create($contactId, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
				}
				break;
			case 'supplier_invoice':
			case 'project':
			case 'contact':
			case 'supplier_order':
			case 'thirdparty':
				$contact = new ContactController($contactId);
				$sms_to = $contact->get_contact_mobile_number();
				$contactSettingObj = new SMS_Contact_Setting($db);
				$contactObj = new Contact($db);
				$contactObj->fetch($contactId);

				$message = $contactSettingObj->fillKeywordsWithValues($contactObj, $sms_message);

				$resp = seven_send_sms($sms_from, $sms_to, $message, 'Tab', $sms_args);
				$totalSmsResponses[] = $resp;
				if ($resp['messages'][0]['status'] == 0) {
					EventLogger::create($contactId, 'contact', 'SMS sent to contact');
				} else {
					EventLogger::create($contactId, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $resp['messages'][0]['err_msg']);
				}
				break;
			default:
				return [
					'success' => 0,
					'failed' => 0,
				];
		}
	}

	$success_sms = 0;
	$total_sms = count($totalSmsResponses);
	foreach ($totalSmsResponses as $sms_response) {
		if ($sms_response['messages'][0]['status'] == 0) {
			$success_sms++;
		}
	}

	$response = [];
	$response['success'] = $success_sms;
	$response['failed'] = $total_sms - $success_sms;
	return $response;
}

function seven_send_sms($from, $to, $message, $source, $args = [], $medium = 'dolibarr') {
	global $conf, $db;

	$db_obj = new SevenDatabase($db);
	$log = new Seven_Logger;
	$event_log = new EventLogger;

	try {
		$seven_api_key = property_exists($conf->global, 'SEVEN_API_KEY') ? $conf->global->SEVEN_API_KEY : '';

		$from = !empty($from) ? $from : $conf->global->SEVEN_FROM;

		if (empty($to)) {
			$log->add('Seven', 'Mobile number is empty, exiting...');
			throw new Exception('Mobile number cannot be empty');
		}

		$sevenClient = new SevenSMS($seven_api_key);

		$resp = $sevenClient->sendSMS($from, $to, $message, $args, $medium);
		$resp = json_decode($resp, 1);
		$log->add('Seven', 'SMS Resp');
		$log->add('Seven', print_r($resp, 1));
		$msg_status = $resp['messages'][0]['status'];
		$msgid = $resp['messages'][0]['msgid'];

		if (!empty($args['seven-schedule'])) { // TODO
			$sms_scheduled_datetime = $args['seven-schedule'];
			$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone('Asia/Kuala_Lumpur'));
			$real_scheduled_dt->setTimezone(new DateTimeZone('UTC'));
			$msg_status = Seven_Message_Status::SCHEDULED;
			$db_obj->insert($msgid, $from, $to, $message, $msg_status, $source, $real_scheduled_dt->format('Y-m-d H:i:s'));
		} else {
			$db_obj->insert($msgid, $from, $to, $message, $msg_status, $source);
		}

		return $resp;

	} catch (Exception $e) {
		$db_obj->insert(NULL, $from, $to, $message, 1, $source);
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

function seven_get_message_status($msg_id) {
	global $conf;

	if (empty($msg_id)) return;

	$seven_api_key = property_exists($conf->global, 'SEVEN_API_KEY') ? $conf->global->SEVEN_API_KEY : '';
	$sevenClient = new SevenSMS($seven_api_key);
	$resp = $sevenClient->messageStatus($msg_id);
	$resp = json_decode($resp, 1);

	return $resp;
}

function get_country_code_from_ip($ip_address) {
	$log = new Seven_Logger;

	try {
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, 'https://www.iplocate.io/api/lookup/' . $ip_address);
		curl_setopt($c, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux Centos 7;) Chrome/74.0.3729.169 Safari/537.36');
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($c, CURLOPT_TIMEOUT, 10000); // 10 sec
		$response = json_decode(curl_exec($c), 1);
		curl_close($c);

		if (!empty($response['error'])) {
			$log->add('Seven', 'Unable to get country code for IP address: {$ip_address}');
			$log->add('Seven', 'Error from API request: {$response[\'error\']}');
			return ''; // ''
		}

		$country_code = $response['country_code'];

		$log->add('Seven', 'Resolved {$ip_address} to country code: {$country_code}');

		return $country_code;
	} catch (Exception $e) {
		$log->add('Seven', 'Error occured. Failed to get country code from ip address: {$ip_address}');
		$log->add('Seven', print_r($e->getMessage(), 1));

		return '';
	}
}

function validatedMobileNumber($phone, $countryCode) { // TODO
	global $db;

	$logger = new Seven_Logger;

	if (empty($phone)) {
		$logger->add('Seven', 'Mobile number is empty. Exiting');
		return false;
	}

	$toCheck = $countryCode + $phone;
	$apiUrl = 'https://gateway.seven.io/api/lookup/format?number=' . $toCheck;
	$logger->add('Seven', 'Url used: ' . $apiUrl);

	try {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		if (empty($response) || !is_object($response) || !$response->success) {
			$logger->add('Seven', 'Invalid phone number: {$phone} for country: {$countryCode}');
			(new SevenDatabase($db))->insert(NULL, 'Seven', $phone, sprintf('Invalid phone number: %s for country: %s', $phone, $countryCode), 1, '', 'validation');
			return ''; // '' // return $phone; // 0123456789
		}

		$logger->add('Seven', sprintf('%s is converted to country (%s): %s', $phone, $countryCode, $response));

		return $response;

	} catch (Exception $e) {
		$logger->add('Seven', 'Error occurred. Failed to validate mobile number');
		$logger->add('Seven', print_r($e->getMessage(), 1));
		return $phone;
	}
}

function generateUniqueKey(): string { // Generates a 256 bits character long string. (hex size: 64)
	return hash('sha256', time() . bin2hex(random_bytes(32)));
}

function displayView(string $domain, string $fileName, array $templateVariables): void {
	$filePath = '/seven/core/controllers/templates/' . $domain . '/' . $fileName . '.php';
	include_once DOL_DOCUMENT_ROOT . '/custom/' . $filePath;
}
