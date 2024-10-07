<?php
dol_include_once('./seven_logger.class.php');

class SevenSMSTemplateDatabase {
	private Seven_Logger $log;
	public string $table_name = MAIN_DB_PREFIX . 'seven_sms_template';

	public function __construct(public DoliDB $db) {
		$this->log = new Seven_Logger;
	}

	public function healthcheck(): bool { // Check if SMS template table has been created
		$result = $this->db->num_rows($this->db->query(sprintf('DESCRIBE `%s`', $this->table_name)));
		return (bool)$result;
	}

	public function insert($title, $message): bool {
		$this->log->add('Seven', 'Adding SMS Template.');
		$sql = sprintf('INSERT INTO `%s` (`title`, `message`, `created_at`)', $this->table_name);
		$sql .= sprintf(' VALUES (\'%s\', \'%s\', UTC_TIMESTAMP() )',
			$this->db->escape($title),
			$this->db->escape($message),
		);

		if ($this->db->query($sql)) {
			$this->log->add('Seven', 'Successfully added to SMS template.');
			return true;
		} else {
			$this->log->add('Seven', 'Failed to add into SMS template.');
			return false;
		}
	}

	public function update($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		// Proxy the $query to $this->db->query() so we can do logging.
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function delete($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		// Proxy the $query to $this->db->query() so we can do logging.
		return $this->db->query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0);
	}

	public function getAll($limit = null, $offset = null) {
		$sql = 'SELECT `id`, `title`, `message`, `created_at` FROM `' . $this->table_name . '`';
		$sql .= ' ORDER BY `id` DESC';

		if (!is_null($limit) && !is_null($offset)) $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

		$result = $this->db->query($sql);
		return $result ?: [];
	}

	public function get($id) {
		$sql = 'SELECT `id`, `title`, `message`, `created_at` FROM `' . $this->table_name . '`';
		$sql .= sprintf(' WHERE `id` = %d', $id);
		$result = $this->db->query($sql);
		return $result ?: [];
	}

	function updateById($id, $title, $message) {
		$sql = 'UPDATE ' . $this->table_name;
		$sql .= sprintf(' SET `title` = \'%s\', `message` = \'%s\' ',
			$this->db->escape($title),
			$this->db->escape($message)
		);
		$sql .= 'WHERE `id` = ' . $id;
		return $this->update($sql);
	}

	function deleteById($id) {
		$sql = 'DELETE FROM ' . $this->table_name;
		$sql .= ' WHERE id = ' . $id;
		return $this->delete($sql);
	}

	function getTotalRecords() {
		$results = [];
		$result = $this->db->query('SELECT COUNT(*) as `total_records` FROM `' . $this->table_name . '`');
		foreach ($result as $qr) $results[] = $qr;
		return $results[0]['total_records'];
	}

}
