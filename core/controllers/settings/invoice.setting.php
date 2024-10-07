<?php
require_once DOL_DOCUMENT_ROOT . "/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT . "/core/class/html.form.class.php";

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/EventLogger.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven_sms_reminder.db.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");
dol_include_once("/seven/core/controllers/settings/sms_setting.php");

class SMS_Invoice_Setting extends SevenBaseSettingController {
	var $form;
	var $errors;
	var $log;
	var $page_name;
	var $context;
	var $db;
	public $trigger_events = [
		"BILL_CREATE",
		"BILL_MODIFY",
		"BILL_VALIDATE",
		"BILL_CANCEL",
		"BILL_PAYED",
		"BILL_UNPAYED",
	];

	public $db_key = "SEVEN_INVOICE_SETTING";

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->context = 'invoice';
		$this->page_name = 'invoice_page_title';
	}

	private function get_default_settings() {
		$settings = [
			"enable" => "off",
			"send_from" => "",
			"send_on" => [
				"created" => "on",
				"updated" => "off",
				"validated" => "off",
				"paid" => "off",
			],
			"reminder_settings" => [],
			"sms_templates" => [
				"created" => "Hi [company_name], we are drafting your invoice now and will send it to you once it's ready.",
				"updated" => "Hi [company_name], your invoice ([ref]) of [total_ttc] has been updated.",
				"validated" => "Hi [company_name], your invoice ([ref]) of [total_ttc] is ready.",
				"paid" => "Hi [company_name], thank you for your payment, your invoice ([ref]) has been paid.",
			],
		];
		return json_encode($settings);
	}

	public function update_settings() {
		global $db, $user;

		if (!empty($_POST)) {
			if (!$user->rights->seven->permission->write) {
				accessforbidden();
			}
			// Handle Update
			// must json encode before updating
			$action = GETPOST("action");
			if ($action == "update_{$this->context}") {
				$settings = [
					"enable" => GETPOST("enable"),
					"send_from" => GETPOST("send_from"),
					"send_on" => [
						"created" => GETPOST("send_on_created"),
						"updated" => GETPOST("send_on_updated"),
						"validated" => GETPOST("send_on_validated"),
						"paid" => GETPOST("send_on_paid"),
					],
					"reminder_settings" => json_decode(GETPOST("reminder_settings", $check = ''), true),
					"sms_templates" => [
						"created" => GETPOST("sms_templates_created"),
						"updated" => GETPOST("sms_templates_updated"),
						"validated" => GETPOST("sms_templates_validated"),
						"paid" => GETPOST("sms_templates_paid"),
					],
				];

				$settings = json_encode($settings);

				$error = dolibarr_set_const($db, $this->db_key, $settings);
				if ($error < 1) {
					$this->log->add("Seven", "failed to update the invoice settings: " . print_r($settings, 1));
					$this->errors[] = "There was an error saving invoice settings.";
				}
				if (count($this->errors) > 0) {
					$this->add_notification_message("Failed to save invoice settings", "error");
				} else {
					$this->add_notification_message("Invoice settings saved");
				}

				$this->reschedule_sms_reminders();
			}
		}
	}

	public function get_settings() {

		global $conf, $db;

		$settings = [];
		if (property_exists($conf->global, $this->db_key)) {
			$settings = $conf->global->{$this->db_key};
			$decoded_setting = json_decode($settings);
			if (!property_exists($decoded_setting, "reminder_settings")) {
				$decoded_setting->reminder_settings = [];
				$settings = json_encode($decoded_setting);
			}
		} else {
			$settings = $this->get_default_settings();
		}
		return json_decode($settings);
	}

	public function bill_trigger_sms(Facture $object, $action) {
		global $db;

		if ($action === 'validated') {
			try {
				$this->schedule_sms_reminders($object);
			} catch (Exception $e) {
				$this->log->add("Seven", "Error occured when scheduling sms reminder");
				$this->log->add("Seven", $e->getTraceAsString());
			}
		}

		// check settings is it enabled.
		$settings = $this->get_settings();
		if ($settings->enable != 'on') {
			return;
		}
		$this->log->add("Seven", "current action triggered: {$action}");
		if ($settings->send_on->{$action} != 'on') {
			return;
		}

		$from = $settings->send_from;
		$thirdparty = new Societe($db);
		$thirdparty->fetch($object->socid);
		$to = validated_mobile_number($thirdparty->phone, $thirdparty->country_code);
		if (empty($to)) {
			return;
		}
		$message = $settings->sms_templates->{$action};
		$message = $this->replace_keywords_with_value($object, $message);

		$resp = seven_send_sms($from, $to, $message, "Automation");
		if ($resp['messages'][0]['status'] == 0) {
			EventLogger::create($object->socid, "thirdparty", "SMS sent to third party");
		} else {
			$err_msg = $resp['messages'][0]['err_msg'];
			EventLogger::create($object->socid, "thirdparty", "SMS failed to send to third party", "SMS failed due to: {$err_msg}");
		}
		$this->log->add("Seven", "SMS Successfully sent from fx bill_trigger_sms");
	}

	public function schedule_sms_reminders(Facture $object) {
		// when they added new SMS reminder option
		// reschedule all of them
		global $db;
		$this->log->add("Seven", "Scheduling SMS Reminders for object_id: {$object->id}");

		// invoice due date
		$invoice_overdue_date = $object->date_lim_reglement;
		$today = new DateTime("now", new DateTimeZone(getServerTimeZoneString()));

		$object_id = $object->id;
		$object_type = Facture::class;

		$sms_reminder_setting = $this->get_settings()->reminder_settings;

		$sms_reminder = new SevenSMSReminderDatabase($db);

		// delete all the settings for object_id and object_type here
		$delete_sql = "DELETE FROM {$sms_reminder->table_name}";
		$delete_sql .= sprintf(" WHERE `object_id` = %d AND `object_type` = '%s'",
			$object_id,
			$sms_reminder->db->escape($object_type),
		);
		$sms_reminder->db->query($delete_sql, 0, "ddl");

		foreach ($sms_reminder_setting as $setting) {

			$setting_uuid = $setting->uuid;
			$reminder_delay = $setting->reminder_delay; // 1
			$reminder_type = $setting->reminder_type; // days
			$reminder_delay_type = $setting->reminder_delay_type; // before
			$reminder_time = $setting->reminder_time; // 12:00am

			$dt = new DateTime("@{$invoice_overdue_date}");
			$dt->setTimezone(new DateTimeZone(getServerTimeZoneString()));
			// add 1 day = "P1D"
			// add 1 hour = "PT1H"
			$interval = 'P';
			if ($reminder_type == 'hours') {
				$interval .= "T";
			}
			$interval .= sprintf("%d%s", $reminder_delay, substr(strtoupper($reminder_type), 0, 1));
			if ($reminder_delay_type === 'after') {
				$dt->add(new DateInterval($interval));
			} else {
				$dt->sub(new DateInterval($interval));
			}
			$dt->setTime(date("H", strtotime($reminder_time)), date("i", strtotime($reminder_time)));

			if ($dt->getTimestamp() < $today->getTimestamp()) {
				$this->log->add("Seven", "Scheduled DT less than Today");
				$this->log->add("Seven", "Scheduled DT: {$dt->format("Y-m-d H:i:s e")}");
				$this->log->add("Seven", "Current DT: {$today->format("Y-m-d H:i:s e")}");
				continue;
			}

			$dt->setTimezone(new DateTimeZone("UTC"));
			$reminder_datetime = $dt->format("Y-m-d H:i:s");

			$sms_reminder->insert($setting_uuid, $object_id, $object_type, $reminder_datetime, null, 0);

		}
	}

	public function reschedule_sms_reminders() {
		global $db;
		$sms_reminder_db = new SevenSMSReminderDatabase($db);
		$sms_reminders = $sms_reminder_db->getAllWhere("object_type", '=', Facture::class);
		foreach ($sms_reminders as $reminder) {
			$object_id = $reminder['object_id'];
			$invoice_model = new Facture($db);
			$invoice_model->fetch($object_id);
			$this->schedule_sms_reminders($invoice_model);
		}
	}

	public function replace_keywords_with_value(Facture $object, $message) {
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

		$replaced_msg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add("Seven", "replaced_msg: " . $replaced_msg);
		return $replaced_msg;
	}

	public function get_keywords() {
		$keywords = [
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
		return $keywords;
	}

	public function render() {
		global $conf, $user, $langs, $db;
		$settings = $this->get_settings();
		$sms_obj = new SMS_Setting($db);
		$sms_setting = $sms_obj->get_settings();
		?>
		<!-- Begin form SMS -->

		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, 'invoice', $langs->trans($this->page_name), -1);

		if (!empty($this->notification_messages)) {
			foreach ($this->notification_messages as $notification_message) {
				dol_htmloutput_mesg($notification_message['message'], [], $notification_message['style']);
			}
		}

		?>
		<?php if ($this->errors) { ?>
			<?php foreach ($this->errors as $error) { ?>
				<p style="color: red;"><?= $error ?></p>
			<?php } ?>
		<?php } ?>

		<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>">
			<table class="border seven-table">
				<input type="hidden" name="token" value="<?= newToken(); ?>">
				<input type="hidden" name="action" value="<?= "update_{$this->context}" ?>">
				<input type="hidden" name="reminder_settings" value="">
				<!-- SMS Notification -->
				<tr>
					<td width="200px"><?= $langs->trans("sms_form_enable_notification") ?></td>
					<td>
						<label for="enable">
							<input type="hidden" name="enable" value="off"></input>
							<input id="enable" type="checkbox"
								   name="enable" <?= ($settings->enable == 'on') ? "checked" : '' ?>></input>
							<?= $langs->trans("seven_{$this->context}_enable") ?>
						</label>
					</td>
				</tr>
				<!-- SMS Sender -->
				<tr>
					<td width="200px"><?= $langs->trans("sms_form_send_from") ?></td>
					<td>
						<input type="text" name="send_from" value="<?= $settings->send_from ?>">
					</td>
				</tr>

				<!-- SMS Send On -->
				<tr>
					<td width="200px"><?= $langs->trans("sms_form_send_on") ?></td>
					<td>

						<?php foreach ($settings->send_on as $key => $value) { ?>
							<label for="<?= "send_on_{$key}" ?>">
								<input type="hidden" name="<?= "send_on_{$key}" ?>" value="off"></input>
								<input id="<?= "send_on_{$key}" ?>"
									   name="<?= "send_on_{$key}" ?>" <?= ($value == 'on') ? "checked" : '' ?>
									   type="checkbox">
								</input>

								<?= $langs->trans("seven_{$this->context}_send_on_{$key}") ?>
							</label>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<td width="200px">Reminder Management:</td>
					<td>
						<table id="sms-reminder-table" class="sms-generic-table">

						</table>
						<button type="button" id="sms_add_reminder">Add reminder</button>
					</td>
				</tr>

				<!-- SMS Templates  -->

				<?php foreach ($settings->sms_templates as $key => $value) { ?>
					<tr>
						<td width="200px"><?= $langs->trans("seven_{$this->context}_sms_templates_{$key}") ?></td>
						<td>
							<label for="<?= "sms_templates_{$key}" ?>">
								<textarea id="<?= "sms_templates_{$key}" ?>"
										  name="<?= "sms_templates_{$key}" ?>" cols="40"
										  rows="4"><?= $value ?></textarea>
							</label>
							<p>Customize your SMS with keywords
								<button type="button" class="seven_open_keyword"
										data-attr-target="<?= "sms_templates_{$key}" ?>">
									Keywords
								</button>
							</p>
						</td>
					</tr>
				<?php } ?>
			</table>
			<!-- Submit -->
			<p>SMS will be sent to the associated third party</p>

			<center>
				<input class="button" type="submit" name="submit" value="<?= $langs->trans("sms_form_save") ?>">
			</center>
		</form>

		<script>
			const entity_keywords = <?= json_encode($this->get_keywords()); ?>;
			sms_reminder_form_errors = [];
			const settings = <?= json_encode($this->get_settings(), true); ?>;
			const sms_setting = <?= json_encode($sms_setting, true) ?>;
			let reminderTableData = settings.reminder_settings; // TODO
			// let reminderTableData = [
			//     {
			//         uuid: "123",
			//         message: "Hi this is sms template",
			//         reminder_delay: 1,
			//         reminder_type: "hours",
			//         reminder_delay_type: "after",
			//         reminder_time: "12:45pm",
			//     },
			//     {
			//         uuid: "456",
			//         message: "Hi this is sms template",
			//         reminder_delay: 1,
			//         reminder_type: "hours",
			//         reminder_delay_type: "after",
			//         reminder_time: "12:45am",
			//     },
			// ];
			populateReminderTable();

			jQuery(function ($) {
				var div = $('<div />').appendTo('body');
				div.attr('id', `keyword-modal`);
				div.attr('class', "modal");
				div.attr('style', "display: none;");

				var reminder_div = $('<div />').appendTo('body');
				reminder_div.attr('id', `add-reminder-modal`);
				reminder_div.attr('class', "modal");
				reminder_div.attr('style', "display: none;max-width: 650px;");

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target');
					caretPosition = document.getElementById(target).selectionStart;

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3);

						let tableCode = '';
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) {
								tableCode += '<table class="widefat fixed striped"><tbody>';
							}

							tableCode += '<tr>';
							row.forEach(function (col) {
								tableCode += `<td class="column"><button class="button-link" onclick="seven_bind_text_to_field('${target}', '[${col}]')">[${col}]</button></td>`;
							});
							tableCode += '</tr>';

							if (rowIndex === chunkedKeywords.length - 1) {
								tableCode += '</tbody></table>';
							}
						});

						return tableCode;
					};

					$('#keyword-modal').off();
					$('#keyword-modal').on($.modal.AFTER_CLOSE, function () {
						document.getElementById(target).focus();
						document.getElementById(target).setSelectionRange(caretPosition, caretPosition);
					});

					let mainTable = '';
					for (let [key, value] of Object.entries(entity_keywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`;
						mainTable += buildTable(value);
					}

					mainTable += '<div style="margin-top: 10px"><small>*Press on keyword to add to sms template</small></div>';

					$('#keyword-modal').html(mainTable);
					$('#keyword-modal').modal();
				});

				$("#sms_add_reminder").click(function (e) {

					getSMSReminderSkeleton();
					$('#add-reminder-modal').modal();
				})

			});

			$(document).ready(function () {

				getSMSReminderSkeleton = function (uuid, action = "add") {
					setting = getSetting(uuid);
					if (Object.keys(setting).length !== 0) {
						uuid = uuid;
						msgid = `msgid_${uuid}`;
						reminder_delay = `reminder_delay_${uuid}`;
						reminder_type = `reminder_type_${uuid}`;
						reminder_delay_type = `reminder_delay_type_${uuid}`;
						reminder_time = `reminder_time_${uuid}`;

					} else {
						uuid = generateUUID();
						msgid = `msgid_${uuid}`;
						reminder_delay = `reminder_delay_${uuid}`;
						reminder_type = `reminder_type_${uuid}`;
						reminder_delay_type = `reminder_delay_type_${uuid}`;
						reminder_time = `reminder_time_${uuid}`;
					}

					let message = setting.message;
					let reminder_delay_value = setting.reminder_delay;
					let reminder_type_value = setting.reminder_type;
					let reminder_delay_type_value = setting.reminder_delay_type;
					let reminder_time_value = setting.reminder_time;

					let reminder_delay_element = `
						<input id=${reminder_delay} name=${reminder_delay} type="number" min="1" style="width: 50px;margin-bottom: 5px" value=${reminder_delay_value || 1}>
							<select id=${reminder_type} name=${reminder_type}>
						`
					if (reminder_type_value === "days") {
						reminder_delay_element += '<option selected value="days">days</option>'
					} else {
						reminder_delay_element += '<option value="days">days</option>'
					}

					if (reminder_type_value === "hours") {
						reminder_delay_element += '<option selected value="hours">hours</option>'
					} else {
						reminder_delay_element += '<option value="hours">hours</option>'
					}

					reminder_delay_element += `
							</select>
						</input>`

					let reminder_delay_type_element = `
						<select id=${reminder_delay_type} name=${reminder_delay_type}>
					`
					if (reminder_delay_type_value === 'before') {
						reminder_delay_type_element += '<option selected value="before">before</option>';
					} else {
						reminder_delay_type_element += '<option value="before">before</option>'
					}
					if (reminder_delay_type_value === 'after') {
						reminder_delay_type_element += '<option selected value="after">after</option>';
					} else {
						reminder_delay_type_element += '<option value="after">after</option>'
					}
					reminder_delay_type_element += `
						</select>
					`

					let elementToAdd = `
						<h3>${ucFirst(action)} SMS reminder</h3>
						<div class="reminder-errors"></div>

						<div class="row">
							<div class="col-3">
								<p>Reminder criteria</p>
							</div>
							<div class="col-9">
								<label for="reminder">
									<input type="hidden" id="uuid" name="uuid" value=${uuid}></input>
					`
					elementToAdd += reminder_delay_element;
					elementToAdd += reminder_delay_type_element;
					elementToAdd += `

									invoice due date at
									<input type="text" name=${reminder_time} id="timepicker" style="width:75px" value=${reminder_time_value || ""}>

									<!-- valid time must be between active hours in SMS setting if not, show all  -->

					</label>
					</div>
					</div>
					<div class="row">
					<div class="col-3">
					<p>SMS message</p>
					</div>
					<div class="col-9">
					<textarea id=${msgid} name=${msgid} cols="40" rows="4">${message || ""}</textarea>
					</div>
					</div>

					<p>Personalise your SMS with keywords below</p>
					<div id="sms_reminder_modal_keyword" style="border: 1px solid;margin: 5px 0px;padding: 0px 10px;">

					</div>
					<div>
					<button type="button" onclick="saveReminder('${uuid}')">
					Save
					</button>
					<button type="button" onclick="cancelReminder('${uuid}')">
					Cancel
					</button>
					</div>
					`






																									$("#add-reminder-modal").html(elementToAdd);
																									// https://github.com/jonthornton/jquery-timepicker#timepicker-plugin-for-jquery
																									$('#timepicker').timepicker({
																										minTime: sms_setting.SEVEN_ACTIVE_HOUR_START,
																										maxTime: sms_setting.SEVEN_ACTIVE_HOUR_END,
																									});
																									const target = msgid
																									caretPosition = document.getElementById(target).selectionStart;

																									$('#add-reminder-modal').off();
																									$(




					`#${msgid}`




																				).blur( function() {
																										caretPosition = document.getElementById(target).selectionStart;
																									});

																									const buildTable = function(keywords) {
																										const chunkedKeywords = keywords.toChunk(3);

																										let tableCode = '';
																										chunkedKeywords.forEach(function(row, rowIndex) {
																											if (rowIndex === 0) {
																												tableCode += '<table class="widefat fixed striped"><tbody>';
																											}

																											tableCode += '<tr>';
																											row.forEach(function(col) {
																												tableCode +=




					`<td class="column"><button class="button-link" onclick="seven_bind_text_to_field('${target}', '[${col}]')">[${col}]</button></td>`




																				;
																											});
																											tableCode += '</tr>';

																											if (rowIndex === chunkedKeywords.length - 1) {
																												tableCode += '</tbody></table>';
																											}
																										});

																										return tableCode;
																									};

																									let mainTable = '';
																									for (let [key, value] of Object.entries(entity_keywords)) {
																										mainTable +=




					`<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`




																				;
																										mainTable += buildTable(value);
																									}

																									mainTable += '<div style="margin-top: 10px"><small>*Press on keyword to add to sms template</small></div>';

																									$('#sms_reminder_modal_keyword').html(mainTable);

																									return elementToAdd;
																								}

																								getSetting = function(uuid) {
																									for (const setting of reminderTableData) {
																										if(setting.uuid === uuid) {
																											return setting;
																										}
																									}
																									return {};
																								}

																								saveReminder = function (uuid) {
																									// validate first
																									// repopulate the table;
																									let message = $(




					`#msgid_${uuid}`




																				).val();
																									let reminder_delay = $(




					`#reminder_delay_${uuid}`




																				).val();
																									let reminder_type = $(




					`#reminder_type_${uuid}`




																				).val();
																									let reminder_delay_type = $(




					`#reminder_delay_type_${uuid}`




																				).val();
																									let reminder_time = $(




					`input[name="reminder_time_${uuid}"`




																				).val();
																									let sms_reminder_obj = {
																										uuid,
																										message,
																										reminder_delay,
																										reminder_type,
																										reminder_delay_type,
																										reminder_time,
																									}

																									if(!validateSMSReminder(sms_reminder_obj)) { return; }

																									// save the setting.
																									let setting_found = false;
																									for(var i=0;i<reminderTableData.length;i++) {
																										if(reminderTableData[i].uuid === uuid) {
																											reminderTableData[i] = sms_reminder_obj;
																											setting_found = true;
																										}
																									}
																									if(!setting_found) {
																										reminderTableData.push(sms_reminder_obj);
																									}

																									populateReminderTable();

																									$(".close-modal").click();

																								}

																								editReminder = function (uuid) {
																									getSMSReminderSkeleton(uuid, "edit");
																									$('#add-reminder-modal').modal();
																								}

																								deleteReminder = function (uuid) {
																									const confirmed = confirm("Are you sure you want to delete this reminder?");

																									if(confirmed) {
																										for(var i=0;i<reminderTableData.length;i++) {
																											if(reminderTableData[i].uuid === uuid) {
																												reminderTableData.splice(i, 1);
																											}
																										}
																									}

																									populateReminderTable();
																								}

																								cancelReminder = function (uuid) {
																									$('#add-reminder-modal').modal().hide();
																									$(".jquery-modal.blocker.current").hide();
																								}

																								validateSMSReminder = function (sms_reminder_obj) {
																									let validation_flag = 0;
																									let reminder_errors = [];
																									let uuid = sms_reminder_obj.uuid;
																									let message = sms_reminder_obj.message;
																									let reminder_delay = sms_reminder_obj.reminder_delay;
																									let reminder_type = sms_reminder_obj.reminder_type;
																									let reminder_delay_type = sms_reminder_obj.reminder_delay_type;
																									let reminder_time = sms_reminder_obj.reminder_time;
																									if(!reminder_delay) {
																										reminder_errors.push("Reminder delay is required </br>");
																										$(




					`#reminder_delay_${uuid}`




																				).css({"border-bottom-color":"red"});
																										validation_flag++;
																									}
																									if(!reminder_time) {
																										reminder_errors.push("Reminder time is required </br>");
																										$(




					`input[name="reminder_time_${uuid}"`




																				).css({"border-bottom-color":"red"});
																										validation_flag++;
																									}
																									if(!message) {
																										reminder_errors.push("SMS message is required </br>");
																										$(




					`#msgid_${uuid}`




																				).css({"border-bottom-color":"red"});
																										validation_flag++;
																									}
																									reminder_errors.join("<br>");
																									$(".reminder-errors").html(reminder_errors).addClass("error");

																									return validation_flag === 0;
																								}
																							})

																							function populateReminderTable() {
																								/*
																								[
																									{
																										uuid: "123123123",
																										message: "Hi this is sms template",
																										reminder_delay: 1,
																										reminder_type: "hours",
																										reminder_delay_type: "after",
																										reminder_time: "12:45",
																									}
																								]
																								*/

																								let elementToAdd =




					`<thead>
					<tr>
					<th>Action</th>
					<th>SMS reminder condition</th>
					</tr>
					</thead>`




																				;
																								elementToAdd +=




					`<tbody>`




																				;

																								reminderTableData.forEach(( element ) => {
																									let uuid = element.uuid;
																									elementToAdd +=




					`<tr>
					<td><a onclick="editReminder('${uuid}')" href="#">Edit</a> <a onclick="deleteReminder('${uuid}')" href="#">Delete</a></td>
					<td>${element.reminder_delay} ${element.reminder_type} ${element.reminder_delay_type} invoice due date at ${element.reminder_time}</td>
					</tr>`





																								});
																								elementToAdd +=




					`</tbody>`;

					$('input[name="reminder_settings"]').val(JSON.stringify(reminderTableData));

					let sms_reminder_table = document.getElementById(`




																				sms-reminder-table




					`);
					sms_reminder_table.innerHTML = elementToAdd;
					}

				function generateUUID() {
					return Date.now().toString(36) + Math.random().toString(36).substr(2);
				}

				function seven_bind_text_to_field(target, keyword) {
					const startStr = document.getElementById(target).value.substring(0, caretPosition);
					const endStr = document.getElementById(target).value.substring(caretPosition);
					document.getElementById(target).value = startStr + keyword + endStr;
					caretPosition += keyword.length;
				}

		</script>
		<?php
		// Page end
		print dol_get_fiche_end();

		llxFooter();
		?>
		<?php
	}
}
