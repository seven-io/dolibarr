<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven_sms_reminder.db.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_setting.php');

class SMS_Invoice_Setting extends SevenBaseSettingController {
	var $context;
	var $db;
	var $errors;
	var $form;
	var $log;
	var $page_name;
	public $db_key = 'SEVEN_INVOICE_SETTING';
	public $trigger_events = [
		'BILL_CREATE',
		'BILL_MODIFY',
		'BILL_VALIDATE',
		'BILL_CANCEL',
		'BILL_PAYED',
		'BILL_UNPAYED',
	];

	function __construct($db) {
		$this->context = 'invoice';
		$this->db = $db;
		$this->errors = [];
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->page_name = 'invoice_page_title';
	}

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'reminder_settings' => [],
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'updated' => 'off',
				'validated' => 'off',
				'paid' => 'off',
			],
			'sms_templates' => [
				'created' => 'Hi [company_name], we are drafting your invoice now and will send it to you once it\'s ready.',
				'updated' => 'Hi [company_name], your invoice ([ref]) of [total_ttc] has been updated.',
				'validated' => 'Hi [company_name], your invoice ([ref]) of [total_ttc] is ready.',
				'paid' => 'Hi [company_name], thank you for your payment, your invoice ([ref]) has been paid.',
			],
		]);
	}

	public function updateSettings(): void {
		global $db, $user;

		if (empty($_POST)) return;
		if (GETPOST('action') != 'update_' . $this->context) return;

		if (!$user->rights->seven->permission->write) accessforbidden();

		$settings = json_encode([
			'enable' => GETPOST('enable'),
			'send_from' => GETPOST('send_from'),
			'send_on' => [
				'created' => GETPOST('send_on_created'),
				'updated' => GETPOST('send_on_updated'),
				'validated' => GETPOST('send_on_validated'),
				'paid' => GETPOST('send_on_paid'),
			],
			'reminder_settings' => json_decode(GETPOST('reminder_settings', $check = ''), true),
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'updated' => GETPOST('sms_templates_updated'),
				'validated' => GETPOST('sms_templates_validated'),
				'paid' => GETPOST('sms_templates_paid'),
			],
		]);
		$error = dolibarr_set_const($db, $this->db_key, $settings);
		if ($error < 1) {
			$this->log->add('Seven', 'failed to update the invoice settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving invoice settings.';
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save invoice settings', 'error');
		else $this->addNotificationMessage('Invoice settings saved');

		$this->rescheduleSmsReminders();
	}

	public function getSettings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) {
			$settings = $conf->global->{$this->db_key};
			$decoded_setting = json_decode($settings);
			if (!property_exists($decoded_setting, 'reminder_settings')) {
				$decoded_setting->reminder_settings = [];
				$settings = json_encode($decoded_setting);
			}
		} else $settings = $this->getDefaultSettings();

		return json_decode($settings);
	}

	public function billTriggerSms(Facture $object, $action): void {
		global $db;

		if ($action === 'validated') {
			try {
				$this->scheduleSmsReminders($object);
			} catch (Exception $e) {
				$this->log->add('Seven', 'Error occurred when scheduling sms reminder');
				$this->log->add('Seven', $e->getTraceAsString());
			}
		}

		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		$this->log->add('Seven', 'current action triggered: ' . $action);
		if ($settings->send_on->{$action} != 'on') return;

		$from = $settings->send_from;
		$thirdparty = new Societe($db);
		$thirdparty->fetch($object->socid);
		$to = validatedMobileNumber($thirdparty->phone, $thirdparty->country_code);
		if (empty($to)) return;
		$message = $settings->sms_templates->{$action};
		$message = $this->fillKeywordsValues($object, $message);

		$resp = seven_send_sms($from, $to, $message, 'Automation');
		$msg = $resp['messages'][0];
		if ($msg['status'] == 0)
			EventLogger::create($object->socid, 'thirdparty', 'SMS sent to third party');
		else
			EventLogger::create($object->socid, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);
		$this->log->add('Seven', 'SMS Successfully sent from fx billTriggerSms');
	}

	public function scheduleSmsReminders(Facture $object): void {
		// when they added new SMS reminder option: reschedule all of them
		global $db;
		$this->log->add('Seven', 'Scheduling SMS Reminders for object_id: ' . $object->id);

		$invoiceOverdueDate = $object->date_lim_reglement;
		$today = new DateTime('now', new DateTimeZone(getServerTimeZoneString()));

		$objectId = $object->id;
		$objectType = Facture::class;

		$smsReminder = new SevenSMSReminderDatabase($db);

		$deleteSQL = 'DELETE FROM ' . $smsReminder->tableName;
		$deleteSQL .= sprintf(' WHERE `object_id` = %d AND `object_type` = \'%s\'',
			$objectId,
			$smsReminder->db->escape($objectType),
		);
		$smsReminder->db->query($deleteSQL, 0, 'ddl');

		foreach ($this->getSettings()->reminder_settings as $setting) {
			$reminderType = $setting->reminder_type; // days
			$reminderTime = $setting->reminder_time; // 12:00am

			$dt = new DateTime('@{$invoiceOverdueDate}');
			$dt->setTimezone(new DateTimeZone(getServerTimeZoneString()));
			// add 1 day = 'P1D'
			// add 1 hour = 'PT1H'
			$interval = 'P';
			if ($reminderType == 'hours') $interval .= 'T';
			$interval .= sprintf('%d%s', $setting->reminder_delay, substr(strtoupper($reminderType), 0, 1));
			if ($setting->reminder_delay_type === 'after') $dt->add(new DateInterval($interval));
			else $dt->sub(new DateInterval($interval));
			$dt->setTime(date('H', strtotime($reminderTime)), date('i', strtotime($reminderTime)));

			if ($dt->getTimestamp() < $today->getTimestamp()) {
				$this->log->add('Seven', 'Scheduled DT less than Today');
				$this->log->add('Seven', 'Scheduled DT: ' . $dt->format('Y-m-d H:i:s e'));
				$this->log->add('Seven', 'Current DT: ' . $today->format('Y-m-d H:i:s e'));
				continue;
			}

			$dt->setTimezone(new DateTimeZone('UTC'));

			$smsReminder->insert($setting->uuid, $objectId, $objectType, $dt->format('Y-m-d H:i:s'), null, 0);
		}
	}

	public function rescheduleSmsReminders(): void {
		global $db;

		$reminders = (new SevenSMSReminderDatabase($db))->getAllWhere('object_type', '=', Facture::class);
		foreach ($reminders as $reminder) {
			$invoiceModel = new Facture($db);
			$invoiceModel->fetch($reminder['object_id']);
			$this->scheduleSmsReminders($invoiceModel);
		}
	}

	public function fillKeywordsValues(Facture $object, $message) {
		global $db;
		$company = new Societe($db);
		$company->fetch($object->socid);
		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[ref]' => !empty($object->newref) ? $object->newref : $object->ref,
			'[total_ttc]' => !empty($object->total_ttc) ? number_format($object->total_ttc, 2) : '',
			'[note_public]' => !empty($object->note_public) ? $object->note_public : '',
			'[note_private]' => !empty($object->note_private) ? $object->note_private : '',
			'[company_id]' => !empty($company->id) ? $company->id : '',
			'[company_name]' => !empty($company->name) ? $company->name : '',
			'[company_alias_name]' => !empty($company->name_alias) ? $company->name_alias : '',
			'[company_address]' => !empty($company->address) ? $company->address : '',
			'[company_zip]' => !empty($company->zip) ? $company->zip : '',
			'[company_town]' => !empty($company->town) ? $company->town : '',
			'[company_phone]' => !empty($company->phone) ? $company->phone : '',
			'[company_fax]' => !empty($company->fax) ? $company->fax : '',
			'[company_email]' => !empty($company->email) ? $company->email : '',
			'[company_url]' => !empty($company->url) ? $company->url : '',
			'[company_capital]' => !empty($company->capital) ? $company->capital : '',
		];

		$replacedMsg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add('Seven', 'replaced_msg: ' . $replacedMsg);
		return $replacedMsg;
	}

	public function getKeywords(): array {
		return [
			'invoice' => [
				'id',
				'ref',
				'total_ttc',
				'note_public',
				'note_private',
			],
			'company' => [
				'company_id',
				'company_name',
				'company_alias_name',
				'company_address',
				'company_zip',
				'company_town',
				'company_phone',
				'company_fax',
				'company_email',
				'company_url',
				'company_capital',
			],
		];
	}

	public function render(): void {
		global $langs, $db;

		$settings = $this->getSettings();
		$sms_obj = new SMS_Setting($db);
		$sms_setting = $sms_obj->getSettings();
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		print dol_get_fiche_head(sevenAdminPrepareHead(), 'invoice', $langs->trans($this->page_name), -1);

		foreach ($this->notificationMessages ?? [] as $notification)
			dol_htmloutput_mesg($notification['message'], [], $notification['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>

		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
			<table class='border seven-table'>
				<input type='hidden' name='token' value='<?= newToken(); ?>'/>
				<input type='hidden' name='action' value='<?= 'update_' . $this->context ?>'/>
				<input type='hidden' name='reminder_settings' value=''>
				<tr>
					<td width='200px'><?= $langs->trans('sms_form_enable_notification') ?></td>
					<td>
						<label for='enable'>
							<input type='hidden' name='enable' value='off'/>
							<input id='enable' type='checkbox'
								   name='enable' <?= ($settings->enable == 'on') ? 'checked' : '' ?> />
							<?= $langs->trans('seven_' . $this->context . '_enable') ?>
						</label>
					</td>
				</tr>
				<tr>
					<td width='200px'><?= $langs->trans('sms_form_send_from') ?></td>
					<td>
						<input type='text' name='send_from' value='<?= $settings->send_from ?>'>
					</td>
				</tr>
				<tr>
					<td width='200px'><?= $langs->trans('sms_form_send_on') ?></td>
					<td>
						<?php foreach ($settings->send_on as $key => $value): ?>
							<label for='send_on_ <?= $key ?>'>
								<input type='hidden' name='send_on_<?= $key ?>' value='off'/>
								<input id='send_on_<?= $key ?>'
									   name='send_on_<?= $key ?>' <?= ($value == 'on') ? 'checked' : '' ?>
									   type='checkbox'/>

								<?= $langs->trans('seven_' . $this->context . '_send_on_' . $key) ?>
							</label>
						<?php endforeach ?>
					</td>
				</tr>
				<tr>
					<td width='200px'>Reminder Management:</td>
					<td>
						<table id='sms-reminder-table' class='smsGenericTable'></table>
						<button type='button' id='sms_add_reminder'>Add reminder</button>
					</td>
				</tr>
				<?php foreach ($settings->sms_templates as $key => $value): ?>
					<tr>
						<td width='200px'><?= $langs->trans('seven_' . $this->context . '_sms_templates_' . $key) ?></td>
						<td>
							<label for='sms_templates_<?= $key ?>'>
								<textarea id='sms_templates_ <?= $key ?>'
										  name='sms_templates_' <?= $key ?> cols='40'
										  rows='4'><?= $value ?></textarea>
							</label>
							<button type='button' class='seven_open_keyword'
									data-attr-target='sms_templates_<?= $key ?>'>
								Insert Placeholders
							</button>
						</td>
					</tr>
				<?php endforeach ?>
			</table>

			<div style='text-align:center'>
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('sms_form_save') ?>'>
			</div>
		</form>

		<script>
			const entityKeywords = <?= json_encode($this->getKeywords()); ?>;
			const settings = <?= json_encode($this->getSettings(), true); ?>;
			const smsSetting = <?= json_encode($sms_setting, true) ?>;
			const reminderTableData = settings.reminder_settings ?? [] // TODO - populate

			populateReminderTable()

			$(document).ready(function () {
				const $addReminderModal = $('#add-reminder-modal')
				const div = $('<div />').appendTo('body')
				div.attr('id', 'keyword-modal')
				div.attr('class', 'modal')
				div.attr('style', 'display: none;')

				const reminderDiv = $('<div />').appendTo('body')
				reminderDiv.attr('id', 'add-reminder-modal')
				reminderDiv.attr('class', 'modal')
				reminderDiv.attr('style', 'display: none;max-width: 650px;')

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target')
					caretPosition = document.getElementById(target).selectionStart

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3)

						let tableCode = ''
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) tableCode += `<table class='widefat fixed striped'><tbody>`

							tableCode += '<tr>'
							row.forEach(function (col) {
								tableCode += `<td class='column'><button class='button-link' onclick='sevenBindTextToField('${target}', '[${col}]')'>[${col}]</button></td>`
							})
							tableCode += '</tr>'

							if (rowIndex === chunkedKeywords.length - 1) tableCode += '</tbody></table>'
						})

						return tableCode
					}

					const $keywordModal = $('#keyword-modal')
					$keywordModal.off()
					$keywordModal.on($.modal.AFTER_CLOSE, function () {
						const $elem = document.getElementById(target)
						$elem.focus()
						$elem.setSelectionRange(caretPosition, caretPosition)
					})

					let mainTable = ''
					for (let [key, value] of Object.entries(entityKeywords))
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>` + buildTable(value)
					mainTable +=
						`<div style='margin-top:10px'><small>*Press on placeholder to add to template.</small></div>`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
				})

				$('#sms_add_reminder').click(() => {
					getSMSReminderSkeleton()
					$addReminderModal.modal()
				})

				getSMSReminderSkeleton = function (uuid, action = 'add') {
					setting = getSetting(uuid)

					if (Object.keys(setting).length === 0)
						uuid = Date.now().toString(36) + Math.random().toString(36).substr(2)

					msgid = `msgid_${uuid}`
					reminder_delay = `reminder_delay_${uuid}`
					reminder_type = `reminder_type_${uuid}`
					reminder_delay_type = `reminder_delay_type_${uuid}`
					reminder_time = `reminder_time_${uuid}`

					const message = setting.message
					const reminderType = setting.reminder_type
					const reminderDelayType = setting.reminder_delay_type

					let reminderDelayElem = `
						<input id='${reminder_delay}' name='${reminder_delay}' type='number' min='1' style='width: 50px;margin-bottom: 5px' value='${setting.reminder_delay || 1}'>
							<select id='${reminder_type}' name='${reminder_type}'>
						`
					if (reminderType === 'days') reminderDelayElem += `<option selected value='days'>days</option>`
				else
					reminderDelayElem += `<option value='days'>days</option>`

					if (reminderType === 'hours') reminderDelayElem += `<option selected value='hours'>hours</option>`
				else
					reminderDelayElem += `<option value='hours'>hours</option>`

					reminderDelayElem += '</select></input>'

					let reminderDelayTypeElem = `
						<select id='${reminder_delay_type}' name='${reminder_delay_type}'>
					`
					if (reminderDelayType === 'before')
						reminderDelayTypeElem += `<option selected value='before'>before</option>`
				else
					reminderDelayTypeElem += `<option value='before'>before</option>`
					if (reminderDelayType === 'after')
						reminderDelayTypeElem += `<option selected value='after'>after</option>`
				else
					reminderDelayTypeElem += `<option value='after'>after</option>`
					reminderDelayTypeElem += '</select>'

					let elementToAdd = `
						<h3>${ucFirst(action)} SMS reminder</h3>
						<div class='reminder-errors'></div>

						<div class='row'>
							<div class='col-3'>
								<p>Reminder criteria</p>
							</div>
							<div class='col-9'>
								<label for='reminder'>
									<input type='hidden' id='uuid' name='uuid' value='${uuid}' />
${reminderDelayElem}${reminderDelayTypeElem}
					`
					elementToAdd += `
									invoice due date at
									<input type='text' name='${reminder_time}' id='imepicker' style='width:75px' value='${setting.reminder_time || ''}' />
					</label>
					</div>
					</div>
					<div class='row'>
					<div class='col-3'>
					<p>SMS Message</p>
					</div>
					<div class='col-9'>
					<textarea id='${msgid}' name='${msgid}' cols='40' rows='4'>${message || ''}</textarea>
					</div>
					</div>

					<p>Personalise your SMS with keywords below</p>
					<div id='sms_reminder_modal_keyword' style='border: 1px solid;margin: 5px 0;padding: 0 10px;'>
					</div>
					<div>
					<button type='button' onclick='saveReminder(\'${uuid}\')'>Save</button>
					<button type='button' onclick='cancelReminder()'>Cancel</button>
					</div>
					`


					$addReminderModal.html(elementToAdd)
					// https://github.com/jonthornton/jquery-timepicker#timepicker-plugin-for-jquery
					$('#timepicker').timepicker({
						minTime: smsSetting.SEVEN_ACTIVE_HOUR_START,
						maxTime: smsSetting.SEVEN_ACTIVE_HOUR_END,
					})
					const target = msgid
					caretPosition = document.getElementById(target).selectionStart

					$addReminderModal.off()
					$(`#${msgid}`).blur(function () {
						caretPosition = document.getElementById(target).selectionStart
					})

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3)

						let tableCode = ''
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) tableCode += `<table class='widefat fixed striped'><tbody>`

							tableCode += '<tr>'
							row.forEach(function (col) {
								tableCode +=
									`<td class='column'><button class='button-link' onclick='sevenBindTextToField(\'${target}\', \'[${col}]\')'>[${col}]</button></td>`
							})
							tableCode += '</tr>'

							if (rowIndex === chunkedKeywords.length - 1) tableCode += '</tbody></table>'
						})

						return tableCode
					}

					let mainTable = ''
					for (let [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

					mainTable += `<div style='margin-top: 10px'><small>*Press on placeholder to add to template.</small></div>`

					$('#sms_reminder_modal_keyword').html(mainTable)

					return elementToAdd
				}

				window.sevenInvoice = {
					getSetting(uuid) {
						for (const setting of reminderTableData) if (setting.uuid === uuid) return setting
						return {}
					},
					editReminder(uuid) {
						getSMSReminderSkeleton(uuid, 'edit')
						$addReminderModal.modal()
					},
					saveReminder(uuid) {
						// validate first, then repopulate the table;
						let message = $(`#msgid_${uuid}`).val()
						let reminder_delay = $(`#reminder_delay_${uuid}`).val()
						let reminder_type = $(`#reminder_type_${uuid}`).val()
						let reminder_delay_type = $(`#reminder_delay_type_${uuid}`).val()
						let reminder_time = $(`input[name='reminder_time_${uuid}'`).val()
						let sms_reminder_obj = {
							uuid,
							message,
							reminder_delay,
							reminder_type,
							reminder_delay_type,
							reminder_time,
						}

						if (!validateSMSReminder(sms_reminder_obj)) return

						// save the setting.
						let setting_found = false
						for (let i = 0; i < reminderTableData.length; i++) {
							if (reminderTableData[i].uuid === uuid) {
								reminderTableData[i] = sms_reminder_obj
								setting_found = true
							}
						}
						if (!setting_found) reminderTableData.push(sms_reminder_obj)

						populateReminderTable()

						$('.close-modal').click()
					},
					deleteReminder(uuid) {
						const confirmed = confirm('Are you sure you want to delete this reminder?')

						if (confirmed)
							for (let i = 0; i < reminderTableData.length; i++)
								if (reminderTableData[i].uuid === uuid) reminderTableData.splice(i, 1)

						populateReminderTable()
					},

					cancelReminder() {
						$addReminderModal.modal().hide()
						$('.jquery-modal.blocker.current').hide()
					},
					validateSMSReminder(sms_reminder_obj) {
						let validationFlag = 0
						const reminderErrors = []
						const uuid = sms_reminder_obj.uuid
						if (!sms_reminder_obj.reminder_delay) {
							reminderErrors.push('Reminder delay is required </br>')
							$(`#reminder_delay_${uuid}`).css({'border-bottom-color': 'red'})
							validationFlag++
						}
						if (!sms_reminder_obj.reminder_time) {
							reminderErrors.push('Reminder time is required </br>')
							$(`input[name='reminder_time_${uuid}'`).css({'border-bottom-color': 'red'})
							validationFlag++
						}
						if (!sms_reminder_obj.messageage) {
							reminderErrors.push('SMS message is required </br>')
							$(`#msgid_${uuid}`).css({'border-bottom-color': 'red'})
							validationFlag++
						}
						reminderErrors.join('<br>')
						$('.reminder-errors').html(reminderErrors).addClass('error')

						return validationFlag === 0
					}
				}
			})

			function populateReminderTable() {
				let elementToAdd = `<thead>
					<tr>
					<th>Action</th>
					<th>SMS reminder condition</th>
					</tr>
					</thead>
					<tbody>`
				reminderTableData.forEach((element) => {
					const uuid = element.uuid
					elementToAdd +=
						`<tr>
					<td><a onclick='editReminder(\'${uuid}\')' href='#'>Edit</a> <a onclick='deleteReminder('${uuid}')' href='#'>Delete</a></td>
					<td>${element.reminder_delay} ${element.reminder_type} ${element.reminder_delay_type} invoice due date at ${element.reminder_time}</td>
					</tr>`
				})
				elementToAdd += '</tbody>'

				$(`input[name='reminder_settings']`).val(JSON.stringify(reminderTableData))

				document.getElementById('sms-reminder-table').innerHTML = elementToAdd
			}

			function sevenBindTextToField(target, keyword) {
				const startStr = document.getElementById(target).value.substring(0, caretPosition)
				const endStr = document.getElementById(target).value.substring(caretPosition)
				document.getElementById(target).value = startStr + keyword + endStr
				caretPosition += keyword.length
			}
		</script>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
