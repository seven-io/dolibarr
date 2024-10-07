<?php
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/contact.setting.php");
dol_include_once("/seven/core/controllers/settings/proposal.setting.php");
dol_include_once("/seven/core/controllers/settings/invoice.setting.php");
dol_include_once("/seven/core/controllers/settings/supplier_order.setting.php");
dol_include_once("/seven/core/controllers/settings/sales_order.setting.php");
dol_include_once("/seven/core/controllers/settings/project.setting.php");
dol_include_once("/seven/core/controllers/settings/ticket.setting.php");
dol_include_once("/seven/core/controllers/settings/shipment.setting.php");
dol_include_once("/seven/core/controllers/settings/third_party.setting.php");
dol_include_once("/seven/core/controllers/settings/member.setting.php");

/**
 *  Class of triggers for Seven module
 */
class InterfaceSevenTriggers extends DolibarrTriggers {
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db) {
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Seven triggers.";
		$this->version = '1.0.0';
		$this->picto = 'seven@seven';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc() {
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string $action Event action code
	 * @param CommonObject $object Object
	 * @param User $user Object user
	 * @param Translate $langs Object langs
	 * @param Conf $conf Object conf
	 * @return int                    <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf) {
		if (empty($conf->seven) || empty($conf->seven->enabled)) {
			return 0; // If module is not enabled, we do nothing
		}
		global $db;
		$contact = new SMS_Contact_Setting($db);
		$invoice = new SMS_Invoice_Setting($db);
		$project = new SMS_Project_Setting($db);
		$supplier_order = new SMS_SupplierOrder_Setting($db);
		$sales_order = new SMS_SalesOrder_Setting($db);
		$ticket = new SMS_Ticket_Setting($db);
		$shipment = new SMS_Shipment_Setting($db);
		$third_party = new SMS_ThirdParty_Setting($db);
		$member = new SMS_Member_Setting($db);
		$proposal = new SMS_Proposal_Setting($db);

		$log = new Seven_Logger;
		// $log->add("Seven", "Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		// $methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		// $callback = array($this, $methodName);
		// if (is_callable($callback)) {
		// 	dol_syslog(
		// 		"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
		// 	);


		// 	return call_user_func($callback, $action, $object, $user, $langs, $conf);
		// };


		// Or you can execute some code here
		switch ($action) {
			// Users
			//case 'USER_CREATE':
			//case 'USER_MODIFY':
			//case 'USER_NEW_PASSWORD':
			//case 'USER_ENABLEDISABLE':
			//case 'USER_DELETE':

			// Actions
			//case 'ACTION_MODIFY':
			//case 'ACTION_CREATE':
			//case 'ACTION_DELETE':

			// Groups
			//case 'USERGROUP_CREATE':
			//case 'USERGROUP_MODIFY':
			//case 'USERGROUP_DELETE':

			// Companies
			case 'COMPANY_CREATE':
				$third_party->trigger_send_sms($object, 'created');
				break;
			//case 'COMPANY_MODIFY':
			//case 'COMPANY_DELETE':

			case 'COMPANY_SENTBYMAIL':
				$third_party->trigger_send_sms($object, 'email_sent');
				break;

			// Contacts
			case 'CONTACT_CREATE':
				$contact->contactCreate($object);
				break;
			//case 'CONTACT_MODIFY':
			//case 'CONTACT_DELETE':
			case 'CONTACT_ENABLEDISABLE':
				$contact->contactEnableDisable($object);
				break;

			// Products
			//case 'PRODUCT_CREATE':
			//case 'PRODUCT_MODIFY':
			//case 'PRODUCT_DELETE':
			//case 'PRODUCT_PRICE_MODIFY':
			//case 'PRODUCT_SET_MULTILANGS':
			//case 'PRODUCT_DEL_MULTILANGS':

			//Stock mouvement
			//case 'STOCK_MOVEMENT':

			//MYECMDIR
			//case 'MYECMDIR_CREATE':
			//case 'MYECMDIR_MODIFY':
			//case 'MYECMDIR_DELETE':

			// Customer orders
			case 'ORDER_CREATE':
				$sales_order->trigger_sms($object, "created");
				break;
			case 'ORDER_MODIFY':
				$sales_order->trigger_sms($object, "modified");
				break;
			case 'ORDER_VALIDATE':
				$sales_order->trigger_sms($object, "validated");
				break;
			case 'ORDER_DELETE':
				$sales_order->trigger_sms($object, "deleted");
				break;
			case 'ORDER_CANCEL':
				$sales_order->trigger_sms($object, "canceled");
				break;
			case 'ORDER_CLASSIFY_BILLED':
				$sales_order->trigger_sms($object, "classify_billed");
				break;
			// case 'ORDER_SENTBYMAIL':
			//case 'ORDER_SETDRAFT':
			//case 'LINEORDER_INSERT':
			//case 'LINEORDER_UPDATE':
			//case 'LINEORDER_DELETE':

			// Supplier orders
			case 'ORDER_SUPPLIER_CREATE':
				$supplier_order->trigger_sms($object, "created");
				break;
			case 'ORDER_SUPPLIER_VALIDATE':
				$supplier_order->trigger_sms($object, "validated");
				break;
			case 'ORDER_SUPPLIER_APPROVE':
				$supplier_order->trigger_sms($object, "approved");
				break;
			case 'ORDER_SUPPLIER_REFUSE':
				/*
					For testing, need to create a separate USER with no permission to approve PO
				*/
				$log = new Seven_Logger;
				$log->add("Seven", "ORDER_SUPPLIER_REFUSE");
				$supplier_order->trigger_sms($object, "refused");
				break;

			// dolibarr v17
			// https://github.com/Dolibarr/dolibarr/blob/17.0/ChangeLog
			// ORDER_SUPPLIER_DISPATCH @deprecated
			// use ORDER_SUPPLIER_RECEIVE
			case 'ORDER_SUPPLIER_DISPATCH':
			case 'ORDER_SUPPLIER_RECEIVE':
				$log = new Seven_Logger;
				$log->add("Seven", "ORDER_SUPPLIER_RECEIVE");
				$supplier_order->trigger_sms($object, "dispatched");
				break;
			// case 'ORDER_SUPPLIER_MODIFY':
			// case 'ORDER_SUPPLIER_DELETE':
			// case 'ORDER_SUPPLIER_CANCEL':
			// case 'ORDER_SUPPLIER_SENTBYMAIL':
			//case 'LINEORDER_SUPPLIER_DISPATCH':
			//case 'LINEORDER_SUPPLIER_CREATE':
			//case 'LINEORDER_SUPPLIER_UPDATE':
			//case 'LINEORDER_SUPPLIER_DELETE':

			// Proposals
			case 'PROPAL_CREATE':
				$proposal->trigger_sms($object, "created");
				break;
			case 'PROPAL_MODIFY':
				$proposal->trigger_sms($object, "updated");
				break;
			case 'PROPAL_VALIDATE':
				$proposal->trigger_sms($object, "validated");
				break;
			case 'PROPAL_CLOSE_SIGNED':
				$proposal->trigger_sms($object, "close_signed");
				break;
			case 'PROPAL_CLOSE_REFUSED':
				$proposal->trigger_sms($object, "close_refused");
				break;

			//case 'PROPAL_SENTBYMAIL':
			//case 'PROPAL_DELETE':
			//case 'LINEPROPAL_INSERT':
			//case 'LINEPROPAL_UPDATE':
			//case 'LINEPROPAL_DELETE':

			// SupplierProposal
			//case 'SUPPLIER_PROPOSAL_CREATE':
			//case 'SUPPLIER_PROPOSAL_MODIFY':
			//case 'SUPPLIER_PROPOSAL_VALIDATE':
			//case 'SUPPLIER_PROPOSAL_SENTBYMAIL':
			//case 'SUPPLIER_PROPOSAL_CLOSE_SIGNED':
			//case 'SUPPLIER_PROPOSAL_CLOSE_REFUSED':
			//case 'SUPPLIER_PROPOSAL_DELETE':
			//case 'LINESUPPLIER_PROPOSAL_INSERT':
			//case 'LINESUPPLIER_PROPOSAL_UPDATE':
			//case 'LINESUPPLIER_PROPOSAL_DELETE':

			// Contracts
			//case 'CONTRACT_CREATE':
			//case 'CONTRACT_MODIFY':
			//case 'CONTRACT_ACTIVATE':
			//case 'CONTRACT_CANCEL':
			//case 'CONTRACT_CLOSE':
			//case 'CONTRACT_DELETE':
			//case 'LINECONTRACT_INSERT':
			//case 'LINECONTRACT_UPDATE':
			//case 'LINECONTRACT_DELETE':

			// Bills
			case 'BILL_CREATE':
				$invoice->bill_trigger_sms($object, "created");
				break;
			case 'BILL_MODIFY':
				$invoice->bill_trigger_sms($object, "updated");
				break;
			case 'BILL_VALIDATE':
				$invoice->bill_trigger_sms($object, "validated");
				break;
			// case 'BILL_UNVALIDATE':
			// case 'BILL_SENTBYMAIL':
			// case 'BILL_CANCEL':
			// case 'BILL_DELETE':
			case 'BILL_PAYED':
				$invoice->bill_trigger_sms($object, "paid");
				break;
			//case 'LINEBILL_INSERT':
			//case 'LINEBILL_UPDATE':
			//case 'LINEBILL_DELETE':

			//Supplier Bill
			//case 'BILL_SUPPLIER_CREATE':
			//case 'BILL_SUPPLIER_UPDATE':
			//case 'BILL_SUPPLIER_DELETE':
			//case 'BILL_SUPPLIER_PAYED':
			//case 'BILL_SUPPLIER_UNPAYED':
			//case 'BILL_SUPPLIER_VALIDATE':
			//case 'BILL_SUPPLIER_UNVALIDATE':
			//case 'LINEBILL_SUPPLIER_CREATE':
			//case 'LINEBILL_SUPPLIER_UPDATE':
			//case 'LINEBILL_SUPPLIER_DELETE':

			// Payments
			//case 'PAYMENT_CUSTOMER_CREATE':
			//case 'PAYMENT_SUPPLIER_CREATE':
			//case 'PAYMENT_ADD_TO_BANK':
			//case 'PAYMENT_DELETE':

			// Online
			//case 'PAYMENT_PAYBOX_OK':
			//case 'PAYMENT_PAYPAL_OK':
			//case 'PAYMENT_STRIPE_OK':

			// Donation
			//case 'DON_CREATE':
			//case 'DON_UPDATE':
			//case 'DON_DELETE':

			// Interventions
			//case 'FICHINTER_CREATE':
			//case 'FICHINTER_MODIFY':
			//case 'FICHINTER_VALIDATE':
			//case 'FICHINTER_DELETE':
			//case 'LINEFICHINTER_CREATE':
			//case 'LINEFICHINTER_UPDATE':
			//case 'LINEFICHINTER_DELETE':

			// Members
			case 'MEMBER_CREATE':
				$member->trigger_send_sms($object, "created");
				break;
			case 'MEMBER_VALIDATE':
				$member->trigger_send_sms($object, "validated");
				break;
			case 'MEMBER_SUBSCRIPTION_CREATE':
				$member->trigger_send_sms($object, "subscription_created");
				break;
			case 'MEMBER_RESILIATE':
				$member->trigger_send_sms($object, "terminated");
				break;

			// Categories
			//case 'CATEGORY_CREATE':
			//case 'CATEGORY_MODIFY':
			//case 'CATEGORY_DELETE':
			//case 'CATEGORY_SET_MULTILANGS':

			// Projects
			//case 'PROJECT_CREATE':
			case 'PROJECT_MODIFY':
				$project->trigger_send_sms($object);
				break;
			//case 'PROJECT_DELETE':

			// Project tasks
			//case 'TASK_CREATE':
			//case 'TASK_MODIFY':
			//case 'TASK_DELETE':

			// Task time spent
			//case 'TASK_TIMESPENT_CREATE':
			//case 'TASK_TIMESPENT_MODIFY':
			//case 'TASK_TIMESPENT_DELETE':
			//case 'PROJECT_ADD_CONTACT':
			//case 'PROJECT_DELETE_CONTACT':
			//case 'PROJECT_DELETE_RESOURCE':

			// Shipping
			case 'SHIPPING_CREATE':
				$shipment->trigger_send_sms($object, "created");
				break;
			// case 'SHIPPING_MODIFY':
			// 	break;
			case 'SHIPPING_VALIDATE':
				$shipment->trigger_send_sms($object, "validated");
				break;
			// case 'SHIPPING_SENTBYMAIL':
			// 	$log->add("Seven", "SHIPPING_SENTBYMAIL");
			// 	break;
			// case 'SHIPPING_BILLED':
			// 	$log->add("Seven", "SHIPPING_BILLED");
			// 	break;
			case 'SHIPPING_CLOSED':
				$shipment->trigger_send_sms($object, "closed");
				break;
			// case 'SHIPPING_REOPEN':
			// 	$log->add("Seven", "SHIPPING_REOPEN");
			// 	break;
			// case 'SHIPPING_DELETE':
			// 	$log->add("Seven", "SHIPPING_DELETE");
			// 	break;

			case 'ORDER_CLOSE':
				// order closed = shipment delivered
				// $log->add("Seven", print_r($object, 1));
				$shipment->trigger_send_sms($object, "delivered");
				break;


			// Ticket

			case 'TICKET_MODIFY':
				$ticket->trigger_sms($object);
				break;
			case 'TICKET_ASSIGNED':
				$ticket->trigger_sms($object, 'assigned');
				break;
			case 'TICKET_CREATE':
				$ticket->trigger_sms($object, 'created');
				break;
			case 'TICKET_CLOSE':
				$ticket->trigger_sms($object, 'closed');
				break;
			case 'TICKET_DELETE':
				$ticket->trigger_sms($object, 'deleted');
				break;

			default:
				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				break;
		}

		return 0;
	}
}
