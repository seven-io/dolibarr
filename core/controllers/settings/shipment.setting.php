<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';

class SMS_Shipment_Setting extends SevenBaseSettingController {
	var string $context = 'shipment';
	var array $errors = [];
	var string $page_name = 'shipment_page_title';
	public string $db_key = 'SEVEN_SHIPMENT_SETTING';
	public array $trigger_events = [
		'SHIPMENT_CREATE',
		'SHIPMENT_CLOSED',
		'SHIPMENT_VALIDATE',
		'ORDER_CLOSED',
	];

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'closed' => 'on',
				'validated' => 'on',
				'delivered' => 'on',
			],
			'sms_templates' => [
				'created' => 'Hi [company_name], your shipment has been created.',
				'closed' => 'Hi [company_name], your shipment has been processed successfully,',
				'validated' => 'Hi [company_name], your shipment is confirmed, it will be delivered via [shipping_method]. Tracking number: [tracking_number].',
				'delivered' => 'Hi [company_name], your shipment has been delivered.',
			],
		]);
	}

	public function updateSettings(): void {
		global $db, $user;

		if (empty($_POST)) return;

		if (!$user->rights->seven->permission->write) accessforbidden();
		if (GETPOST('action') != 'update_' . $this->context) return;

		$settings = json_encode([
			'enable' => GETPOST('enable'),
			'send_from' => GETPOST('send_from'),
			'send_on' => [
				'created' => GETPOST('send_on_created'),
				'closed' => GETPOST('send_on_closed'),
				'validated' => GETPOST('send_on_validated'),
				'delivered' => GETPOST('send_on_delivered'),
			],
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'closed' => GETPOST('sms_templates_closed'),
				'validated' => GETPOST('sms_templates_validated'),
				'delivered' => GETPOST('sms_templates_delivered'),
			],
		]);

		$error = dolibarr_set_const($db, $this->db_key, $settings);
		if ($error < 1) {
			$this->log->add('Seven', 'failed to update the shipment settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving shipment settings.';
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save shipment settings', 'error');
		else $this->addNotificationMessage('Shipment settings saved');
	}

	public function getSettings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) $settings = $conf->global->{$this->db_key};
		else $settings = $this->getDefaultSettings();
		return json_decode($settings);
	}

	public function triggerSendSms($object, string $status): void {
		global $db;

		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		if ($settings->send_on->{$status} != 'on') return;
		$this->log->add('Seven', 'current status action triggered: ' . $status);

		$from = $settings->send_from;
		$thirdparty = new Societe($db);
		$thirdparty->fetch($object->socid);
		$to = $thirdparty->phone;
		if (empty($to)) return;
		$message = $settings->sms_templates->{$status};
		$message = $this->fillKeywordsWithValues($object, $message);

		$resp = sevenSendSms($from, $to, $message, 'Automation');
		$msg = $resp['messages'][0];
		if ($msg['status'] == 0)
			EventLogger::create($object->socid, 'thirdparty', 'SMS sent to third party');
		else
			EventLogger::create($object->socid, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);
	}

	protected function fillKeywordsWithValues($object, $message): array {
		global $db;

		$company = new Societe($db);
		$company->fetch($object->socid);

		$order = new Commande($db);
		$order = get_class($object) == 'Commande'
			? $object
			: $order->fetch($object->origin_id);

		if ($object instanceof Commande) {
			$order->fetchObjectLinked();
			$object = !empty($order->linkedObjects['shipping'])
				? end($order->linkedObjects['shipping'])
				: new Expedition($this->db);
		}

		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[ref]' => !empty($object->newref) ? $object->newref : $object->ref,
			'[tracking_number]' => !empty($object->tracking_number) ? $object->tracking_number : '',
			'[planned_date_of_delivery]' => !empty($object->date_delivery)
				? DateTime::createFromFormat('U', $object->date_delivery)->format('d F Y')
				: '',
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

		$replacedMsg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add('Seven', 'replaced_msg: ' . $replacedMsg);

		return $replacedMsg;
	}

	public function getKeywords(): array {
		return [
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
			<input type='hidden' name='action' value='<?= 'update_' . $this->context ?>'/>

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
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('sms_form_save') ?>'/>
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

							if (rowIndex === chunkedKeywords.length - 1) {
								tableCode += '</tbody></table>'
							}
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
					for (let [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

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
