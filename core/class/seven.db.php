<?php
dol_include_once('./seven_logger.class.php');

class SevenDatabase {
	private Seven_Logger $log;
	private string $tableName = MAIN_DB_PREFIX . 'seven_msg_outbox';

	public function __construct(private $db) {
		$this->log = new Seven_Logger;
	}

	public function update($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		// Proxy the $query to $this->db->query() so we can do logging.
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function delete($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		// Proxy the $query to $this->db->query() so we can do logging.
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function insert($msgid, $sender, $recipient, $message, $status, $source, $type, $date = NULL): bool {
		if (strlen($sender) > 20) $sender = substr($sender, 0, 20);
		$sql = sprintf('INSERT INTO `%s` (`msgid`, `sender`, `recipient`, `message`, `status`, `source`, `date`, `type`) ', $this->tableName);
		$sql .= (is_null($date))
			? sprintf('VALUES (\'%s\', \'%s\', \'%s\', \'%s\', %d, \'%s\', UTC_TIMESTAMP(), \'%s\' )',
				$this->db->escape($msgid),
				$this->db->escape($sender),
				$this->db->escape($recipient),
				$this->db->escape($message),
				$this->db->escape($status),
				$this->db->escape($source),
				$this->db->escape($type)
			) : sprintf('VALUES (\'%s\', \'%s\', \'%s\', \'%s\', %d, \'%s\', \'%s\', \'%s\')',
				$this->db->escape($msgid),
				$this->db->escape($sender),
				$this->db->escape($recipient),
				$this->db->escape($message),
				$this->db->escape($status),
				$this->db->escape($source),
				$this->db->escape($date),
				$this->db->escape($type)
			);

		if ($this->db->query($sql)) {
			$this->log->add('Seven', 'Successfully added to Outbox');
			return true;
		} else {
			$this->log->add('Seven', 'Failed to add into Outbox');
			return false;
		}
	}

	public function deleteAll(): bool {
		if ($this->db->query(sprintf('DELETE FROM `%s`;', $this->tableName))) {
			$this->log->add('Seven', 'Successfully cleared Outbox');
			return true;
		} else {
			$this->log->add('Seven', 'Failed to clear Outbox');
			return false;
		}
	}

	public function getAll($limit = null, $offset = null) {
		$sql = sprintf('SELECT `id`, `msgid`, `sender`, `recipient`, `message`, `status`, `source`, `date`, `type` FROM `%s`', $this->tableName);
		$sql .= ' ORDER BY `date` DESC';

		if (!is_null($limit) && !is_null($offset)) $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);

		$result = $this->db->query($sql);
		return $result ?: [];
	}

	public function updateStatusByMsgId($msgid, $status) {
		$sql = 'UPDATE ' . $this->tableName;
		$sql .= sprintf(' SET `status` = %d', $status);
		$sql .= sprintf(' WHERE `msgid` = \'%s\'', $msgid);
		return $this->update($sql);
	}

	function getTotalRecords() {
		$sql = sprintf('SELECT COUNT(*) as `total_records` FROM `%s`', $this->tableName);
		$queryResults = [];
		$result = $this->db->query($sql);
		foreach ($result as $qr) $queryResults[] = $qr;
		return $queryResults[0]['total_records'];
	}

}
