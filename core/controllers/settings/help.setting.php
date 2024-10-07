<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/seven_logger.class.php');

class SMS_Help_Setting {
	var string $context = 'help';
	var array $errors  = [];
	var string $page_name = 'help_page_title';

	public function render(): void {
		global $langs;

		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), $this->context, $langs->trans($this->page_name), -1);
		?>
		<?php if ($this->errors): ?>
			<?php foreach ($this->errors as $error): ?>
				<p style='color: red;'><?= $error ?></p>
			<?php endforeach; ?>
		<?php endif ?>
		<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
			<h2>What is Seven ?</h2>
			<p>Seven is a Germany based communications provider founded in 2003.</p>

			<h2>How to create an API key?</h2>
			<p>In order to use Seven in Dolibarr, you need to create an account. You can do
				this
				<a href='https://app.seven.io/signup'>
					<strong>here</strong>
				</a>
				. The account creation is free and trial credit will be provided subject to approval.
			</p>

			<h2>Questions and Support</h2>
			<p>
				If you have any questions or feedback, you are more than welcome to reach out to our <a
					href='https://www.seven.io/contact'>
					<strong>support team</strong>
				</a>, and we will get back to you as soon as possible.
			</p>
		</form>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
