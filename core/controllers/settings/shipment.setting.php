<?php

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/EventLogger.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");
require_once DOL_DOCUMENT_ROOT . "/core/class/html.form.class.php";
require_once DOL_DOCUMENT_ROOT . "/commande/class/commande.class.php";
require_once DOL_DOCUMENT_ROOT . "/expedition/class/expedition.class.php";

class SMS_Shipment_Setting extends SevenBaseSettingController {
	var $form;
	var $errors;
	var $log;
	var $page_name;
	var $db;
	var $context;
	public $trigger_events = [
		"SHIPMENT_CREATE",
		"SHIPMENT_CLOSED",
		"SHIPMENT_VALIDATE",
		"ORDER_CLOSED",
	];

	public $db_key = "SEVEN_SHIPMENT_SETTING";

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->context = 'shipment';
		$this->page_name = 'shipment_page_title';
	}

	private function get_default_settings() {
		$settings = [
			"enable" => "off",
			"send_from" => "",
			"send_on" => [
				"created" => "on",
				"closed" => "on",
				"validated" => "on",
				"delivered" => "on",
			],
			"sms_templates" => [
				"created" => "Hi [company_name], your shipment has been created,",
				"closed" => "Hi [company_name], your shipment has been processed successfully,",
				"validated" => "Hi [company_name], your shipment is confirmed, it will be delivered via [shipping_method]. Tracking number: [tracking_number].",
				"delivered" => "Hi [company_name], your shipment has been delivered.",
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
						"validated" => GETPOST("send_on_validated"),
						"delivered" => GETPOST("send_on_delivered"),
					],
					"sms_templates" => [
						"created" => GETPOST("sms_templates_created"),
						"closed" => GETPOST("sms_templates_closed"),
						"validated" => GETPOST("sms_templates_validated"),
						"delivered" => GETPOST("sms_templates_delivered"),
					],
				];

				$settings = json_encode($settings);

				$error = dolibarr_set_const($db, $this->db_key, $settings);
				if ($error < 1) {
					$this->log->add("Seven", "failed to update the shipment settings: " . print_r($settings, 1));
					$this->errors[] = "There was an error saving shipment settings.";
				}
				if (count($this->errors) > 0) {
					$this->add_notification_message("Failed to save shipment settings", "error");
				} else {
					$this->add_notification_message("Shipment settings saved");
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

	public function trigger_send_sms($object, $status) {
		global $db;

		$settings = $this->get_settings();
		if ($settings->enable != 'on') { // check settings is it enabled.
			return;
		}
		if ($settings->send_on->{$status} != 'on') {
			return;
		}
		$this->log->add("Seven", "current status action triggered: {$status}");

		$from = $settings->send_from;
		$thirdparty = new Societe($db);
		$thirdparty->fetch($object->socid);
		$to = validated_mobile_number($thirdparty->phone, $thirdparty->country_code);
		if (empty($to)) {
			return;
		}
		$message = $settings->sms_templates->{$status};
		$message = $this->replace_keywords_with_value($object, $message);

		$resp = seven_send_sms($from, $to, $message, "Automation");
		if ($resp['messages'][0]['status'] == 0) {
			EventLogger::create($object->socid, "thirdparty", "SMS sent to third party");
		} else {
			$err_msg = $resp['messages'][0]['err_msg'];
			EventLogger::create($object->socid, "thirdparty", "SMS failed to send to third party", "SMS failed due to: {$err_msg}");
		}
	}

	protected function replace_keywords_with_value($object, $message) {
		global $db;
		$company = new Societe($db);
		$company->fetch($object->socid);
		$order = new Commande($db);
		$order = get_class($object) == 'Commande' ? $object : $order->fetch($object->origin_id);

		if ($object instanceof Commande) {
			$order->fetchObjectLinked();
			$object = !empty($order->linkedObjects['shipping']) ? end($order->linkedObjects['shipping']) : new Expedition($this->db);
		}

		$dt = DateTime::createFromFormat('U', $object->date_delivery);

		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[ref]' => !empty($object->newref) ? $object->newref : $object->ref,
			'[tracking_number]' => !empty($object->tracking_number) ? $object->tracking_number : '',
			'[planned_date_of_delivery]' => !empty($object->date_delivery) ? $dt->format("d F Y") : '',
			'[weight]' => !empty($object->trueWeight) ? $object->trueWeight : '',
			'[width]' => !empty($object->trueWidth) ? $object->trueWidth : '',
			'[depth]' => !empty($object->trueDepth) ? $object->trueDepth : '',
			'[shipping_method]' => !empty($object->shipping_method) ? $object->shipping_method : '',
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

			'[order_id]' => !empty($order->id) ? $order->id : '',
			'[order_ref]' => !empty($order->newref) ? $order->newref : $order->ref,
			'[order_payment_terms]' => !empty($order->cond_reglement) ? $order->cond_reglement : '',
			'[order_payment_method]' => !empty($order->mode_reglement) ? $order->mode_reglement : '',
			'[order_availability_delay]' => !empty($order->availability) ? $order->availability : '',
		];

		$replaced_msg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add("Seven", "replaced_msg: " . $replaced_msg);
		return $replaced_msg;
	}

	public function get_keywords() {
		$keywords = [
			'shipment' => [
				'id',
				'ref',
				'tracking_number',
				'planned_date_of_delivery',
				'weight',
				'width',
				'depth',
				'shipping_method',
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
				'company_skype',
				'company_twitter',
				'company_facebook',
				'company_linkedin',
				'company_url',
				'company_capital',
			],
			'order' => [
				'order_id',
				'order_ref',
				'order_payment_terms',
				'order_payment_method',
				'order_availability_delay',
			]
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
										   type="checkbox"/>

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
