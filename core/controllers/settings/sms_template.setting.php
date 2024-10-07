<?php

use JetBrains\PhpStorm\NoReturn;

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven_sms_template.db.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');
dol_include_once('/seven/core/controllers/settings/contact.setting.php');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';

class SMS_Template_Setting extends SevenBaseSettingController {
	private string $context = 'sms_template';
	private array $errors = [];
	private Form $form;
	private FormCompany $formCompany;
	private Seven_Logger $log;
	private string $pageName = 'sms_template_page_title';
	private SevenSMSTemplateDatabase $smsTemplateDB;

	function __construct(private DoliDB $db) {
		$this->form = new Form($db);
		$this->formCompany = new FormCompany($db);
		$this->log = new Seven_Logger;
		$this->smsTemplateDB = new SevenSMSTemplateDatabase($db);
	}

	function processPostData(): void {
		global $user;

		if (!isset($_POST) || empty($_POST)) return;

		if (!$user->rights->seven->permission->write) accessforbidden();

		switch (GETPOST('action', 'alphanohtml', '3')) {
			case 'save_sms_template':
				$this->saveSmsTemplate([
					'sms_template_id' => GETPOST('sms_template_id', 'int', 2),
					'sms_template_title' => GETPOST('sms_template_title', 'alphanohtml', 2),
					'sms_template_message' => GETPOST('sms_template_message', 'alphanohtml', 2)
				]);
				break;
			case 'delete_sms_template':
				$this->deleteSmsTemplate(GETPOST('sms_template_id', 'int', 2));
				break;
		}
	}

	function processGetData(): void {
		global $user;

		if (!isset($_GET) || empty($_GET)) return;

		if (!$user->rights->seven->permission->write) accessforbidden();

		if (GETPOST('action', 'alphanohtml', 1) == 'fetch_sms_template') $this->fetchSmsTemplate();
	}

	function deleteSmsTemplate($id): void {
		$this->smsTemplateDB->deleteById($id);
	}

	#[NoReturn] function fetchSmsTemplate(): void {
		echo json_encode($this->getSmsTemplate(GETPOST('sms_template_id', 'int', 1)));
		exit();
	}

	function getSmsTemplate($id) {
		return $this->smsTemplateDB->get($id)->fetch_assoc();
	}

	function saveSmsTemplate($tpl): void {
		$id = $tpl['sms_template_id'];
		$title = $tpl['sms_template_title'];
		$message = $tpl['sms_template_message'];

		if (empty($id)) $this->smsTemplateDB->insert($title, $message);
		else $this->smsTemplateDB->updateById($id, $title, $message);
	}

	function getAllSmsTemplates() {
		return $this->smsTemplateDB->getAll();
	}

	function getAllSmsTemplatesAsArray(): array {
		$sms_template_kv = [];
		foreach ($this->getAllSmsTemplates() as $sms_template)
			$sms_template_kv[$sms_template['id']] = $sms_template['title'];
		return $sms_template_kv;
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

	private function listView(): void {
		$currentPage = 1;

		if (isset($_GET['pageno'])) $currentPage = filter_var($_GET['pageno'], FILTER_SANITIZE_NUMBER_INT);

		$perPage = 15;
		$offset = ($currentPage - 1) * $perPage;

		$results = [];
		foreach ($this->smsTemplateDB->getAll($perPage, $offset) as $qr) $results[] = $qr;
		$total = $this->smsTemplateDB->getTotalRecords();
		$totalPage = ceil($total / $perPage);
		$totalShowPages = 10;
		$middlePageAddOnNumber = floor($totalShowPages / 2);

		if ($totalPage < $totalShowPages) {
			$startPage = 1;
			$endPage = $totalPage;
		} else {
			if (($currentPage + $middlePageAddOnNumber) > $totalPage) {
				$startPage = $totalPage - $totalShowPages + 1;
				$endPage = $totalPage;
			} else if ($currentPage > $middlePageAddOnNumber) {
				$startPage = $currentPage - $middlePageAddOnNumber;
				$endPage = $startPage + $totalShowPages - 1;
			} else {
				$startPage = 1;
				$endPage = $totalShowPages;
			}
		}

		$firstPage = 1;
		$lastPage = ($totalPage > 0 ? $totalPage : $firstPage);

		displayView('sms_template', 'list', [
			'current_page' => $currentPage,
			'end_page' => $endPage,
			'first_page' => $firstPage,
			'last_page' => $lastPage,
			'next_page' => ($currentPage < $totalPage ? $currentPage + 1 : $lastPage),
			'previous_page' => ($currentPage > 1 ? $currentPage - 1 : 1),
			'smsTemplates' => $results,
			'start_page' => $startPage,
			'total' => $total,
		]);
	}

	private function createView(): void {
		global $langs;

		displayView('sms_template', 'create', [
			'langs' => $langs,
		]);
	}

	private function editView(): void {
		global $langs;

		displayView('sms_template', 'edit', [
			'langs' => $langs,
			'sms_template' => $this->getSmsTemplate(GETPOST('sms_template_id', 'int', 1)),
		]);
	}

	public function render(): void {
		global $langs;

		$action = GETPOST('action', 'alphanohtml', 1);
		llxHeader('', $langs->trans($this->pageName));
		echo load_fiche_titre($langs->trans($this->pageName), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), $this->context, $langs->trans($this->pageName), -1);

		foreach ($this->notificationMessages ?? [] as $notificationMessage)
			dol_htmloutput_mesg($notificationMessage['message'], [], $notificationMessage['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>

		<?php
		if ($action == 'create_sms_template') $this->createView();
		else if ($action == 'edit_sms_template') $this->editView();
		else $this->listView();
		?>

		<script>
			const entityKeywords = <?= json_encode($this->getKeywords()); ?>;
			const currentUrl = '<?= $_SERVER['PHP_SELF'] ?>'

			$(document).ready(function () {
				const smsTplDiv = $('<div />').appendTo('body')
				smsTplDiv.attr('id', `add-sms-template-modal`)
				smsTplDiv.attr('class', 'modal')
				smsTplDiv.attr('style', 'display: none;max-width: 650px;')

				const keywordModal = $('<div />').appendTo('body')
				keywordModal.attr('id', `keyword-modal`)
				keywordModal.attr('class', 'modal')
				keywordModal.attr('style', 'display: none;max-width: 650px;')

				const $addSmsTplModal = $('#add-sms-template-modal')

				$('#add_sms_template').click(function () {
					getSMSTemplateSkeleton('add')
					$addSmsTplModal.modal()
				})

				const getSMSTemplateSkeleton = function (action, sms_template_obj = {}) {
					let elementToAdd = `
						<input type='hidden' name='sms_template_id' value='${sms_template_obj.id || ''}'>
						<h3>${ucFirst(action)} SMS Template</h3>

						<div class='sms-template-errors'></div>

						<div class='row'>
							<div class='col-3'>
								<p>Title *</p>
							</div>
							<div class='col-9'>
								<input type='text' id='sms-template-title' name='sms_template_title' value='${sms_template_obj.title || ''}' />
							</div>
						</div>

						<div class='row'>
							<div class='col-3'>
								<p>Message *</p>
							</div>
							<div class='col-9'>
								<textarea id='sms-template-message' name='sms_template_message' cols='40' rows='4'>${sms_template_obj.message || ''}</textarea>
							</div>
						</div>

						<p>Personalise your SMS with keywords below</p>
						<div id='sms_template_keyword_list' style='border: 1px solid;margin: 5px 0px;padding: 0px 10px;'>

						</div>
						<button type='button' onClick='saveSMSTemplate()'>
							Save
						</button>
					`

					$addSmsTplModal.html(elementToAdd)

					const target = 'sms-template-message'
					const $smsTemplateMessage = document.getElementById('sms-template-message')
					caretPosition = $smsTemplateMessage.selectionStart

					$addSmsTplModal.off()
					$(`#${target}`).blur(function () {
						caretPosition = $smsTemplateMessage.selectionStart
					})

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3)

						let tableCode = ''
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) tableCode += `<table class='widefat fixed striped'><tbody>`

							tableCode += '<tr>'
							row.forEach(function (col) {
								tableCode += `<td class='column'><button class='button-link' onclick='sevenBindTextToField('${target}', '[${col}]');'>[${col}]</button></td>`
							})
							tableCode += '</tr>'

							if (rowIndex === chunkedKeywords.length - 1) tableCode += '</tbody></table>'
						})

						return tableCode
					}

					let mainTable = ''
					for (let [key, value] of Object.entries(entityKeywords))
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>` + buildTable(value)

					mainTable += `<div style='margin-top:10px'><small>*Press on placeholder to add to template.</small></div>`

					$('#sms_template_keyword_list').html(mainTable)

					return elementToAdd
				}

				function saveSMSTemplate() {
					const id = $(`input[name='sms_template_id']`).val()
					const message = $('#sms-template-message').val()
					const title = $('#sms-template-title').val()
					const smsTemplateObj = {
						id,
						title,
						message
					}

					if (!validateSMSTemplate(smsTemplateObj)) return

					const formData = new FormData
					formData.append('sms_template_id', id)
					formData.append('sms_template_title', title)
					formData.append('sms_template_message', message)
					formData.append('action', 'save_sms_template')
					formData.append('token', '<?= newToken(); ?>')

					axios.post(currentUrl, formData).then(function () {
						window.location.reload()
					})
				}

				function editSMSTemplate(id) {
					axios.get(currentUrl, {
						params: {
							action: 'fetch_sms_template',
							sms_template_id: id,
						}
					}).then(function (response) {
						sms_template_obj = response.data
						getSMSTemplateSkeleton('edit', sms_template_obj)
						$addSmsTplModal.modal()
					})
				}

				function deleteSMSTemplate(id) {
					axios.get(currentUrl, {
						params: {
							action: 'fetch_sms_template',
							sms_template_id: id,
						}
					}).then(function (response) {
						sms_template_obj = response.data

						const confirmDelete = confirm(`Confirm Delete SMS Template: ${sms_template_obj.title} ?`)
						if (!confirmDelete) return

						const formData = new FormData()
						formData.append('sms_template_id', id)
						formData.append('action', 'delete_sms_template')
						formData.append('token', '<?= newToken(); ?>')

						axios.post(currentUrl, formData).then(function (response) {
							sms_template_obj = response.data
							window.location.reload()
						})
					})
				}

				function validateSMSTemplate(sms_template_obj) {
					let validationFlag = 0
					const smsTemplateErrors = []

					if (!sms_template_obj.title) {
						smsTemplateErrors.push('Title is required </br>')
						$('#sms-template-title').css({'border-bottom-color': 'red'})
						validationFlag++
					}
					if (!sms_template_obj.message) {
						smsTemplateErrors.push('Message is required </br>')
						$('#sms-template-message').css({'border-bottom-color': 'red'})
						validationFlag++
					}

					if (smsTemplateErrors.length > 0) {
						smsTemplateErrors.join('<br>')
						$('.sms-template-errors').html(smsTemplateErrors).addClass('error')
					}

					return validationFlag === 0
				}

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
