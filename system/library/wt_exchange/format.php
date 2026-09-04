<?php
class WtExchangeFormat {
	public static function encodeCsv(array $rows, array $columns, $delimiter = ';', $encoding = 'UTF-8') {
		$fh = fopen('php://temp', 'r+');
		fputcsv($fh, $columns, $delimiter);

		foreach ($rows as $row) {
			$line = array();
			foreach ($columns as $column) {
				$value = isset($row[$column]) ? $row[$column] : '';
				if (is_array($value) || is_object($value)) {
					$value = json_encode($value, JSON_UNESCAPED_UNICODE);
				}
				$line[] = (string)$value;
			}
			fputcsv($fh, $line, $delimiter);
		}

		rewind($fh);
		$content = stream_get_contents($fh);
		fclose($fh);

		return self::convertFromUtf8($content, $encoding);
	}

	public static function decodeCsv($content, $delimiter = ';', $encoding = 'UTF-8') {
		$content = self::convertToUtf8($content, $encoding);

		if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
			$content = substr($content, 3);
		}

		$fh = fopen('php://temp', 'r+');
		fwrite($fh, $content);
		rewind($fh);

		$header = fgetcsv($fh, 0, $delimiter);
		if (!$header) {
			fclose($fh);
			return array();
		}

		$header = array_map('trim', $header);
		$rows = array();

		while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
			if (count($data) === 1 && ($data[0] === null || $data[0] === '')) {
				continue;
			}
			$row = array();
			foreach ($header as $i => $name) {
				$row[$name] = isset($data[$i]) ? $data[$i] : '';
			}
			$rows[] = $row;
		}

		fclose($fh);
		return $rows;
	}

	public static function encodeJson(array $rows, $entity = 'product') {
		return json_encode(array(
			'version' => 1,
			'entity'  => $entity,
			'count'   => count($rows),
			'items'   => $rows
		), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	public static function encodeXml(array $rows, array $fields, $entity = 'product', $encoding = 'UTF-8') {
		$charset = self::charsetAlias($encoding);
		$xml = '<?xml version="1.0" encoding="' . self::escapeXml($charset) . '"?>' . "\n";
		$xml .= '<wt_exchange version="1" entity="' . self::escapeXml($entity) . '" count="' . count($rows) . '">' . "\n";

		foreach ($rows as $row) {
			$xml .= "  <item>\n";
			foreach ($fields as $code) {
				$value = isset($row[$code]) ? $row[$code] : '';
				if (is_array($value) || is_object($value)) {
					$value = json_encode($value, JSON_UNESCAPED_UNICODE);
				}
				$tag = self::xmlTagName($code);
				$xml .= '    <' . $tag . '>' . self::escapeXml((string)$value) . '</' . $tag . '>' . "\n";
			}
			$xml .= "  </item>\n";
		}

		$xml .= "</wt_exchange>\n";

		return self::convertXmlFromUtf8($xml, $encoding);
	}

	public static function decodeJson($content) {
		$data = json_decode($content, true);
		if (!is_array($data)) {
			return array();
		}
		if (isset($data['items']) && is_array($data['items'])) {
			return $data['items'];
		}
		if (isset($data[0]) && is_array($data[0])) {
			return $data;
		}
		return array();
	}

	public static function decodeXml($content, $encoding = 'UTF-8') {
		$content = self::convertToUtf8($content, $encoding);
		if ($content === '') {
			return array();
		}

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
		if ($xml === false) {
			return array();
		}

		$rows = array();
		$items = array();
		if (isset($xml->item)) {
			$items = $xml->item;
		} elseif ($xml->getName() === 'item') {
			$items = array($xml);
		}

		foreach ($items as $item) {
			$row = array();
			foreach ($item->children() as $child) {
				$row[$child->getName()] = trim((string)$child);
			}
			if ($row) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	public static function charsetAlias($encoding) {
		$encoding = trim((string)$encoding);
		if ($encoding === '' || strtoupper($encoding) === 'UTF-8') {
			return 'UTF-8';
		}
		if (strtoupper($encoding) === 'UNICODE') {
			return 'UTF-16LE';
		}
		return $encoding;
	}

	public static function convertFromUtf8($content, $encoding) {
		$to = self::charsetAlias($encoding);
		if ($to === 'UTF-8') {
			return "\xEF\xBB\xBF" . $content;
		}
		if ($to === 'UTF-16LE') {
			$converted = @iconv('UTF-8', 'UTF-16LE//IGNORE', $content);
			if ($converted === false) {
				return $content;
			}
			return "\xFF\xFE" . $converted;
		}
		$converted = @iconv('UTF-8', $to . '//IGNORE', $content);
		return ($converted === false) ? $content : $converted;
	}

	public static function convertToUtf8($content, $encoding) {
		$from = self::charsetAlias($encoding);
		if ($from === 'UTF-8') {
			return $content;
		}
		if ($from === 'UTF-16LE') {
			if (substr($content, 0, 2) === "\xFF\xFE") {
				$content = substr($content, 2);
			}
			$converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
			return ($converted === false) ? $content : $converted;
		}
		$converted = @iconv($from, 'UTF-8//IGNORE', $content);
		return ($converted === false) ? $content : $converted;
	}

	public static function csvContentType($encoding) {
		$charset = self::charsetAlias($encoding);
		if ($charset === 'UTF-16LE') {
			return 'text/csv; charset=UTF-16LE';
		}
		return 'text/csv; charset=' . $charset;
	}

	public static function xmlContentType($encoding) {
		$charset = self::charsetAlias($encoding);
		if ($charset === 'UTF-16LE') {
			return 'application/xml; charset=UTF-16LE';
		}
		return 'application/xml; charset=' . $charset;
	}

	private static function escapeXml($value) {
		return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	private static function xmlTagName($name) {
		$name = preg_replace('/[^a-zA-Z0-9_\-.:]/', '_', (string)$name);
		if ($name === '' || preg_match('/^[0-9.\-]/', $name)) {
			$name = 'f_' . $name;
		}
		return $name;
	}

	private static function convertXmlFromUtf8($content, $encoding) {
		$to = self::charsetAlias($encoding);
		if ($to === 'UTF-8') {
			return $content;
		}
		if ($to === 'UTF-16LE') {
			$converted = @iconv('UTF-8', 'UTF-16LE//IGNORE', $content);
			if ($converted === false) {
				return $content;
			}
			return "\xFF\xFE" . $converted;
		}
		$converted = @iconv('UTF-8', $to . '//IGNORE', $content);
		return ($converted === false) ? $content : $converted;
	}
}
