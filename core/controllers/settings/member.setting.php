<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';

class SMS_Member_Setting extends SevenBaseSettingController {
	var string $context = 'member';
	var array $errors = [];
	var string $page_name = 'member_page_title';
	public string $db_key = 'SEVEN_MEMBER_SETTING';
	public array $trigger_events = [
		'MEMBER_CREATE',
		'MEMBER_VALIDATE',
		'MEMBER_SUBSCRIPTION_CREATE',
		'MEMBER_RESILIATE',
	];

	private function getDefaultSettings(): string {
		return json_encode([
			'enable' => 'off',
			'send_from' => '',
			'send_on' => [
				'created' => 'on',
				'validated' => 'on',
				'subscription_created' => 'on',
				'terminated' => 'on',
			],
			'sms_templates' => [
				'created' => 'Hi [member_firstname], we\'re processing your membership application.',
				'validated' => 'Hi [member_firstname], your membership has been verified!',
				'subscription_created' => 'Hi [member_firstname], thank you for your contribution of \$[member_contribution_amount].',
				'terminated' => 'Hi [member_firstname], your membership has expired. Please renew now to retain access.',
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
				'subscription_created' => GETPOST('send_on_subscription_created'),
				'terminated' => GETPOST('send_on_terminated'),
			],
			'sms_templates' => [
				'created' => GETPOST('sms_templates_created'),
				'validated' => GETPOST('sms_templates_validated'),
				'subscription_created' => GETPOST('sms_templates_subscription_created'),
				'terminated' => GETPOST('sms_templates_terminated'),
			],
		];

		$settings = json_encode($settings);
		$error = dolibarr_set_const($db, $this->db_key, $settings);

		if ($error < 1) {
			$this->log->add('Seven', 'failed to update the member settings: ' . print_r($settings, 1));
			$this->errors[] = 'There was an error saving member settings.';
		}
		if (count($this->errors) > 0) $this->addNotificationMessage('Failed to save member settings', 'error');
		else $this->addNotificationMessage('Member settings saved');
	}

	public function getSettings() {
		global $conf;

		if (property_exists($conf->global, $this->db_key)) $settings = $conf->global->{$this->db_key};
		else $settings = $this->getDefaultSettings();

		return json_decode($settings);
	}

	public function triggerSendSms($object, $status): void { // $object can either be class Adherent (member) or Subscription
		$settings = $this->getSettings();
		if ($settings->enable != 'on') return;
		if ($settings->send_on->created != 'on') return;

		$from = $settings->send_from;
		$member = $object;

		if (get_class($object) == 'Subscription') {
			$member = $object->context['member'];

			if (empty($member)) {
				$this->log->add('Seven', 'member object is empty. Aborting');
				return;
			}
		}

		$member->fetch($object->id);
		$to = $member->phone_mobile;
		if (empty($to)) return;
		$message = $settings->sms_templates->{$status};
		$message = $this->fillKeywordsWithValues($object, $message);

		sevenSendSms($from, $to, $message, 'Automation');
	}

	protected function fillKeywordsWithValues($object, $message): array {
		$member = $object;

		if (get_class($object) == 'Subscription') {
			$subscription = $object;
			$member = $subscription->context['member'];

			if (empty($member)) {
				$this->log->add('Seven', 'member object is empty. Aborting');
				return [];
			}
		}

		$keywords = [
			'[member_id]' => !empty($member->id) ? $member->id : '',
			'[member_firstname]' => !empty($member->firstname) ? $member->firstname : '',
			'[member_lastname]' => !empty($member->lastname) ? $member->lastname : '',
			'[company_name]' => !empty($member->company) ? $member->company : '',
			'[member_country]' => !empty($member->country) ? $member->country : '',
			'[member_country_code]' => !empty($member->country_code) ? $member->country_code : '',
			'[member_address]' => !empty($member->address) ? $member->address : '',
			'[member_town]' => !empty($member->town) ? $member->town : '',
			'[member_zip]' => !empty($member->zip) ? $member->zip : '',
			'[member_mobile_phone]' => !empty($member->phone_mobile) ? $member->phone_mobile : '',
			'[membership_type]' => !empty($member->type) ? $member->type : '',
			'[member_fax]' => !empty($member->fax) ? $member->fax : '',
			'[member_email]' => !empty($member->email) ? $member->email : '',
			'[member_url]' => !empty($member->url) ? $member->url : '',
			'[member_contribution_amount]' => !empty($subscription->amount) ? number_format($subscription->amount, 2) : '',
			'[member_contribution_note]' => !empty($subscription->note_private) ? $subscription->note_private : '',
		];

		$replaced_msg = str_replace(array_keys($keywords), array_values($keywords), $message);
		$this->log->add('Seven', 'replaced_msg: ' . $replaced_msg);

		return $replaced_msg;
	}

	public function getKeywords(): array {
		return [
			'member' => [
				'member_id',
				'member_firstname',
				'member_lastname',
				'company_name',
				'member_country',
				'member_country_code',
				'member_address',
				'member_town',
				'member_zip',
				'member_mobile_phone',
				'membership_type',
				'member_fax',
				'member_email',
				'member_url',
				'member_contribution_amount',
				'member_contribution_note',
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
						<?php foreach ($settings->send_on as $key => $value) { ?>
							<label for='send_on_<?= $key ?>'>
								<input type='hidden' name='send_on_<?= $key ?>' value='off'/>
								<input id='send_on_<?= $key ?>'
									   name='send_on_<?= $key ?>' <?= ($value == 'on') ? 'checked' : '' ?>
									   type='checkbox'/>

								<?= $langs->trans('seven_' . $this->context . '_send_on_' . $key) ?>
							</label>
						<?php } ?>
					</td>
				</tr>
				<?php foreach ($settings->sms_templates as $key => $value) : ?>
					<tr>
						<td><?= $langs->trans('seven_' . $this->context . '_sms_templates_' . $key) ?></td>
						<td>
							<label for='sms_templates_<?= $key ?>'>
								<textarea id='sms_templates_<?= $key ?>' name='sms_templates_<?= $key ?>'
										  cols='40' rows='4'><?= $value ?></textarea>
							</label>
							<p>
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
					for (let [key, value] of Object.entries(entityKeywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`
						mainTable += buildTable(value)
					}

					mainTable += `<div style='margin-top:10px'><small>Press on placeholder to add to template.</small></div>`

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
