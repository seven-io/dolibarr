<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class SMS_Ticket_Setting extends SevenBaseSettingController {
	var $context;
	var $db;
	var $errors;
	var $form;
	var $log;
	var $page_name;
	public $db_key = 'SEVEN_TICKET_SETTING';
	public $trigger_events = [
		'TICKET_MODIFY',
		'TICKET_ASSIGNED',
		'TICKET_CREATE',
		'TICKET_CLOSE',
		'TICKET_DELETE',
	];

	function __construct($db) {
		$this->context = 'ticket';
		$this->db = $db;
		$this->errors = [];
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->page_name = 'ticket_page_title';
	}

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'closed' => 'off',
				'read' => 'off',
				'assigned' => 'off',
			],
			'sms_templates' => [
				'created' => 'Hi [company_name], your ticket: ([ticket_ref]) has been created. Our agents will attend to you shortly.',
				'closed' => 'Hi [company_name], your ticket: ([ticket_ref]) has been closed.',
				'read' => 'Hi [company_name], your ticket: ([ticket_ref]) has been read by [assigned_user_fullname].',
				'assigned' => 'Hi [company_name], your ticket has been assigned to [assigned_user_fullname].',
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
				'read' => GETPOST('send_on_read'),
				'assigned' => GETPOST('send_on_assigned'),
			],
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'closed' => GETPOST('sms_templates_closed'),
				'read' => GETPOST('sms_templates_read'),
				'assigned' => GETPOST('sms_templates_assigned'),
			],
		]);

		$error = dolibarr_set_const($db, $this->db_key, $settings);
		if ($error < 1) {
			$this->log->add('Seven', 'failed to update the ticket settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving ticket settings.';
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save ticket settings', 'error');
		else $this->addNotificationMessage('Ticket settings saved');
	}

	public function getSettings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) {
			$settings = $conf->global->{$this->db_key};
			$default_settings = $this->getDefaultSettings();
			$supported_statuses = array_keys(json_decode($default_settings, 1)['send_on']);
			$settings = json_decode($settings, 1);
			foreach ($settings['send_on'] as $key => $value)
				if (!in_array($key, $supported_statuses)) unset($settings['send_on'][$key]);
			$settings = json_encode($settings);

		} else $settings = $this->getDefaultSettings();

		return json_decode($settings);
	}

	public function trigger_sms(Ticket $object, $action = ''): void {
		global $db;

		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;

		if (empty($action)) {
			$ticket = new Ticket($db);
			$ticket->fetch($object->id);
			$action = match ($ticket->status) {
				0 => 'not_read',
				1 => 'read',
				2 => 'assigned',
				3 => 'in_progress',
				5 => 'need_more_info',
				7 => 'waiting',
				default => 'not_available',
			};
		}

		if ($settings->send_on->{$action} != 'on') return;
		$this->log->add('Seven', 'current ticket action triggered: {$action}');

		$company = new Societe($db);
		$company->fetch($object->fk_soc);

		$from = $settings->send_from;
		$to = validatedMobileNumber($company->phone, $company->country_code);
		if (empty($to)) {
			$this->log->add('Seven', 'SMS to is empty. Aborting SMS');
			return;
		}
		$message = $settings->sms_templates->{$action};
		$message = $this->fillKeywordsWithValues($object, $message);

		$resp = seven_send_sms($from, $to, $message, 'Automation');
		if ($resp['messages'][0]['status'] == 0)
			EventLogger::create($object->fk_soc, 'thirdparty', 'SMS sent to third party');
		else
			EventLogger::create($object->fk_soc, 'thirdparty', 'SMS failed to send to third party', 'SMS failed due to: ' . $resp['messages'][0]['err_msg']);
	}

	protected function fillKeywordsWithValues(Ticket $object, $message) {
		global $db, $langs;
		$company = new Societe($db);
		$company->fetch($object->fk_soc);

		$assignedUser = new User($db);
		$assignedUser->fetch($object->fk_user_assign);
		$keywords = [
			'[ticket_id]' => !empty($object->id) ? $object->id : 'N/A',
			'[ticket_track_id]' => !empty($object->track_id) ? $object->track_id : 'N/A',
			'[ticket_priority]' => !empty($object->severity_label) ? $object->severity_label : 'N/A',
			'[ticket_ref]' => !empty($object->ref) ? $object->ref : 'N/A',
			'[ticket_status]' => !empty($object->getLibStatut(1)) ? $object->getLibStatut(1) : 'N/A',
			'[ticket_subject]' => !empty($object->subject) ? $object->subject : 'N/A',
			'[ticket_message]' => !empty($object->message) ? $object->message : 'N/A',
			'[assigned_user_town]' => !empty($assignedUser->town) ? $assignedUser->town : 'N/A',
			'[assigned_user_country]' => !empty($assignedUser->country) ? $assignedUser->country : 'N/A',
			'[assigned_user_country_code]' => !empty($assignedUser->country_code) ? $assignedUser->country_code : 'N/A',
			'[assigned_user_email]' => !empty($assignedUser->email) ? $assignedUser->email : 'N/A',
			'[assigned_user_note_public]' => !empty($assignedUser->note_public) ? $assignedUser->note_public : 'N/A',
			'[assigned_user_note_private]' => !empty($assignedUser->note_private) ? $assignedUser->note_private : 'N/A',
			'[assigned_user_firstname]' => !empty($assignedUser->firstname) ? $assignedUser->firstname : 'N/A',
			'[assigned_user_lastname]' => !empty($assignedUser->lastname) ? $assignedUser->lastname : 'N/A',
			'[assigned_user_fullname]' => !empty($assignedUser->getFullName($langs)) ? $assignedUser->getFullName($langs) : 'N/A',
			'[company_id]' => !empty($company->id) ? $company->id : 'N/A',
			'[company_name]' => !empty($company->name) ? $company->name : 'N/A',
			'[company_alias_name]' => !empty($company->name_alias) ? $company->name_alias : 'N/A',
			'[company_address]' => !empty($company->address) ? $company->address : 'N/A',
			'[company_zip]' => !empty($company->zip) ? $company->zip : 'N/A',
			'[company_town]' => !empty($company->town) ? $company->town : 'N/A',
			'[company_phone]' => !empty($company->phone) ? $company->phone : 'N/A',
			'[company_fax]' => !empty($company->fax) ? $company->fax : 'N/A',
			'[company_email]' => !empty($company->email) ? $company->email : 'N/A',
			'[company_url]' => !empty($company->url) ? $company->url : 'N/A',
			'[company_capital]' => !empty($company->capital) ? $company->capital : 'N/A',
		];

		$replacedMsg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add('Seven', 'replaced_msg: ' . $replacedMsg);
		return $replacedMsg;
	}

	public function getKeywords(): array {
		return [
			'ticket' => [
				'id',
				'track_id',
				'priority',
				'ref',
				'ticket_status',
				'subject',
				'message',
			],
			'Assigned User' => [
				'assigned_user_town',
				'assigned_user_country',
				'assigned_user_country_code',
				'assigned_user_email',
				'assigned_user_note_public',
				'assigned_user_note_private',
				'assigned_user_firstname',
				'assigned_user_lastname',
			],
			'Third Party' => [
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
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		print dol_get_fiche_head(sevenAdminPrepareHead(), 'ticket', $langs->trans($this->page_name), -1);

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
					<td width='200px'><?= $langs->trans('sms_form_send_from') ?></td>
					<td>
						<input type='text' name='send_from' value='<?= $settings->send_from ?>'>
					</td>
				</tr>
				<tr>
					<td width='200px'><?= $langs->trans('sms_form_send_on') ?></td>
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
				<input class='button' type='submit' name='submit' value='<?= $langs->trans('sms_form_save') ?>'>
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
					for (let [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

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
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
