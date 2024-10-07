<?php
require_once DOL_DOCUMENT_ROOT . "/core/lib/files.lib.php";

class Seven_Logger {
	private $_handles;
	private $log_directory;

	public function __construct() {
		$this->log_directory = DOL_DATA_ROOT . '/seven/logs/';
		dol_mkdir($this->log_directory);
	}

	private function open($handle) {
		if (isset($this->_handles[$handle])) {
			return true;
		}

		if ($this->_handles[$handle] = @fopen($this->log_directory . "{$handle}.log", 'a')) {
			return true;
		}

		return false;
	}

	public function add($handle, $message) {
		if ($this->open($handle)) {
			$current_datetime = date('Y-m-d H:i:s');
			@fwrite($this->_handles[$handle], "$current_datetime $message\n");
		}
	}

	public function get_log_file($handle) {
		$log_file = $this->log_directory . "{$handle}.log"; //The log file.
		if (!file_exists($log_file)) {
			return '';
		}
		return file_get_contents($log_file);
	}

	public function get_log_file_path($handle) {
		$log_file = $this->log_directory . "{$handle}.log"; //The log file.
		return $log_file;
	}

	public function delete_log_file($handle) {
		$log_file = $this->log_directory . "{$handle}.log";
		return dol_delete_file($log_file);
	}
}
