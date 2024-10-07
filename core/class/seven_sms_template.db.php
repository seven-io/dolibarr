<?php
dol_include_once("./seven_logger.class.php");
dol_include_once("/seven/core/interfaces/healthcheck.class.php");

class SevenSMSTemplateDatabase implements IHealthcheck {

	public $db;
	private $log;

	public $table_name = MAIN_DB_PREFIX . 'seven_sms_template';

	public function __construct(DoliDB $db) {
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	public function healthcheck() {
		/*
			Check if SMS reminder table is created
			Return @boolean true on success
		*/
		$sql = sprintf("DESCRIBE `%s`",
			$this->table_name
		);
		$result = $this->db->num_rows($this->db->query($sql));
		return ($result) ? true : false;
	}

	public function insert($title, $message) {
		$this->log->add("Seven", "Adding SMS Template.");
		$sql = "INSERT INTO `{$this->table_name}` (`title`, `message`, `created_at`)";
		$sql .= sprintf(' VALUES ("%s", "%s", UTC_TIMESTAMP() )',
			$this->db->escape($title),
			$this->db->escape($message),
		);

		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add("Seven", "Successfully added to SMS template.");
			return true;
		} else {
			$this->log->add("Seven", "Failed to add into SMS template.");
			return false;
		}
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

	public function deleteAll() {
		$sql = "DELETE FROM `{$this->table_name}`;";
		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add("Seven", "Successfully cleared SMS templates");
			return true;
		} else {
			$this->log->add("Seven", "Failed to clear SMS templates");
			return false;
		}
	}

	public function getAll($limit = null, $offset = null) {
		$sql = "SELECT `id`, `title`, `message`, `created_at` FROM `{$this->table_name}`";
		$sql .= " ORDER BY `id` DESC";

		if (!is_null($limit) && !is_null($offset)) {
			$sql .= " LIMIT {$limit} OFFSET {$offset}";
		}

		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function getAllWhere($key, $operator, $value) {
		$sql = "SELECT `id`, `title`, `message`, `created_at` FROM `{$this->table_name}`";
		$sql .= sprintf(' WHERE `%s` %s "%s"',
			$this->db->escape($key),
			$this->db->escape($operator),
			$this->db->escape($value)
		);
		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function get($id) {
		$sql = "SELECT `id`, `title`, `message`, `created_at` FROM `{$this->table_name}`";
		$sql .= sprintf(" WHERE `id` = %d", $id);
		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	function updateById($id, $title, $message) {

		$sql = "UPDATE {$this->table_name}";
		$sql .= sprintf(' SET `title` = "%s", `message` = "%s" ',
			$this->db->escape($title),
			$this->db->escape($message)
		);
		$sql .= "WHERE `id` = $id";
		return $this->update($sql);
	}

	function deleteById($id) {
		$sql = "DELETE FROM {$this->table_name}";
		$sql .= " WHERE id = $id";
		return $this->delete($sql);
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
