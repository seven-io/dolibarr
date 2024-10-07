<?php

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/EventLogger.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");

require_once DOL_DOCUMENT_ROOT . "/core/class/html.form.class.php";
require_once DOL_DOCUMENT_ROOT . "/ticket/class/ticket.class.php";
require_once DOL_DOCUMENT_ROOT . "/user/class/user.class.php";

class SMS_Ticket_Setting extends SevenBaseSettingController {
	var $form;
	var $errors;
	var $log;
	var $page_name;
	var $db;
	var $context;
	public $trigger_events = [
		'TICKET_MODIFY',
		'TICKET_ASSIGNED',
		'TICKET_CREATE',
		'TICKET_CLOSE',
		'TICKET_DELETE',
	];

	public $db_key = "SEVEN_TICKET_SETTING";

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->context = 'ticket';
		$this->page_name = 'ticket_page_title';
	}

	private function get_default_settings() {
		$settings = [
			"enable" => "off",
			"send_from" => "",
			"send_on" => [
				"created" => "on",
				"closed" => "off",
				"read" => "off",
				"assigned" => "off",
			],
			"sms_templates" => [
				"created" => "Hi [company_name], your ticket: ([ticket_ref]) has been created. Our agents will attend to you shortly",
				"closed" => "Hi [company_name], your ticket: ([ticket_ref]) has been closed",
				"read" => "Hi [company_name], your ticket: ([ticket_ref]) has been read by [assigned_user_fullname]",
				"assigned" => "Hi [company_name], your ticket has been assigned to [assigned_user_fullname]",
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
			// Handle Update - must JSON encode before updating
			$action = GETPOST("action");
			if ($action == "update_{$this->context}") {
				$settings = [
					"enable" => GETPOST("enable"),
					"send_from" => GETPOST("send_from"),
					"send_on" => [
						"created" => GETPOST("send_on_created"),
						"closed" => GETPOST("send_on_closed"),
						"read" => GETPOST("send_on_read"),
						"assigned" => GETPOST("send_on_assigned"),
					],
					"sms_templates" => [
						"created" => GETPOST("sms_templates_created"),
						"closed" => GETPOST("sms_templates_closed"),
						"read" => GETPOST("sms_templates_read"),
						"assigned" => GETPOST("sms_templates_assigned"),
					],
				];

				$settings = json_encode($settings);

				$error = dolibarr_set_const($db, $this->db_key, $settings);
				if ($error < 1) {
					$this->log->add("Seven", "failed to update the ticket settings: " . print_r($settings, 1));
					$this->errors[] = "There was an error saving ticket settings.";
				}
				if (count($this->errors) > 0) {
					$this->add_notification_message("Failed to save ticket settings", "error");
				} else {
					$this->add_notification_message("Ticket settings saved");
				}
			}
		}
	}

	public function get_settings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) {
			$settings = $conf->global->{$this->db_key};
			$default_settings = $this->get_default_settings();
			$supported_statuses = array_keys(json_decode($default_settings, 1)['send_on']);
			$settings = json_decode($settings, 1);
			foreach ($settings['send_on'] as $key => $value) {
				if (!in_array($key, $supported_statuses)) {
					unset($settings['send_on'][$key]);
				}
			}
			$settings = json_encode($settings);

		} else {
			$settings = $this->get_default_settings();
		}
		return json_decode($settings);
	}

	public function delete_settings() {
		global $db, $conf;

		$deleted = dolibarr_del_const($db, $this->db_key);
		if ($deleted < 0) {
			$this->log->add("Seven", "there was an issue deleting {$this->db_key} settings");
		}
		$this->log->add("Seven", "Successfully deleted {$this->db_key} settings");

	}

	public function trigger_sms(Ticket $object, $action = '') {
		global $db;

		$settings = $this->get_settings();
		if ($settings->enable != 'on') { // check settings is it enabled.
			return;
		}

		if (empty($action)) {
			$ticket = new Ticket($db);
			$ticket->fetch($object->id);
			switch ($ticket->status) {
				case 0:
					$action = 'not_read';
					break;
				case 1:
					$action = 'read';
					break;
				case 2:
					$action = 'assigned';
					break;
				case 3:
					$action = 'in_progress';
					break;
				case 5:
					$action = 'need_more_info';
					break;
				case 7:
					$action = 'waiting';
					break;
				default:
					$action = 'not_available';
					break;
			}
		}

		if ($settings->send_on->{$action} != 'on') {
			return;
		}
		$this->log->add("Seven", "current ticket action triggered: {$action}");

		$company = new Societe($db);
		$company->fetch($object->fk_soc);

		$from = $settings->send_from;
		$to = validated_mobile_number($company->phone, $company->country_code);
		if (empty($to)) {
			$this->log->add("Seven", "SMS to is empty. Aborting SMS");
			return;
		}
		$message = $settings->sms_templates->{$action};
		$message = $this->replace_keywords_with_value($object, $message);

		$resp = seven_send_sms($from, $to, $message, "Automation");
		if ($resp['messages'][0]['status'] == 0) {
			EventLogger::create($object->fk_soc, "thirdparty", "SMS sent to third party");
		} else {
			$err_msg = $resp['messages'][0]['err_msg'];
			EventLogger::create($object->fk_soc, "thirdparty", "SMS failed to send to third party", "SMS failed due to: {$err_msg}");
		}
	}

	protected function replace_keywords_with_value(Ticket $object, $message) {
		global $db, $langs;
		$company = new Societe($db);
		$company->fetch($object->fk_soc);

		$assigned_user = new User($db);
		$assigned_user->fetch($object->fk_user_assign);
		$keywords = [
			'[ticket_id]' => !empty($object->id) ? $object->id : 'N/A',
			'[ticket_track_id]' => !empty($object->track_id) ? $object->track_id : 'N/A',
			'[ticket_priority]' => !empty($object->severity_label) ? $object->severity_label : 'N/A',
			'[ticket_ref]' => !empty($object->ref) ? $object->ref : 'N/A',
			'[ticket_status]' => !empty($object->getLibStatut(1)) ? $object->getLibStatut(1) : 'N/A',
			'[ticket_subject]' => !empty($object->subject) ? $object->subject : 'N/A',
			'[ticket_message]' => !empty($object->message) ? $object->message : 'N/A',
			'[assigned_user_town]' => !empty($assigned_user->town) ? $assigned_user->town : 'N/A',
			'[assigned_user_country]' => !empty($assigned_user->country) ? $assigned_user->country : 'N/A',
			'[assigned_user_country_code]' => !empty($assigned_user->country_code) ? $assigned_user->country_code : 'N/A',
			'[assigned_user_email]' => !empty($assigned_user->email) ? $assigned_user->email : 'N/A',
			'[assigned_user_note_public]' => !empty($assigned_user->note_public) ? $assigned_user->note_public : 'N/A',
			'[assigned_user_note_private]' => !empty($assigned_user->note_private) ? $assigned_user->note_private : 'N/A',
			'[assigned_user_firstname]' => !empty($assigned_user->firstname) ? $assigned_user->firstname : 'N/A',
			'[assigned_user_lastname]' => !empty($assigned_user->lastname) ? $assigned_user->lastname : 'N/A',
			'[assigned_user_fullname]' => !empty($assigned_user->getFullName($langs)) ? $assigned_user->getFullName($langs) : 'N/A',
			'[company_id]' => !empty($company->id) ? $company->id : 'N/A',
			'[company_name]' => !empty($company->name) ? $company->name : 'N/A',
			'[company_alias_name]' => !empty($company->name_alias) ? $company->name_alias : 'N/A',
			'[company_address]' => !empty($company->address) ? $company->address : 'N/A',
			'[company_zip]' => !empty($company->zip) ? $company->zip : 'N/A',
			'[company_town]' => !empty($company->town) ? $company->town : 'N/A',
			'[company_phone]' => !empty($company->phone) ? $company->phone : 'N/A',
			'[company_fax]' => !empty($company->fax) ? $company->fax : 'N/A',
			'[company_email]' => !empty($company->email) ? $company->email : 'N/A',
			'[company_url]' => !empty($company->url) ? $company->url : 'N/A',
			'[company_capital]' => !empty($company->capital) ? $company->capital : 'N/A',
		];

		$replaced_msg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add("Seven", "replaced_msg: " . $replaced_msg);
		return $replaced_msg;
	}

	public function get_keywords() {
		$keywords = [
			'ticket' => [
				'id',
				'track_id',
				'priority',
				'ref',
				'ticket_status',
				'subject',
				'message',
			],
			'Assigned User' => [
				'assigned_user_town',
				'assigned_user_country',
				'assigned_user_country_code',
				'assigned_user_email',
				'assigned_user_note_public',
				'assigned_user_note_private',
				'assigned_user_firstname',
				'assigned_user_lastname',
			],
			'Third Party' => [
				'company_id',
				'company_name',
				'company_alias_name',
				'company_address',
				'company_zip',
				'company_town',
				'company_phone',
				'company_fax',
				'company_email',
				'company_skype',
				'company_twitter',
				'company_facebook',
				'company_linkedin',
				'company_url',
				'company_capital',
			],
		];
		return $keywords;
	}

	public function render() {
		global $conf, $user, $langs, $db;
		$settings = $this->get_settings();
		?>
		<!-- Begin form SMS -->

		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, 'ticket', $langs->trans($this->page_name), -1);

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
			<input type="hidden" name="token" value="<?= newToken(); ?>">
			<input type="hidden" name="action" value="<?= "update_{$this->context}" ?>">

			<table class="border seven-table">
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
				<div>

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


				</div>
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
			jQuery(function ($) {
				var $div = $('<div />').appendTo('body');
				$div.attr('id', `keyword-modal`);
				$div.attr('class', "modal");
				$div.attr('style', "display: none;");

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
			});

			function seven_bind_text_to_field(target, keyword) {
				const startStr = document.getElementById(target).value.substring(0, caretPosition);
				const endStr = document.getElementById(target).value.substring(caretPosition);
				document.getElementById(target).value = startStr + keyword + endStr;
				caretPosition += keyword.length;
			}
		</script>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
