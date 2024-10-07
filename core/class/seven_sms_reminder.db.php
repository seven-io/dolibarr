<?php
require_once DOL_DOCUMENT_ROOT . '/core/db/DoliDB.class.php';
dol_include_once('./seven_logger.class.php');

class SevenSMSReminderDatabase {
	private $log;
	public $tableName = MAIN_DB_PREFIX . 'seven_sms_reminder';

	public function __construct(public DoliDB $db) {
		$this->log = new Seven_Logger;
	}

	public function healthcheck(): bool {
		/*
			Check if SMS reminder table is created
			Return @boolean true on success
		*/
		$sql = sprintf('DESCRIBE `%s` ', $this->tableName);
		$result = $this->db->num_rows($this->db->query($sql));
		return (bool)$result;
	}

	public function insert($setting_uuid, $object_id, $object_type, $reminder_datetime, $update_key, $retry) {
		$this->log->add('Seven', sprintf('Adding SMS reminder. ID: %s, OBJ_TYPE: %s', $object_id, $object_type));
		$sql = sprintf('INSERT INTO `%s` (`setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at`) ', $this->tableName);
		$sql .= sprintf('VALUES (\'%s\', %d, \'%s\', \'%s\', \'%s\', %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
			$this->db->escape($setting_uuid),
			$object_id,
			$this->db->escape($object_type),
			$this->db->escape($reminder_datetime),
			$this->db->escape($update_key),
			$this->db->escape($retry)
		);

		$result = $this->db->query($sql);
		if ($result) {
			$this->log->add('Seven', 'Successfully added to SMS reminders.');
			return true;
		} else {
			$this->log->add('Seven', 'Failed to add into SMS reminders.');
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

	public function getAll() {
		$sql = sprintf('SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `%s`', $this->tableName);
		$result = $this->db->query($sql);
		return $result ?: [];
	}

	public function getAllWhere($key, $operator, $value) {
		$sql = sprintf('SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `%s`', $this->tableName);
		$sql .= sprintf(' WHERE `%s` %s \'%s\'',
			$this->db->escape($key),
			$this->db->escape($operator),
			$this->db->escape($value)
		);
		$result = $this->db->query($sql);
		return $result ?: [];
	}

	public function get($update_key) {
		$sql = sprintf('SELECT `id`, `setting_uuid`, `object_id`, `object_type`, `reminder_datetime`, `update_key`, `retry`, `created_at`, `updated_at` FROM `%s`', $this->tableName);
		$sql .= sprintf(' WHERE `update_key` = \'%s\'', $update_key);
		$result = $this->db->query($sql);
		return $result ?: [];
	}

	public function resetUpdateKey($update_key = null) {
		if ($update_key) {
			$sql = sprintf('UPDATE %s SET `update_key` = NULL WHERE `update_key` = \'%s\'', $this->tableName, $update_key);
		} else {
			$sql = sprintf('UPDATE %s SET `update_key` = NULL', $this->tableName);
		}
		$this->db->query($sql, 0, 'ddl');
		$this->log->add('Seven', 'Resetted update key: {' . $update_key);
	}

	public function resetUpdateKeyById($id) {
		$sql = sprintf('UPDATE %s SET `update_key` = NULL WHERE `id` = %d', $this->tableName, $id);
		$this->db->query($sql, 0, 'ddl');
		$this->log->add('Seven', 'Resetted update key for ID: ' . $id);
	}

}
