<?php
dol_include_once("./seven_logger.class.php");

class SevenDatabase {

	private $db;
	private $log;

	private $table_name = MAIN_DB_PREFIX . 'seven_sms_outbox';

	public function __construct($db) {
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	public function update($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		/*
			Proxy the $query to $this->db->query() so we can do logging.
		*/
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function delete($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		/*
			Proxy the $query to $this->db->query() so we can do logging.
		*/
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function insert($msgid, $sender, $recipient, $message, $status, $source, $date = NULL) {
		if (strlen($sender) > 20) {
			$sender = substr($sender, 0, 20);
		}
		$sql = "INSERT INTO `{$this->table_name}` (`msgid`, `sender`, `recipient`, `message`, `status`, `source`, `date`) ";
		$sql .= (is_null($date))
			? sprintf('VALUES ("%s", "%s", "%s", "%s", %d, "%s", UTC_TIMESTAMP() )',
				$this->db->escape($msgid),
				$this->db->escape($sender),
				$this->db->escape($recipient),
				$this->db->escape($message),
				$this->db->escape($status),
				$this->db->escape($source)
			) : sprintf('VALUES ("%s", "%s", "%s", "%s", %d, "%s", "%s")',
				$this->db->escape($msgid),
				$this->db->escape($sender),
				$this->db->escape($recipient),
				$this->db->escape($message),
				$this->db->escape($status),
				$this->db->escape($source),
				$this->db->escape($date),
			);

		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add("Seven", "Successfully added to SMS Outbox");
			return true;
		} else {
			$this->log->add("Seven", "Failed to add into SMS Outbox");
			return false;
		}
	}

	public function delete_all() {
		$sql = "DELETE FROM `{$this->table_name}`;";
		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add("Seven", "Successfully cleared SMS Outbox");
			return true;
		} else {
			$this->log->add("Seven", "Failed to clear SMS Outbox");
			return false;
		}
	}

	public function getAll($limit = null, $offset = null) {
		$sql = "SELECT `id`, `msgid`, `sender`, `recipient`, `message`, `status`, `source`, `date` FROM `{$this->table_name}`";
		$sql .= " ORDER BY `date` DESC";

		if (!is_null($limit) && !is_null($offset)) {
			$sql .= " LIMIT {$limit} OFFSET {$offset}";
		}

		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function updateStatusByMsgId($msgid, $status) {
		$sql = "UPDATE {$this->table_name}";
		$sql .= sprintf(' SET `status` = %d',
			$status
		);
		$sql .= " WHERE `msgid` = '$msgid'";
		return $this->update($sql);
	}

	function getTotalRecords() {
		$sql = "SELECT COUNT(*) as `total_records` FROM `{$this->table_name}`";
		$query_results = [];
		$result = $this->db->query($sql);
		foreach ($result as $qr) {
			$query_results[] = $qr;
		}
		return $query_results[0]['total_records'];
	}

}
