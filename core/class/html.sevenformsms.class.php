<?php

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
dol_include_once("/seven/lib/SevenSMS.class.php");
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/controllers/seven.controller.php");
dol_include_once("/seven/core/controllers/settings/sms_template.setting.php");


class SevenFormSms {
	var $db;
	var $param = [];
	var $logger;
	var $sms_from;
	var $sms_contact_id;
	var $sms_message;
	var $errors;


	/**
	 * @param DoliDB $db Database handler
	 */
	function __construct($db) {
		$this->db = $db;
		$this->errors = [];
		$this->logger = new Seven_Logger;
	}

	function add_errors($error_msg) {
		$this->errors[] = $error_msg;
	}

	function get_errors() {
		return $this->errors;
	}

	public function handle_post_request() {
		$action = GETPOST('action');
		if ($action == 'send_sms') {
			$error = false;
			$sms_contact_id = GETPOST("sms_contact_ids");
			$sms_thirdparty_id = GETPOST("thirdparty_id");
			$sms_from = GETPOST("sms_from");
			$sms_message = GETPOST('sms_message');
			$object_id = GETPOST("object_id");

			if (empty($sms_from)) {
				$this->add_errors("From field is required");
				$error = true;
			}
			if (empty($sms_message)) {
				$this->add_errors("Message is required");
				$error = true;
			}

			if (!$error) {
				try {
					$result = process_send_sms_data();
					if (is_array($result)) {
						dol_htmloutput_mesg("SMS sent successfully: {$result['success']}, Failed: {$result['failed']}");
					} else {
						dol_htmloutput_mesg("Failed to send SMS", [], 'error');
					}

				} catch (Exception $e) {
					dol_htmloutput_mesg("Something went wrong...", [], 'error');
					echo "Error: " . $e->getMessage();
				}
			}

		}
	}

	/**
	 *    Show the form to input an sms.
	 *
	 * @param string $width Width of form
	 * @return    void
	 */
	function show_form() {
		global $conf, $langs, $user, $form, $db;

		if (!is_object($form)) $form = new Form($this->db);

		$langs->load("other");
		$langs->load("mails");
		$langs->load("sms");

		$sms_templates = [];
		$sms_templates[0] = "Select a template to use";

		$sms_template_obj = new SMS_Template_Setting($db);
		$sms_template_in_kv = $sms_template_obj->get_all_sms_templates_as_array();
		foreach ($sms_template_in_kv as $id => $title) {
			$sms_templates[$id] = $title;
		}

		$sms_templates_obj = $sms_template_obj->get_all_sms_templates();
		$sms_template_full_data = [];
		foreach ($sms_templates_obj as $sms_t_obj) {
			$sms_template_full_data[$sms_t_obj['id']] = $sms_t_obj;
		}

		?>
		<!-- Begin form SMS -->
		<form method="POST" name="send_sms_form" enctype="multipart/form-data"
			  action="<?= $this->param["returnUrl"] ?>" style="max-width: 500px;">
			<?php if (!empty($this->get_errors())) { ?>
				<?php foreach ($this->get_errors() as $error) { ?>
					<div class="error"><?= $error ?></div>
				<?php } ?>
			<?php } ?>
			<input type="hidden" name="token" value="<?= newToken(); ?>">
			<?php foreach ($this->param as $key => $value) { ?>
				<input type="hidden" name="<?= $key ?>" value="<?= $value ?>">
			<?php } ?>

			<table class="border" width="100%">
				<tr>
					<td width="200px"><?= $form->textwithpicto($langs->trans("SEVEN_FROM"), "Sender/Caller ID"); ?>
						*
					</td>
					<td>
						<input type="text" name="sms_from" size="30"
							   value="<?= isset($conf->global->SEVEN_FROM) ? $conf->global->SEVEN_FROM : "" ?>">
					</td>
				</tr>
				<?php
				// sms_contact_id must come from here
				$entity = $_GET['entity'];
				$invoice_id = (isset($_GET['invoice_id']) ? $_GET['invoice_id'] : 0);
				$thirdparty_id = (isset($_GET['thirdparty_id']) ? $_GET['thirdparty_id'] : 0);
				$supplier_invoice_id = (isset($_GET['supplier_invoice_id']) ? $_GET['supplier_invoice_id'] : 0);
				$supplier_order_id = (isset($_GET['supplier_order_id']) ? $_GET['supplier_order_id'] : 0);
				$contact_id = (isset($_GET['contact_id']) ? $_GET['contact_id'] : 0);
				$project_id = (isset($_GET['project_id']) ? $_GET['project_id'] : 0);

				if (intval($invoice_id) > 0 && !empty($entity) && $entity == 'invoice') {
					$id = intval($invoice_id);
					dol_include_once("/seven/core/controllers/invoice.class.php");

					$controller = new InvoiceController($id);
					$controller->render();
				} else if (intval($thirdparty_id) > 0 && !empty($entity) && $entity == 'thirdparty') {
					$id = intval($thirdparty_id);
					dol_include_once("/seven/core/controllers/thirdparty.class.php");

					$controller = new ThirdPartyController($id);
					$controller->render();
				} else if (intval($supplier_invoice_id) > 0 && !empty($entity) && $entity == 'supplier_invoice') {
					$id = intval($supplier_invoice_id);
					dol_include_once("/seven/core/controllers/supplier_invoice.class.php");

					$controller = new SupplierInvoiceController($id);
					$controller->render();
				} else if (intval($supplier_order_id) > 0 && !empty($entity) && $entity == 'supplier_order') {
					$id = intval($supplier_order_id);
					dol_include_once("/seven/core/controllers/supplier_order.class.php");

					$controller = new SupplierOrderController($id);
					$controller->render();
				} else if (intval($contact_id) > 0 && !empty($entity) && $entity == 'contact') {
					$id = intval($contact_id);
					dol_include_once("/seven/core/controllers/contact.class.php");

					$controller = new ContactController($id);
					$controller->render();
				} else if (intval($project_id) > 0 && !empty($entity) && $entity == 'project') {
					$id = intval($project_id);
					dol_include_once("/seven/core/controllers/project.class.php");

					$controller = new ProjectController($id);
					$controller->render();
				}

				?>

				<tr>
					<td width="200px" valign="top"><?= $langs->trans("Sms_To_Thirdparty_Flag") ?></td>
					<td>
						<input id="send_sms_to_thirdparty_flag" type="checkbox"
							   name="send_sms_to_thirdparty_flag"></input>
					</td>
				</tr>
				<tr>
					<td>
						<?= $form->textwithpicto($langs->trans("ScheduleSMSButtonTitle"), "Schedule your SMS to be sent at specific date time"); ?>
					</td>
					<td>
						<input style="width:300px" type="text" name="sms_scheduled_datetime"
							   id="sms_scheduled_datetime"/>
					</td>
				</tr>
				<tr>
					<td width="200px">
						<?= $form->textwithpicto($langs->trans("SmsTemplate"), "The SMS template you want to use"); ?>
					</td>
					<td>
						<?= $form->selectarray('sms_template_id', $sms_templates) ?>
					</td>
				</tr>
				<tr>
					<td width="200px" valign="top"><?= $langs->trans("SmsText") ?>*</td>
					<td>
						<textarea cols="40" name="sms_message" id="message" rows="4"></textarea>
						<div>
							<p id="sms_keyword_paragraph">Customize your SMS with keywords
								<button type="button" class="seven_open_keyword" data-attr-target="message">
									Keywords
								</button>
							</p>
						</div>
					</td>
				</tr>

			</table>

			<input style="float:right;" class="button" type="submit" name="submit"
				   value="<?= $langs->trans("SendSMSButtonTitle") ?>">
		</form>
		<script>
			const entity_keywords = <?= json_encode($sms_template_obj->get_keywords()); ?>;
			const sms_templates = <?= json_encode($sms_template_full_data); ?>;

			let div = $('<div />').appendTo('body');
			div.attr('id', `keyword-modal`);
			div.attr('class', "modal");
			div.attr('style', "display: none;");

			jQuery(document).ready(function () {
				$("#send_sms_to_thirdparty_flag").closest("tr").hide();
				$("#sms_contact_ids").on("change", function () {
					let sms_contact_id = $("#sms_contact_ids").val();
					let thirdpartyTree = $("#thirdparty_id");
					// if the tree exists
					if (thirdpartyTree.length > 0) {
						if (sms_contact_id.length > 0) {
							$("#send_sms_to_thirdparty_flag").closest("tr").show();
						} else {
							$("#send_sms_to_thirdparty_flag").closest("tr").hide();
						}
					}
				});

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

				$('#sms_template_id').on("change", function () {
					let selected_template_id = this.value;
					const sms_template_data = sms_templates[selected_template_id];
					$("#message").val(sms_template_data.message);
				});

				$("#sms_scheduled_datetime").datetimepicker({
					minDate: new Date(),
					step: 30,
				});

			})

			function seven_bind_text_to_field(target, keyword) {
				const startStr = document.getElementById(target).value.substring(0, caretPosition);
				const endStr = document.getElementById(target).value.substring(caretPosition);
				document.getElementById(target).value = startStr + keyword + endStr;
				caretPosition += keyword.length;
			}
		</script>
		<?php
	}
}

