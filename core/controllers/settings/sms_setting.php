<?php

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven_sms_template.db.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

class SMS_Setting extends SevenBaseSettingController {
	private array $errors = [];
	private string $page_name = 'setting_page_title';
	var $context;

	private static array $setting_vars = [
		'SEVEN_FROM',
		'SEVEN_API_KEY',
		'SEVEN_COUNTRY_CODE',
	];

	public function updateSettings(): void {
		global $db, $user;

		if (empty($_POST)) return;

		if (!$user->rights->seven->permission->write) accessforbidden();

		if (GETPOST('action') == 'update_' . $this->context) {
			foreach (self::$setting_vars as $key) {
				$value = GETPOST($key, 'alphanohtml');

				if ($key == 'SEVEN_FROM') {
					if (ctype_digit($value) && strlen($value) > 15) {
						$this->errors[] = 'Numeric sender must be less than 15 characters';
						continue;
					} else if (strlen($value) > 11) {
						$this->errors[] = 'Alphanumeric sender must be less than 11 characters';
						continue;
					}
				}
				if (dolibarr_set_const($db, $key, $value) < 1) {
					$this->log->add('Seven', 'failed to update the value of {$key}');
					$this->errors[] = 'There was an error saving {$key}.';
				}
			}
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save settings', 'error');
		else $this->addNotificationMessage('Settings saved');
	}

	public static function getSettings(): array {
		global $conf;
		$settings = [];

		foreach (self::$setting_vars as $key)
			$settings[$key] = property_exists($conf->global, $key) ? $conf->global->$key : '';

		return $settings;
	}

	public function render(): void {
		global $langs, $db;

		$settings = $this->getSettings();
		$requireReactivation = 0;
		$healthcheckObjects = [
			new SevenSMSTemplateDatabase($db),
		];

		foreach ($healthcheckObjects as $obj) if (!$obj->healthcheck()) $requireReactivation++;

		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		echo dol_get_fiche_head($head, 'settings', $langs->trans($this->page_name), -1);

		foreach ($this->notificationMessages ?? [] as $notification_message)
			dol_htmloutput_mesg($notification_message['message'], [], $notification_message['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>

		<?php if ($requireReactivation !== 0): ?>
			<h2 style='color: red'>Please reactivate module Seven</h2>
		<?php endif ?>

		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
			<input type='hidden' name='token' value='<?= newToken(); ?>'/>
			<input type='hidden' name='action' value='<?= 'update_{$this->context}' ?>'/>

			<table class='border seven-table'>
				<tr>
					<td><label for='seven_api_key'>API Key *</label></td>
					<td>
						<input id='seven_api_key' name='SEVEN_API_KEY' value='<?= $settings['SEVEN_API_KEY'] ?>' size='50' required/>
					</td>
				</tr>
				<tr>
					<td>
						<label for='seven_from'>
							<?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID'); ?>
						</label>
					</td>
					<td><input id='seven_from' name='SEVEN_FROM' size='50' value='<?= $settings['SEVEN_FROM'] ?>'/></td>
				</tr>
			</table>
			<div style='text-align: center'>
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('savesetting') ?>' />
			</div>
		</form>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
