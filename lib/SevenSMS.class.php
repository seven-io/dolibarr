<?php
dol_include_once('/seven/core/class/seven_logger.class.php');

class SevenSMS {
	private Seven_Logger $log;
	private array $restCommands = [
		'send_sms' => ['url' => '/sms', 'method' => 'POST'],
		'get_message_status' => ['url' => '/status', 'method' => 'GET'],
		'get_balance' => ['url' => '/balance', 'method' => 'GET'],
		'get_pricing' => ['url' => '/pricing', 'method' => 'GET']
	];

	public function __construct(private readonly ?string $apiKey = null) {
		$this->log = new Seven_Logger;
	}

	function sendSMS($from, $to, $message, $args = [], $medium = 'dolibarr', $message_type = null, $dlr_url = null, $udh = null) {
		$this->log->add('Seven', sprintf('SMS Sent to {%s}: {%s}', $to, $message));
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

	private function invokeApi($command, $params = []) {
		$command_info = $this->restCommands[$command];
		$url = 'https://gateway.seven.io/api' . $command_info['url'];
		$method = $command_info['method'];

		$params = array_merge($params, [
			'seven-api-key' => $this->apiKey,
		]);

		$rest_request = curl_init();
		if ($method == 'POST') {
			curl_setopt($rest_request, CURLOPT_URL, $url);
			curl_setopt($rest_request, CURLOPT_POST, $method == 'POST');
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

		if ($rest_response === false) throw new Exception('curl error: ' . curl_error($rest_request));

		curl_close($rest_request);

		return $rest_response;
	}
}
