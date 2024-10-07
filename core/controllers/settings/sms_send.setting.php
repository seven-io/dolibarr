<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/contact.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/third_party.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_template.setting.php');

class SMS_Send_Setting extends SevenBaseSettingController {
	private string $context = 'send_sms';
	private array $errors = [];
	private string $page_name = 'send_sms_page_title';
	private Societe $thirdparty;

	function __construct($db) {
		$this->thirdparty = new Societe($db);
		parent::__construct($db);
	}

	public function handleSendSmsForm(): void {
		global $db, $user;

		if (!$user->rights->seven->permission->write) accessforbidden();

		$this->log->add('Seven', 'xxx');

		if (empty($_POST['action']) || GETPOST('action') != 'send_sms') return;

		$smsFrom = GETPOST('sms_from');
		$smsContactIds = GETPOST('sms_contact_ids');
		$smsThirdPartyId = GETPOST('sms_thirdparty_id');
		$sendSmsToThirdPartyFlag = GETPOST('send_sms_to_thirdparty_flag') == 'on';
		$smsMessage = GETPOST('sms_message');
		$smsScheduledDatetime = GETPOST('sms_scheduled_datetime');

		if (empty($smsMessage)) {
			 $this->addError('Message is required');
			 return;
		}

		$totalSmsResponses = [];

		if (!empty($smsThirdPartyId)) {
			if (empty($smsContactIds)) {
				$thirdPartyController = new ThirdPartyController($smsThirdPartyId);

				$sms3rdPartySetting = new SMS_ThirdParty_Setting($db);
				$societe = new Societe($db);
				$societe->fetch($smsThirdPartyId);

				$message = $sms3rdPartySetting->fillKeywordsWithValues($societe, $smsMessage);

				$args = [];
				if (!empty($smsScheduledDatetime)) {
					$realScheduledDt = new DateTime($smsScheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
					$realScheduledDt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

					$args['delay'] = $realScheduledDt->format('Y-m-d H:i:s');
				}

				$resp = sevenSendSms($smsFrom, $thirdPartyController->getThirdPartyMobileNumber(), $message, 'Send SMS', $args);
				$totalSmsResponses[] = $resp;
				$msg = $resp['messages'][0];
				if ($msg['status'] == 0)
					EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS sent to third party');
				else
					EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);
			} else if (!empty($smsContactIds) && $sendSmsToThirdPartyFlag) {
				$thirdPartyController = new ThirdPartyController($smsThirdPartyId);
				$thirdPartyMobileNumber = $thirdPartyController->getThirdPartyMobileNumber();
				$sms3rdPartySetting = new SMS_ThirdParty_Setting($db);
				$societe = new Societe($db);
				$societe->fetch($smsThirdPartyId);
				$message = $sms3rdPartySetting->fillKeywordsWithValues($societe, $smsMessage);

				$args = [];
				if (!empty($smsScheduledDatetime)) {
					$realScheduledDt = new DateTime($smsScheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
					$realScheduledDt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

					$args['delay'] = $realScheduledDt->format('Y-m-d H:i:s');
				}

				$resp = sevenSendSms($smsFrom, $thirdPartyMobileNumber, $message, 'Send SMS', $args);
				$totalSmsResponses[] = $resp;
				$msg = $resp['messages'][0];
				if ($msg['status'] == 0) EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS sent to third party');
				else
					EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);
			}
		}

		foreach ($smsContactIds as $contactId) {
			$contactController = new ContactController($contactId);
			$contact = new Contact($db);
			$contact->fetch($contactId);
			$message = (new SMS_Contact_Setting($db))->fillKeywordsWithValues($contact, $smsMessage);

			$args = [];
			if (!empty($smsScheduledDatetime)) {
				$realScheduledDt = new DateTime($smsScheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
				$realScheduledDt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
				$args['delay'] = $realScheduledDt->format('Y-m-d H:i:s');
			}

			$resp = sevenSendSms($smsFrom, $contactController->getContactMobileNumber(), $message, 'Send SMS', $args);
			$totalSmsResponses[] = $resp;
			$msg = $resp['messages'][0];
			if ($msg['status'] == 0) EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS sent to third party');
			else
				EventLogger::create($smsThirdPartyId, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $msg['err_msg']);
		}
		$successSms = 0;
		foreach ($totalSmsResponses as $smsResponse) if ($smsResponse['messages'][0]['status'] == 0) $successSms++;

		$response = [];
		$response['success'] = $successSms;
		$response['failed'] = count($totalSmsResponses) - $successSms;

		try {
			if (is_array($response))
				$this->addNotificationMessage(sprintf('SMS sent successfully: %d, Failed: %d', $response['success'], $response['failed']));
			else $this->addNotificationMessage('Failed to send SMS', 'error');
		} catch (Exception $e) {
			$this->addNotificationMessage('Critical error...', 'error');
			$this->log->add('Seven', 'Error: ' . $e->getMessage());
		}
	}

	public function getKeywords(): array {
		return [
			'contact' => [
				'id',
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
				'company_url',
				'company_capital',
			],
		];
	}

	private function addError(string $error): void {
		$this->errors[] = $error;
	}

	private function getErrors(): array {
		return $this->errors;
	}

	public function render(): void {
		global $langs, $conf, $db;

		$this->thirdparty->id = !empty($this->thirdparty->id) ? $this->thirdparty->id : 0;

		$smsTemplates = ['Select a template to use'];

		$smsTemplateObj = new SMS_Template_Setting($db);
		foreach ($smsTemplateObj->getAllSmsTemplatesAsArray() as $id => $title) $smsTemplates[$id] = $title;

		$smsTemplateFullData = [];
		foreach ($smsTemplateObj->getAllSmsTemplates() as $obj) $smsTemplateFullData[$obj['id']] = $obj;

		if (!empty($_GET['thirdparty_id'])) $this->thirdparty->fetch(intval($_GET['thirdparty_id']));
		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), $this->context, $langs->trans($this->page_name), -1);

		foreach ($this->notificationMessages ?? [] as $msg) dol_htmloutput_mesg($msg['message'], [], $msg['style']);
		?>
		<form method='POST' enctype='multipart/form-data' action='<?= $_SERVER['PHP_SELF'] ?>' style='max-width: 500px'>
			<?php if (!empty($this->getErrors())): ?>
				<?php foreach ($this->getErrors() as $error): ?>
					<div class='error'><?= $error ?></div>
				<?php endforeach ?>
			<?php endif ?>
			<input type='hidden' name='action' value='send_sms'/>
			<input type='hidden' name='token' value='<?= newToken() ?>'/>
			<table class='border'>
				<tr>
					<td>
						<label for='sms_from'>
							<?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID') ?>
						</label>
					</td>
					<td>
						<input style='width:300px' id='sms_from' name='sms_from' size='30'
							   value='<?= !empty($conf->global->SEVEN_FROM) ? $conf->global->SEVEN_FROM : ''; ?>'/>
					</td>
				</tr>
				<tr>
					<td>
						<?= $this->form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
						*
					</td>
					<td>
						<?= $this->form->select_thirdparty_list($this->thirdparty->id, 'sms_thirdparty_id', '', '', 0, 0, [], '', 0, 0, 'width300') ?>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<?= $this->form->selectcontacts($this->thirdparty->id, '', 'sms_contact_ids', 1, '', '', 1, 'width300', false, 0, 0, [], '', '', true) ?>
					</td>
				</tr>
				<tr>
					<td>
						<label for='sms_scheduled_datetime'>
							<?= $this->form->textwithpicto($langs->trans('ScheduleSMSButtonTitle'), 'Schedule your SMS to be sent at specific date time'); ?>
						</label>
					</td>
					<td>
						<input
							id="sms_scheduled_datetime"
							name="sms_scheduled_datetime"
							style='width:300px'
							type="datetime-local"
						/>
					</td>
				</tr>
				<tr>
					<td>
						<?= $this->form->textwithpicto($langs->trans('SmsTemplate'), 'The SMS template you want to use'); ?>
					</td>
					<td>
						<?= $this->form->selectarray('sms_template_id', $smsTemplates, '', 0, 0, 0, '', 0, 0, 0, '', 'width300') ?>
					</td>
				</tr>
				<tr>
					<td>
						<label for='send_sms_to_thirdparty_flag'><?= $langs->trans('Sms_To_Thirdparty_Flag') ?></label>
					</td>
					<td>
						<input id='send_sms_to_thirdparty_flag' type='checkbox' name='send_sms_to_thirdparty_flag'/>
					</td>
				</tr>
				<tr>
					<td><label for='message'><?= $langs->trans('SmsText') ?>*</label></td>
					<td>
						<textarea style='width:300px' cols='40' name='sms_message' id='message' rows='4' required></textarea>
						<button type='button' class='seven_open_keyword' data-attr-target='message'>
							Insert Placeholders
						</button>
					</td>
				</tr>
			</table>

			<input style='float:right' class='button' type='submit' name='submit'
				   value='<?= $langs->trans('SendSMSButtonTitle') ?>'/>
		</form>
		<script>
			jQuery(document).ready(function () {
				const entityKeywords = <?= json_encode($this->getKeywords()); ?>;
				const smsTemplates = <?= json_encode($smsTemplateFullData); ?>;

				const div = $('<div />').appendTo('body')
				div.attr('id', 'keyword-modal')
				div.attr('class', 'modal')
				div.attr('style', 'display: none;')

				const $thirdPartyFlag = $('#send_sms_to_thirdparty_flag')
				$thirdPartyFlag.closest('tr').hide()

				const $smsContactIds = $('#sms_contact_ids')
				$smsContactIds.on('change', function () {
					if ($('#sms_thirdparty_id').length > 0) {
						if ($smsContactIds.val().length > 0) $thirdPartyFlag.closest('tr').show()
						else $thirdPartyFlag.closest('tr').hide()
					}
				})
				$('#sms_thirdparty_id').on('change', function () {
					const urlParams = new URLSearchParams(window.location.search)
					urlParams.set('thirdparty_id', $(this).val())
					window.location = window.location.href.split('?')[0] + '?' + urlParams.toString()
				})
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
								tableCode += `<td class='column'><button class='button-link sevenBindTextToField' data-keyword='[${col}]' data-target='${target}' data-keyword=''>[${col}]</button></td>`
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
					for (const [k, v] of Object.entries(entityKeywords))
						mainTable += `<h3>${ucFirst(k.replaceAll('_', ' '))}</h3>` +  buildTable(v)

					mainTable += `
<div style='margin-top:10px'><small>Press on placeholder to add to template.</small></div>
`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
				})

				$('#sms_template_id').on('change', function () {
					$('#message').val(smsTemplates[this.value].message)
				})

				document.getElementById('sms_scheduled_datetime').min
					= new Date().toISOString().slice(0,new Date().toISOString().lastIndexOf(":"))
			})
		</script>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
