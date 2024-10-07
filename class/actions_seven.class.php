<?php
dol_include_once('/seven/vendor/autoload.php');
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/class/actions/sms.action.php');

class ActionsSeven {
	private SevenActionSMS $smsAction;
	public string $error = '';
	public array $errors = [];
	public array $results = [];
	public ?string $resprints;

	public function __construct(public DoliDB $db) {
		$this->smsAction = new SevenActionSMS;
	}

	/**
	 * Execute action
	 * @param array $parameters Array of parameters
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action 'add', 'update', 'view'
	 * @return    int                            <0 if KO,
	 *                                        =0 if OK but we want to process standard actions too,
	 *                                            >0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action) {
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager) {
		$this->smsAction->makeActions($parameters, $object, $action, $hookmanager);
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
		$this->smsAction->makeActionButtons($parameters, $object, $action, $hookmanager);
	}

	public function beforeBodyClose($parameters, &$object, &$action, $hookmanager) {
		$this->smsAction->makeBeforeBodyClose($parameters, $object, $action, $hookmanager);
	}

	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager) {
		return 0; // or return 1 to replace standard code
	}

	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager) {
		return 0; // or return 1 to replace standard code
	}


	/**
	 * Execute action
	 * @param array $parameters Array of parameters
	 * @param Object $object Object output on PDF
	 * @param string $action 'add', 'update', 'view'
	 * @return  int                    <0 if KO,
	 *                                =0 if OK but we want to process standard actions too,
	 *                                >0 if OK and we want to replace standard actions.
	 * @throws Exception
	 */
	public function beforePDFCreation($parameters, &$object, &$action) {
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);
		return 0;
	}

	/**
	 * Execute action
	 * @param array $parameters Array of parameters
	 * @param Object $pdfhandler PDF builder handler
	 * @param string $action 'add', 'update', 'view'
	 * @return  int                    <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 * @throws Exception
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action) {
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		return 0;
	}


	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager) {
		global $langs;

		$langs->load('seven@seven');

		$this->results = [];

		$head = [];
		$h = 0;

		if ($parameters['tabfamily'] == 'seven') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans('Home');
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans('seven');
			$this->results['picto'] = 'seven@seven';
		}

		$head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
		$head[$h][1] = $langs->trans('CustomReports');
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}


	/**
	 * Overloading the restrictedArea function : check permission on an object
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                            <0 if KO,
	 *                                        =0 if OK but we want to process standard actions too,
	 *                                        >0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager) {
		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 * @param array $parameters Array of parameters
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action 'add', 'update', 'view'
	 * @param Hookmanager $hookmanager hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager) {
		global $langs;

		if (!isset($parameters['object']->element)) return 0;
		if ($parameters['mode'] == 'remove') return 0;
		elseif ($parameters['mode'] == 'add') {
			$langs->load('seven@seven');
			$counter = count($parameters['head']);

			if ($counter > 0 && (int)DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				return 1; // return 1 to replace standard code
			} else return 0; // en V14 et + $parameters['head'] est modifiable par référence
		}
		return 0; // TODO?
	}
}
