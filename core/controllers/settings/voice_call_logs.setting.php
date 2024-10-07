<?php
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/class/seven_voice_call.db.php");
dol_include_once("/seven/core/controllers/settings/seven.controller.setting.php");

class SevenVoiceCallLogs_Setting extends SevenBaseSettingController {
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
		$this->context = 'voice_call_logs';
		$this->page_name = 'voice_call_logs_page_title';
	}

	public function post_request_handler() {
		global $user;

		if (!empty($_POST)) {
			$action = GETPOST("action");

			if ($action == 'clear_voice_call_logs') {
				if (!$user->rights->seven->permission->delete) {
					accessforbidden();
				}
				$cleared = $this->clear_voice_call_logs();
				if ($cleared) {
					$this->add_notification_message("Voice Call Logs cleared");
				} else {
					$this->add_notification_message("Failed to clear Voice Call Logs", 'error');
				}
			}
		}
	}

	private function clear_voice_call_logs() {
		$db_obj = new SevenVoiceCallDatabase($this->db);
		return $db_obj->delete_all();
	}

	public function render() {
		global $langs, $db;
		$db_obj = new SevenVoiceCallDatabase($db);
		$query_result = $db_obj->get();
		$results = [];
		foreach ($query_result as $qr) {
			$results[] = $qr;
		}
		$total = $this->db->num_rows($query_result);
		$total_page = ceil($total / 10);
		$total_show_pages = 10;
		$middle_page_add_on_number = floor($total_show_pages / 2);
		if (isset($_GET['pageno'])) {
			$current_page = filter_var($_GET['pageno'], FILTER_SANITIZE_NUMBER_INT);
		} else {
			$current_page = 1;
		}

		if ($total_page < $total_show_pages) {
			$start_page = 1;
			$end_page = $total_page;
		} else {
			if (($current_page + $middle_page_add_on_number) > $total_page) {
				$start_page = $total_page - $total_show_pages + 1;
				$end_page = $total_page;
			} else if ($current_page > $middle_page_add_on_number) {
				$start_page = $current_page - $middle_page_add_on_number;
				$end_page = $start_page + $total_show_pages - 1;
			} else {
				$start_page = 1;
				$end_page = $total_show_pages;
			}
		}

		$first_page = 1;
		$last_page = ($total_page > 0 ? $total_page : $first_page);
		$previous_page = ($current_page > 1 ? $current_page - 1 : 1);
		$next_page = ($current_page < $total_page ? $current_page + 1 : $last_page);

		$pageno = ($current_page - 1) * 10;
		$endIndex = $current_page * $total_show_pages;
		$startIndex = $endIndex - $total_show_pages;
		$total_elements = array_slice($results, $startIndex, $total_show_pages);
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


			<span>Page : <?= $current_page; ?></span>
			<table class="table">
				<thead>
				<tr>
					<th scope="col" id='date'>Date</th>
					<th scope="col" id='sender'>Sender</th>
					<th scope="col" id='recipient'>Recipient</th>
					<th scope="col" id='model_id'>Recipient name</th>
					<th scope="col" id='status'>Status</th>
				</tr>
				</thead>
				<tbody id="the-list">
				<?php
				foreach ($total_elements as $result) {
					?>
					<tr>
						<td><?php
							$timezone = new DateTimeZone($_SESSION['dol_tz_string']);
							$datetime = new DateTime($result['date'], new DateTimeZone("UTC"));
							$datetime->setTimezone($timezone);
							echo $datetime->format("Y-m-d H:i:s");
							?></td>
						<td><?= $result['sender']; ?></td>
						<td><?= $result['recipient']; ?></td>
						<td><?php
							$contact = new Contact($db);
							$contact->fetch($result['model_id']);
							echo $contact->getFullName($langs);
							?></td>
						<td><?= ($result['status'] == 0 ? "success" : "failed"); ?></td>
					</tr>
				<?php } ?>
				</tbody>

			</table>
		</div>
		<?php if (count($total_elements) > 0) { ?>
			<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>" style="max-width: 500px">
			<input type="hidden" name="token" value="<?= newToken(); ?>">
			<input type="hidden" name="action" value="clear_voice_call_logs">
			<button>Clear Voice Call Logs</button>
		<?php } ?>

		<?php
		print dol_get_fiche_end();
		llxFooter();
		?>
		<?php
	}
}
