<?php
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

class SMS_Contact_Setting extends SevenBaseSettingController {
	var string $context = 'contact';
	var array $errors = [];
	var Form $form;
	var Seven_Logger $log;
	var string $page_name = 'contact_page_title';
	public string $db_key = 'SEVEN_CONTACT_SETTING';
	public array $trigger_events = [
		'CONTACT_CREATE',
		'CONTACT_MODIFY',
		'CONTACT_DELETE',
		'CONTACT_ENABLEDISABLE',
	];

	function __construct(public DoliDB $db) {
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
	}

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'enabledisabled' => 'off',
			],
			'sms_templates' => [
				'created' => 'Thank you for interest in our services, we\'d like to congratulate you for choosing us as your partner.',
				'enabledisabled' => 'Hi, your account has been [acc_status].',
			],
		]);
	}

	public function updateSettings(): void {
		global $db, $user;

		if (empty($_POST)) return;

		if (!$user->rights->seven->permission->write) accessforbidden();

		if (GETPOST('action') !== 'update_' . $this->context) return;

		$settings = json_encode([
			'enable' => GETPOST('enable'),
			'send_from' => GETPOST('send_from'),
			'send_on' => [
				'created' => GETPOST('send_on_created'),
				'enabledisabled' => GETPOST('send_on_enabledisabled'),
			],
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'enabledisabled' => GETPOST('sms_templates_enabledisabled'),
			],
		]);
		$error = dolibarr_set_const($db, $this->db_key, $settings);

		if ($error < 1) {
			$this->log->add('Seven', 'failed to update the contact settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving contact settings.';
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save contact settings', 'error');
		else $this->addNotificationMessage('Contact settings saved');
	}

	public function getSettings() {
		global $conf;

		$settings = property_exists($conf->global, $this->db_key)
			? $conf->global->{$this->db_key}
			: $this->getDefaultSettings();

		return json_decode($settings);
	}

	public function contactCreate(Contact $object): void {
		$to = validatedMobileNumber($object->phone_mobile, $object->country_code);
		if (empty($to)) return;

		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		if ($settings->send_on->created != 'on') return;

		$message = $this->fillKeywordsWithValues($object, $settings->sms_templates->created);

		$resp = seven_send_sms($settings->send_from, $to, $message, 'Automation');
		$msg = $resp['messages'][0];
		if ($msg['status'] == 0) EventLogger::create($object->id, 'contact', 'SMS sent to contact');
		else
			EventLogger::create($object->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $msg['err_msg']);
	}

	public function contactEnableDisable(Contact $object): void {
		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		if ($settings->send_on->enabledisabled != 'on') return;

		$from = $settings->send_from;
		$to = validatedMobileNumber($object->phone_mobile, $object->country_code);
		$message = $settings->sms_templates->enabledisabled;
		$message = $this->fillKeywordsWithValues($object, $message);

		$resp = seven_send_sms($from, $to, $message, 'Automation');
		$msg = $resp['messages'][0];
		if ($msg['status'] == 0) EventLogger::create($object->id, 'contact', 'SMS sent to contact');
		else
			EventLogger::create($object->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $msg['err_msg']);

	}

	public function fillKeywordsWithValues(Contact $object, $message) {
		global $db;

		$company = new Societe($db);
		$company->fetch($object->socid);

		$keywords = [
			'[id]' => !empty($object->id) ? $object->id : '',
			'[salutation]' => !empty($object->civility) ? $object->civility : '',
			'[address]' => !empty($object->address) ? $object->address : '',
			'[zip]' => !empty($object->zip) ? $object->zip : '',
			'[town]' => !empty($object->town) ? $object->town : '',
			'[country]' => !empty($object->country) ? $object->country : '',
			'[country_code]' => !empty($object->country_code) ? $object->country_code : '',
			'[job_title]' => !empty($object->poste) ? $object->poste : '',
			'[email]' => !empty($object->email) ? $object->email : '',
			'[acc_status]' => $object->statut == 1 ? 'Enabled' : 'Disabled',
			'[note_public]' => !empty($object->note_public) ? $object->note_public : '',
			'[note_private]' => !empty($object->note_private) ? $object->note_private : '',
			'[firstname]' => !empty($object->firstname) ? $object->firstname : '',
			'[lastname]' => !empty($object->lastname) ? $object->lastname : '',
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
			'contact' => [
				'firstname',
				'lastname',
				'salutation',
				'job_title',
				'address',
				'zip',
				'town',
				'country',
				'country_code',
				'email',
				'note_private',
				'note_public',
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
		];
	}

	public function render() {
		global $langs;

		$settings = $this->getSettings();
		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), 'contact', $langs->trans($this->page_name), -1);

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
					<td width='200px'><label for='send_from'><?= $langs->trans('sms_form_send_from') ?></label></td>
					<td><input id='send_from' name='send_from' value='<?= $settings->send_from ?>'/></td>
				</tr>
				<tr>
					<td width='200px'><?= $langs->trans('sms_form_send_on') ?></td>
					<td>
						<?php foreach ($settings->send_on as $key => $value): ?>
							<label for='send_on_<?= $key ?>'>
								<input type='hidden' name='send_on_<?= $key ?>' value='off'/>
								<input id='send_on_<?= $key ?>'
									   name='send_on_<?= $key ?>' <?= ($value == 'on') ? 'checked' : '' ?>
									   type='checkbox'/>

								<?= $langs->trans('seven_' . $this->context . '_send_on_' . $key) ?>
							</label>
						<?php endforeach ?>
					</td>
				</tr>
				<?php foreach ($settings->smsTemplates as $key => $value): ?>
					<tr>
						<td width='200px'><?= $langs->trans('seven_' . $this->context . '_sms_templates_' . $key) ?></td>
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
			const entityKeywords = <?= json_encode($this->getKeywords()); ?>;
			jQuery(function ($) {
				const $div = $('<div />').appendTo('body')
				$div.attr('id', `keyword-modal`)
				$div.attr('class', 'modal')
				$div.attr('style', 'display: none;')

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

					mainTable += `<div style='margin-top:10px'><small>*Press on placeholder to add to template.</small></div>`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
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
		echo dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
