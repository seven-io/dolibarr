<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

class Seven_Logger {
	private array $handles;
	private string $directory;

	public function __construct() {
		$this->directory = DOL_DATA_ROOT . '/seven/logs/';
		dol_mkdir($this->directory);
	}

	private function open(string $handle): bool {
		if (isset($this->handles[$handle])) return true;

		if ($this->handles[$handle] = @fopen($this->directory . $handle . '.log', 'a')) return true;

		return false;
	}

	public function add(string $handle, string $message): void {
		if ($this->open($handle)) @fwrite($this->handles[$handle], date('Y-m-d H:i:s') . $message . ' \n');
	}

	public function getLogFile(string $handle): bool|string {
		$logFile = $this->directory . $handle . '.log';
		if (!file_exists($logFile)) return '';
		return file_get_contents($logFile);
	}

	public function deleteLogFile(string $handle): bool {
		return dol_delete_file($this->directory . $handle . '.log');
	}
}
