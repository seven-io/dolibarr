<?php

include_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
include_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
include_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
include_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

class EventLogger {
	public static function create($id, $entity, $title, $description = '') {
		global $db, $user;
		$actioncomm = new ActionComm($db);

		if ($entity == 'thirdparty') {
			$actioncomm->socid = $id ?? 0;

		} else if ($entity == 'contact') {
			$contact = new Contact($db);
			$contact->fetch($id);

			$actioncomm->socid = $contact->socid;
			$actioncomm->contact_id = $contact->id;
			$actioncomm->socpeopleassigned = [$contact->id => $contact->id];
			$actioncomm->fk_element = $contact->socid;

		}

		$actioncomm->type_code = 'AC_OTH_AUTO'; // Event insert into agenda automatically
		$actioncomm->code = 'AC_SMS_SENT';
		$actioncomm->label = $title;
		$actioncomm->note_private = $description;
		$actioncomm->datep = dol_now();
		$actioncomm->datef = $actioncomm->datep;
		$actioncomm->percentage = -1; // Not applicable
		$actioncomm->authorid = $user->id; // User saving action
		$actioncomm->userownerid = $user->id; // Owner of action

		$actioncomm->create($user);
	}
}

