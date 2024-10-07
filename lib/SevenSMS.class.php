<?php
dol_include_once("/seven/core/class/seven_logger.class.php");

class SevenSMS {
	private $log;
	private $api_key = '';
	public $rest_base_url = 'https://gateway.seven.io/api';
	private $rest_commands = [
		'send_sms' => ['url' => '/sms', 'method' => 'POST'],
		'get_message_status' => ['url' => '/status', 'method' => 'GET'],
		'get_balance' => ['url' => '/balance', 'method' => 'GET'],
		'get_pricing' => ['url' => '/pricing', 'method' => 'GET']
	];
	public $response_format = 'json';
	public $message_type_option = ['7-bit' => 1, '8-bit' => 2, 'Unicode' => 3];

	public function __construct($api_key = null) {
		$this->api_key = $api_key;
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

		return $this->invokeApi('send_sms', array_merge($params, $args));
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
