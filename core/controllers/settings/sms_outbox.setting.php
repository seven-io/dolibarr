<?php

dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/seven_message_status.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven.db.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");

class SMS_SMSOutbox_Setting extends SevenBaseSettingController {
	var $form;
	var $errors;
	var $log;
	var $page_name;
	var $db;
	var $context;

	function __construct($db) {
		$this->db = $db;
		$this->form = new Form($db);
		$this->log = new Seven_Logger;
		$this->errors = [];
		$this->context = 'sms_outbox';
		$this->page_name = 'sms_outbox_page_title';
	}

	public function post_request_handler() {
		global $user;

		if (!empty($_POST)) {
			$action = GETPOST("action");
			if ($action == 'clear_sms_outbox') {
				if (!$user->rights->seven->permission->delete) {
					accessforbidden();
				}
				$cleared = $this->clear_sms_outbox();
				if ($cleared) {
					$this->add_notification_message("SMS Outbox cleared");
				} else {
					$this->add_notification_message("Failed to clear SMS Outbox", 'error');
				}
			}
		}
	}

	private function clear_sms_outbox() {
		return (new SevenDatabase($this->db))->delete_all();
	}

	public function render() {
		global $langs, $db;
		$db_obj = new SevenDatabase($db);
		$today = new DateTime("now", new DateTimeZone(getServerTimeZoneString()));

		$current_page = 1;

		if (isset($_GET['pageno'])) {
			$current_page = filter_var($_GET['pageno'], FILTER_SANITIZE_NUMBER_INT);
		}

		$per_page = 15;
		$offset = ($current_page - 1) * $per_page;

		$query_result = $db_obj->getAll($per_page, $offset);
		$results = [];
		foreach ($query_result as $qr) {
			$sms_scheduled_date = new DateTime($qr['date'], new DateTimeZone("UTC"));
			$sms_scheduled_date->setTimezone(new DateTimeZone(getServerTimeZoneString()));
			if ($today >= $sms_scheduled_date && $qr['status'] == Seven_Message_Status::SCHEDULED) {
				$msgid = $qr['msgid'];
				$resp = seven_get_message_status($msgid);
				$msg_status = $resp['message_status'];
				if ($msg_status != 4) {
					if ($msg_status == 1) {
						// success
						$db_obj->updateStatusByMsgId($msgid, Seven_Message_Status::SUCCESS);
					} else if ($msg_status == 2) {
						// failed
						$db_obj->updateStatusByMsgId($msgid, Seven_Message_Status::FAILED);
					}
					$qr['status'] = $msg_status;
				}
			}
			$results[] = $qr;
		}

		$total = $db_obj->getTotalRecords();
		$total_page = ceil($total / $per_page);
		$total_pages_to_show = 10;
		$middle_page_add_on_number = floor($total_pages_to_show / 2);

		if ($total_page < $total_pages_to_show) {
			$start_page = 1;
			$end_page = $total_page;
		} else {
			if (($current_page + $middle_page_add_on_number) > $total_page) {
				$start_page = $total_page - $total_pages_to_show + 1;
				$end_page = $total_page;
			} else if ($current_page > $middle_page_add_on_number) {
				$start_page = $current_page - $middle_page_add_on_number;
				$end_page = $start_page + $total_pages_to_show - 1;
			} else {
				$start_page = 1;
				$end_page = $total_pages_to_show;
			}
		}

		$first_page = 1;
		$last_page = ($total_page > 0 ? $total_page : $first_page);
		$previous_page = ($current_page > 1 ? $current_page - 1 : 1);
		$next_page = ($current_page < $total_page ? $current_page + 1 : $last_page);

		$total_elements = $results;

		?>
		<!-- Begin form SMS -->
		<?php
		llxHeader('', $langs->trans($this->page_name));
		print load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		$head = sevenAdminPrepareHead();
		print dol_get_fiche_head($head, $this->context, $langs->trans($this->page_name), -1);

		if (!empty($this->notification_messages)) {
			foreach ($this->notification_messages as $notification_message) {
				dol_htmloutput_mesg($notification_message['message'], [], $notification_message['style']);
			}
		}

		?>
		<div class="bootstrap-wrapper">
			<nav aria-label="Page navigation example">
				<ul class="pagination">
					<li class="page-item"><a class="page-link"
											 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $first_page ?>">First</a>
					</li>
					<li class="page-item"><a class="page-link"
											 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $previous_page ?>">Previous</a>
					</li>
					<?php for ($i = $start_page; $i <= $end_page; $i++) { ?>
						<li class="page-item"><a class="page-link"
												 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $i ?>"><?= $i; ?></a>
						</li>
					<?php } ?>
					<li class="page-item"><a class="page-link"
											 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $next_page ?>">Next</a>
					</li>
					<li class="page-item"><a class="page-link"
											 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $last_page ?>">Last</a>
					</li>
				</ul>
			</nav>


			<p>Total records : <?= $total; ?></p>
			<p>Page : <?= $current_page; ?></p>
			<table class="table">
				<thead>
				<tr>
					<th scope="col" id='date'>Date</th>
					<th scope="col" id='sender'>Sender</th>
					<th scope="col" id='recipient'>Recipient</th>
					<th scope="col" id='message'>Message</th>
					<th scope="col" id='status'>Status</th>
					<th scope="col" id='source'>Source</th>
				</tr>
				</thead>
				<tbody id="the-list">
				<?php
				foreach ($total_elements as $result) {
					?>
					<tr>
						<td style="width:200px"><?php
							$timezone = new DateTimeZone($_SESSION['dol_tz_string']);
							$datetime = new DateTime($result['date'], new DateTimeZone("UTC"));
							$datetime->setTimezone($timezone);
							echo $datetime->format("Y-m-d H:i:s");
							?></td>
						<td style="width:150px"><?= $result['sender']; ?></td>
						<td style="width:150px"><?= $result['recipient']; ?></td>
						<td><?= $result['message']; ?></td>
						<td style="width:150px"><?= Seven_Message_Status::get_msg_status($result['status']); ?></td>
						<td style="width:150px"><?= $result['source'] ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php if (count($total_elements) > 0) { ?>
			<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>" style="max-width: 500px">
			<input type="hidden" name="token" value="<?= newToken(); ?>">
			<input type="hidden" name="action" value="clear_sms_outbox">
			<button>Clear SMS Outbox</button>
		<?php } ?>
		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
