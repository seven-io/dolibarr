<?php

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven_sms_template.db.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/class/jobs/send-sms-reminder.class.php');

class SMS_Setting extends SevenBaseSettingController {
	private $form;
	private $errors;
	private $log;
	var $context;
	private $page_name;

	private static $setting_vars = [
		'SEVEN_FROM',
		'SEVEN_API_KEY',
		'SEVEN_API_SECRET',
		'SEVEN_COUNTRY_CODE',
		'SEVEN_CALLBACK_NUMBER',
		'SEVEN_ACTIVE_HOUR_START',
		'SEVEN_ACTIVE_HOUR_END',
	];

	function __construct($db) {
		$this->form = new Form($db);
		$this->errors = [];
		$this->log = new Seven_Logger;
		$this->page_name = 'setting_page_title';
	}

	public function update_settings() {
		global $db, $user;

		if (!empty($_POST)) {
			if (!$user->rights->seven->permission->write) {
				accessforbidden();
			}
			// Handle Update
			$action = GETPOST('action');
			if ($action == 'update_{$this->context}') {
				foreach (self::$setting_vars as $key) {
					$value = GETPOST($key, 'alphanohtml');
					if ($key == 'SEVEN_CALLBACK_NUMBER' && !empty($value)) {
						if (!ctype_digit($value)) {
							$this->errors[] = 'Seven Callback Number must be in digits';
							continue;
						}
					}
					if ($key == 'SEVEN_FROM') {
						if (ctype_digit($value) && strlen($value) > 15) {
							$this->errors[] = 'Numeric sender must be less than 15 characters';
							continue;
						} else if (strlen($value) > 11) {
							$this->errors[] = 'Alphanumeric sender must be less than 11 characters';
							continue;
						}
					}
					$error = dolibarr_set_const($db, $key, $value);
					if ($error < 1) {
						$this->log->add('Seven', 'failed to update the value of {$key}');
						$this->errors[] = 'There was an error saving {$key}.';
					}
				}
			}
			if (count($this->errors) > 0) {
				$this->add_notification_message('Failed to save settings', 'error');
			} else {
				$this->add_notification_message('Settings saved');
			}
		}
	}

	public static function get_settings() {
		global $conf, $db;
		$settings = [];

		// set default value if key not found or empty
		if (empty($conf->global->SEVEN_ACTIVE_HOUR_START)) {
			dolibarr_set_const($db, 'SEVEN_ACTIVE_HOUR_START', '12:00am');
		}
		if (empty($conf->global->SEVEN_ACTIVE_HOUR_END)) {
			dolibarr_set_const($db, 'SEVEN_ACTIVE_HOUR_END', '11.59pm');
		}

		foreach (self::$setting_vars as $key) {
			$settings[$key] = property_exists($conf->global, $key) ? $conf->global->$key : '';
		}

		return $settings;
	}

	public static function delete_settings(): int {
		global $db;
		$log = new Seven_Logger;
		$errors = 0;
		foreach (self::$setting_vars as $key) {
			$error = dolibarr_del_const($db, $key);
			if ($error < 1) {
				$log->add('Seven', 'failed to delete the value of {$key}');
				$errors--;
			}
		}
		return $errors;
	}

	public static function download_log_file($handler) {
		$log = new Seven_Logger;
		$filepath = $log->get_log_file_path($handler);
		if (!file_exists($filepath)) {
			http_response_code(404);
			die();
		}
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=\'' . basename($filepath) . '\'');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filepath));
		flush();
		readfile($filepath);
		die();
	}

	public function render() {
		global $langs, $db;

		$settings = $this->get_settings();
		$seven_api_key = $settings['SEVEN_API_KEY'];
		$seven_api_secret = $settings['SEVEN_API_SECRET'];
		$seven_from = $settings['SEVEN_FROM'];
		$seven_callback_number = $settings['SEVEN_CALLBACK_NUMBER'];
		$seven_country_code = $settings['SEVEN_COUNTRY_CODE'];
		$seven_active_hour_start = $settings['SEVEN_ACTIVE_HOUR_START'];
		$seven_active_hour_end = $settings['SEVEN_ACTIVE_HOUR_END'];

		$seven_balance = get_seven_balance();

		$require_reactivation = 0;

		$healthcheck_objects = [
			new SevenSMSReminderDatabase($db),
			new SevenSMSTemplateDatabase($db),
		];

		foreach ($healthcheck_objects as $obj) {
			if (!$obj->healthcheck()) {
				$require_reactivation++;
			}
		}

		$client_ip_address = filter_var(get_user_ip(), FILTER_VALIDATE_IP);
		$default_country_code_from_ip = get_country_code_from_ip($client_ip_address);

		$selected_country_code = !empty($seven_country_code) ? $seven_country_code : $default_country_code_from_ip;

		?>
		<!-- Begin form SMS -->

		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, 'settings', $langs->trans($this->page_name), -1);

		if (!empty($this->notification_messages)) {
			foreach ($this->notification_messages as $notification_message) {
				dol_htmloutput_mesg($notification_message['message'], [], $notification_message['style']);
			}
		}
		?>
		<?php if ($this->errors) { ?>
			<?php foreach ($this->errors as $error) { ?>
				<p style='color: red;'><?= $error ?></p>
			<?php } ?>
		<?php } ?>

		<?php if ($require_reactivation !== 0) { ?>
			<h2 style='color: red'>Please reactivate module Seven</h2>
		<?php } ?>

		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
			<input type='hidden' name='token' value='<?= newToken(); ?>'>
			<input type='hidden' name='action' value='<?= 'update_{$this->context}' ?>'>

			<table class='border seven-table'>
				<?php if (strpos($seven_balance, 'Invalid') !== false) { ?>
					<tr>
						<td width='200px'></td>
						<td>
							<p style='color: red;'>If you are sure your API credentials is correct, please whitelist
								your own IP address
								<a target='_blank'
								   href='https://help.seven.io/en/articles/9582197-whitelist-for-accessing-the-api'>here</a>
							</p>
							<p>
								Your server's IP address is: <b><?= $client_ip_address; ?></b>
							</p>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<td width='200px'>Balance</td>
					<td>
						<input type='text' name='sms_balance' size='30' value='<?= $seven_balance ?>' disabled />
					</td>
				</tr>
				<tr>
					<td width='200px'>API Key *</td>
					<td>
						<input type='text' name='SEVEN_API_KEY' size='30' value='<?= $seven_api_key ?>'
							   required />
					</td>
				</tr>
				<tr>
					<td width='200px'>API Secret</td>
					<td>
						<input type='password' name='SEVEN_API_SECRET' size='30'
							   value='<?= $seven_api_secret ?>' />
					</td>
				</tr>
				<tr>
					<td width='200px'><?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID'); ?>
						*
					</td>
					<td>
						<input type='text' name='SEVEN_FROM' size='30' value='<?= $seven_from ?>' required>
					</td>
				</tr>
				<tr>
					<td width='200px'>Default country</td>
					<td>
						<?= $this->form->select_country($selected_country_code, 'SEVEN_COUNTRY_CODE', '', 0, 'minwidth250', 'code2', 0) ?>
					</td>
				</tr>
				<tr>
					<td width='200px'><?= $this->form->textwithpicto($langs->trans('SEVEN_CALLBACK_NUMBER'), $langs->trans('SEVEN_CALLBACK_NUMBER_TOOLTIP')); ?></td>

					<td>
						<input type='text' name='SEVEN_CALLBACK_NUMBER' size='30'
							   value='<?= $seven_callback_number ?>'>
						<p>
							If you bought a virtual number, please configure call forwarding <a
								href='https://help.seven.io/en/articles/9582240-redirect-incoming-calls'
								rel='noopener noreferrer'
								target='_blank'>here</a>
						</p>
					</td>
				</tr>
				<tr>
					<td width='200px'>
						<?= $this->form->textwithpicto($langs->trans('SEVEN_ACTIVE_HOUR'), $langs->trans('SEVEN_ACTIVE_HOUR_TOOLTIP')); ?>
					</td>
					<td>
						<input type='text' name='SEVEN_ACTIVE_HOUR_START'
							   value='<?= $seven_active_hour_start ?>' style='width: 75px;' />
						-
						<input type='text' name='SEVEN_ACTIVE_HOUR_END' value='<?= $seven_active_hour_end ?>'
							   style='width: 75px;' />
					</td>
				</tr>

				<tr>
					<td width='200px'>Export log</td>
					<td>
						<button>
							<a href='<?= '{$_SERVER[\'PHP_SELF\']}?action=download_log&handler=Seven' ?>'>
								Download
							</a>
						</button>
					</td>
				</tr>
			</table>
			<p>
				Create an account
				<a href='https://app.seven.io/signup' style='font-weight: bold'>
					here
				</a> in less than 5 minutes.
			</p>
			<center>
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('savesetting') ?>'>
			</center>
		</form>
		<script>
			// https://github.com/jonthornton/jquery-timepicker#timepicker-plugin-for-jquery
			$(document).ready(function () {
				$('input[name='SEVEN_ACTIVE_HOUR_START']').timepicker();
				$('input[name='SEVEN_ACTIVE_HOUR_END']').timepicker();
			})
		</script>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
