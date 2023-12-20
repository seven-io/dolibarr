<?php

class SevenApi {
	/**
	 * @return array<User>
	 */
	function getMobileUsers() {
		global $db;

		$users = [];
		$User = new User($db);
		$User->fetchAll();

		foreach ($User->users as $user) {
			/** @var User $user */
			if ('' !== $user->user_mobile) $users[] = $user;
		}

		return $users;
	}

	function request($endpoint, array $data) {
		global $conf;

		$ch = curl_init('https://gateway.seven.io/api/' . $endpoint);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-type: application/json',
			'SentWith: dolibarr',
			'X-Api-Key: ' . $conf->global->SEVEN_API_KEY,
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$json = curl_exec($ch);
		curl_close($ch);

		return json_decode($json);
	}

	function sms(array $data) {
		return $this->request('sms', $data);
	}

	/** @noinspection PhpUnused */
	function voice(array $data) {
		$responses = [];

		foreach (explode(',', $data['to']) as $to) {
			$responses[] = $this->request('voice', array_merge($data, compact('to')));
		}

		return $responses;
	}
}
