<?php

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");

class SMS_Log_Setting extends SevenBaseSettingController {
	var $log;
	var $page_name;
	var $db;
	var $form;
	var $errors;

	public $db_key = "SEVEN_CONTACT_SETTING";

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->page_name = 'logs_page_title';
	}

	public function post_request_handler() {
		global $user;

		if (!empty($_POST)) {
			$action = GETPOST('action');
			if ($action == 'clear_log_file') {
				if (!$user->rights->seven->permission->delete) {
					accessforbidden();
				}
				$cleared = $this->clear_log_file();
				if ($cleared) {
					$this->add_notification_message("Seven log file cleared");
				} else {
					$this->add_notification_message("Failed to clear Seven log file", 'error');
				}
			}
		}
	}

	public function clear_log_file() {
		$handler = "Seven";
		return $this->log->delete_log_file($handler);
	}

	public function render() {
		global $langs;
		$customer_logs = $this->log->get_log_file("Seven");
		?>
		<!-- Begin form SMS -->

		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, 'logs', $langs->trans($this->page_name), -1);

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
		<div class="bootstrap-wrapper">
			<div id="setting-error-settings_updated" class="border border-primary"
				 style="padding:4px;width:1200px;height:600px;overflow:auto">
				<pre><strong><?= htmlspecialchars($customer_logs, ENT_QUOTES); ?></strong></pre>
			</div>
		</div>

		<div style="margin-bottom: 20px"></div>

	<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>" style="max-width: 500px">
		<input type="hidden" name="token" value="<?= newToken(); ?>">
		<input type="hidden" name="action" value="clear_log_file">
		<button>Clear log file</button>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
