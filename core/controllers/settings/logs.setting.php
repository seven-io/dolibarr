<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

class SMS_Log_Setting extends SevenBaseSettingController {
	var array $errors  = [];
	var string $page_name = 'logs_page_title';
	public string $db_key = 'SEVEN_CONTACT_SETTING';

	public function postRequestHandler(): void {
		global $user;

		if (empty($_POST)) return;

		if (GETPOST('action') != 'clear_log_file') return;

		if (!$user->rights->seven->permission->delete) accessforbidden();

		if ($this->clearLogFile()) $this->addNotificationMessage('Seven log file cleared');
		else $this->addNotificationMessage('Failed to clear Seven log file', 'error');
	}

	private function clearLogFile(): bool {
		return $this->log->deleteLogFile('Seven');
	}

	public function render(): void {
		global $langs;
		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), 'logs', $langs->trans($this->page_name), -1);
		foreach ($this->notificationMessages ?? [] as $msg) dol_htmloutput_mesg($msg['message'], [], $msg['style']);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach ?>
		<?php endif ?>
		<div id='setting-error-settings_updated' class='border border-primary'
			 style='padding:4px;width:1200px;height:600px;overflow:auto'>
			<pre><strong><?= htmlspecialchars($this->log->getLogFile('Seven'), ENT_QUOTES); ?></strong></pre>
		</div>

		<div style='margin-bottom: 20px'></div>

	<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>' style='max-width: 500px'>
		<input type='hidden' name='token' value='<?= newToken(); ?>'/>
		<input type='hidden' name='action' value='clear_log_file'/>
		<button>Clear log file</button>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
