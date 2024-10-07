<?php

//dol_include_once('/seven/vendor/autoload.php');
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EntityEnum.php');
dol_include_once('/seven/core/helpers/event/log/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');

class SevenActionSMS {
	private array $supportedEntities;

	public function __construct() {
		$this->supportedEntities = [
			'Contact' => [
				new SevenActionSMS,
			],
			'Societe' => [
				new SevenActionSMS,
			],
			'Facture' => [
				new SevenActionSMS,
			],
			'FactureFournisseur' => [
				new SevenActionSMS,
			],
			'Project' => [
				new SevenActionSMS,
			],
			'CommandeFournisseur' => [
				new SevenActionSMS,
			]
		];
	}

	public function makeActions($parameters, &$object, &$action, $hookmanager): void {
		foreach ($this->supportedEntities[get_class($object)] as $obj)
			$obj->doAction($parameters, $object, $action, $hookmanager);
	}

	public function makeActionButtons($parameters, &$object, &$action, $hookmanager): void {
		foreach ($this->supportedEntities[get_class($object)] as $obj) {
			$obj->showActionButton($object);
			$obj->cleanUp($parameters, $object, $action, $hookmanager);
		}
	}

	public function makeBeforeBodyClose($parameters, &$object, &$action, $hookmanager) {
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
		return 0;
	}

	/** @noinspection PhpUnused */
	public function cleanUp($parameters, &$object, &$action, $hookmanager) {
	}
}
