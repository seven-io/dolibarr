<?php

dol_include_once("/seven/vendor/autoload.php");
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/class/seven_voice_call.class.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven_voice_call.db.php");
dol_include_once("/seven/class/mc_action_factory.class.php");

class SevenActionVoice {
	private $db;
	public $voice_call_resp;
	private $log;
	private $can_initiate_call;


	public function __construct() {
		global $db;
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	public function showActionButton($parameters, &$object, &$action, $hookmanager) {
		global $langs, $conf, $db;

		/**
		 * This hints what type the variable is
		 * @var Contact $object
		 */

		if (!$object instanceof Contact) return 0;

		$seven_callback_number = property_exists($conf->global, "SEVEN_CALLBACK_NUMBER")
			? $conf->global->SEVEN_CALLBACK_NUMBER
			: "";
		// if virtual number is set $conf->SEVEN_VIRTUAL_NUMBER
		$phone = $object->phone_mobile;
		$cc = $object->country_code;
		$vc_class = new SevenVoiceCall;

		$validated_phone = validated_mobile_number($phone, $cc);
		$disabled_button = '<div id="seven-disabled-btn" class="inline-block divButAction"><a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("seven_voice_call_disabled_title")) . '">' . $langs->trans('seven_voice_call_button_label') . '<span id="cd-timer"> (2:00)</span></a></div>';
		$enabled_button = '<div id="seven-enabled-btn" class="inline-block divButAction"><a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=clicktodial&to=' . $validated_phone . '">' . $langs->trans('seven_voice_call_button_label') . '</a></div>';
		if (!$this->can_initiate_call) {
			print $disabled_button;
			?>
			<script>
				function startTimer(duration, display, $) {
					let timer = duration, minutes, seconds;
					setInterval(function () {
						minutes = parseInt(timer / 60, 10);
						seconds = parseInt(timer % 60, 10);

						minutes = minutes < 10 ? "0" + minutes : minutes;
						seconds = seconds < 10 ? "0" + seconds : seconds;

						display.text(" (" + minutes + ":" + seconds + ")");

						let btnLabel = "<?= $langs->trans('seven_voice_call_button_label') ?>";
						let submitUrl = "<?= $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=clicktodial&to=' . $validated_phone ?>";
						let anchorTag = `<a class="butAction" href="${submitUrl}">${btnLabel}</a>`;
						if (--timer < 0) {
							timer = duration;
							$("#seven-disabled-btn").empty();
							$("#seven-disabled-btn").append(anchorTag);
						}
					}, 1000);
				}

				jQuery(function ($) {
					let twoMinutes = 120 - <?= $vc_class->getTsDifferenceInSeconds($object->id); ?>,
						display = $('#cd-timer');
					startTimer(twoMinutes, display, $);
				});
			</script>
			<?php
			return 0;
		}

		if ($seven_callback_number) {
			if (!empty($validated_phone)) {
				print $enabled_button;
			} else {
				dol_htmloutput_mesg("User phone number is invalid", [], 'error');
				print $disabled_button;
			}
		} else {
			print $disabled_button;
		}
		return 0;
	}

	public function doAction($parameters, &$object, &$action, $hookmanager) {
		global $conf, $user, $langs, $db;

		if (!$object instanceof Contact) {
			return 0;
		}

		$seven_api_key = property_fexists($conf->global, "SEVEN_API_KEY") ? $conf->global->SEVEN_API_KEY : "";
		$seven_api_secret = property_exists($conf->global, "SEVEN_API_SECRET") ? $conf->global->SEVEN_API_SECRET : "";
		$seven_callback_number = property_exists($conf->global, "SEVEN_CALLBACK_NUMBER") ? $conf->global->SEVEN_CALLBACK_NUMBER : "";

		$error = 0; // Error counter

		$vc_class = new SevenVoiceCall;
		$db_obj = new SevenVoiceCallDatabase($db);

		if ($parameters['currentcontext'] == 'contactcard') { // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
			$this->can_initiate_call = $vc_class->canInitiateCall($object->id);
			if ($action == 'clicktodial' && $this->can_initiate_call) {

				$to = GETPOST("to");
				$text = GETPOST("text");

				$ch = curl_init('https://gateway.seven.io/api/sms');
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
					'to' => $to,
					'text' => $text,
					'from' => $seven_callback_number
				]));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Accept: application/json',
					'Content-type: application/json',
					'X-Api-Key:' . $seven_api_key,
					'X-Api-Secret:' . $seven_api_secret, // TODO: verify!
				]);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$result = curl_exec($ch);
				curl_close($ch);

				$this->log->add("Seven", "Voice call initiated. Response as below");
				$this->log->add("Seven", print_r($result, 1));
				$this->voice_call_resp = $result;
				$db_obj->insert($seven_callback_number, $to, $object->id, "contact", $result['messages'][0]['success']);

				$vc_class->setLastVoiceCallSent($object->id, strtotime("now"));
				$this->clearActionData();
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	public function cleanUp() {
		$act = GETPOST("action");
		if ($act == 'clicktodial' && !$this->can_initiate_call) {
			dol_htmloutput_mesg("Please wait 2 minutes before calling again.", [], 'error');
			$this->clearActionData();
		}

		$vc_resp = $this->voice_call_resp;

		if (isset($vc_resp) && !empty($vc_resp)) {
			if (!empty($vc_resp['err_msg'])) {
				dol_htmloutput_mesg($vc_resp['err_msg'], [], 'error');
				return 0;
			} else {
				$to = $vc_resp['calls'][0]['receiver'];
				dol_htmloutput_mesg("Call initiated to: {$to}");
				return 0;
			}
		}
	}

	public function clearActionData() {
		?>
		<script>
			let url = window.location.href.split('&')[0];
			window.history.pushState("", document.title, url)
		</script>
		<?php
	}

}
