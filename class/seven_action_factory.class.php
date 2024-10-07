<?php

abstract class SevenAction {
	private $log;

	public function __construct() {
		$this->log = new Seven_Logger;
	}

	abstract public function doAction($parameters, &$object, &$action, $hookmanager);

	abstract public function showActionButton($parameters, &$object, &$action, $hookmanager);

	abstract public function cleanUp($parameters, &$object, &$action, $hookmanager);

}

class SevenActionFactory {

	private $supported_entities;

	public function __construct() {
		$objects = [];
		$objects['Contact'] = [
			new SevenActionSMS,
			new SevenActionVoice,
		];
		$objects['ThirdParty'] = [
			new SevenActionSMS,
		];

		$objects['Invoice'] = [
			new SevenActionSMS,
		];
		$objects['SupplierInvoice'] = [
			new SevenActionSMS,
		];
		$objects['Project'] = [
			new SevenActionSMS,
		];
		$objects['SupplierOrder'] = [
			new SevenActionSMS,
		];

		$this->supported_entities = $objects;
	}

	public function makeActions($parameters, &$object, &$action, $hookmanager) {
		$class_name = get_class($object);
		foreach ($this->supported_entities[$class_name] as $obj) {
			$obj->doAction($parameters, $object, $action, $hookmanager);
		}

	}

	public function makeActionButtons($parameters, &$object, &$action, $hookmanager) {
		$class_name = get_class($object);

		foreach ($this->supported_entities[$class_name] as $obj) {
			$obj->showActionButton($parameters, $object, $action, $hookmanager);
			$obj->cleanUp($parameters, $object, $action, $hookmanager);
		}

	}

	public function makeBeforeBodyClose($parameters, &$object, &$action, $hookmanager) {
	}
}
