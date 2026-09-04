<?php
/**
 * Хелпер разбора строки фильтра WT Filter.
 * Формат: 12:5,8;m:3;p:100-500
 */

function wt_filter_is_range($string) {
	return (bool)preg_match('/^(-|)(\d+\.)?\d+?\-(-|)(\d+\.)?\d+?$/', (string)$string);
}

function wt_filter_range_parts($string) {
	if (!preg_match('/^((-|)(\d+\.)?\d+?)\-((-|)(\d+\.)?\d+?)$/', (string)$string, $m)) {
		return null;
	}

	return array(
		'from' => $m[1],
		'to'   => $m[4],
	);
}

function wt_filter_is_id($string) {
	return (bool)preg_match('/^[0-9]+$/', (string)$string);
}

function wt_filter_clean_params($params, $config) {
	$matches = array();
	$part_sep = $config->get('wt_filter_part_separator');
	$opt_sep = $config->get('wt_filter_option_separator');
	$val_sep = $config->get('wt_filter_option_value_separator');

	if (!$params) {
		return '';
	}

	foreach (explode($part_sep, (string)$params) as $part) {
		$option = explode($opt_sep, $part, 2);

		if (!isset($option[1]) || $option[1] === '') {
			continue;
		}

		$key = $option[0];
		$raw = $option[1];

		if (wt_filter_is_range($raw)) {
			$range = wt_filter_range_parts($raw);

			if ($range) {
				$matches[] = $key . $opt_sep . (float)$range['from'] . '-' . (float)$range['to'];
			}
		} elseif ($key === 'm' || $key === 's' || $key === 'd') {
			$values = array();

			foreach (explode($val_sep, $raw) as $value_id) {
				if ($key === 's' && ($value_id === 'in' || $value_id === 'out')) {
					$values[] = $value_id;
				} elseif ($key === 'd' && ($value_id === '1' || $value_id === 'yes')) {
					$values[] = '1';
				} elseif (ctype_digit((string)$value_id)) {
					$values[] = (int)$value_id;
				}
			}

			if ($values) {
				$matches[] = $key . $opt_sep . implode($val_sep, array_unique($values));
			}
		} elseif (wt_filter_is_id($key)) {
			$values = array();

			foreach (explode($val_sep, $raw) as $value_id) {
				if ($value_id !== '') {
					$values[] = (string)$value_id;
				}
			}

			if ($values) {
				$matches[] = (int)$key . $opt_sep . implode($val_sep, $values);
			}
		}
	}

	return implode($part_sep, $matches);
}

function wt_filter_decode_params($params, $config) {
	$decode = array();
	$params = wt_filter_clean_params($params, $config);

	if ($params === '') {
		return $decode;
	}

	$part_sep = $config->get('wt_filter_part_separator');
	$opt_sep = $config->get('wt_filter_option_separator');
	$val_sep = $config->get('wt_filter_option_value_separator');

	foreach (explode($part_sep, $params) as $part) {
		$option = explode($opt_sep, $part, 2);
		$values = explode($val_sep, $option[1]);
		sort($values);
		$decode[$option[0]] = $values;
	}

	ksort($decode);

	return $decode;
}

function wt_filter_encode_params($params, $config) {
	if (!$params) {
		return '';
	}

	$part_sep = $config->get('wt_filter_part_separator');
	$opt_sep = $config->get('wt_filter_option_separator');
	$val_sep = $config->get('wt_filter_option_value_separator');
	$encode = array();

	ksort($params);

	foreach ($params as $option_id => $values) {
		if (!$values) {
			continue;
		}

		$values = array_values($values);
		sort($values);
		$encode[] = $option_id . $opt_sep . implode($val_sep, $values);
	}

	return wt_filter_clean_params(implode($part_sep, $encode), $config);
}

function wt_filter_is_slider_type($type) {
	return in_array((string)$type, array('slider_single', 'slider_range', 'slide', 'slide_dual'), true);
}

function wt_filter_is_image_type($type) {
	return in_array((string)$type, array('checkbox_image', 'radio_image', 'image_checkbox', 'image_radio'), true);
}

function wt_filter_parse_numeric($text) {
	$text = trim((string)$text);

	if ($text === '') {
		return null;
	}

	$normalized = preg_replace('/\s+/u', '', $text);
	$normalized = str_replace(',', '.', (string)$normalized);

	if (!preg_match('/-?\d+(?:\.\d+)?/', $normalized, $m)) {
		return null;
	}

	return (float)$m[0];
}

function wt_filter_decimal_scale($number) {
	if ($number === null || $number === '') {
		return 0;
	}

	$s = trim(str_replace(',', '.', (string)$number));

	if (!is_numeric($s)) {
		return 0;
	}

	if (strpos($s, '.') !== false) {
		$s = rtrim(rtrim($s, '0'), '.');
	}

	$pos = strpos($s, '.');

	if ($pos === false) {
		return 0;
	}

	return strlen(substr($s, $pos + 1));
}

function wt_filter_scale_from_values(array $numbers) {
	$scale = 0;

	foreach ($numbers as $number) {
		if ($number === null || $number === '') {
			continue;
		}

		$scale = max($scale, wt_filter_decimal_scale($number));
	}

	return $scale;
}

function wt_filter_step_from_scale($scale) {
	$scale = max(0, (int)$scale);

	if ($scale === 0) {
		return 1;
	}

	return (float)('0.' . str_repeat('0', $scale - 1) . '1');
}

function wt_filter_format_number($number, $scale) {
	$scale = max(0, (int)$scale);
	$number = (float)$number;

	if ($scale === 0) {
		return (string)(int)round($number);
	}

	return number_format($number, $scale, '.', '');
}
