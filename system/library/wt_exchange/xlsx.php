<?php
class WtExchangeXlsx {
	public static function encode(array $rows, array $columns) {
		if (!class_exists('ZipArchive')) {
			return '';
		}

		$sheet_rows = array();
		$sheet_rows[] = self::sheetRow($columns, 1);
		$row_num = 2;
		foreach ($rows as $row) {
			$line = array();
			foreach ($columns as $column) {
				$line[] = isset($row[$column]) ? (string)$row[$column] : '';
			}
			$sheet_rows[] = self::sheetRow($line, $row_num++);
		}

		$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>' . implode('', $sheet_rows) . '</sheetData></worksheet>';

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '</Relationships>';

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '</Types>';

		$tmp = tempnam(sys_get_temp_dir(), 'wtxlsx');
		$zip = new ZipArchive();
		$zip->open($tmp, ZipArchive::OVERWRITE);
		$zip->addFromString('[Content_Types].xml', $content_types);
		$zip->addFromString('_rels/.rels', $rels);
		$zip->addFromString('xl/workbook.xml', $workbook);
		$zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);
		$zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
		$zip->close();

		$content = file_get_contents($tmp);
		@unlink($tmp);
		return $content;
	}

	public static function decode($content) {
		if (!class_exists('ZipArchive')) {
			return array();
		}

		$tmp = tempnam(sys_get_temp_dir(), 'wtxlsx');
		file_put_contents($tmp, $content);
		$zip = new ZipArchive();
		if ($zip->open($tmp) !== true) {
			@unlink($tmp);
			return array();
		}

		$sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
		$zip->close();
		@unlink($tmp);

		if ($sheet === false) {
			return array();
		}

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($sheet);
		if ($xml === false || !isset($xml->sheetData->row)) {
			return array();
		}

		$rows = array();
		$header = array();
		foreach ($xml->sheetData->row as $row) {
			$cells = array();
			foreach ($row->c as $cell) {
				$ref = (string)$cell['r'];
				preg_match('/([A-Z]+)/', $ref, $m);
				$col = isset($m[1]) ? self::columnIndex($m[1]) : count($cells);
				$value = '';
				if (isset($cell->v)) {
					$value = (string)$cell->v;
				} elseif (isset($cell->is->t)) {
					$value = (string)$cell->is->t;
				}
				$cells[$col] = $value;
			}
			if (!$cells) {
				continue;
			}
			ksort($cells);
			$cells = array_values($cells);
			if (!$header) {
				$header = $cells;
				continue;
			}
			$item = array();
			foreach ($header as $i => $name) {
				$name = trim($name);
				if ($name !== '') {
					$item[$name] = isset($cells[$i]) ? $cells[$i] : '';
				}
			}
			if ($item) {
				$rows[] = $item;
			}
		}
		return $rows;
	}

	private static function sheetRow(array $values, $row_num) {
		$cells = '';
		$col = 0;
		foreach ($values as $value) {
			$ref = self::columnName($col) . $row_num;
			$value = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
			$cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
			$col++;
		}
		return '<row r="' . $row_num . '">' . $cells . '</row>';
	}

	private static function columnName($index) {
		$name = '';
		$index = (int)$index;
		do {
			$name = chr(65 + ($index % 26)) . $name;
			$index = (int)floor($index / 26) - 1;
		} while ($index >= 0);
		return $name;
	}

	private static function columnIndex($letters) {
		$letters = strtoupper($letters);
		$num = 0;
		for ($i = 0; $i < strlen($letters); $i++) {
			$num = $num * 26 + (ord($letters[$i]) - 64);
		}
		return $num - 1;
	}
}
