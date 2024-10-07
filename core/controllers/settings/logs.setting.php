<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

class SMS_Log_Setting extends SevenBaseSettingController {
	var $db;
	var $errors;
	var $form;
	var $log;
	var $page_name;

	public $db_key = 'SEVEN_CONTACT_SETTING';

	function __construct($db) {
		$this->db = $db;
		$this->errors = [];
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->page_name = 'logs_page_title';
	}

	public function post_request_handler() {
		global $user;

		if (empty($_POST)) return;

		if (GETPOST('action') == 'clear_log_file') {
			if (!$user->rights->seven->permission->delete) accessforbidden();
			if ($this->clear_log_file()) $this->addNotificationMessage('Seven log file cleared');
			else $this->addNotificationMessage('Failed to clear Seven log file', 'error');
		}
	}

	public function clear_log_file() {
		return $this->log->delete_log_file('Seven');
	}

	public function render() {
		global $langs;
		$customerLogs = $this->log->get_log_file('Seven');
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		print dol_get_fiche_head(sevenAdminPrepareHead(), 'logs', $langs->trans($this->page_name), -1);

		foreach ($this->notificationMessages ?? [] as $notificationMessage)
			dol_htmloutput_mesg($notificationMessage['message'], [], $notificationMessage['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>
		<div class='bootstrap-wrapper'>
			<div id='setting-error-settings_updated' class='border border-primary'
				 style='padding:4px;width:1200px;height:600px;overflow:auto'>
				<pre><strong><?= htmlspecialchars($customerLogs, ENT_QUOTES); ?></strong></pre>
			</div>
		</div>

		<div style='margin-bottom: 20px'></div>

	<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>' style='max-width: 500px'>
		<input type='hidden' name='token' value='<?= newToken(); ?>'/>
		<input type='hidden' name='action' value='clear_log_file'/>
		<button>Clear log file</button>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
