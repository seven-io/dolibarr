<?php
require_once DOL_DOCUMENT_ROOT . "/core/db/DoliDB.class.php";
dol_include_once("./seven_logger.class.php");
dol_include_once("/seven/core/interfaces/healthcheck.class.php");

class SevenSMSReminderDatabase implements IHealthcheck {

	public $db;
	private $log;

	public $table_name = MAIN_DB_PREFIX . 'seven_sms_reminder';

	public function __construct(DoliDB $db) {
		$this->db = $db;
		$this->log = new Seven_Logger;
	}

	public function healthcheck() {
		/*
			Check if SMS reminder table is created
			Return @boolean true on success
		*/
		$sql = sprintf("DESCRIBE `%s` ",
			$this->table_name
		);
		$result = $this->db->num_rows($this->db->query($sql));
		return ($result) ? true : false;
	}

	public function insert($setting_uuid, $object_id, $object_type, $reminder_datetime, $update_key, $retry) {
		$this->log->add("Seven", "Adding SMS reminder. ID: {$object_id}, OBJ_TYPE: {$object_type}");
		$sql = "INSERT INTO `{$this->table_name}` (`setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at`) ";
		$sql .= sprintf('VALUES ("%s", %d, "%s", "%s", "%s", %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
			$this->db->escape($setting_uuid),
			$object_id,
			$this->db->escape($object_type),
			$this->db->escape($reminder_datetime),
			$this->db->escape($update_key),
			$this->db->escape($retry)
		);

		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add("Seven", "Successfully added to SMS reminders.");
			return true;
		} else {
			$this->log->add("Seven", "Failed to add into SMS reminders.");
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
			$this->log->add("Seven", "Successfully cleared SMS reminders");
			return true;
		} else {
			$this->log->add("Seven", "Failed to clear SMS reminders");
			return false;
		}
	}

	public function getAll() {
		$sql = "SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `{$this->table_name}`";
		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function getAllWhere($key, $operator, $value) {
		$sql = "SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `{$this->table_name}`";
		$sql .= sprintf(' WHERE `%s` %s "%s"',
			$this->db->escape($key),
			$this->db->escape($operator),
			$this->db->escape($value)
		);
		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function get($update_key) {
		$sql = "SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `{$this->table_name}`";
		$sql .= sprintf(" WHERE `update_key` = '%s'", $update_key);
		$result = $this->db->query($sql);
		return $result ? $result : [];
	}

	public function resetUpdateKey($update_key = null) {
		$sql = '';
		if ($update_key) {
			$sql = sprintf("UPDATE {$this->table_name} SET `update_key` = NULL WHERE `update_key` = '%s'", $update_key);
		} else {
			$sql = "UPDATE {$this->table_name} SET `update_key` = NULL";
		}
		$this->db->query($sql, 0, "ddl");
		$this->log->add("Seven", "Resetted update key: {$update_key}");
	}

	public function resetUpdateKeyById($id) {
		$sql = sprintf("UPDATE {$this->table_name} SET `update_key` = NULL WHERE `id` = %d", $id);
		$this->db->query($sql, 0, "ddl");
		$this->log->add("Seven", "Resetted update key for ID: {$id}");
	}

}
