<?php

dol_include_once('/contact/class/contact.class.php'); // Contact
dol_include_once('/societe/class/societe.class.php'); // Third Party
dol_include_once('/seven/core/controllers/seven.controller.php');

class ThirdPartyController extends SevenBaseController {
	var Societe $thirdparty;

	function __construct($id) {
		global $db;

		$this->thirdparty = new Societe($db);
		$this->thirdparty->fetch($id);

		parent::__construct($id);
	}

	public function getThirdPartyMobileNumber() {
		return validatedMobileNumber($this->thirdparty->phone, $this->thirdparty->country_code);
	}

	public function render(): void {
		global $langs, $form;
		?>
		<input type='hidden' name='object_id' value='<?= $this->id ?>'/>
		<input type='hidden' name='send_context' value='thirdparty'/>
		<tr>
			<td width='200px'>
				<?= $form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
			</td>
			<td>
				<input type='hidden' name='thirdparty_id' value='<?= $this->thirdparty->id ?>'/>
				<?= $form->select_thirdparty_list($this->thirdparty->id, 'thirdparty_id', '', 0, 0, 0, [], '', 0, 0, '', 'disabled') ?>
			</td>
		</tr>
		<tr>
			<td width='200px'></td>
			<td>
				<?= $form->selectcontacts($this->thirdparty->id, '', 'sms_contact_ids', 1, '', '', 1, 'width200', false, 0, 0, [], '', '', true) ?>
			</td>
		</tr>
		<?php
	}
}
