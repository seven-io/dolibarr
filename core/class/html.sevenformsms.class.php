<?php

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
dol_include_once('/seven/lib/SevenSMS.class.php');
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/controllers/seven.controller.php');
dol_include_once('/seven/core/controllers/settings/sms_template.setting.php');

class SevenFormSms {
	var array $errors = [];
	var Seven_Logger $logger;
	var $param = [];
	var $sms_contact_id;
	var $sms_from;
	var $sms_message;

	function __construct(public DoliDB $db) {
		$this->logger = new Seven_Logger;
	}

	function addErrors($error_msg): void {
		$this->errors[] = $error_msg;
	}

	function getErrors(): array {
		return $this->errors;
	}

	public function handle_post_request(): void {
		if (GETPOST('action') != 'send_sms') return;

		$error = false;
		$sms_contact_id = GETPOST('sms_contact_ids');
		$sms_thirdparty_id = GETPOST('thirdparty_id');
		$sms_from = GETPOST('sms_from');
		$sms_message = GETPOST('sms_message');
		$object_id = GETPOST('object_id');

		if (empty($sms_from)) {
			$this->addErrors('From field is required');
			$error = true;
		}
		if (empty($sms_message)) {
			$this->addErrors('Message is required');
			$error = true;
		}
		if (!$error) {
			try {
				$result = process_send_sms_data();
				if (is_array($result))
					dol_htmloutput_mesg(sprintf('SMS sent successfully: %d, Failed: %d', $result['success'], $result['failed']));
				else dol_htmloutput_mesg('Failed to send SMS', [], 'error');
			} catch (Exception $e) {
				dol_htmloutput_mesg('Something went wrong...', [], 'error');
				echo 'Error: ' . $e->getMessage();
			}
		}
	}

	function show_form(): void {
		global $conf, $langs, $form, $db;

		if (!is_object($form)) $form = new Form($this->db);

		$langs->load('other');
		$langs->load('mails');
		$langs->load('sms');

		$smsTemplates = [];
		$smsTemplates[0] = 'Select a template to use';

		$smsTemplateSetting = new SMS_Template_Setting($db);
		foreach ($smsTemplateSetting->getAllSmsTemplatesAsArray() as $id => $title) $smsTemplates[$id] = $title;

		$sms_template_full_data = [];
		foreach ($smsTemplateSetting->getAllSmsTemplates() as $obj) $sms_template_full_data[$obj['id']] = $obj;
		?>
		<form method='POST' name='send_sms_form' enctype='multipart/form-data'
			  action='<?= $this->param['returnUrl'] ?>' style='max-width: 500px;'>
			<?php if (!empty($this->getErrors())): ?>
				<?php foreach ($this->getErrors() as $error): ?>
					<div class='error'><?= $error ?></div>
				<?php endforeach ?>
			<?php endif ?>
			<input type='hidden' name='token' value='<?= newToken(); ?>'/>
			<?php foreach ($this->param as $key => $value): ?>
				<input type='hidden' name='<?= $key ?>' value='<?= $value ?>' />
			<?php endforeach ?>

			<table class='border' width='100%'>
				<tr>
					<td width='200px'>
						<label for='sms_from'>
							<?= $form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID'); ?>
						</label>
					</td>
					<td>
						<input id='sms_from' name='sms_from' size='30' value='<?= $conf->global->SEVEN_FROM ?? '' ?>'/>
					</td>
				</tr>
				<?php
				$entity = $_GET['entity'];
				$invoiceId = $_GET['invoice_id'] ?? 0;
				$thirdPartyId = $_GET['thirdparty_id'] ?? 0;
				$supplierInvoiceId = $_GET['supplier_invoice_id'] ?? 0;
				$supplierOrderId = $_GET['supplier_order_id'] ?? 0;
				$contactId = $_GET['contact_id'] ?? 0;
				$projectId = $_GET['project_id'] ?? 0;

				if (intval($invoiceId) > 0 && !empty($entity) && $entity == 'invoice') {
					dol_include_once('/seven/core/controllers/invoice.class.php');
					(new InvoiceController(intval($invoiceId)))->render();
				} else if (intval($thirdPartyId) > 0 && !empty($entity) && $entity == 'thirdparty') {
					dol_include_once('/seven/core/controllers/thirdparty.class.php');
					(new ThirdPartyController(intval($thirdPartyId)))->render();
				} else if (intval($supplierInvoiceId) > 0 && !empty($entity) && $entity == 'supplier_invoice') {
					dol_include_once('/seven/core/controllers/supplier_invoice.class.php');
					(new SupplierInvoiceController(intval($supplierInvoiceId)))->render();
				} else if (intval($supplierOrderId) > 0 && !empty($entity) && $entity == 'supplier_order') {
					dol_include_once('/seven/core/controllers/supplier_order.class.php');
					(new SupplierOrderController(intval($supplierOrderId)))->render();
				} else if (intval($contactId) > 0 && !empty($entity) && $entity == 'contact') {
					dol_include_once('/seven/core/controllers/contact.class.php');
					(new ContactController(intval($contactId)))->render();
				} else if (intval($projectId) > 0 && !empty($entity) && $entity == 'project') {
					dol_include_once('/seven/core/controllers/project.class.php');
					(new ProjectController(intval($projectId)))->render();
				}
				?>
				<tr>
					<td width='200px' valign='top'>
						<label for='send_sms_to_thirdparty_flag'><?= $langs->trans('Sms_To_Thirdparty_Flag') ?></label>
					</td>
					<td>
						<input id='send_sms_to_thirdparty_flag' type='checkbox' name='send_sms_to_thirdparty_flag'/>
					</td>
				</tr>
				<tr>
					<td>
						<?= $form->textwithpicto($langs->trans('ScheduleSMSButtonTitle'), 'Schedule your SMS to be sent at specific date time'); ?>
					</td>
					<td>
						<input style='width:300px' name='sms_scheduled_datetime' id='sms_scheduled_datetime'/>
					</td>
				</tr>
				<tr>
					<td width='200px'>
						<?= $form->textwithpicto($langs->trans('SmsTemplate'), 'The SMS template you want to use'); ?>
					</td>
					<td><?= $form->selectarray('sms_template_id', $smsTemplates) ?></td>
				</tr>
				<tr>
					<td width='200px' valign='top'>
						<label for='message'><?= $langs->trans('SmsText') ?>*</label>
					</td>
					<td>
						<textarea cols='40' name='sms_message' id='message' rows='4'></textarea>
						<button id='sms_keyword_paragraph' type='button' class='seven_open_keyword' data-attr-target='message'>
							Insert Placeholders
						</button>
					</td>
				</tr>
			</table>

			<input style='float:right;' class='button' type='submit' name='submit'
				   value='<?= $langs->trans('SendSMSButtonTitle') ?>' />
		</form>
		<script>
			const entityKeywords = <?= json_encode($smsTemplateSetting->getKeywords()); ?>;
			const smsTemplates = <?= json_encode($sms_template_full_data); ?>;

			const div = $('<div />').appendTo('body')
			div.attr('id', `keyword-modal`)
			div.attr('class', 'modal')
			div.attr('style', 'display: none;')

			jQuery(document).ready(function () {
				const $thirdPartyFlag = $('#send_sms_to_thirdparty_flag')
				$thirdPartyFlag.closest('tr').hide()
				const $contactIds = $('#sms_contact_ids')
				$contactIds.on('change', function () {
					if ($('#thirdparty_id').length > 0) {
						const $tr = $thirdPartyFlag.closest('tr')
						if ($contactIds.val().length > 0) $tr.show()
						else $tr.hide()
					}
				})

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
						document.getElementById(target).focus()
						document.getElementById(target).setSelectionRange(caretPosition, caretPosition)
					})

					let mainTable = ''
					for (let [key, value] of Object.entries(entityKeywords))
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>` + buildTable(value)

					mainTable +=
						`<div style='margin-top:10px'><small>*Press on placeholder to add to template.</small></div>`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
				})

				$('#sms_template_id').on('change', function () {
					$('#message').val(smsTemplates[this.value].message)
				})

				$('#sms_scheduled_datetime').datetimepicker({
					minDate: new Date(),
					step: 30,
				})
			})

			function sevenBindTextToField(target, keyword) {
				const startStr = document.getElementById(target).value.substring(0, caretPosition)
				const endStr = document.getElementById(target).value.substring(caretPosition)
				document.getElementById(target).value = startStr + keyword + endStr
				caretPosition += keyword.length
			}
		</script>
		<?php
	}
}

