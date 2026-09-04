<?php
class WtExchangeImageFetch {
	public static function resolve($value, $subdir = 'wt_exchange') {
		$value = trim((string)$value);
		if ($value === '') {
			return '';
		}
		if (!preg_match('#^https?://#i', $value)) {
			return $value;
		}

		$target_dir = DIR_IMAGE . 'catalog/' . trim($subdir, '/') . '/';
		if (!is_dir($target_dir)) {
			@mkdir($target_dir, 0755, true);
		}

		$path = parse_url($value, PHP_URL_PATH);
		$basename = basename($path ? $path : 'image.jpg');
		$basename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $basename);
		if ($basename === '' || strpos($basename, '.') === false) {
			$basename .= '.jpg';
		}

		$filename = $target_dir . $basename;
		if (is_file($filename)) {
			return 'catalog/' . trim($subdir, '/') . '/' . $basename;
		}

		$context = stream_context_create(array(
			'http' => array(
				'timeout' => 20,
				'user_agent' => 'WT-Exchange/1.0'
			),
			'ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false
			)
		));

		$data = @file_get_contents($value, false, $context);
		if ($data === false || $data === '') {
			return $value;
		}

		if (@file_put_contents($filename, $data) === false) {
			return $value;
		}

		return 'catalog/' . trim($subdir, '/') . '/' . $basename;
	}

	public static function resolveList($value, $subdir = 'wt_exchange') {
		$parts = preg_split('/[\|]/', (string)$value);
		$result = array();
		foreach ($parts as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}
			if (strpos($part, ':') !== false && preg_match('/:\d+$/', $part)) {
				$pos = strrpos($part, ':');
				$image = substr($part, 0, $pos);
				$sort = (int)substr($part, $pos + 1);
				$result[] = array('image' => self::resolve($image, $subdir), 'sort_order' => $sort);
			} else {
				$result[] = array('image' => self::resolve($part, $subdir), 'sort_order' => count($result));
			}
		}
		return $result;
	}
}
