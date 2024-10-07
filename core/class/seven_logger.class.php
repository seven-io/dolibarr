<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

class Seven_Logger {
	private $_handles;
	private string $log_directory;

	public function __construct() {
		$this->log_directory = DOL_DATA_ROOT . '/seven/logs/';
		dol_mkdir($this->log_directory);
	}

	private function open($handle): bool {
		if (isset($this->_handles[$handle])) return true;

		if ($this->_handles[$handle] = @fopen($this->log_directory . $handle . '.log', 'a')) return true;

		return false;
	}

	public function add($handle, $message): void {
		if ($this->open($handle)) @fwrite($this->_handles[$handle], date('Y-m-d H:i:s') . ' $message\n');
	}

	public function get_log_file($handle): bool|string {
		$logFile = $this->log_directory . '{$handle}.log'; //The log file.
		if (!file_exists($logFile)) return '';
		return file_get_contents($logFile);
	}

	public function delete_log_file($handle): bool {
		return dol_delete_file($this->log_directory . $handle . '.log');
	}
}
