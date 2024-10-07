<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/contact.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/third_party.setting.php');
dol_include_once('/seven/core/controllers/settings/contact.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_template.setting.php');

class SMS_SendSMS_Setting extends SevenBaseSettingController {
	private $context;
	private $errors;
	private $form;
	private $log;
	private $page_name;
	private $thirdparty;

	function __construct($db) {
		$this->context = 'send_sms';
		$this->errors = [];
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->page_name = 'send_sms_page_title';
		$this->thirdparty = new Societe($db);
	}

	public function validate_sms_form($data) {
		$error = false;
		if (empty($data['sms_from'])) {
			$this->add_error('Sender field is required');
			$error = true;
		}
		if (empty($data['sms_message'])) {
			$this->add_error('Message is required');
			$error = true;
		}

		return $error;
	}

	public function handle_send_sms_form() {
		global $db, $user;

		if (!empty($_POST) && !empty($_POST['action'])) {

			if (!$user->rights->seven->permission->write) {
				accessforbidden();
			}

			$action = GETPOST('action');

			if ($action == 'send_sms') {
				$sms_from = GETPOST('sms_from');
				$sms_contact_ids = GETPOST('sms_contact_ids');
				$sms_thirdparty_id = GETPOST('sms_thirdparty_id');
				$send_sms_to_thirdparty_flag = GETPOST('send_sms_to_thirdparty_flag') == 'on' ? true : false;
				$sms_message = GETPOST('sms_message');
				$sms_scheduled_datetime = GETPOST('sms_scheduled_datetime');

				$post_data = [];
				$post_data['sms_contact_ids'] = $sms_contact_ids;
				$post_data['sms_thirdparty_id'] = $sms_thirdparty_id;
				$post_data['send_sms_to_thirdparty_flag'] = $send_sms_to_thirdparty_flag;
				$post_data['sms_from'] = $sms_from;
				$post_data['sms_message'] = $sms_message;
				$post_data['sms_scheduled_datetime'] = $sms_scheduled_datetime;

				$error = $this->validate_sms_form($post_data);

				if ($error) {
					return;
				}

				$total_sms_responses = [];

				// schedule SMS
				// YYYY-MM-DD hh:mm:ss
				if (!empty($sms_thirdparty_id)) {
					if (empty($sms_contact_ids)) {
						$tp_obj = new ThirdPartyController($sms_thirdparty_id);
						$tp_phone_no = $tp_obj->get_thirdparty_mobile_number();

						$tp_setting_obj = new SMS_ThirdParty_Setting($db);
						$dol_tp_obj = new Societe($db);
						$dol_tp_obj->fetch($sms_thirdparty_id);

						$message = $tp_setting_obj->replace_keywords_with_value($dol_tp_obj, $sms_message);

						$args = [];
						if (!empty($sms_scheduled_datetime)) {
							$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$resp = seven_send_sms($sms_from, $tp_phone_no, $message, 'Send SMS', $args);
						$total_sms_responses[] = $resp;
						if ($resp['messages'][0]['status'] == 0) {
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS sent to third party');
						} else {
							$err_msg = $resp['messages'][0]['err_msg'];
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: {$err_msg}');
						}
					} else if (!empty($sms_contact_ids) && $send_sms_to_thirdparty_flag) {
						$tp_obj = new ThirdPartyController($sms_thirdparty_id);
						$tp_phone_no = $tp_obj->get_thirdparty_mobile_number();

						$tp_setting_obj = new SMS_ThirdParty_Setting($db);
						$dol_tp_obj = new Societe($db);
						$dol_tp_obj->fetch($sms_thirdparty_id);

						$message = $tp_setting_obj->replace_keywords_with_value($dol_tp_obj, $sms_message);

						$args = [];
						if (!empty($sms_scheduled_datetime)) {
							$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$resp = seven_send_sms($sms_from, $tp_phone_no, $message, 'Send SMS', $args);
						$total_sms_responses[] = $resp;
						if ($resp['messages'][0]['status'] == 0) {
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS sent to third party');
						} else {
							$err_msg = $resp['messages'][0]['err_msg'];
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: {$err_msg}');
						}
					}
				}

				if (isset($sms_contact_ids) && !empty($sms_contact_ids)) {
					foreach ($sms_contact_ids as $sms_contact_id) {
						$contact = new ContactController($sms_contact_id);
						$sms_to = $contact->get_contact_mobile_number();

						$contact_setting_obj = new SMS_Contact_Setting($db);
						$dol_contact_obj = new Contact($db);
						$dol_contact_obj->fetch($sms_contact_id);

						$message = $contact_setting_obj->replace_keywords_with_value($dol_contact_obj, $sms_message);

						$args = [];
						if (!empty($sms_scheduled_datetime)) {
							$real_scheduled_dt = new DateTime($sms_scheduled_datetime, new DateTimeZone(getServerTimeZoneString()));
							$real_scheduled_dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));

							$args['delay'] = $real_scheduled_dt->format('Y-m-d H:i:s');
						}

						$resp = seven_send_sms($sms_from, $sms_to, $message, 'Send SMS', $args);
						$total_sms_responses[] = $resp;
						if ($resp['messages'][0]['status'] == 0) {
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS sent to third party');
						} else {
							$err_msg = $resp['messages'][0]['err_msg'];
							EventLogger::create($sms_thirdparty_id, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: {$err_msg}');
						}
					}
				}
				$success_sms = 0;
				$total_sms = count($total_sms_responses);
				foreach ($total_sms_responses as $sms_response) {
					if ($sms_response['messages'][0]['status'] == 0) {
						$success_sms++;
					}
				}

				$response = [];
				$response['success'] = $success_sms;
				$response['failed'] = $total_sms - $success_sms;

				try {
					if (is_array($response)) {
						$this->add_notification_message('SMS sent successfully: {$response[\'success\']}, Failed: {$response[\'failed\']}');
					} else {
						$this->add_notification_message('Failed to send SMS', 'error');
					}

				} catch (Exception $e) {
					$this->add_notification_message('Critical error...', 'error');
					$this->log->add('Seven', 'Error: ' . $e->getMessage());
				}

			}

		}
	}

	public function get_keywords() {
		$keywords = [
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
		return $keywords;
	}

	private function add_error($error) {
		$this->errors[] = $error;
	}

	private function get_errors() {
		return $this->errors;
	}

	public function render() {
		global $conf, $user, $langs, $db;

		$this->thirdparty->id = !empty($this->thirdparty->id) ? $this->thirdparty->id : 0;

		$sms_templates = [];
		$sms_templates[0] = 'Select a template to use';

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

		if (isset($_GET) && !empty($_GET)) {
			if (!empty($_GET['thirdparty_id'])) {
				$tp_id = intval($_GET['thirdparty_id']);
				$this->thirdparty->fetch($tp_id);
			}
		}

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
		<form method='POST' enctype='multipart/form-data' action='<?= $_SERVER['PHP_SELF'] ?>'
			  style='max-width: 500px'>
			<?php if (!empty($this->get_errors())) { ?>
				<?php foreach ($this->get_errors() as $error) { ?>
					<div class='error'><?= $error ?></div>
				<?php } ?>
			<?php } ?>
			<input type='hidden' name='action' value='send_sms'>
			<input type='hidden' name='token' value='<?= newToken(); ?>'>
			<!-- Balance -->

			<table class='border' width='100%'>
				<!-- From -->

				<tr>
					<td><?= $this->form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID'); ?>*
					</td>
					<td>
						<input style='width:300px' type='text' name='sms_from' size='30'
							   value='<?= !empty($conf->global->SEVEN_FROM) ? $conf->global->SEVEN_FROM : ''; ?>'>
					</td>
				</tr>

				<!-- To -->
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
					<td>
					</td>
					<td>
						<?= $this->form->selectcontacts($this->thirdparty->id, '', 'sms_contact_ids', 1, '', '', 1, 'width300', false, 0, 0, [], '', '', true) ?>
					</td>
				</tr>

				<!-- Schedule SMS -->
				<tr>
					<td>
						<?= $this->form->textwithpicto($langs->trans('ScheduleSMSButtonTitle'), 'Schedule your SMS to be sent at specific date time'); ?>
					</td>
					<td>
						<input style='width:300px' type='text' name='sms_scheduled_datetime'
							   id='sms_scheduled_datetime'/>
					</td>
				</tr>

				<!-- SMS Template -->
				<tr>
					<td>
						<?= $this->form->textwithpicto($langs->trans('SmsTemplate'), 'The SMS template you want to use'); ?>
					</td>
					<td>
						<?= $this->form->selectarray('sms_template_id', $sms_templates, '', 0, 0, 0, '', 0, 0, 0, '', 'width300') ?>
					</td>
				</tr>

				<tr>
					<td valign='top'><?= $langs->trans('Sms_To_Thirdparty_Flag') ?></td>
					<td>
						<input id='send_sms_to_thirdparty_flag' type='checkbox'
							   name='send_sms_to_thirdparty_flag'></input>
					</td>
				</tr>
				<tr>
					<td valign='top'><?= $langs->trans('SmsText') ?>*</td>
					<td>
						<textarea style='width:300px' cols='40' name='sms_message' id='message' rows='4'></textarea>
						<div>
							<p id='sms_keyword_paragraph'>Customize your SMS with keywords
								<button type='button' class='seven_open_keyword' data-attr-target='message'>
									Keywords
								</button>
							</p>
						</div>
					</td>
				</tr>
			</table>

			<input style='float:right' class='button' type='submit' name='submit' value='<?= $langs->trans('SendSMSButtonTitle') ?>' />
		</form>
		<script>
			const entity_keywords = <?= json_encode($this->get_keywords()); ?>;
			const sms_templates = <?= json_encode($sms_template_full_data); ?>;

			const div = $('<div />').appendTo('body');
			div.attr('id', `keyword-modal`);
			div.attr('class', 'modal');
			div.attr('style', 'display: none;');

			jQuery(document).ready(function () {
				$('#send_sms_to_thirdparty_flag').closest('tr').hide();
				$('#sms_contact_ids').on('change', function () {
					const sms_contact_id = $('#sms_contact_ids').val();
					const thirdpartyTree = $('#sms_thirdparty_id');
					// if the tree exists
					if (thirdpartyTree.length > 0) {
						if (sms_contact_id.length > 0) {
							$('#send_sms_to_thirdparty_flag').closest('tr').show();
						} else {
							$('#send_sms_to_thirdparty_flag').closest('tr').hide();
						}
					}
				});
				$('#sms_thirdparty_id').on('change', function () {
					const chosen_tp_id = $(this).val();

					urlParams = new URLSearchParams(window.location.search);
					urlParams.set('thirdparty_id', chosen_tp_id);

					const baseURL = window.location.href.split('?')[0];
					window.location = baseURL + '?' + urlParams.toString();
				});

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target');
					caretPosition = document.getElementById(target).selectionStart;

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3);

						let tableCode = '';
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) {
								tableCode += '<table class='widefat fixed striped'><tbody>';
							}

							tableCode += '<tr>';
							row.forEach(function (col) {
								tableCode += `<td class='column'><button class='button-link' onclick='seven_bind_text_to_field('${target}', '[${col}]')'>[${col}]</button></td>`;
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

					mainTable += '<div style='margin-top: 10px'><small>*Press on keyword to add to sms template</small></div>';

					$('#keyword-modal').html(mainTable);
					$('#keyword-modal').modal();
				});

				$('#sms_template_id').on('change', function () {
					const selected_template_id = this.value;
					const sms_template_data = sms_templates[selected_template_id];
					$('#message').val(sms_template_data.message);
				});

				$('#sms_scheduled_datetime').datetimepicker({
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
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
