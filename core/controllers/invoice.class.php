<?php
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/seven/core/controllers/seven.controller.php');

class InvoiceController extends SevenBaseController {
	var Facture $invoice;
	var Societe $thirdparty;

	function __construct($id) {
		global $db;

		$this->invoice = new Facture($db);
		$this->invoice->fetch($id);

		$this->thirdparty = new Societe($db);
		$this->thirdparty->fetch($this->invoice->socid);

		parent::__construct($id);
	}

	public function render(): void {
		global $langs, $form;

		?>
		<input type='hidden' name='object_id' value='<?= $this->id ?>'/>
		<input type='hidden' name='send_context' value='invoice'/>
		<tr>
			<td>
				<?= $form->textwithpicto($langs->trans('SmsTo'), 'The contact mobile you want to send SMS to'); ?>
			</td>
			<td>
				<input type='hidden' name='thirdparty_id' value='<?= $this->thirdparty->id ?>'/>
				<?= $form->select_thirdparty_list($this->thirdparty->id, 'thirdparty_id', '', 0, 0, 0, [], '', 0, 0, 'minwidth100', 'disabled') ?>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<?= $form->selectcontacts($this->thirdparty->id, '', 'sms_contact_ids', 1, '', '', 1, 'width200', false, 0, 0, [], '', '', true) ?>
			</td>
		</tr>
		<?php
	}
}
