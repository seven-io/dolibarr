<?php
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_template.setting.php');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';

class SMS_Bulk_Setting extends SevenBaseSettingController {
	private string $context = 'bulk_sms';
	private array $errors = [];
	private FormCompany $form_company;
	private string $page_name = 'bulk_sms_page_title';

	function __construct(DoliDB $db) {
		$this->form_company = new FormCompany($db);
		parent::__construct($db);
	}

	private function validateSmsForm(array $payload): bool {
		if ($payload['to'] == 'all_contacts_of_tp')
			if (empty($payload['socid'])) $this->errors[] = 'Third party is required';
		if ($payload['to'] == 'spec_phone_numbers' && !preg_match('/^\d+(?:,\d+)*$/', $payload['phone_numbers']))
			$this->errors[] = 'Mobile phone must be comma separated';
		$filterBy = htmlspecialchars($payload['filter_by']);
		$countryId = htmlspecialchars($payload['country_id']);
		$tpType = htmlspecialchars($payload['tp_type']);
		$prospectcustomer = htmlspecialchars($payload['prospectcustomer']);

		if ($payload['to'] == 'all_contacts_of_spec_tp') {
			if ($filterBy == 'countries') if (!ctype_digit($countryId)) $this->errors[] = 'country cannot be empty';
			if ($filterBy == 'tp_type') if (empty($tpType)) $this->errors[] = 'Third party type cannot be empty';
			if ($filterBy == 'prospectcustomer')
				if (!ctype_digit($prospectcustomer)) $this->errors[] = 'Prospect / customer cannot be empty';
		}

		return count($this->errors) == 0;
	}

	public function handleSendSmsForm(array $postData) {
		global $user, $db;

		if (empty($_POST['to'])) accessforbidden();
		if (!$user->rights->seven->permission->write) accessforbidden();
		if ($postData['action'] != 'send_bulk_sms') return -1;
		if (!$this->validateSmsForm($postData)) return -1;

		$sevenFrom = htmlspecialchars($postData['from']);
		$sevenTo = htmlspecialchars($postData['to']);
		$message = htmlspecialchars($postData['message']);
		$socid = htmlspecialchars($postData['socid']);
		$phoneNumbers = htmlspecialchars($postData['phone_numbers']);
		$filterBy = htmlspecialchars($postData['filter_by']);
		$tpType = htmlspecialchars($postData['tp_type']);
		$prospectcustomer = htmlspecialchars($postData['prospectcustomer']);
		$scheduledDatetime = htmlspecialchars($postData['sms_scheduled_datetime']);
		$totalResponses = [];
		$contactModel = new SMS_Contact_Setting($db);

		if ($sevenTo == 'all_contacts') {
			$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'socpeople';
			$resql = $this->db->query($sql);

			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$contact = new Contact($this->db);
					$contact->fetch($obj->rowid);
					$validatedMobileNumber = $contact->phone_mobile;
					if (empty($validatedMobileNumber)) {
						$failed_message_data = [
							'messages' => [
								[
									'status' => 1,
									'err_msg' => sprintf('Invalid mobile number %s for country code: %s', $contact->phone_mobile, $contact->country_code),
								]
							]
						];
						$totalResponses[] = $failed_message_data;
						continue;
					}
					$text = $contactModel->fillKeywordsWithValues($contact, $message);
					$args = [];
					if (!empty($scheduledDatetime)) {
						$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
						$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

						$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
					}

					$resp = sevenSendSms($sevenFrom, $validatedMobileNumber, $text, 'Bulk SMS', $args);
					$totalResponses[] = $resp;
					$msg = $resp['messages'][0];
					if ($msg['status'] == 0) EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
					else
						EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $msg['err_msg']);
				}
			} else return -1; // no contacts
		} else if ($sevenTo == 'all_contacts_of_tp') {
			// TODO
			$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE fk_soc = ;' . $socid;
			$resql = $this->db->query($sql);
			if ($resql) {
				if ($resql->num_rows > 0) {
					while ($obj = $this->db->fetch_object($resql)) {
						$contact = new Contact($this->db);
						$contact->fetch($obj->rowid);
						$validatedMobileNumber = $contact->phone_mobile;
						if (empty($validatedMobileNumber)) {
							$failed_message_data = [
								'messages' => [
									[
										'status' => 1
									]
								]
							];
							$totalResponses[] = $failed_message_data;
							continue;
						}
						$text = $contactModel->fillKeywordsWithValues($contact, $message);

						$args = [];
						if (!empty($scheduledDatetime)) {
							$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
							$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
						}

						$resp = sevenSendSms($sevenFrom, $validatedMobileNumber, $text, 'Bulk SMS', $args);
						$totalResponses[] = $resp;
						$firstMsg = $resp['messages'][0];
						if ($firstMsg['status'] == 0)
							EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
						else
							EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $firstMsg['err_msg']);

					}
				} else $this->addNotificationMessage('No contacts found in third party', 'error');
			} else {
				return -1; // sql failed
			}
		} else if ($sevenTo == 'all_contacts_of_spec_tp') {
			if ($filterBy == 'countries') {
				// TODO
				$sql = 'SELECT sp.rowid FROM ' . MAIN_DB_PREFIX . 'societe as s, ' . MAIN_DB_PREFIX . 'socpeople as sp WHERE s.fk_pays = {$countryId} AND sp.fk_soc = s.rowid;';
				$resql = $this->db->query($sql);
				if ($resql) {
					if ($resql->num_rows > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$contact = new Contact($this->db);
							$contact->fetch($obj->rowid);
							$validatedMobileNumber = $contact->phone_mobile;
							if (empty($validatedMobileNumber)) {
								$failed_message_data = [
									'messages' => [
										[
											'status' => 1
										]
									]
								];
								$totalResponses[] = $failed_message_data;
								continue;
							}
							$text = $contactModel->fillKeywordsWithValues($contact, $message);

							$args = [];
							if (!empty($scheduledDatetime)) {
								$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
							}

							$resp = sevenSendSms($sevenFrom, $validatedMobileNumber, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							$firstMsg = $resp['messages'][0];
							if ($firstMsg['status'] == 0)
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							else
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $firstMsg['err_msg']);
						}

					} else $this->addNotificationMessage('No filtered contacts found in third party', 'error');
				} else return -1; // sql failed
			} else if ($filterBy == 'tp_type') {
				$sql = 'SELECT sp.rowid FROM ' . MAIN_DB_PREFIX . 'societe as s, ' . MAIN_DB_PREFIX . sprintf('socpeople as sp WHERE s.fk_typent = % AND sp.fk_soc = s.rowid;', $tpType);
				$resql = $this->db->query($sql);
				if ($resql) {
					if ($resql->num_rows > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$contact = new Contact($this->db);
							$contact->fetch($obj->rowid);
							$validatedMobileNumber = $contact->phone_mobile;
							if (empty($validatedMobileNumber)) {
								$failed_message_data = [
									'messages' => [
										[
											'status' => 1
										]
									]
								];
								$totalResponses[] = $failed_message_data;
								continue;
							}
							$text = $contactModel->fillKeywordsWithValues($contact, $message);

							$args = [];
							if (!empty($scheduledDatetime)) {
								$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
							}

							$resp = sevenSendSms($sevenFrom, $validatedMobileNumber, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							$firstMsg = $resp['messages'][0];
							if ($firstMsg['status'] == 0)
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							else
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $firstMsg['err_msg']);
						}
					} else $this->addNotificationMessage('No filtered contacts found in third party', 'error');
				} else return -1; // no contacts
			} else if ($filterBy == 'prospectcustomer') {
				$sql = 'SELECT sp.rowid FROM ' . MAIN_DB_PREFIX . 'societe as s, ' . MAIN_DB_PREFIX . sprintf('socpeople as sp WHERE s.client = %s AND sp.fk_soc = s.rowid;', $prospectcustomer);
				$resql = $this->db->query($sql);
				if ($resql) {
					if ($resql->num_rows > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$contact = new Contact($this->db);
							$contact->fetch($obj->rowid);
							$validatedMobileNumber = $contact->phone_mobile;
							if (empty($validatedMobileNumber)) {
								$failed_message_data = [
									'messages' => [
										[
											'status' => 1
										]
									]
								];
								$totalResponses[] = $failed_message_data;
								continue;
							}
							$text = $contactModel->fillKeywordsWithValues($contact, $message);

							$args = [];
							if (!empty($scheduledDatetime)) {
								$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
							}

							$resp = sevenSendSms($sevenFrom, $validatedMobileNumber, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							if ($resp['messages'][0]['status'] == 0) {
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							} else {
								$err_msg = $resp['messages'][0]['err_msg'];
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
							}
						}
					} else $this->addNotificationMessage('No filtered contacts found in third party', 'error');
				} else return -1; // no contacts
			}
		} else if ($sevenTo == 'spec_phone_numbers') {
			foreach (explode(',', $phoneNumbers) as $mobile) {
				if (!ctype_digit($mobile)) continue;

				$args = [];
				if (!empty($scheduledDatetime)) {
					$realScheduledDatetime = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
					$realScheduledDatetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

					$args['delay'] = $realScheduledDatetime->format('Y-m-d H:i:s');
				}

				$totalResponses[] = sevenSendSms($sevenFrom, $mobile, $message, 'Bulk SMS', $args);
			}
		}

		$success_sms = 0;
		$total_sms = count($totalResponses);

		foreach ($totalResponses as $sms_response) if ($sms_response['messages'][0]['status'] == 0) $success_sms++;

		$response = [];
		$response['success'] = $success_sms;
		$response['failed'] = $total_sms - $success_sms;

		try {
			if (is_array($response))
				$this->addNotificationMessage(sprintf('SMS sent successfully: %d, Failed: %d', $response['success'], $response['failed']));
			else $this->addNotificationMessage('Failed to send SMS', 'error');

		} catch (Exception $e) {
			$this->addNotificationMessage('Critical error...', 'error');
			$this->log->add('Seven', 'Error: ' . $e->getMessage());
		}

		return 1;
	}

	public function addNotificationMessage($message, $style = 'ok'): void {
		$this->notificationMessages[] = compact('message', 'style');
	}

	public function render(): void {
		global $conf, $langs, $db;

		$contactModel = new SMS_Contact_Setting($db);
		$templates = [];
		$templates[0] = 'Select a template to use';
		$templateObj = new SMS_Template_Setting($db);
		$templateInKv = $templateObj->getAllSmsTemplatesAsArray();
		foreach ($templateInKv as $id => $title) $templates[$id] = $title;

		$sms_templates_obj = $templateObj->getAllSmsTemplates();
		$sms_template_full_data = [];
		foreach ($sms_templates_obj as $obj) $sms_template_full_data[$obj['id']] = $obj;
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
		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>' style='max-width: 500px'>
			<input type='hidden' name='token' value='<?= newToken(); ?>'/>
			<input type='hidden' name='action' value='send_bulk_sms'/>
			<table class='border seven-table'>
				<tr>
					<td>
						<label for='seven_from'>
							<?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Your business name'); ?>
						</label>
					</td>
					<td>
						<input style='width:300px' name='seven_from' id='seven_from'
							   value='<?= !empty($conf->global->SEVEN_FROM) ? $conf->global->SEVEN_FROM : '' ?>'
						/>
					</td>
				</tr>
				<tr>
					<td>
						<label for='seven_to'>
							<?= $this->form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
							*
						</label>
					</td>
					<td>
						<select style='width:300px' name='seven_to' id='seven_to'>
							<option value='all_contacts' selected>All contacts</option>
							<option value='all_contacts_of_tp'>All contacts of a third party</option>
							<option value='all_contacts_of_spec_tp'>All contacts of a filtered third party(s)</option>
							<option value='spec_phone_numbers'>Specific mobile numbers</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>Thirdparty list</td>
					<td>
						<?= $this->form->select_thirdparty_list('', 'socid', '', '', 0, 0, [], '', 0, 0, 'width300') ?>
					</td>
				</tr>
				<tr>
					<td><label for='filter_by'>Filter by</label></td>
					<td>
						<select style='width:300px' name='filter_by' id='filter_by'>
							<option value='countries' selected>Countries</option>
							<option value='tp_type'>Third party type</option>
							<option value='prospectcustomer'>Prospect / Customer</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>Countries</td>
					<td>
						<?= $this->form->select_country('', 'country_id', '', 0, 'width300', '', 0) ?>
					</td>
				</tr>
				<tr>
					<td>Third party type</td>
					<td>
						<?= $this->form->selectarray('tp_type', $this->form_company->typent_array(0), '', 0, 0, 0, '', 0, 0, 0, '', 'width300'); ?>
					</td>
				</tr>
				<tr>
					<td>Prospect / customer</td>
					<td>
						<?= $this->form_company->selectProspectCustomerType('', 'prospectcustomer', 'prospectcustomer', 'form', 'width300') ?>
					</td>
				</tr>
				<tr>
					<td><label for='phone_numbers'>Mobile numbers</label></td>
					<td>
						<textarea style='width:300px' name='phone_numbers' id='phone_numbers' cols='30' rows='5'
								  placeholder='60123456789,12014567890'></textarea>
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
						<?= $this->form->selectarray('sms_template_id', $templates, '', 0, 0, 0, '', 0, 0, 0, '', 'width300') ?>
					</td>
				</tr>
				<tr>
					<td><label for='message'><?= $langs->trans('SmsText') ?>*</label></td>
					<td>
						<textarea style='width:300px' id='message' name='message' cols='30' rows='5'
								  placeholder='Your SMS message' required></textarea>
						<button type='button' class='seven_open_keyword' data-attr-target='message'>
							Insert Placeholders
						</button>
					</td>
				</tr>
			</table>
			<input style='float:right;' class='button' type='submit' name='submit'
				   value='<?= $langs->trans('SendSMSButtonTitle') ?>'/>
		</form>
		<script>
			jQuery(function ($) {
				const entityKeywords = <?= json_encode($contactModel->getKeywords()); ?>;
				const smsTemplates = <?= json_encode($sms_template_full_data); ?>;

				const div = $('<div />').appendTo('body')
				div.attr('id', 'keyword-modal')
				div.attr('class', 'modal')
				div.attr('style', 'display: none;')

				const $socId = $('#socid')
				const $phoneNumbers = $('#phone_numbers')
				const $autoAddCountryCode = $('#auto_add_country_code')
				const $filterBy = $('#filter_by')
				const $selectCountryId = $('#selectcountry_id')
				const $tpType = $('#tp_type')
				const $prospectCustomer = $('#prospectcustomer')
				$socId.closest('tr').hide()
				$phoneNumbers.closest('tr').hide()
				$autoAddCountryCode.closest('tr').hide()
				$filterBy.closest('tr').hide()
				$selectCountryId.closest('tr').hide()
				$tpType.closest('tr').hide()
				$prospectCustomer.closest('tr').hide()
				const $keywordParagraph = $('#sms_keyword_paragraph')
				const $selectCountryCode = $('#selectcountry_code')

				$('#seven_to').on('change', function () {
					const value = $(this).val()
					if (value === 'all_contacts') {
						$keywordParagraph.show()
						$socId.closest('tr').hide()
						$phoneNumbers.closest('tr').hide()
						$autoAddCountryCode.closest('tr').hide()
						$filterBy.closest('tr').hide()
						$selectCountryId.closest('tr').hide()
						$tpType.closest('tr').hide()
						$prospectCustomer.closest('tr').hide()
					} else if (value === 'all_contacts_of_tp') {
						$keywordParagraph.show()
						$socId.closest('tr').show()
						$phoneNumbers.closest('tr').hide()
						$autoAddCountryCode.closest('tr').hide()
						$filterBy.closest('tr').hide()
						$selectCountryId.closest('tr').hide()
						$tpType.closest('tr').hide()
						$prospectCustomer.closest('tr').hide()
					} else if (value === 'all_contacts_of_spec_tp') {
						$keywordParagraph.show()
						$socId.closest('tr').hide()
						$phoneNumbers.closest('tr').hide()
						$autoAddCountryCode.closest('tr').hide()

						$filterBy.closest('tr').show()
						$selectCountryId.closest('tr').show()

						$filterBy.on('change', function () {
							const value = $(this).val()
							if (value === 'countries') {
								$selectCountryId.closest('tr').show()
								$tpType.closest('tr').hide()
								$prospectCustomer.closest('tr').hide()
							} else if (value === 'tp_type') {
								$selectCountryId.closest('tr').hide()
								$tpType.closest('tr').show()
								$prospectCustomer.closest('tr').hide()
							} else if (value === 'prospectcustomer') {
								$selectCountryId.closest('tr').hide()
								$tpType.closest('tr').hide()
								$prospectCustomer.closest('tr').show()
							} else {
								$selectCountryId.closest('tr').hide()
								$tpType.closest('tr').hide()
								$prospectCustomer.closest('tr').hide()
							}
						})
					} else if (value === 'spec_phone_numbers') {
						$keywordParagraph.hide()
						$socId.closest('tr').hide()
						$phoneNumbers.closest('tr').show()
						$autoAddCountryCode.closest('tr').show()
						$filterBy.closest('tr').hide()
						$selectCountryCode.closest('tr').hide()
						$tpType.closest('tr').hide()
						$prospectCustomer.closest('tr').hide()
					} else {
						$socId.closest('tr').hide()
						$phoneNumbers.closest('tr').hide()
						$autoAddCountryCode.closest('tr').hide()
						$filterBy.closest('tr').hide()
						$selectCountryCode.closest('tr').hide()
						$tpType.closest('tr').hide()
						$prospectCustomer.closest('tr').hide()
					}
				})

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target')
					let caretPosition = document.getElementById(target).selectionStart

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3)

						let tableCode = ''
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) {
								tableCode += `<table class='widefat fixed striped'><tbody>`
							}

							tableCode += '<tr>'
							row.forEach(function (col) {
								tableCode += `<td class='column'>
<button class='button-link' onclick='sevenBindTextToField('${target}', '[${col}]')'>[${col}]</button>
</td>`
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
							console.log(el)
							el.addEventListener('click', function () {
								const {target, keyword} = el.dataset
								console.log({target, keyword})

								const startStr = document.getElementById(target).value.substring(0, caretPosition)
								const endStr = document.getElementById(target).value.substring(caretPosition)
								document.getElementById(target).value = startStr + keyword + endStr
								caretPosition += keyword.length
							})
						})
					})
					let mainTable = ''
					for (const [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

					mainTable += `
					<div style='margin-top: 10px'>
						<small>Press on placeholder to add to template.</small>
					</div>
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
