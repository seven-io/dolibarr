<?php
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven_sms_template.db.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");
dol_include_once("/seven/core/controllers/settings/contact.setting.php");

require_once DOL_DOCUMENT_ROOT . "/core/class/html.formcompany.class.php";

class SMS_Template_Setting extends SevenBaseSettingController {
	private $form;
	private $form_company;
	private $errors;
	private $log;
	private $page_name;
	private $db;
	private $context;
	private $sms_template_db;

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->form_company = new FormCompany($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->sms_template_db = new SevenSMSTemplateDatabase($db);
		$this->context = 'sms_template';
		$this->page_name = 'sms_template_page_title';
	}

	public function add_notification_message($message, $style = 'ok') {
		$this->notification_messages[] = [
			'message' => $message,
			'style' => $style,
		];
	}

	function processPostData() {
		global $user;

		if (!isset($_POST) || empty($_POST)) {
			return;
		}

		if (!$user->rights->seven->permission->write) {
			accessforbidden();
		}

		$action = GETPOST("action", "alphanohtml", "3");

		if ($action == 'save_sms_template') {
			$sms_template_id = GETPOST('sms_template_id', "int", 2);
			$sms_template_title = GETPOST('sms_template_title', "alphanohtml", 2);
			$sms_template_message = GETPOST('sms_template_message', "alphanohtml", 2);
			$sms_template_obj = [
				"sms_template_id" => $sms_template_id,
				"sms_template_title" => $sms_template_title,
				"sms_template_message" => $sms_template_message
			];

			$this->save_sms_template($sms_template_obj);
		} else if ($action == 'delete_sms_template') {
			$sms_template_id = GETPOST('sms_template_id', "int", 2);

			$this->delete_sms_template($sms_template_id);
		}

	}

	function processGetData() {
		global $user;

		if (!isset($_GET) || empty($_GET)) {
			return;
		}

		if (!$user->rights->seven->permission->write) {
			accessforbidden();
		}

		$action = GETPOST("action", 'alphanohtml', 1);

		if ($action == 'fetch_sms_template') {
			$this->fetch_sms_template();
		}

	}

	function delete_sms_template($id) {
		$this->sms_template_db->deleteById($id);
	}

	function fetch_sms_template() {
		// FOR APIs
		$sms_template_id = GETPOST('sms_template_id', "int", 1);
		$data = $this->get_sms_template($sms_template_id);
		echo json_encode($data);
		exit();
	}

	function get_sms_template($sms_template_id) {
		// used internally
		$result = $this->sms_template_db->get($sms_template_id);
		$data = $result->fetch_assoc();
		return $data;
	}

	function save_sms_template($sms_template_obj) {
		$sms_template_id = $sms_template_obj['sms_template_id'];
		$sms_template_title = $sms_template_obj['sms_template_title'];
		$sms_template_message = $sms_template_obj['sms_template_message'];

		if (empty($sms_template_id)) {
			$this->sms_template_db->insert($sms_template_title, $sms_template_message);
		} else {
			$this->sms_template_db->updateById($sms_template_id, $sms_template_title, $sms_template_message);
		}
	}

	function get_all_sms_templates() {
		return $this->sms_template_db->getAll();
	}

	function get_all_sms_templates_as_array() {
		$sms_template_kv = [];
		$sms_templates = $this->get_all_sms_templates();
		foreach ($sms_templates as $sms_template) {
			$sms_template_kv[$sms_template['id']] = $sms_template['title'];
		}

		return $sms_template_kv;
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

	private function list_view() {
		$current_page = 1;

		if (isset($_GET['pageno'])) {
			$current_page = filter_var($_GET['pageno'], FILTER_SANITIZE_NUMBER_INT);
		}

		$per_page = 15;
		$offset = ($current_page - 1) * $per_page;

		$query_result = $this->sms_template_db->getAll($per_page, $offset);
		$results = [];
		foreach ($query_result as $qr) {
			$results[] = $qr;
		}
		$total = $this->sms_template_db->getTotalRecords();
		$total_page = ceil($total / $per_page);
		$total_show_pages = 10;
		$middle_page_add_on_number = floor($total_show_pages / 2);

		if ($total_page < $total_show_pages) {
			$start_page = 1;
			$end_page = $total_page;
		} else {
			if (($current_page + $middle_page_add_on_number) > $total_page) {
				$start_page = $total_page - $total_show_pages + 1;
				$end_page = $total_page;
			} else if ($current_page > $middle_page_add_on_number) {
				$start_page = $current_page - $middle_page_add_on_number;
				$end_page = $start_page + $total_show_pages - 1;
			} else {
				$start_page = 1;
				$end_page = $total_show_pages;
			}
		}

		$first_page = 1;
		$last_page = ($total_page > 0 ? $total_page : $first_page);
		$previous_page = ($current_page > 1 ? $current_page - 1 : 1);
		$next_page = ($current_page < $total_page ? $current_page + 1 : $last_page);

		$sms_templates_list = $results;

		$template_variables = [
			"first_page" => $first_page,
			"last_page" => $last_page,
			"previous_page" => $previous_page,
			"next_page" => $next_page,
			"start_page" => $start_page,
			"end_page" => $end_page,
			"current_page" => $current_page,
			"total" => $total,
			"sms_templates_list" => $sms_templates_list,
		];
		displayView("sms_template", "list", $template_variables);
	}

	private function create_view() {
		global $langs;

		$template_variables = [
			'langs' => $langs,
		];
		displayView("sms_template", "create", $template_variables);
	}

	private function edit_view() {
		global $langs;

		$sms_template_id = GETPOST("sms_template_id", "int", 1);

		$sms_template = $this->get_sms_template($sms_template_id);

		$template_variables = [
			"langs" => $langs,
			"sms_template" => $sms_template,
		];

		displayView("sms_template", "edit", $template_variables);
	}

	public function render() {
		global $conf, $user, $langs, $db;
		$action = GETPOST('action', "alphanohtml", 1);
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
		<?php if ($this->errors) { ?>
			<?php foreach ($this->errors as $error) { ?>
				<p style="color: red;"><?= $error ?></p>
			<?php } ?>
		<?php } ?>

		<?php

		if ($action == 'create_sms_template') {
			$this->create_view();
		} else if ($action == 'edit_sms_template') {
			$this->edit_view();
		} else {
			$this->list_view();
		}

		?>

		<script>
			const entity_keywords = <?= json_encode($this->get_keywords()); ?>;
			const currentUrl = "<?= $_SERVER['PHP_SELF'] ?>";

			$(document).ready(function () {

				var sms_template_div = $('<div />').appendTo('body');
				sms_template_div.attr('id', `add-sms-template-modal`);
				sms_template_div.attr('class', "modal");
				sms_template_div.attr('style', "display: none;max-width: 650px;");

				var keyword_modal = $('<div />').appendTo('body');
				keyword_modal.attr('id', `keyword-modal`);
				keyword_modal.attr('class', "modal");
				keyword_modal.attr('style', "display: none;max-width: 650px;");

				$("#add_sms_template").click(function (e) {
					getSMSTemplateSkeleton("add");
					$('#add-sms-template-modal').modal();
				})

				getSMSTemplateSkeleton = function (action, sms_template_obj = {}) {

					let elementToAdd = `
						<input type="hidden" name="sms_template_id" value="${sms_template_obj.id || ''}">
						<h3>${ucFirst(action)} SMS Template</h3>

						<div class="sms-template-errors"></div>

						<div class="row">
							<div class="col-3">
								<p>Title *</p>
							</div>
							<div class="col-9">
								<input type="text" id="sms-template-title" name="sms_template_title" value="${sms_template_obj.title || ''}" />
							</div>
						</div>

						<div class="row">
							<div class="col-3">
								<p>Message *</p>
							</div>
							<div class="col-9">
								<textarea id="sms-template-message" name="sms_template_message" cols="40" rows="4">${sms_template_obj.message || ""}</textarea>
							</div>
						</div>

						<p>Personalise your SMS with keywords below</p>
						<div id="sms_template_keyword_list" style="border: 1px solid;margin: 5px 0px;padding: 0px 10px;">

						</div>
						<button type="button" onClick="saveSMSTemplate()">
							Save
						</button>
					`

					$("#add-sms-template-modal").html(elementToAdd);

					const target = "sms-template-message"
					caretPosition = document.getElementById(target).selectionStart;

					$('#add-sms-template-modal').off();
					$(`#sms-template-message`).blur(function () {
						caretPosition = document.getElementById(target).selectionStart;
					});

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3);

						let tableCode = '';
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) {
								tableCode += '<table class="widefat fixed striped"><tbody>';
							}

							tableCode += '<tr>';
							row.forEach(function (col) {
								tableCode += `<td class="column"><button class="button-link" onclick="seven_bind_text_to_field('${target}', '[${col}]');">[${col}]</button></td>`;
							});
							tableCode += '</tr>';

							if (rowIndex === chunkedKeywords.length - 1) {
								tableCode += '</tbody></table>';
							}
						});

						return tableCode;
					};

					let mainTable = '';
					for (let [key, value] of Object.entries(entity_keywords)) {
						mainTable += `<h3>${ucFirst(key.replaceAll('_', ' '))}</h3>`;
						mainTable += buildTable(value);
					}

					mainTable += '<div style="margin-top: 10px"><small>*Press on keyword to add to sms template</small></div>';

					$('#sms_template_keyword_list').html(mainTable);

					return elementToAdd;
				}

				saveSMSTemplate = function () {
					let id = $('input[name="sms_template_id"]').val();
					let message = $(`#sms-template-message`).val();
					let title = $(`#sms-template-title`).val();

					let sms_template_obj = {
						id,
						title,
						message
					}

					if (!validateSMSTemplate(sms_template_obj)) {
						return;
					}

					const form = new FormData();
					form.append('sms_template_id', id);
					form.append('sms_template_title', title);
					form.append('sms_template_message', message);
					form.append('action', "save_sms_template");
					form.append('token', "<?= newToken(); ?>");

					axios.post(currentUrl, form).then(function (response) {
						window.location.reload();
					})
				}

				editSMSTemplate = function (id) {
					axios.get(currentUrl, {
						params: {
							sms_template_id: id,
							action: "fetch_sms_template"
						}
					}).then(function (response) {
						sms_template_obj = response.data;
						getSMSTemplateSkeleton("edit", sms_template_obj);
						$('#add-sms-template-modal').modal();
					})
				}

				deleteSMSTemplate = function (id) {
					axios.get(currentUrl, {
						params: {
							sms_template_id: id,
							action: "fetch_sms_template"
						}
					}).then(function (response) {
						sms_template_obj = response.data;

						const confirmDelete = confirm(`Confirm Delete SMS Template: ${sms_template_obj.title} ?`);
						if (!confirmDelete) {
							return;
						}

						const form = new FormData();
						form.append('sms_template_id', id);
						form.append('action', "delete_sms_template");
						form.append('token', "<?= newToken(); ?>");

						axios.post(currentUrl, form).then(function (response) {
							sms_template_obj = response.data;
							window.location.reload();
						})

					})
				}

				validateSMSTemplate = function (sms_template_obj) {
					let validation_flag = 0;
					let sms_template_errors = [];
					let title = sms_template_obj.title
					let message = sms_template_obj.message

					if (!title) {
						sms_template_errors.push("Title is required </br>");
						$(`#sms-template-title`).css({"border-bottom-color": "red"});
						validation_flag++;
					}
					if (!message) {
						sms_template_errors.push("Message is required </br>");
						$(`#sms-template-message`).css({"border-bottom-color": "red"});
						validation_flag++;
					}

					if (sms_template_errors.length > 0) {
						sms_template_errors.join("<br>");
						$(".sms-template-errors").html(sms_template_errors).addClass("error");
					}

					return validation_flag === 0;
				}

				$('.seven_open_keyword').click(function (e) {
					const target = $(e.target).attr('data-attr-target');
					caretPosition = document.getElementById(target).selectionStart;

					const buildTable = function (keywords) {
						const chunkedKeywords = keywords.toChunk(3);

						let tableCode = '';
						chunkedKeywords.forEach(function (row, rowIndex) {
							if (rowIndex === 0) {
								tableCode += '<table class="widefat fixed striped"><tbody>';
							}

							tableCode += '<tr>';
							row.forEach(function (col) {
								tableCode += `<td class="column"><button class="button-link" onclick="seven_bind_text_to_field('${target}', '[${col}]')">[${col}]</button></td>`;
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

					mainTable += '<div style="margin-top: 10px"><small>*Press on keyword to add to sms template</small></div>';

					$('#keyword-modal').html(mainTable);
					$('#keyword-modal').modal();
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
