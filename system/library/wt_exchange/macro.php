<?php
class WtExchangeMacro {
	public static function apply(array $row, array $macros) {
		if (!$macros) {
			return $row;
		}
		foreach ($macros as $field => $formula) {
			$formula = trim((string)$formula);
			if ($formula === '' || !isset($row[$field])) {
				continue;
			}
			$row[$field] = self::evaluate($formula, $row);
		}
		return $row;
	}

	public static function evaluate($formula, array $row) {
		$formula = trim((string)$formula);
		if ($formula === '') {
			return '';
		}

		$expr = preg_replace_callback('/\{(_[A-Z0-9_]+_)\}/', function ($matches) use ($row) {
			if (!isset($row[$matches[1]]) || $row[$matches[1]] === '') {
				return '0';
			}
			$value = str_replace(',', '.', (string)$row[$matches[1]]);
			if (is_numeric($value)) {
				return $value;
			}
			return '0';
		}, $formula);

		if (preg_match('/^(round|ceil|floor)\((.+)\)$/i', $expr, $fn)) {
			$inner = self::safeMath($fn[2]);
			if ($inner === null) {
				return $formula;
			}
			$fn_name = strtolower($fn[1]);
			return (string)$fn_name($inner);
		}

		if (strpos($expr, '{') === false) {
			$math = self::safeMath($expr);
			if ($math !== null) {
				return (string)$math;
			}
		}

		return $expr;
	}

	private static function safeMath($expr) {
		$expr = trim((string)$expr);
		if ($expr === '') {
			return null;
		}
		if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
			return null;
		}
		$result = null;
		try {
			$result = eval('return (' . $expr . ');');
		} catch (Throwable $e) {
			return null;
		} catch (Exception $e) {
			return null;
		}
		return is_numeric($result) ? (float)$result : null;
	}
}
