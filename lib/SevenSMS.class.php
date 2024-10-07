<?php
dol_include_once("/seven/core/class/seven_logger.class.php");

class SevenSMS {
	private $log;
	private $api_key = '';
	private $api_secret = '';

	public $rest_base_url = 'https://gateway.seven.io/api';

	private $rest_commands = [
		'send_sms' => ['url' => '/sms', 'method' => 'POST'],
		'get_message_status' => ['url' => '/status', 'method' => 'GET'],
		'get_balance' => ['url' => '/balance', 'method' => 'GET'],
		'get_pricing' => ['url' => '/pricing', 'method' => 'GET']
	];

	public $response_format = 'json';

	public $message_type_option = ['7-bit' => 1, '8-bit' => 2, 'Unicode' => 3];

	public function __construct($api_key = null, $api_secret = null) {
		$this->api_key = $api_key;
		$this->api_secret = $api_secret;
		$this->log = new Seven_Logger;
	}

	function sendSMS($from, $to, $message, $args = [], $medium = 'dolibarr', $message_type = null, $dlr_url = null, $udh = null) {
		$this->log->add("Seven", "SMS Sent to {$to}: {$message}");
		$params = [
			'from' => $from,
			'to' => $to,
			'text' => $message,
			'seven-dlr-mask' => 1, // TODO
			'seven-dlr-url' => $dlr_url, // TODO
			'udh' => $udh,
			'seven-medium' => $medium // TODO
		];

		$params = array_merge($params, $args);

		return $this->invokeApi('send_sms', $params);
	}

	public function receiveDLR($data) {
		$delivery_status = [
			1 => 'Success',
			2 => 'Failed',
			3 => 'Expired'
		];
		$delivery_report_data = new stdClass;
		$delivery_report_data->from = $data['sender'];
		$delivery_report_data->to = $data['system'];
		$delivery_report_data->dlr_status = $delivery_status[$data['seven-dlr-status']]; // TODO
		$delivery_report_data->msgid = $data['id']; // TODO? is it msg_id?
		$delivery_report_data->error_code = $data['seven-error-code'];
		$delivery_report_data->dlr_received_time = $data['timestamp'];

		return $delivery_report_data;
	}

	public function receiveMO($data) {
		$mo_message = new stdClass;
		$mo_message->from = $data['from'];
		$mo_message->to = $data['to'];
		$mo_message->keyword = $data['seven-keyword']; // TODO
		$mo_message->text = $data['text'];
		$mo_message->coding = $data['seven-coding']; // TODO
		$mo_message->time = $data['timestamp']; // TODO

		if ($mo_message->coding == $this->message_type_option['Unicode']) {
			$mo_message->keyword = $this->utf16HexToUtf8($mo_message->keyword);
			$mo_message->text = $this->utf16HexToUtf8($mo_message->text);
		}

		return $mo_message;
	}

	public function messageStatus($msgid) {
		return $this->invokeApi('get_message_status', ['msg_id' => $msgid]);
	}

	public function accountBalance() {
		return $this->invokeApi('get_balance');
	}

	public function accountPricing($mcc = null, $mnc = null) {
		$params = [];
		if ($mcc) {
			$params['seven-mcc'] = $mcc; // TODO
		}
		if ($mnc) {
			$params['seven-mnc'] = $mnc; // TODO
		}
		return $this->invokeApi('get_pricing', $params);
	}

	private function invokeApi($command, $params = []) {
		// Get REST URL and HTTP method
		$command_info = $this->rest_commands[$command];
		$url = $this->rest_base_url . $command_info['url'];
		$method = $command_info['method'];

		// Build the post data
		$params = array_merge($params, [
			'seven-api-key' => $this->api_key,
			'seven-api-secret' => $this->api_secret,
			'seven-resp-format' => $this->response_format // TODO ?
		]);

		$rest_request = curl_init();
		if ($method == 'POST') {
			curl_setopt($rest_request, CURLOPT_URL, $url);
			curl_setopt($rest_request, CURLOPT_POST, $method == 'POST' ? true : false);
			curl_setopt($rest_request, CURLOPT_POSTFIELDS, http_build_query($params));
		} else {
			$query_string = '';
			foreach ($params as $parameter_name => $parameter_value) {
				$query_string .= '&' . $parameter_name . '=' . $parameter_value;
			}
			$query_string = substr($query_string, 1);
			curl_setopt($rest_request, CURLOPT_URL, $url . '?' . $query_string);
		}
		curl_setopt($rest_request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($rest_request, CURLOPT_SSL_VERIFYPEER, false);
		$rest_response = curl_exec($rest_request);

		if ($rest_response === false) {
			throw new Exception('curl error: ' . curl_error($rest_request));
		}

		curl_close($rest_request);

		return $rest_response;
	}

	private function utf16HexToUtf8($string) {
		if (strlen($string) % 4) {
			$string = '00' . $string;
		}

		$converted_string = '';
		$string_length = strlen($string);
		for ($counter = 0; $counter < $string_length; $counter += 4) {
			$converted_string .= "&#" . hexdec(substr($string, $counter, 4)) . ";";
		}
		$converted_string = mb_convert_encoding($converted_string, "UTF-8", "HTML-ENTITIES");

		return $converted_string;
	}
}

?>
