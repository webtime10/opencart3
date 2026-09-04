<?php
class WtExchangeImportJob {
	private $workdir;

	public function __construct($workdir) {
		$this->workdir = rtrim($workdir, '/\\') . '/jobs/';
		if (!is_dir($this->workdir)) {
			@mkdir($this->workdir, 0755, true);
		}
	}

	public function create(array $payload) {
		$id = substr(md5(uniqid('', true)), 0, 16);
		$payload['id'] = $id;
		$payload['created'] = date('Y-m-d H:i:s');
		$payload['offset'] = 0;
		$payload['updated'] = 0;
		$payload['inserted'] = 0;
		$payload['skipped'] = 0;
		$payload['errors'] = array();
		$payload['finished'] = false;
		$this->save($id, $payload);
		return $payload;
	}

	public function get($id) {
		$path = $this->path($id);
		if (!is_file($path)) {
			return null;
		}
		$data = json_decode(file_get_contents($path), true);
		return is_array($data) ? $data : null;
	}

	public function save($id, array $payload) {
		file_put_contents($this->path($id), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
	}

	public function path($id) {
		$id = preg_replace('/[^a-zA-Z0-9]/', '', (string)$id);
		return $this->workdir . $id . '.json';
	}

	public function storeUpload($id, $tmp_name, $original_name) {
		$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		if ($ext === '') {
			$ext = 'csv';
		}
		$dest = $this->workdir . $id . '_file.' . $ext;
		if (!@move_uploaded_file($tmp_name, $dest)) {
			@copy($tmp_name, $dest);
		}
		return $dest;
	}

	public function countRows(array $job) {
		if (!empty($job['total'])) {
			return (int)$job['total'];
		}
		$rows = $this->loadAllRows($job);
		return count($rows);
	}

	public function loadBatch(array $job, $offset, $limit) {
		$rows = $this->loadAllRows($job);
		return array_slice($rows, $offset, $limit);
	}

	private function loadAllRows(array $job) {
		if (!empty($job['rows']) && is_array($job['rows'])) {
			return $job['rows'];
		}
		if (empty($job['file']) || !is_file($job['file'])) {
			return array();
		}

		$content = file_get_contents($job['file']);
		$name = strtolower($job['file']);
		$delimiter = !empty($job['config']['delimiter']) ? $job['config']['delimiter'] : ';';
		$encoding = !empty($job['config']['encoding']) ? $job['config']['encoding'] : 'UTF-8';

		if (substr($name, -5) === '.json') {
			return WtExchangeFormat::decodeJson($content);
		}
		if (substr($name, -4) === '.xml') {
			return WtExchangeFormat::decodeXml($content, $encoding);
		}
		if (substr($name, -5) === '.xlsx') {
			return WtExchangeXlsx::decode($content);
		}
		return WtExchangeFormat::decodeCsv($content, $delimiter, $encoding);
	}
}
