<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

class SMS_SupplierOrder_Setting extends SevenBaseSettingController {
	var string $context = 'supplier_order';
	var array $errors = [];
	var string $page_name= 'supplier_order_page_title';
	public string $db_key = 'SEVEN_SUPPLIER_ORDER_SETTING';
	public array $trigger_events = [
		'ORDER_SUPPLIER_CREATE',
		'ORDER_SUPPLIER_VALIDATE',
		'ORDER_SUPPLIER_APPROVE',
		'ORDER_SUPPLIER_REFUSE',
		'ORDER_SUPPLIER_DISPATCH',
	];

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'validated' => 'off',
				'approved' => 'off',
				'refused' => 'off',
				'dispatched' => 'off',
			],
			'sms_templates' => [
				'created' => 'Hi [company_name], we are drafting your purchase order and will send to you once it\'s validated.',
				'validated' => 'Hi [company_name], your purchase order ([ref]) of [total_ttc] has been validated.',
				'approved' => 'Hi [company_name], here\'s our purchase order ([ref]) of [total_ttc]. We will be expecting delivery on [delivery_date].',
				'refused' => 'Hi [company_name], we\'re not able to proceed with purchase order ([ref]).',
				'dispatched' => 'Hi [company_name], thank you for your payment, your invoice ([ref]) has been paid.',
			],
		]);
	}

	public function updateSettings(): void {
		global $db, $user;

		if (empty($_POST)) return;
		if (!$user->rights->seven->permission->write) accessforbidden();
		if (GETPOST('action') != 'update_' . $this->context) return;

		$settings = [
			'enable' => GETPOST('enable'),
			'send_from' => GETPOST('send_from'),
			'send_on' => [
				'created' => GETPOST('send_on_created'),
				'validated' => GETPOST('send_on_validated'),
				'approved' => GETPOST('send_on_approved'),
				'refused' => GETPOST('send_on_refused'),
				'dispatched' => GETPOST('send_on_dispatched'),
			],
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'validated' => GETPOST('sms_templates_validated'),
				'approved' => GETPOST('sms_templates_approved'),
				'refused' => GETPOST('sms_templates_refused'),
				'dispatched' => GETPOST('sms_templates_dispatched'),
			],
		];

		$settings = json_encode($settings);

		if (dolibarr_set_const($db, $this->db_key, $settings) < 1) {
			$this->log->add('Seven', 'failed to update the invoice settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving invoice settings.';
		}
		if (count($this->errors) > 0)
			$this->addNotificationMessage('Failed to save supplier order settings', 'error');
		else $this->addNotificationMessage('Supplier Order settings saved');
	}

	public function getSettings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) $settings = $conf->global->{$this->db_key};
		else $settings = $this->getDefaultSettings();

		return json_decode($settings);
	}

	public function triggerSms(CommandeFournisseur $object, $action): void {
		global $db;

		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		$this->log->add('Seven', 'current action triggered: ' . $action);
		if ($settings->send_on->{$action} != 'on') return;

		$thirdparty = new Societe($db);
		$thirdparty->fetch($object->socid);
		$to = $thirdparty->phone;
		if (empty($to)) return;
		$message = $this->fillKeywordsWithValues($object, $settings->sms_templates->{$action});

		$resp = sevenSendSms($settings->send_from, $to, $message, 'Automation');
		$msg = $resp['messages'][0];
		if ($msg['status'] == 0)
			EventLogger::create($object->socid, 'thirdparty', 'SMS sent to third party');
		else
			EventLogger::create($object->socid, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);

		$this->log->add('Seven', 'SMS Successfully sent from fx triggerSms');
	}

	protected function fillKeywordsWithValues(CommandeFournisseur $object, $message): string {
		global $db;

		$company = new Societe($db);
		$company->fetch($object->socid);

		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[ref]' => !empty($object->newref) ? $object->newref : $object->ref,
			'[ref_supplier]' => !empty($object->ref_supplier) ? $object->ref_supplier : '',
			'[delivery_date]' => !empty($object->delivery_date) ? date('m/d/Y H:i:s', $object->delivery_date) : '',
			'[total_ttc]' => !empty($object->total_ttc) ? number_format($object->total_ttc, 2) : '',
			'[currency_code]' => !empty($object->multicurrency_code) ? $object->multicurrency_code : '',
			'[label_incoterms]' => !empty($object->label_incoterms) ? $object->label_incoterms : '',
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
		global $langs;

		$settings = $this->getSettings();
		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), $this->context, $langs->trans($this->page_name), -1);
		foreach ($this->notificationMessages ?? [] as $msg) dol_htmloutput_mesg($msg['message'], [], $msg['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>
		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
			<input type='hidden' name='token' value='<?= newToken(); ?>'/>
			<input type='hidden' name='action' value='update_<?= $this->context ?>'/>

			<table class='border seven-table'>
				<tr>
					<td><?= $langs->trans('sms_form_enable_notification') ?></td>
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
					<td><label for='send_from'><?= $langs->trans('sms_form_send_from') ?></label></td>
					<td><input id='send_from' name='send_from' value='<?= $settings->send_from ?>'/></td>
				</tr>
				<tr>
					<td><?= $langs->trans('sms_form_send_on') ?></td>
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
				<?php foreach ($settings->sms_templates as $key => $value): ?>
					<tr>
						<td><?= $langs->trans('seven_' . $this->context . '_sms_templates_' . $key) ?></td>
						<td>
							<label for='sms_templates_<?= $key ?>'>
								<textarea id='sms_templates_<?= $key ?>'
										  name='sms_templates_<?= $key ?>' cols='40'
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
			<div style='text-align: center'>
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('sms_form_save') ?>'>
			</div>
		</form>
		<script>
			jQuery(function ($) {
				const entityKeywords = <?= json_encode($this->getKeywords()); ?>;
				const $div = $('<div />').appendTo('body')
				$div.attr('id', 'keyword-modal')
				$div.attr('class', 'modal')
				$div.attr('style', 'display: none;')

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target')

					let caretPosition = document.getElementById(target).selectionStart

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3)

						let tableCode = ''
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) tableCode += `<table class='widefat fixed striped'><tbody>`

							tableCode += '<tr>'
							row.forEach(function (col) {
								tableCode += `<td class='column'><button class='button-link sevenBindTextToField' data-target='${target}' data-keyword='[${col}]'>[${col}]</button></td>`
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
					$keywordModal.on($.modal.OPEN, function () {
						;[...document.querySelectorAll('.sevenBindTextToField')].forEach(el => {
							el.addEventListener('click', function () {
								const {target, keyword} = el.dataset
								const startStr = document.getElementById(target).value.substring(0, caretPosition)
								const endStr = document.getElementById(target).value.substring(caretPosition)
								document.getElementById(target).value = startStr + keyword + endStr
								caretPosition += keyword.length
							})
						})
					})
					let mainTable = ''
					for (let [key, value] of Object.entries(entityKeywords))
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>` + buildTable(value)

					mainTable += `<div style='margin-top: 10px'><small>Press on placeholder to add to template.</small></div>`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
				})
			})
		</script>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
