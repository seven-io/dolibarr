<?php

dol_include_once("/seven/vendor/autoload.php");
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/enums/EntityEnum.php");
dol_include_once("/seven/core/helpers/event/log/EventLogger.php");
dol_include_once("/seven/class/seven_voice_call.class.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven_voice_call.db.php");
dol_include_once("/seven/class/mc_action_factory.class.php");

class SevenActionSMS extends SevenActionFactory {

	private $db;
	private $log;

	public function __construct() {
		global $db;
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	public function showActionButton($parameters, &$object, &$action, $hookmanager) {
		global $langs, $conf, $db;

		/**
		 * This hints what type the variable is
		 * @var Societe $object
		 */
		$class_name = get_class($object);
		$entity = EntityEnum::$$class_name;

		$tab_view_url = DOL_URL_ROOT . "/custom/seven/tab_view.php?{$entity}_id=" . $object->id . "&entity={$entity}";
		$enabled_button = '<div id="seven-enabled-btn" class="inline-block divButAction">';
		$enabled_button .= '<a class="butAction" href="' . $tab_view_url . '">' . $langs->trans('SendSMSButtonTitle') . '</a></div>';
		print $enabled_button;

		return 0;
	}

	public function doAction($parameters, &$object, &$action, $hookmanager) {
		global $conf, $user, $langs, $db;

		$seven_api_key = property_exists($conf->global, "SEVEN_API_KEY") ? $conf->global->SEVEN_API_KEY : "";
		$seven_api_secret = property_exists($conf->global, "SEVEN_API_SECRET") ? $conf->global->SEVEN_API_SECRET : "";
		$seven_callback_number = property_exists($conf->global, "SEVEN_CALLBACK_NUMBER") ? $conf->global->SEVEN_CALLBACK_NUMBER : "";

		$error = 0; // Error counter

		$vc_class = new SevenVoiceCall();
		$db_obj = new SevenVoiceCallDatabase($db);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if ($parameters['currentcontext'] == 'thirdparty') {        // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
			// not needed at the moment.
			// WIll be required in Phase 2 where we display UI to customer within same Card
		}

		if (!$error) {
			// $this->results = array('myreturn' => 999);
			// $this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	public function cleanUp($parameters, &$object, &$action, $hookmanager) {
		// return $this->clearActionData();
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
