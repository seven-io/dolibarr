<?php

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/EventLogger.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");

class SMS_SalesOrder_Setting extends SevenBaseSettingController {
	var $form;
	var $errors;
	var $log;
	var $page_name;
	var $db;
	var $context;
	public $trigger_events = [
		'ORDER_CREATE',
		'ORDER_VALIDATE',
		'ORDER_MODIFY',
		'ORDER_DELETE',
		'ORDER_CANCEL',
		// 'ORDER_SENTBYMAIL',
		'ORDER_CLASSIFY_BILLED',
	];

	public $db_key = "SEVEN_SALES_ORDER_SETTING";

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->context = 'sales_order';
		$this->page_name = 'sales_order_page_title';
	}

	private function get_default_settings() {
		$settings = [
			"enable" => "off",
			"send_from" => "",
			"send_on" => [
				"created" => "on",
				"validated" => "off",
				"modified" => "off",
				"deleted" => "off",
				"canceled" => "off",
				"classify_billed" => "off",
			],
			"sms_templates" => [
				"created" => "Hi [company_name], we are drafting your sales order and will send to you once it's validated.",
				"validated" => "Hi [company_name], your sales order ([ref]) of [total_ttc] has been validated.",
				"modified" => "Hi [company_name], here's our sales order ([ref]) of [total_ttc] has been updated.",
				"deleted" => "Hi [company_name], sales order ([ref]) has been deleted.",
				"canceled" => "Hi [company_name], sales order ([ref]) has been canceled.",
				"classify_billed" => "Hi [company_name], thank you for your payment, your invoice ([ref]) has been paid.",
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
						"validated" => GETPOST("send_on_validated"),
						"modified" => GETPOST("send_on_modified"),
						"deleted" => GETPOST("send_on_deleted"),
						"canceled" => GETPOST("send_on_canceled"),
						"classify_billed" => GETPOST("send_on_classify_billed"),
					],
					"sms_templates" => [
						"created" => GETPOST("sms_templates_created"),
						"validated" => GETPOST("sms_templates_validated"),
						"modified" => GETPOST("sms_templates_modified"),
						"deleted" => GETPOST("sms_templates_deleted"),
						"canceled" => GETPOST("sms_templates_canceled"),
						"classify_billed" => GETPOST("sms_templates_classify_billed"),
					],
				];

				$settings = json_encode($settings);

				$error = dolibarr_set_const($db, $this->db_key, $settings);
				if ($error < 1) {
					$this->log->add("Seven", "failed to update the invoice settings: " . print_r($settings, 1));
					$this->errors[] = "There was an error saving invoice settings.";
				}
				if (count($this->errors) > 0) {
					$this->add_notification_message("Failed to save Sales Order settings", "error");
				} else {
					$this->add_notification_message("Sales Order settings saved");
				}
			}
		}
	}

	public function get_settings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) {
			$settings = $conf->global->{$this->db_key};
		} else {
			$settings = $this->get_default_settings();
		}
		return json_decode($settings);
	}

	public function trigger_sms(Commande $object, $action) {
		global $db;
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
		$this->log->add("Seven", "SMS Successfully sent from fx trigger_sms");
	}

	protected function replace_keywords_with_value(Commande $object, $message) {
		global $db;
		$company = new Societe($db);
		$company->fetch($object->socid);
		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[ref]' => !empty($object->newref) ? $object->newref : $object->ref,
			'[delivery_date]' => !empty($object->delivery_date) ? date('m/d/Y H:i:s', $object->delivery_date) : '',
			'[total_ttc]' => !empty($object->total_ttc) ? number_format($object->total_ttc, 2) : '',
			'[currency_code]' => !empty($object->multicurrency_code) ? $object->multicurrency_code : '',
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
			'order' => [
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
		global $conf, $user, $langs;
		$settings = $this->get_settings();
		?>
		<!-- Begin form SMS -->

		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, $this->context, $langs->trans($this->page_name), -1);

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
