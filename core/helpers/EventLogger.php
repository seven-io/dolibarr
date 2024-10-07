<?php

include_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
include_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
include_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

class EventLogger {
	public static function create($id, $entity, $title, $description = ''): void {
		global $db, $user;

		$actionComm = new ActionComm($db);

		if ($entity == 'thirdparty') {
			$actionComm->socid = $id ?? 0;
		} else if ($entity == 'contact') {
			$contact = new Contact($db);
			$contact->fetch($id);

			$actionComm->socid = $contact->socid;
			$actionComm->contact_id = $contact->id;
			$actionComm->socpeopleassigned = [$contact->id => $contact->id];
			$actionComm->fk_element = $contact->socid;
		}

		$actionComm->type_code = 'AC_OTH_AUTO'; // Event insert into agenda automatically
		$actionComm->code = 'AC_SMS_SENT';
		$actionComm->label = $title;
		$actionComm->note_private = $description;
		$actionComm->datep = dol_now();
		$actionComm->datef = $actionComm->datep;
		$actionComm->percentage = -1; // Not applicable
		$actionComm->authorid = $user->id; // User saving action
		$actionComm->userownerid = $user->id; // Owner of action

		$actionComm->create($user);
	}
}

