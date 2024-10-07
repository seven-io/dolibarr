<?php

dol_include_once('/seven/vendor/autoload.php');
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EntityEnum.php');
dol_include_once('/seven/core/helpers/event/log/EventLogger.php');
dol_include_once('/seven/class/seven_voice_call.class.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven_voice_call.db.php');
dol_include_once('/seven/class/seven_action_factory.class.php');

class SevenActionSMS extends SevenActionFactory {
	private DoliDB $db;
	private Seven_Logger $log;

	public function __construct() {
		parent::__construct();

		global $db;
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	/** @noinspection PhpUnused */
	public function showActionButton($object): int {
		global $langs;

		/**
		 * @var Societe $object
		 */
		$className = get_class($object);
		$entity = EntityEnum::$$className;

		$tabViewUrl = DOL_URL_ROOT . '/custom/seven/tab_view.php?' . $entity . '_id=' . $object->id . '&entity=' . $entity;
		$enabledBtn = '<div id=\'seven-enabled-btn\' class=\'inline-block divButAction\'>';
		$enabledBtn .= sprintf('<a class=\'butAction\' href=\'%s\'>', $tabViewUrl) . $langs->trans('SendSMSButtonTitle') . '</a></div>';
		echo $enabledBtn;

		return 0;
	}

	/** @noinspection PhpUnused */
	public function doAction($parameters, &$object, &$action, $hookmanager): int {
		global $conf, $user, $langs, $db;

		$apiKey = property_exists($conf->global, 'SEVEN_API_KEY') ? $conf->global->SEVEN_API_KEY : '';

		$error = 0; // Error counter

		$voiceCall = new SevenVoiceCall();
		$voiceCallDb = new SevenVoiceCallDatabase($db);

		/* print_r($parameters); print_r($object); echo 'action: ' . $action; */
		if ($parameters['currentcontext'] == 'thirdparty') {        // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
			// not needed at the moment.
			// WIll be required in Phase 2 where we display UI to customer within same Card
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/** @noinspection PhpUnused */
	public function cleanUp($parameters, &$object, &$action, $hookmanager) {
		// return $this->clearActionData();
	}

	/** @noinspection PhpUnused */
	public function clearActionData() {
		?>
		<script>
			window.history.pushState('', document.title, window.location.href.split('&')[0])
		</script>
		<?php
	}
}
