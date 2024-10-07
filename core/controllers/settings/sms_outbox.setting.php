<?php

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/seven_message_status.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/class/seven.db.php');
dol_include_once('/seven/core/controllers/settings/seven.controller.setting.php');

class SMS_SMSOutbox_Setting extends SevenBaseSettingController {
	var string $context = 'sms_outbox';
	var array $errors  = [];
	var string $page_name = 'sms_outbox_page_title';

	private function getMessageStatus($msgId): ?array {
		global $conf;

		if (empty($msgId)) return null;

		$apiKey = property_exists($conf->global, 'SEVEN_API_KEY') ? $conf->global->SEVEN_API_KEY : '';
		$sevenClient = new SevenSMS($apiKey);
		$resp = $sevenClient->messageStatus($msgId);
		$resp = json_decode($resp, true);

		return $resp;
	}

	public function postRequestHandler(): void {
		global $user;

		if (empty($_POST)) return;

		if (GETPOST('action') == 'clearSmsOutbox') {
			if (!$user->rights->seven->permission->delete) accessforbidden();
			if ($this->clearSmsOutbox()) $this->addNotificationMessage('SMS Outbox cleared');
			else $this->addNotificationMessage('Failed to clear SMS Outbox', 'error');
		}
	}

	private function clearSmsOutbox(): bool {
		return (new SevenDatabase($this->db))->deleteAll();
	}

	public function render(): void {
		global $langs, $db;

		$sevenDatabase = new SevenDatabase($db);
		$today = new DateTime('now', new DateTimeZone(getServerTimeZoneString()));
		$currentPage = 1;

		if (isset($_GET['pageno'])) $currentPage = filter_var($_GET['pageno'], FILTER_SANITIZE_NUMBER_INT);

		$perPage = 15;
		$offset = ($currentPage - 1) * $perPage;
		$results = [];

		foreach ($sevenDatabase->getAll($perPage, $offset) as $qr) {
			$scheduledDate = new DateTime($qr['date'], new DateTimeZone('UTC'));
			$scheduledDate->setTimezone(new DateTimeZone(getServerTimeZoneString()));

			if ($today >= $scheduledDate && $qr['status'] == Seven_Message_Status::SCHEDULED) {
				$msgId = $qr['msgid'];
				$resp = $this->getMessageStatus($msgId);
				$msgStatus = $resp['message_status'];

				if ($msgStatus != 4) {
					if ($msgStatus == 1) { // success
						$sevenDatabase->updateStatusByMsgId($msgId, Seven_Message_Status::SUCCESS);
					} else if ($msgStatus == 2) { // failed
						$sevenDatabase->updateStatusByMsgId($msgId, Seven_Message_Status::FAILED);
					}
					$qr['status'] = $msgStatus;
				}
			}
			$results[] = $qr;
		}

		$total = $sevenDatabase->getTotalRecords();
		$totalPage = ceil($total / $perPage);
		$totalPagesToShow = 10;
		$middlePageAddOnNumber = floor($totalPagesToShow / 2);

		if ($totalPage < $totalPagesToShow) {
			$startPage = 1;
			$endPage = $totalPage;
		} else {
			if (($currentPage + $middlePageAddOnNumber) > $totalPage) {
				$startPage = $totalPage - $totalPagesToShow + 1;
				$endPage = $totalPage;
			} else if ($currentPage > $middlePageAddOnNumber) {
				$startPage = $currentPage - $middlePageAddOnNumber;
				$endPage = $startPage + $totalPagesToShow - 1;
			} else {
				$startPage = 1;
				$endPage = $totalPagesToShow;
			}
		}

		$firstPage = 1;
		$lastPage = ($totalPage > 0 ? $totalPage : $firstPage);
		$previousPage = ($currentPage > 1 ? $currentPage - 1 : 1);
		$nextPage = ($currentPage < $totalPage ? $currentPage + 1 : $lastPage);
		$totalElements = $results;
		llxHeader('', $langs->trans($this->page_name));
		echo load_fiche_titre($langs->trans($this->page_name), '', 'title_setup');
		echo dol_get_fiche_head(sevenAdminPrepareHead(), $this->context, $langs->trans($this->page_name), -1);
		foreach ($this->notificationMessages ?? [] as $msg) dol_htmloutput_mesg($msg['message'], [], $msg['style']);
		$paginationPrefix = $_SERVER['PHP_SELF'] . '?pageno=';
		?>
		<nav aria-label='Pagination'>
			<ul class='pagination'>
				<li class='page-item'><a class='page-link' href='<?= $paginationPrefix . $firstPage ?>'>First</a></li>
				<li class='page-item'>
					<a class='page-link' href='<?= $paginationPrefix . $previousPage ?>'>Previous</a>
				</li>
				<?php for ($i = $startPage; $i <= $endPage; $i++): ?>
					<li class='page-item'><a class='page-link' href='<?= $paginationPrefix . $i ?>'><?= $i; ?></a></li>
				<?php endfor ?>
				<li class='page-item'><a class='page-link' href='<?= $paginationPrefix . $nextPage ?>'>Next</a></li>
				<li class='page-item'><a class='page-link' href='<?= $paginationPrefix . $lastPage ?>'>Last</a></li>
			</ul>
		</nav>

		<p>Total records : <?= $total; ?></p>
		<p>Page : <?= $currentPage; ?></p>
		<table class='table'>
			<thead>
			<tr>
				<th scope='col' id='date'>Date</th>
				<th scope='col' id='sender'>Sender</th>
				<th scope='col' id='recipient'>Recipient</th>
				<th scope='col' id='message'>Message</th>
				<th scope='col' id='status'>Status</th>
				<th scope='col' id='source'>Source</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ($totalElements as $result):
				?>
				<tr>
					<td style='width:200px'><?php
						$timezone = new DateTimeZone($_SESSION['dol_tz_string']);
						$datetime = new DateTime($result['date'], new DateTimeZone('UTC'));
						$datetime->setTimezone($timezone);
						echo $datetime->format('Y-m-d H:i:s');
						?></td>
					<td style='width:150px'><?= $result['sender']; ?></td>
					<td style='width:150px'><?= $result['recipient']; ?></td>
					<td><?= $result['message']; ?></td>
					<td style='width:150px'><?= Seven_Message_Status::getMessageStatus($result['status']); ?></td>
					<td style='width:150px'><?= $result['source'] ?></td>
				</tr>
			<?php endforeach ?>
			</tbody>
		</table>
		<?php if (count($totalElements) > 0): ?>
			<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>' style='max-width: 500px' />
			<input type='hidden' name='token' value='<?= newToken(); ?>'/>
			<input type='hidden' name='action' value='clearSmsOutbox'/>
			<button>Clear SMS Outbox</button>
		<?php endif ?>
		<?php
		echo dol_get_fiche_end();
		llxFooter();
	}
}
