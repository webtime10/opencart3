<?php
class WtExchangeLogger {
	private $dir;
	private $file;
	private $enabled;

	public function __construct($workdir, $enabled = true, $prefix = 'import') {
		$this->dir = rtrim($workdir, '/\\') . '/logs/';
		$this->enabled = (bool)$enabled;
		$this->file = $this->dir . $prefix . '_' . date('Y-m-d_H-i-s') . '.log';
		if ($this->enabled && !is_dir($this->dir)) {
			@mkdir($this->dir, 0755, true);
		}
	}

	public function getFile() {
		return $this->file;
	}

	public function write($line) {
		if (!$this->enabled) {
			return;
		}
		if (!is_dir($this->dir)) {
			@mkdir($this->dir, 0755, true);
		}
		$message = '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
		@file_put_contents($this->file, $message, FILE_APPEND | LOCK_EX);
	}

	public static function listLogs($workdir) {
		$dir = rtrim($workdir, '/\\') . '/logs/';
		$files = array();
		if (!is_dir($dir)) {
			return $files;
		}
		foreach (glob($dir . '*.log') as $path) {
			$files[] = array(
				'name' => basename($path),
				'size' => filesize($path),
				'date' => date('Y-m-d H:i:s', filemtime($path))
			);
		}
		usort($files, function ($a, $b) {
			return strcmp($b['date'], $a['date']);
		});
		return $files;
	}

	public static function readLog($workdir, $name) {
		$name = basename($name);
		if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.log$/', $name)) {
			return '';
		}
		$path = rtrim($workdir, '/\\') . '/logs/' . $name;
		return is_file($path) ? file_get_contents($path) : '';
	}
}
