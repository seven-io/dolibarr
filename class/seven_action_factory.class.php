<?php

class SevenActionFactory {
	private array $supportedEntities;

	public function __construct() {
		$this->supportedEntities = [
			'Contact' => [
				new SevenActionSMS,
				new SevenActionVoice,
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
}
