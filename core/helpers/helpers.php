<?php
require_once DOL_DOCUMENT_ROOT . "/core/lib/date.lib.php";

dol_include_once("/seven/lib/SevenSMS.class.php");
dol_include_once("/seven/core/helpers/seven_message_status.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven.db.php");
dol_include_once("/seven/core/controllers/invoice.class.php");
dol_include_once("/seven/core/controllers/thirdparty.class.php");
dol_include_once("/seven/core/controllers/supplier_invoice.class.php");
dol_include_once("/seven/core/controllers/supplier_order.class.php");
dol_include_once("/seven/core/controllers/contact.class.php");
dol_include_once("/seven/core/controllers/project.class.php");
dol_include_once("/seven/core/controllers/settings/third_party.setting.php");
dol_include_once("/seven/core/controllers/settings/contact.setting.php");
dol_include_once("/seven/core/helpers/EventLogger.php");


function process_send_sms_data() {
	global $db;

	$log = new Seven_Logger;
	$object_id = GETPOST('object_id');
	$send_context = GETPOST('send_context');
	$sms_from = GETPOST("sms_from");
	$sms_contact_ids = GETPOST("sms_contact_ids");
	$sms_thirdparty_id = GETPOST("thirdparty_id");
	$send_sms_to_thirdparty_flag = GETPOST("send_sms_to_thirdparty_flag") == "on" ? true : false;
	$sms_message = GETPOST('sms_message');
	$sms_scheduled_datetime = GETPOST('sms_scheduled_datetime');
	$sms_args = [];

	if (isset($sms_scheduled_datetime) && !empty($sms_scheduled_datetime)) {
		$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone(getServerTimeZoneString()));
		$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

		$sms_args['seven-schedule'] = $real_scheduled_dt->format("Y-m-d H:i:s"); // TODO?
	}


	$total_sms_responses = [];

	if (!empty($sms_thirdparty_id)) {
		if (empty($sms_contact_ids) || $send_sms_to_thirdparty_flag) {
			$tp_obj = new ThirdPartyController($sms_thirdparty_id);

			$tp_setting_obj = new SMS_ThirdParty_Setting($db);
			$dol_tp_obj = new Societe($db);
			$dol_tp_obj->fetch($sms_thirdparty_id);
			$message = $tp_setting_obj->replace_keywords_with_value($dol_tp_obj, $sms_message);

			$tp_phone_no = $tp_obj->get_thirdparty_mobile_number();
			$resp = seven_send_sms($sms_from, $tp_phone_no, $message, "Tab", $sms_args);
			$total_sms_responses[] = $resp;
			if ($resp['messages'][0]['status'] == 0) {
				EventLogger::create($sms_thirdparty_id, "thirdparty", "SMS sent to third party");
			} else {
				$err_msg = $resp['messages'][0]['err_msg'];
				EventLogger::create($sms_thirdparty_id, "thirdparty", "SMS failed to send to third party", "SMS failed due to: {$err_msg}");
			}

		}
	}

	if (isset($sms_contact_ids) && !empty($sms_contact_ids)) {
		foreach ($sms_contact_ids as $sms_contact_id) {
			switch ($send_context) {
				case 'invoice':
					$obj = new InvoiceController($object_id);
					$contact = new ContactController($sms_contact_id);
					$sms_to = $contact->get_contact_mobile_number();

					$contact_setting_obj = new SMS_Contact_Setting($db);
					$dol_contact_obj = new Contact($db);
					$dol_contact_obj->fetch($sms_contact_id);

					$message = $contact_setting_obj->replace_keywords_with_value($dol_contact_obj, $sms_message);

					$resp = seven_send_sms($sms_from, $sms_to, $message, "Tab", $sms_args);
					$total_sms_responses[] = $resp;
					if ($resp['messages'][0]['status'] == 0) {
						EventLogger::create($sms_contact_id, "contact", "SMS sent to contact");
					} else {
						$err_msg = $resp['messages'][0]['err_msg'];
						EventLogger::create($sms_contact_id, "contact", "SMS failed to send to contact", "SMS failed due to: {$err_msg}");
					}
					break;
				case 'supplier_invoice':
				case 'project':
				case 'contact':
				case 'supplier_order':
				case 'thirdparty':
					$contact = new ContactController($sms_contact_id);
					$sms_to = $contact->get_contact_mobile_number();

					$contact_setting_obj = new SMS_Contact_Setting($db);
					$dol_contact_obj = new Contact($db);
					$dol_contact_obj->fetch($sms_contact_id);

					$message = $contact_setting_obj->replace_keywords_with_value($dol_contact_obj, $sms_message);

					$resp = seven_send_sms($sms_from, $sms_to, $message, "Tab", $sms_args);
					$total_sms_responses[] = $resp;
					if ($resp['messages'][0]['status'] == 0) {
						EventLogger::create($sms_contact_id, "contact", "SMS sent to contact");
					} else {
						$err_msg = $resp['messages'][0]['err_msg'];
						EventLogger::create($sms_contact_id, "contact", "SMS failed to send to contact", "SMS failed due to: {$err_msg}");
					}
					break;
				default:
					return [
						'success' => 0,
						'failed' => 0,
					];
			}
		}
	}
	$success_sms = 0;
	$total_sms = count($total_sms_responses);
	foreach ($total_sms_responses as $sms_response) {
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
		$seven_api_key = property_exists($conf->global, "SEVEN_API_KEY") ? $conf->global->SEVEN_API_KEY : '';
		$seven_api_secret = property_exists($conf->global, "SEVEN_API_SECRET") ? $conf->global->SEVEN_API_SECRET : '';

		$from = !empty($from) ? $from : $conf->global->SEVEN_FROM;

		if (empty($to)) {

			$log->add("Seven", "Mobile number is empty, exiting...");
			throw new Exception("Mobile number cannot be empty");
		}

		$sevenClient = new SevenSMS($seven_api_key, $seven_api_secret);

		$resp = $sevenClient->sendSMS($from, $to, $message, $args, $medium);
		$resp = json_decode($resp, 1);
		$log->add("Seven", "SMS Resp");
		$log->add("Seven", print_r($resp, 1));
		$msg_status = $resp['messages'][0]['status'];
		$msgid = $resp['messages'][0]['msgid'];

		if (!empty($args['seven-schedule'])) { // TODO
			$sms_scheduled_datetime = $args['seven-schedule'];
			$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone("Asia/Kuala_Lumpur"));
			$real_scheduled_dt->setTimezone(new DateTimeZone("UTC"));
			$msg_status = Seven_Message_Status::SCHEDULED;
			$db_obj->insert($msgid, $from, $to, $message, $msg_status, $source, $real_scheduled_dt->format("Y-m-d H:i:s"));
		} else {
			$db_obj->insert($msgid, $from, $to, $message, $msg_status, $source);
		}

		return $resp;

	} catch (Exception $e) {
		$db_obj->insert(NULL, $from, $to, $message, 1, $source);
		$log->add("Seven", print_r($e->getMessage(), 1));
		$failed_data = [
			'messages' => [
				[
					'status' => 1,
				],
			],
		];
		return $failed_data;
	}
}

function seven_get_message_status($msg_id) {
	global $conf;

	if (empty($msg_id)) {
		return;
	}

	$seven_api_key = property_exists($conf->global, "SEVEN_API_KEY") ? $conf->global->SEVEN_API_KEY : '';
	$seven_api_secret = property_exists($conf->global, "SEVEN_API_SECRET") ? $conf->global->SEVEN_API_SECRET : '';

	$sevenClient = new SevenSMS($seven_api_key, $seven_api_secret);

	$resp = $sevenClient->messageStatus($msg_id);
	$resp = json_decode($resp, 1);
	return $resp;
}

function get_seven_balance() {
	global $conf;
	$log = new Seven_Logger;
	try {
		$seven_api_key = property_exists($conf->global, "SEVEN_API_KEY") ? $conf->global->SEVEN_API_KEY : '';
		$seven_api_secret = property_exists($conf->global, "SEVEN_API_SECRET") ? $conf->global->SEVEN_API_SECRET : '';

		$sevenClient = new SevenSMS($seven_api_key, $seven_api_secret);

		$rest_response = $sevenClient->accountBalance();
		$rest_response = json_decode($rest_response);

		if ($rest_response->{'status'} == 0) {
			$account_pricing = json_decode($sevenClient->accountPricing());
			$account_currency = $account_pricing->destinations[0]->currency ?? "Currency not available";
			$balance_value = $rest_response->{'value'};
			$balance_display = "$balance_value $account_currency";
			return $balance_display;
		} else {
			return 'Invalid API Credentials';
		}
	} catch (Exception $e) {
		$log->add("Seven", print_r($e->getMessage(), 1));
		return 'Failed to retrieve account balance';
	}
}

function curl_get_file_contents($URL) {
	$c = curl_init();
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($c, CURLOPT_URL, $URL);
	$contents = curl_exec($c);
	curl_close($c);

	if ($contents) return $contents;
	else return "";
}

function get_user_ip() {
	return curl_get_file_contents("https://ipecho.net/plain");
}

function get_country_code_from_ip($ip_address) {
	$log = new Seven_Logger;
	$api_url = "https://www.iplocate.io/api/lookup/{$ip_address}";
	try {
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $api_url);
		curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux Centos 7;) Chrome/74.0.3729.169 Safari/537.36");
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($c, CURLOPT_TIMEOUT, 10000); // 10 sec
		$response = json_decode(curl_exec($c), 1);
		curl_close($c);


		if (!empty($response['error'])) {
			$log->add("Seven", "Unable to get country code for IP address: {$ip_address}");
			$log->add("Seven", "Error from API request: {$response['error']}");
			return ''; // ''
		}

		$country_code = $response['country_code'];

		$log->add("Seven", "Resolved {$ip_address} to country code: {$country_code}");
		return $country_code;

	} catch (Exception $e) {
		$log->add("Seven", "Error occured. Failed to get country code from ip address: {$ip_address}");
		$log->add("Seven", print_r($e->getMessage(), 1));
		return '';
	}
}

function validated_mobile_number($phone, $country_code) { // TODO
	global $conf, $db;
	$logger = new Seven_Logger;
	$db_obj = new SevenDatabase($db);
	if (empty($country_code)) {
		$country_code = $conf->global->SEVEN_COUNTRY_CODE;
		$logger->add("Seven", "Given country code is empty, using the default one.");
	}
	if (empty($phone)) {
		$logger->add("Seven", "Mobile number is empty. Exiting");
		return false;
	}

	$toCheck = $country_code + $phone;
	$api_url = "https://gateway.seven.io/api/lookup/format?number={$toCheck}";
	$logger->add("Seven", "Url used: {$api_url}");

	try {
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $api_url);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($c);
		curl_close($c);

		if (empty($response) || !is_object($response) || !$response->success) {
			$logger->add("Seven", "Invalid phone number: {$phone} for country: {$country_code}");
			$db_obj->insert(NULL, "Seven", $phone, "Invalid phone number: {$phone} for country: {$country_code}", 1, '');
			// return $phone; // 0123456789
			return ''; // ''
		}

		$logger->add("Seven", "{$phone} is converted to country ({$country_code}): {$response}");
		$phone = $response;
		return $phone; // 60123456789

	} catch (Exception $e) {
		$logger->add("Seven", "Error occurred. Failed to validate mobile number");
		$logger->add("Seven", print_r($e->getMessage(), 1));
		return $phone;
	}
}

function generateUniqueKey(): string { // Generates a 256 bits character long string. (hex size: 64)
	$now = time();
	$salt = bin2hex(random_bytes(32));
	$str_to_hash = $now . $salt;
	return hash("sha256", $str_to_hash);
}

function displayView(string $domain, string $file_name, array $template_variables) {
	$file_path = "/seven/core/controllers/templates/{$domain}/{$file_name}.php";
	include_once DOL_DOCUMENT_ROOT . "/custom/{$file_path}";
}
