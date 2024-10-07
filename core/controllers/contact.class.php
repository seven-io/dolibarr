<?php
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/fourn/class/fournisseur.facture.class.php');
dol_include_once('/fourn/class/fournisseur.commande.class.php');
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/seven/core/controllers/seven.controller.php');

class ContactController extends SevenBaseController {
	private Contact $contact;

	function __construct($id) {
		global $db;

		$this->contact = new Contact($db);
		$this->contact->fetch($id);

		parent::__construct($id);
	}

	public function getContactMobileNumber(): string {
		$mobile = $this->contact->phone_mobile;
		$personal = $this->contact->phone_perso;

		$phone = !empty($mobile)
			? $mobile
			: (!empty($personal)
				? $personal
				: $this->contact->phone_pro);

		return $phone;
	}

	public function render(): void {
		global $langs, $form;
		?>
		<input type='hidden' name='object_id' value='<?= $this->id ?>'/>
		<input type='hidden' name='send_context' value='contact'/>
		<tr>
			<td>
				<?= $form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
			</td>
			<td>
				<?= $form->selectcontacts(0, $this->contact->id, 'sms_contact_ids', 0, '', '', 1, 'width200', false, 1, 0, [], '', '', true) ?>
			</td>
		</tr>
		<?php
	}
}
