<?php

dol_include_once("/seven/core/interfaces/view_interface.class.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/fourn/class/fournisseur.facture.class.php"); // Supplier Invoice
dol_include_once("/fourn/class/fournisseur.commande.class.php"); // Supplier Order
dol_include_once("/contact/class/contact.class.php"); // Contact
dol_include_once("/seven/core/controllers/seven.controller.php"); // seven base controller

class ContactController extends SevenBaseController implements ViewInterface {

	private $contact;
	private $log;

	function __construct($id) {
		global $db;
		$this->log = new Seven_Logger;
		$this->contact = new Contact($db);
		$this->contact->fetch($id);
		parent::__construct($id);
	}

	public function get_contact_mobile_number() {
		$country_code = $this->contact->country_code;
		$phone_mobile = $this->contact->phone_mobile;
		$phone_perso = $this->contact->phone_perso;
		$phone_pro = $this->contact->phone_pro;

		$phone = (!empty($phone_mobile) ? $phone_mobile : (!empty($phone_perso) ? $phone_perso : $phone_pro));

		return validated_mobile_number($phone, $country_code);
	}

	/**
	 *    Show the Send SMS To section in HTML
	 *
	 * @param void
	 * @return    void
	 */
	public function render() {
		global $conf, $langs, $user, $form;
		?>

		<input type="hidden" name="object_id" value="<?= $this->id ?>">
		<input type="hidden" name="send_context" value="contact">
		<tr>
			<td width="200px">
				<?= $form->textwithpicto($langs->trans("SmsTo"), "The contact mobile you want to send SMS to"); ?>
			</td>
			<td>
				<?= $form->selectcontacts(0, $this->contact->id, 'sms_contact_ids', 0, '', '', 1, 'width200', false, 1, 0, [], '', '', true) ?>
			</td>
		</tr>
		<?php
	}
}
