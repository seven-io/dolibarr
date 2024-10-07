<?php
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/contact.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_template.setting.php');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';

class SMS_Bulk_Setting extends SevenBaseSettingController {
	private $context;
	private $db;
	private $errors;
	private $form;
	private $form_company;
	private $log;
	private $page_name;

	function __construct($db) {
		$this->context = 'bulk_sms';
		$this->db = $db;
		$this->errors = [];
		$this->form = new Form($db);
		$this->form_company = new FormCompany($db);
		$this->log = new Seven_Logger;
		$this->page_name = 'bulk_sms_page_title';
	}

	public function validateSmsForm(array $postData): bool {
		if ($postData['to'] == 'all_contacts_of_tp')
			if (empty($postData['socid'])) $this->errors[] = 'Third party is required';
		if ($postData['to'] == 'spec_phone_numbers') {
			$regex_pattern = '/^\d+(?:,\d+)*$/';
			$phone_numbers = $postData['phone_numbers'];

			if (!preg_match($regex_pattern, $phone_numbers)) $this->errors[] = 'Mobile phone must be comma separated';
		}
		$filterBy = htmlspecialchars($postData['filter_by']);
		$countryId = htmlspecialchars($postData['country_id']);
		$tpType = htmlspecialchars($postData['tp_type']);
		$prospectcustomer = htmlspecialchars($postData['prospectcustomer']);

		if ($postData['to'] == 'all_contacts_of_spec_tp') {
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
		if ($postData['action'] != 'send_bulk_sms') return;
		if (!$this->validateSmsForm($postData)) return;

		$sevenFrom = htmlspecialchars($postData['from']);
		$sevenTo = htmlspecialchars($postData['to']);
		$message = htmlspecialchars($postData['message']);
		$socid = htmlspecialchars($postData['socid']);
		$phoneNumbers = htmlspecialchars($postData['phone_numbers']);
		$autoAddCountryCode = htmlspecialchars($postData['auto_add_country_code']) == 'on' ? true : false;
		$filterBy = htmlspecialchars($postData['filter_by']);
		$countryId = htmlspecialchars($postData['country_id']);
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
					$val_mobile = validatedMobileNumber($contact->phone_mobile, $contact->country_code);
					if (empty($val_mobile)) {
						$failed_message_data = [
							'messages' => [
								[
									'status' => 1,
									'err_msg' => 'Invalid mobile number {$contact->phone_mobile} for country code:{$contact->country_code}',
								]
							]
						];
						$totalResponses[] = $failed_message_data;
						continue;
					}
					$text = $contactModel->fillKeywordsWithValues($contact, $message);
					$args = [];
					if (!empty($scheduledDatetime)) {
						$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
						$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

						$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
					}

					$resp = seven_send_sms($sevenFrom, $val_mobile, $text, 'Bulk SMS', $args);
					$totalResponses[] = $resp;
					$msg = $resp['messages'][0];
					if ($msg['status'] == 0) {
						EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
					} else {
						EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $msg['err_msg']);
					}
				}
			} else return -1; // no contacts
		} else if ($sevenTo == 'all_contacts_of_tp') {
			// TODO
			$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE fk_soc = {$socid};';
			$resql = $this->db->query($sql);
			if ($resql) {
				if ($resql->num_rows > 0) {
					while ($obj = $this->db->fetch_object($resql)) {
						$contact = new Contact($this->db);
						$contact->fetch($obj->rowid);
						$val_mobile = validatedMobileNumber($contact->phone_mobile, $contact->country_code);
						if (empty($val_mobile)) {
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
							$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$resp = seven_send_sms($sevenFrom, $val_mobile, $text, 'Bulk SMS', $args);
						$totalResponses[] = $resp;
						if ($resp['messages'][0]['status'] == 0) {
							EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
						} else {
							$err_msg = $resp['messages'][0]['err_msg'];
							EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
						}
					}
				} else {
					$this->addNotificationMessage('No contacts found in third party', 'error');
				}
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
							$val_mobile = validatedMobileNumber($contact->phone_mobile, $contact->country_code);
							if (empty($val_mobile)) {
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
								$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
							}

							$resp = seven_send_sms($sevenFrom, $val_mobile, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							if ($resp['messages'][0]['status'] == 0) {
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							} else {
								$err_msg = $resp['messages'][0]['err_msg'];
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
							}
						}

					} else {
						$this->addNotificationMessage('No filtered contacts found in third party', 'error');
					}
				} else {
					return -1; // sql failed
				}
			} else if ($filterBy == 'tp_type') {
				$sql = 'SELECT sp.rowid FROM ' . MAIN_DB_PREFIX . 'societe as s, ' . MAIN_DB_PREFIX . 'socpeople as sp WHERE s.fk_typent = {$tpType} AND sp.fk_soc = s.rowid;';
				$resql = $this->db->query($sql);
				if ($resql) {
					if ($resql->num_rows > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$contact = new Contact($this->db);
							$contact->fetch($obj->rowid);
							$val_mobile = validatedMobileNumber($contact->phone_mobile, $contact->country_code);
							if (empty($val_mobile)) {
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
								$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
							}

							$resp = seven_send_sms($sevenFrom, $val_mobile, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							if ($resp['messages'][0]['status'] == 0) {
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							} else {
								$err_msg = $resp['messages'][0]['err_msg'];
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
							}
						}
					} else {
						$this->addNotificationMessage('No filtered contacts found in third party', 'error');
					}
				} else {
					return -1; // no contacts
				}
			} else if ($filterBy == 'prospectcustomer') {
				$sql = 'SELECT sp.rowid FROM ' . MAIN_DB_PREFIX . 'societe as s, ' . MAIN_DB_PREFIX . 'socpeople as sp WHERE s.client = {$prospectcustomer} AND sp.fk_soc = s.rowid;';
				$resql = $this->db->query($sql);
				if ($resql) {
					if ($resql->num_rows > 0) {
						while ($obj = $this->db->fetch_object($resql)) {
							$contact = new Contact($this->db);
							$contact->fetch($obj->rowid);
							$val_mobile = validatedMobileNumber($contact->phone_mobile, $contact->country_code);
							if (empty($val_mobile)) {
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
								$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
								$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

								$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
							}

							$resp = seven_send_sms($sevenFrom, $val_mobile, $text, 'Bulk SMS', $args);
							$totalResponses[] = $resp;
							if ($resp['messages'][0]['status'] == 0) {
								EventLogger::create($contact->id, 'contact', 'SMS sent to contact');
							} else {
								$err_msg = $resp['messages'][0]['err_msg'];
								EventLogger::create($contact->id, 'contact', 'SMS failed to send to contact', 'SMS failed due to: {$err_msg}');
							}
						}
					} else {
						$this->addNotificationMessage('No filtered contacts found in third party', 'error');
					}
				} else {
					return -1; // no contacts
				}
			}
		} else if ($sevenTo == 'spec_phone_numbers') {
			$comma_sep_numbers = explode(',', $phoneNumbers);
			$client_ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
			$country_code = get_country_code_from_ip($client_ip_address);
			foreach ($comma_sep_numbers as $mob_num) {
				if (ctype_digit($mob_num)) {
					if ($autoAddCountryCode) {
						// add prefix country code
						$val_mobile = validatedMobileNumber($mob_num, $country_code);
						if (empty($val_mobile)) {
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
						$args = [];
						if (!empty($scheduledDatetime)) {
							$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$totalResponses[] = seven_send_sms($sevenFrom, $val_mobile, $message, 'Bulk SMS', $args);
					} else {
						$args = [];
						if (!empty($scheduledDatetime)) {
							$real_scheduled_dt = new DateTime($scheduledDatetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$totalResponses[] = seven_send_sms($sevenFrom, $mob_num, $message, 'Bulk SMS', $args);
					}
				}
			}
		}

		$success_sms = 0;
		$total_sms = count($totalResponses);

		foreach ($totalResponses as $sms_response) {
			if ($sms_response['messages'][0]['status'] == 0) {
				$success_sms++;
			}
		}

		$response = [];
		$response['success'] = $success_sms;
		$response['failed'] = $total_sms - $success_sms;

		try {
			if (is_array($response)) {
				$this->addNotificationMessage('SMS sent successfully: {$response[\'success\']}, Failed: {$response[\'failed\']}');
			} else {
				$this->addNotificationMessage('Failed to send SMS', 'error');
			}

		} catch (Exception $e) {
			$this->addNotificationMessage('Critical error...', 'error');
			$this->log->add('Seven', 'Error: ' . $e->getMessage());
		}
	}

	public function addNotificationMessage($message, $style = 'ok'): void {
		$this->notificationMessages[] = compact('message', 'style');
	}

	public function render(): void {
		global $conf, $langs, $db;

		$clientIp = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		$autoAddCountryCode = get_country_code_from_ip($clientIp);
		$contactModel = new SMS_Contact_Setting($db);
		$templates = [];
		$templates[0] = 'Select a template to use';
		$templateObj = new SMS_Template_Setting($db);
		$templateInKv = $templateObj->getAllSmsTemplatesAsArray();
		foreach ($templateInKv as $id => $title) $templates[$id] = $title;

		$sms_templates_obj = $templateObj->getAllSmsTemplates();
		$sms_template_full_data = [];
		foreach ($sms_templates_obj as $sms_t_obj) $sms_template_full_data[$sms_t_obj['id']] = $sms_t_obj;
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, $this->context, $langs->trans($this->page_name), -1);

		foreach ($this->notificationMessages ?? [] as $notification_message)
			dol_htmloutput_mesg($notification_message['message'], [], $notification_message['style']);
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
						<?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Your business name'); ?>
					</td>
					<td>
						<input style='width:300px' name='seven_from'
							   value='<?= !empty($conf->global->SEVEN_FROM) ? $conf->global->SEVEN_FROM : '' ?>'
						/>
					</td>
				</tr>
				<tr>
					<td><?= $this->form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
						*
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
					<td>Filter by</td>
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
					<td>Mobile numbers</td>
					<td>
						<textarea style='width:300px' name='phone_numbers' id='phone_numbers' cols='30' rows='5'
								  placeholder='60123456789,12014567890'></textarea>
					</td>
				</tr>
				<tr>
					<td>Automatically add country code (<?= '+' . $autoAddCountryCode ?>)</td>
					<td>
						<input name='auto_add_country_code' id='auto_add_country_code' type='checkbox'/>
					</td>
				</tr>
				<tr>
					<td>
						<?= $this->form->textwithpicto($langs->trans('ScheduleSMSButtonTitle'), 'Schedule your SMS to be sent at specific date time'); ?>
					</td>
					<td>
						<input style='width:300px' type='text' name='sms_scheduled_datetime'
							   id='sms_scheduled_datetime'/>
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
					<td><?= $langs->trans('SmsText') ?>*</td>
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
				div.attr('id', `keyword-modal`)
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
					caretPosition = document.getElementById(target).selectionStart

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

					let mainTable = ''
					for (const [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

					mainTable += `
					<div style='margin-top: 10px'>
						<small>*Press on placeholder to add to template.</small>
					</div>
					`

					$keywordModal.html(mainTable)
					$keywordModal.modal()
				})

				$('#sms_template_id').on('change', function () {
					const selectedTemplateId = this.value
					const templateData = smsTemplates[selectedTemplateId]
					$('#message').val(templateData.message)
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
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
