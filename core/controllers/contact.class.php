<?php

dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/fourn/class/fournisseur.facture.class.php'); // Supplier Invoice
dol_include_once('/fourn/class/fournisseur.commande.class.php'); // Supplier Order
dol_include_once('/contact/class/contact.class.php'); // Contact
dol_include_once('/seven/core/controllers/seven.controller.php'); // seven base controller

class ContactController extends SevenBaseController {
	private Contact $contact;
	private Seven_Logger $log;

	function __construct($id) {
		global $db;

		$this->log = new Seven_Logger;

		$this->contact = new Contact($db);
		$this->contact->fetch($id);

		parent::__construct($id);
	}

	public function get_contact_mobile_number() {
		$mobile = $this->contact->phone_mobile;
		$personal = $this->contact->phone_perso;

		$phone = !empty($mobile)
			? $mobile
			: (!empty($personal)
				? $personal
				: $this->contact->phone_pro);

		return validatedMobileNumber($phone, $this->contact->country_code);
	}

	public function render(): void {
		global $langs, $form;
		?>
		<input type='hidden' name='object_id' value='<?= $this->id ?>'/>
		<input type='hidden' name='send_context' value='contact'/>
		<tr>
			<td width='200px'>
				<?= $form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
			</td>
			<td>
				<?= $form->selectcontacts(0, $this->contact->id, 'sms_contact_ids', 0, '', '', 1, 'width200', false, 1, 0, [], '', '', true) ?>
			</td>
		</tr>
		<?php
	}
}
