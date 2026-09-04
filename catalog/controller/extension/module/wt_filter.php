<?php
/**
 * Витринный модуль WT Filter (OpenCart 3).
 * UI фильтра + AJAX refresh листинга без перезагрузки страницы.
 */
class ControllerExtensionModuleWtFilter extends Controller {
	/** @var array */
	private $filter_state = [];

	/**
	 * Event: catalog/controller/startup/seo_url.index/before
	 * Ранний bootstrap: registry + SEO-сегмент фильтра из _route_.
	 */
	public function eventInitialise() {
		$this->initialise();
	}

	public function initialise() {
		if ($this->registry->get('wt_filter')) {
			return;
		}

		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');
		$this->load->model('catalog/wt_filter');

		$url_key = $this->config->get('wt_filter_url_index');
		$params = [];

		// Path ЧПУ: /desktops/pc/peremikach:malenkiy/
		if (!empty($this->request->get['_route_'])) {
			$parts = explode('/', (string)$this->request->get['_route_']);

			if ($parts && utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			if ($parts) {
				$last = (string)end($parts);

				if ($this->model_catalog_wt_filter->isSeoFilterSegment($last)) {
					$params = $this->model_catalog_wt_filter->decodeParams($last);
					array_pop($parts);
					$this->request->get['_route_'] = implode('/', $parts);
				}
			}
		}

		// Query: ?filter_wt_filter=… (id или keyword; цена/остаток)
		if (isset($this->request->get[$url_key]) && $this->request->get[$url_key] !== '') {
			$query_params = $this->model_catalog_wt_filter->decodeParams($this->request->get[$url_key]);

			foreach ($query_params as $option_id => $values) {
				if (!isset($params[$option_id])) {
					$params[$option_id] = $values;
				} else {
					$params[$option_id] = array_values(array_unique(array_merge($params[$option_id], $values)));
					sort($params[$option_id]);
				}
			}
		}

		if ($params) {
			$this->request->get[$url_key] = wt_filter_encode_params($params, $this->config);
		} elseif (isset($this->request->get[$url_key])) {
			unset($this->request->get[$url_key]);
		}

		$this->registry->set('wt_filter', $this);

		// OC4: регистрируем rewrite здесь (без OCMOD на seo_url)
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}
	}

	/**
	 * URL rewrite: filter_wt_filter → path-сегмент option:value;…
	 * Структура: [host]/[ЧПУ категории]/[ЧПУ фильтра]/[остальные GET]
	 * Параметр остатка s (s:in) в публичный URL не попадает.
	 */
	public function rewrite($link) {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		if (empty($url_info['query'])) {
			return $link;
		}

		$data = [];
		parse_str($url_info['query'], $data);

		$this->load->config('wt_filter');
		$url_key = $this->config->get('wt_filter_url_index');

		if (empty($data[$url_key])) {
			return $link;
		}

		$this->load->model('catalog/wt_filter');
		$this->load->helper('wt_filter');

		$params = $this->model_catalog_wt_filter->decodeParams($data[$url_key]);

		if (!$params) {
			return $link;
		}

		// s:in / s:out — служебный флаг, не для ЧПУ
		unset($params['s']);

		list($seo_segment, $query_params) = $this->model_catalog_wt_filter->splitSeoParams($params);

		// p и диапазоны — в сегмент пути; s сюда уже не попадёт
		if ($query_params) {
			unset($query_params['s']);

			$tail = wt_filter_encode_params($query_params, $this->config);

			if ($tail !== '') {
				$seo_segment = $seo_segment !== '' ? ($seo_segment . ';' . $tail) : $tail;
			}
		}

		// На всякий случай вычистить s:in / s:out из сегмента
		$seo_segment = preg_replace('/(;)?s:(in|out)\b/', '', (string)$seo_segment);
		$seo_segment = trim((string)$seo_segment, ';');

		// Убрать хвост фильтра из path, если SeoUrl/прошлый rewrite уже его дописал
		$base_path = isset($url_info['path']) ? str_replace('/index.php', '', $url_info['path']) : '';
		$base_path = rtrim($base_path, '/');

		if ($base_path !== '') {
			$parts = explode('/', trim($base_path, '/'));

			if ($parts && $this->model_catalog_wt_filter->isSeoFilterSegment(end($parts))) {
				array_pop($parts);
				$base_path = $parts ? '/' . implode('/', $parts) : '';
			}
		}

		// path категории / manufacturer_id: из query link, иначе из текущего запроса
		$path_value = '';
		$manufacturer_id = 0;

		if (!empty($data['path'])) {
			$path_value = (string)$data['path'];
		} elseif (!empty($this->request->get['path'])) {
			$path_value = (string)$this->request->get['path'];
		}

		if (!empty($data['manufacturer_id'])) {
			$manufacturer_id = (int)$data['manufacturer_id'];
		} elseif (!empty($this->request->get['manufacturer_id'])) {
			$manufacturer_id = (int)$this->request->get['manufacturer_id'];
		}

		$is_special = (!empty($data['route']) && $data['route'] === 'product/special')
			|| (isset($this->request->get['route']) && $this->request->get['route'] === 'product/special');

		// Надёжно: ЧПУ базы через обычный link БЕЗ фильтра (SeoUrl/SeoPro)
		$category_seo = '';

		if ($path_value !== '') {
			$category_url = str_replace('&amp;', '&', $this->url->link('product/category', 'path=' . $path_value));
			$cat_info = parse_url($category_url);
			$cat_path = isset($cat_info['path']) ? (string)$cat_info['path'] : '';

			if (strpos($cat_path, 'index.php') === false) {
				$category_seo = rtrim(str_replace('/index.php', '', $cat_path), '/');
			}

			if ($category_seo === '' || $category_seo === '/') {
				$category_seo = $this->buildCategorySeoPath($path_value);
			}
		} elseif ($manufacturer_id > 0) {
			$m_url = str_replace('&amp;', '&', $this->url->link('product/manufacturer.info', 'manufacturer_id=' . $manufacturer_id));
			$m_info = parse_url($m_url);
			$m_path = isset($m_info['path']) ? (string)$m_info['path'] : '';

			if (strpos($m_path, 'index.php') === false) {
				$category_seo = rtrim(str_replace('/index.php', '', $m_path), '/');
			}
		} elseif ($is_special) {
			$s_url = str_replace('&amp;', '&', $this->url->link('product/special', ''));
			$s_info = parse_url($s_url);
			$s_path = isset($s_info['path']) ? (string)$s_info['path'] : '';

			if (strpos($s_path, 'index.php') === false) {
				$category_seo = rtrim(str_replace('/index.php', '', $s_path), '/');
			}
		} elseif ($base_path !== '' && strpos($base_path, 'index.php') === false) {
			$category_seo = $base_path;
		}

		// Нет ЧПУ — оставляем route + path/manufacturer_id + filter в query
		if ($category_seo === '' || $category_seo === '/') {
			unset($data[$url_key], $data['s']);

			if ($path_value !== '') {
				$data['route'] = 'product/category';
				$data['path'] = $path_value;
				unset($data['manufacturer_id']);
			} elseif ($manufacturer_id > 0) {
				$data['route'] = 'product/manufacturer.info';
				$data['manufacturer_id'] = $manufacturer_id;
				unset($data['path']);
			} elseif ($is_special) {
				$data['route'] = 'product/special';
				unset($data['path'], $data['manufacturer_id']);
			}

			if ($seo_segment !== '') {
				$data[$url_key] = $seo_segment;
			}

			$rewrite = $url_info['scheme'] . '://' . $url_info['host'];

			if (isset($url_info['port'])) {
				$rewrite .= ':' . $url_info['port'];
			}

			$rewrite .= '/index.php';
			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode(is_array($value) ? http_build_query($value) : (string)$value);
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $rewrite . $query;
		}

		// Технические ключи никогда не попадают в GET
		unset($data['path'], $data['manufacturer_id'], $data['route'], $data[$url_key], $data['s']);

		$path = $category_seo;

		if ($seo_segment !== '') {
			$path = rtrim($path, '/') . '/' . $seo_segment;
		}

		$path = rtrim($path, '/') . '/';

		$rewrite = $url_info['scheme'] . '://' . $url_info['host'];

		if (isset($url_info['port'])) {
			$rewrite .= ':' . $url_info['port'];
		}

		$rewrite .= $path;

		$query = '';

		if ($data) {
			foreach ($data as $key => $value) {
				$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode(is_array($value) ? http_build_query($value) : (string)$value);
			}

			if ($query) {
				$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
			}
		}

		return $rewrite . $query;
	}

	/**
	 * path=4_8 → /obuv или /obuv/muzhskaya.
	 * Категории без keyword пропускаются (иначе родитель без ЧПУ ломал весь путь).
	 */
	private function buildCategorySeoPath($path) {
		$keywords = [];

		foreach (explode('_', (string)$path) as $category_id) {
			$category_id = (int)$category_id;

			if ($category_id < 1) {
				continue;
			}

			$keyword = $this->getCategorySeoKeyword($category_id);

			if ($keyword === '') {
				continue;
			}

			$keywords[] = rawurlencode($keyword);
		}

		// если у предков нет keyword — берём хотя бы последний id из path
		if (!$keywords) {
			$parts = array_filter(array_map('intval', explode('_', (string)$path)));
			$leaf = $parts ? (int)end($parts) : 0;

			if ($leaf > 0) {
				$keyword = $this->getCategorySeoKeyword($leaf);

				if ($keyword !== '') {
					$keywords[] = rawurlencode($keyword);
				}
			}
		}

		return $keywords ? '/' . implode('/', $keywords) : '';
	}

	private function getCategorySeoKeyword($category_id) {
		$category_id = (int)$category_id;
		$store_id = (int)$this->config->get('config_store_id');
		$language_id = (int)$this->config->get('config_language_id');
		$query = 'category_id=' . $category_id;

		// 1) точное совпадение store + language
		// 2) store=0 + language
		// 3) любой store/language для этой категории
		$sql = "SELECT keyword FROM `" . DB_PREFIX . "seo_url`
			WHERE `query` = '" . $this->db->escape($query) . "'
				AND keyword <> ''
			ORDER BY
				(store_id = '" . $store_id . "') DESC,
				(store_id = '0') DESC,
				(language_id = '" . $language_id . "') DESC,
				language_id ASC
			LIMIT 1";

		$result = $this->db->query($sql);

		if ($result->num_rows && $result->row['keyword'] !== '') {
			return (string)$result->row['keyword'];
		}

		// OC2 fallback
		$check = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "url_alias'");

		if ($check->num_rows) {
			$result = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "url_alias`
				WHERE `query` = '" . $this->db->escape($query) . "' AND keyword <> '' LIMIT 1");

			if ($result->num_rows && $result->row['keyword'] !== '') {
				return (string)$result->row['keyword'];
			}
		}

		return '';
	}

	/**
	 * Вывод модуля в layout категории, производителя или страницы акций.
	 */
	public function index() {
		if (!$this->config->get('module_wt_filter_status')) {
			return '';
		}

		$this->initialise();

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';
		$category_id = 0;
		$manufacturer_id = 0;
		$special = false;
		$path = '';

		// Категория: path обязателен; route может отсутствовать на части SEO-сборок
		if (!empty($this->request->get['path']) && ($route === '' || $route === 'product/category')) {
			$parts = explode('_', (string)$this->request->get['path']);
			$category_id = (int)end($parts);
			$path = (string)$this->request->get['path'];
		} elseif (!empty($this->request->get['manufacturer_id']) && ($route === '' || $route === 'product/manufacturer.info')) {
			$manufacturer_id = (int)$this->request->get['manufacturer_id'];
		} elseif ($route === 'product/special') {
			$special = true;
		} else {
			return '';
		}

		if ($category_id < 1 && $manufacturer_id < 1 && !$special) {
			return '';
		}

		$this->load->language('extension/module/wt_filter');
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');
		$this->load->model('catalog/wt_filter');

		$url_key = $this->config->get('wt_filter_url_index');
		$params_string = isset($this->request->get[$url_key]) ? (string)$this->request->get[$url_key] : '';
		$params = $this->model_catalog_wt_filter->decodeParams($params_string);

		if ($manufacturer_id > 0) {
			unset($params['m']);
		}

		if ($special) {
			unset($params['d']);
		}

		$show_counts = $this->showCounts();
		$counters = $show_counts
			? $this->model_catalog_wt_filter->getCounters($category_id, $params, $manufacturer_id, $special)
			: [];

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_price'] = $this->language->get('text_price');
		$data['text_manufacturer'] = $this->language->get('text_manufacturer');
		$data['text_stock'] = $this->language->get('text_stock');
		$data['text_in_stock'] = $this->language->get('text_in_stock');
		$data['text_out_of_stock'] = $this->language->get('text_out_of_stock');
		$data['text_discount'] = $this->language->get('text_discount');
		$data['text_discount_yes'] = $this->language->get('text_discount_yes');
		$data['text_reset'] = $this->language->get('text_reset');
		$data['text_loading'] = $this->language->get('text_loading');
		$data['text_all'] = $this->language->get('text_all');
		$data['text_empty_filter'] = $this->language->get('text_empty_filter');
		$data['text_show_total'] = $this->language->get('text_show_total');
		$data['text_show_more'] = $this->language->get('text_show_more');
		$data['text_show_less'] = $this->language->get('text_show_less');
		$data['text_search_manufacturer'] = $this->language->get('text_search_manufacturer');
		$data['text_search_manufacturer_empty'] = $this->language->get('text_search_manufacturer_empty');
		$data['text_filter_short'] = $this->language->get('text_filter_short');
		$data['text_filters_title'] = $this->language->get('text_filters_title');
		$data['text_popular_filters'] = $this->language->get('text_popular_filters');
		$data['text_cancel'] = $this->language->get('text_cancel');
		$data['text_apply_filter'] = $this->language->get('text_apply_filter');
		$data['button_filter'] = $this->language->get('button_filter');

		$data['category_id'] = $category_id;
		$data['manufacturer_id'] = $manufacturer_id;
		$data['path'] = $path;
		$data['url_key'] = $url_key;
		$data['params'] = wt_filter_encode_params($params, $this->config);
		$data['callback'] = $this->url->link('extension/module/wt_filter.callback', '');
		$data['refresh'] = $this->url->link('extension/module/wt_filter.refresh', '');
		$data['search_manufacturers'] = $this->url->link('extension/module/wt_filter.searchManufacturers', '');
		$seo_pos = $this->config->get('module_wt_filter_seo_text_position');
		$data['seo_text_position'] = ($seo_pos === 'above') ? 'above' : 'below';
		$data['show_counts'] = $show_counts;
		$data['special'] = $special ? 1 : 0;
		$data['accent_color'] = $this->accentColor();
		$data['accent_rgb'] = $this->accentRgb($data['accent_color']);
		$data['group_expanded'] = $this->groupExpanded();

		$code = $this->session->data['currency'];
		$symbol_left = $this->currency->getSymbolLeft($code);
		$symbol_right = $this->currency->getSymbolRight($code);
		$data['currency_symbol'] = $symbol_left !== '' ? $symbol_left : ($symbol_right !== '' ? $symbol_right : '');

		if ($special) {
			$data['category_url'] = $this->url->link('product/special', '');
			$data['href'] = $this->model_catalog_wt_filter->buildUrl('', $params, 0, true);
			$data['total'] = $this->model_catalog_wt_filter->getTotalProducts(0, $params, 0, true);
			$data['options'] = $this->buildOptions(0, $params, $counters, 0, true);
			$data['price'] = $this->buildPrice(0, $params, 0, true);
			$data['manufacturers'] = $this->buildManufacturers(0, $params, $counters, true);
			$data['popular_filters'] = [];
			$data['discount'] = [];
		} elseif ($manufacturer_id > 0) {
			$data['category_url'] = $this->url->link('product/manufacturer.info', 'manufacturer_id=' . $manufacturer_id);
			$data['href'] = $this->model_catalog_wt_filter->buildUrl('', $params, $manufacturer_id);
			$data['total'] = $this->model_catalog_wt_filter->getTotalProducts(0, $params, $manufacturer_id);
			$data['options'] = $this->buildOptions(0, $params, $counters, $manufacturer_id);
			$data['price'] = $this->buildPrice(0, $params, $manufacturer_id);
			$data['manufacturers'] = [];
			$data['popular_filters'] = [];
			$data['discount'] = $this->buildDiscount($params, $counters);
		} else {
			$data['category_url'] = $this->url->link('product/category', 'path=' . $path);
			$data['href'] = $this->model_catalog_wt_filter->buildUrl($path, $params);
			$data['total'] = $this->model_catalog_wt_filter->getTotalProducts($category_id, $params);
			$data['options'] = $this->buildOptions($category_id, $params, $counters);
			$data['price'] = $this->buildPrice($category_id, $params);
			$data['manufacturers'] = $this->buildManufacturers($category_id, $params, $counters);
			$data['popular_filters'] = $this->model_catalog_wt_filter->getPopularSeoLinks($category_id, 8);
			$data['discount'] = $this->buildDiscount($params, $counters);
		}

		$data['stock'] = $this->buildStock($params, $counters);

		$wt_css = 'catalog/view/theme/default/stylesheet/wt_filter/wt_filter.css';
		$wt_js = 'catalog/view/javascript/wt_filter/wt_filter.js';
		$wt_root = rtrim(dirname(DIR_APPLICATION), '/\\') . '/';
		$wt_ver = max(
			is_file($wt_root . $wt_css) ? (int)filemtime($wt_root . $wt_css) : 0,
			is_file($wt_root . $wt_js) ? (int)filemtime($wt_root . $wt_js) : 0,
			1
		);

		$this->document->addStyle($wt_css . '?v=' . $wt_ver);
		$this->document->addScript($wt_js . '?v=' . $wt_ver);

		return $this->load->view('extension/module/wt_filter', $data);
	}

	/**
	 * AJAX: поиск производителей в категории (подсказки после 2 символов).
	 */
	public function searchManufacturers() {
		$this->load->language('extension/module/wt_filter');
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');
		$this->load->model('catalog/wt_filter');

		$json = ['success' => false, 'items' => []];

		$path = $this->requestParam('path');
		$parts = explode('_', $path);
		$category_id = (int)end($parts);
		$q = trim((string)$this->requestParam('q'));
		$special = ((int)$this->requestParam('special', 0) === 1);

		if ($category_id < 1 && !$special) {
			$json['error'] = $this->language->get('error_category');
			$this->jsonOut($json);
			return;
		}

		if (mb_strlen($q) < 2) {
			$json['success'] = true;
			$this->jsonOut($json);
			return;
		}

		$url_key = $this->config->get('wt_filter_url_index');
		$params_string = $this->requestParam($url_key);
		$params = $this->model_catalog_wt_filter->decodeParams($params_string);
		unset($params['m']);

		if ($special) {
			unset($params['d']);
		}

		$show_counts = $this->showCounts();
		$counters = $show_counts
			? $this->model_catalog_wt_filter->getCounters($category_id, $params, 0, $special)
			: [];
		$q_lower = mb_strtolower($q);
		$items = [];

		$manufacturers = $special
			? $this->model_catalog_wt_filter->getManufacturersBySpecial()
			: $this->model_catalog_wt_filter->getManufacturersByCategoryId($category_id);

		foreach ($manufacturers as $row) {
			$name = (string)$row['name'];

			if (mb_strpos(mb_strtolower($name), $q_lower) === false) {
				continue;
			}

			$key = 'm' . $row['value_id'];
			$count = isset($counters[$key]) ? (int)$counters[$key] : 0;

			if ($show_counts && $count < 1) {
				continue;
			}

			$items[] = [
				'value_id' => (string)$row['value_id'],
				'name'     => $name,
				'count'    => $show_counts ? $count : 0,
			];
		}

		if ($show_counts) {
			usort($items, static function ($a, $b) {
				$cmp = (int)$b['count'] - (int)$a['count'];

				if ($cmp !== 0) {
					return $cmp;
				}

				return strcasecmp((string)$a['name'], (string)$b['name']);
			});
		} else {
			usort($items, static function ($a, $b) {
				return strcasecmp((string)$a['name'], (string)$b['name']);
			});
		}

		$json['success'] = true;
		$json['show_counts'] = $show_counts;
		$json['items'] = array_slice($items, 0, 15);

		$this->jsonOut($json);
	}

	/**
	 * Лёгкий AJAX: только счётчики + total + href (без HTML товаров).
	 */
	public function callback() {
		$this->load->language('extension/module/wt_filter');
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');
		$this->load->model('catalog/wt_filter');

		$json = ['success' => false];
		$path = $this->requestParam('path');
		$manufacturer_id = (int)$this->requestParam('manufacturer_id', 0);
		$special = ((int)$this->requestParam('special', 0) === 1);
		$parts = explode('_', $path);
		$category_id = (int)end($parts);

		if ($category_id < 1 && $manufacturer_id < 1 && !$special) {
			$json['error'] = $this->language->get('error_category');
			$this->jsonOut($json);
			return;
		}

		$url_key = $this->config->get('wt_filter_url_index');
		$params_string = $this->requestParam($url_key);
		$params = $this->model_catalog_wt_filter->decodeParams($params_string);

		if ($manufacturer_id > 0) {
			unset($params['m']);
		}

		if ($special) {
			unset($params['d']);
		}

		$params_string = wt_filter_encode_params($params, $this->config);

		$show_counts = $this->showCounts();
		$counters = $show_counts
			? $this->model_catalog_wt_filter->getCounters($category_id, $params, $manufacturer_id, $special)
			: [];
		$total = $this->model_catalog_wt_filter->getTotalProducts($category_id, $params, $manufacturer_id, $special);
		$href = $this->model_catalog_wt_filter->buildUrl($path, $params, $manufacturer_id, $special);

		$seo_page = $special ? null : $this->model_catalog_wt_filter->getSeoPageByFilter($category_id, $params, $manufacturer_id);

		$json = [
			'success'     => true,
			'total'       => $total,
			'text_total'  => sprintf($this->language->get('text_show_total'), $total),
			'href'        => $href,
			'params'      => $params_string,
			'values'      => $show_counts ? $this->model_catalog_wt_filter->countersToValuesMap($counters) : null,
			'show_counts' => $show_counts,
			'seo'         => $this->model_catalog_wt_filter->formatSeoPagePayload($seo_page),
		];

		$this->jsonOut($json);
	}

	/**
	 * AJAX: HTML товаров + пагинация + URL (без reload).
	 */
	public function refresh() {
		$this->load->language('extension/module/wt_filter');
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');
		$this->load->model('catalog/wt_filter');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$json = ['success' => false];

		$path = $this->requestParam('path');
		$manufacturer_id = (int)$this->requestParam('manufacturer_id', 0);
		$special = ((int)$this->requestParam('special', 0) === 1);
		$parts = explode('_', $path);
		$category_id = (int)end($parts);

		if ($category_id < 1 && $manufacturer_id < 1 && !$special) {
			$json['error'] = $this->language->get('error_category');
			$this->jsonOut($json);
			return;
		}

		$url_key = $this->config->get('wt_filter_url_index');
		$params_string = $this->requestParam($url_key);
		$params = $this->model_catalog_wt_filter->decodeParams($params_string);

		if ($manufacturer_id > 0) {
			unset($params['m']);
		}

		if ($special) {
			unset($params['d']);
		}

		$params_string = wt_filter_encode_params($params, $this->config);

		$sort = $this->requestParam('sort', 'p.sort_order');
		$order = $this->requestParam('order', 'ASC');
		$page = (int)$this->requestParam('page', 1);
		$limit = (int)$this->requestParam('limit', (int)$this->config->get('config_pagination'));

		if ($page < 1) {
			$page = 1;
		}

		if ($limit < 1) {
			$limit = 15;
		}

		$image_width = (int)$this->config->get('config_image_product_width') ?: 250;
		$image_height = (int)$this->config->get('config_image_product_height') ?: 250;
		$description_length = (int)$this->config->get('config_product_description_length') ?: 100;

		$filter_data = [
			'filter_wt_filter' => $params_string,
			'sort'             => $sort,
			'order'            => $order,
			'start'            => ($page - 1) * $limit,
			'limit'            => $limit,
		];

		if ($special) {
			$product_total = $this->model_catalog_product->getTotalSpecials($filter_data);
			$results = $this->model_catalog_product->getSpecials($filter_data);
		} else {
			if ($manufacturer_id > 0) {
				$filter_data['filter_manufacturer_id'] = $manufacturer_id;
			} else {
				$filter_data['filter_category_id'] = $category_id;
				$filter_data['filter_sub_category'] = true;
			}

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
			$results = $this->model_catalog_product->getProducts($filter_data);
		}

		$show_counts = $this->showCounts();
		$counters = $show_counts
			? $this->model_catalog_wt_filter->getCounters($category_id, $params, $manufacturer_id, $special)
			: [];

		$products = [];

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], (int)$image_width, (int)$image_height);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', (int)$image_width, (int)$image_height);
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			if (!is_null($result['special']) && (float)$result['special'] >= 0) {
				$special_price = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$result['special'];
			} else {
				$special_price = false;
				$tax_price = (float)$result['price'];
			}

			if ($this->config->get('config_tax')) {
				$tax = $this->currency->format($tax_price, $this->session->data['currency']);
			} else {
				$tax = false;
			}

			if ($special) {
				$product_href = $this->url->link('product/product', 'product_id=' . (int)$result['product_id'], true);
			} else {
				$extra = ($manufacturer_id > 0) ? ('manufacturer_id=' . (int)$manufacturer_id) : ('path=' . $path);
				$product_href = $this->url->link('product/product', $extra . '&product_id=' . (int)$result['product_id'], true);
			}

			$products[] = array(
				'product_id'  => $result['product_id'],
				'thumb'       => $image,
				'name'        => $result['name'],
				'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $description_length) . '..',
				'price'       => $price,
				'special'     => $special_price,
				'tax'         => $tax,
				'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
				'rating'      => $this->config->get('config_review_status') ? (int)$result['rating'] : false,
				'href'        => $product_href,
			);
		}

		if ($special) {
			$url = '';
		} elseif ($manufacturer_id > 0) {
			$url = 'manufacturer_id=' . $manufacturer_id;
		} else {
			$url = 'path=' . $path;
		}

		if ($params_string !== '') {
			$url .= ($url !== '' ? '&' : '') . $url_key . '=' . rawurlencode($params_string);
		}

		if ($sort !== 'p.sort_order') {
			$url .= ($url !== '' ? '&' : '') . 'sort=' . $sort;
		}

		if ($order !== 'ASC') {
			$url .= ($url !== '' ? '&' : '') . 'order=' . $order;
		}

		if ($limit != (int)$this->config->get('config_pagination')) {
			$url .= ($url !== '' ? '&' : '') . 'limit=' . $limit;
		}

		$route_list = $special ? 'product/special' : (($manufacturer_id > 0) ? 'product/manufacturer.info' : 'product/category');
		$pagination_html = $this->load->controller('common/pagination', [
			'total' => $product_total,
			'page'  => $page,
			'limit' => $limit,
			'url'   => $this->url->link($route_list, 'language=' . $this->config->get('config_language') . '&' . $url . '&page={page}')
		]);

		$text_show_total = $this->language->get('text_show_total');

		$this->load->language('product/category');

		$view_data = [
			'products'     => $products,
			'pagination'   => $pagination_html,
			'results'      => sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit)),
			'text_empty'   => $this->language->get('text_empty'),
			'text_tax'     => $this->language->get('text_tax'),
			'button_cart'  => $this->language->get('button_cart'),
			'button_wishlist' => $this->language->get('button_wishlist'),
			'button_compare'  => $this->language->get('button_compare'),
		];

		$href = $this->model_catalog_wt_filter->buildUrl($path, $params, $manufacturer_id, $special);

		if ($page > 1) {
			$href .= (strpos($href, '?') === false ? '?' : '&') . 'page=' . $page;
		}

		$seo_page = $special ? null : $this->model_catalog_wt_filter->getSeoPageByFilter($category_id, $params, $manufacturer_id);

		$json = [
			'success'     => true,
			'total'       => (int)$product_total,
			'text_total'  => sprintf($text_show_total, (int)$product_total),
			'params'      => $params_string,
			'href'        => $href,
			'url'         => $href,
			'values'      => $show_counts ? $this->model_catalog_wt_filter->countersToValuesMap($counters) : null,
			'show_counts' => $show_counts,
			'products'    => $this->load->view('extension/module/wt_filter_products', $view_data),
			'pagination'  => $view_data['pagination'],
			'results'     => $view_data['results'],
			'seo'         => $this->model_catalog_wt_filter->formatSeoPagePayload($seo_page),
		];

		$this->jsonOut($json);
	}

	/**
	 * Показывать ли (N) у значений и выполнять getCounters().
	 * Выключено в настройках модуля → COUNT не считаем.
	 */
	private function showCounts() {
		$v = $this->config->get('module_wt_filter_show_counts');

		if ($v === null || $v === false || $v === '') {
			return true;
		}

		return (bool)(int)$v;
	}

	/**
	 * Состояние по умолчанию для служебных групп (цена / производитель / наличие / акции).
	 */
	private function groupExpanded() {
		$defaults = [
			'p' => ['desktop' => 1, 'mobile' => 0],
			'm' => ['desktop' => 1, 'mobile' => 0],
			's' => ['desktop' => 1, 'mobile' => 0],
			'd' => ['desktop' => 1, 'mobile' => 0],
		];
		$raw = $this->config->get('module_wt_filter_group_expanded');

		if (!is_array($raw)) {
			return $defaults;
		}

		foreach ($defaults as $key => $row) {
			if (!isset($raw[$key]) || !is_array($raw[$key])) {
				continue;
			}

			$defaults[$key] = [
				'desktop' => !empty($raw[$key]['desktop']) ? 1 : 0,
				'mobile'  => !empty($raw[$key]['mobile']) ? 1 : 0,
			];
		}

		return $defaults;
	}

	/**
	 * HEX акцента витрины из настроек модуля.
	 */
	private function accentColor() {
		return $this->normalizeAccentColor($this->config->get('module_wt_filter_accent_color'));
	}

	/**
	 * "R, G, B" для rgba(var(--wt-accent-rgb), a).
	 */
	private function accentRgb($hex) {
		$hex = ltrim((string)$hex, '#');

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
			return '34, 154, 200';
		}

		return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
	}

	private function normalizeValueColor($color) {
		$color = trim((string)$color);

		if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m)) {
			return '';
		}

		$hex = strtolower($m[1]);

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}

	private function normalizeAccentColor($color) {
		$color = trim((string)$color);

		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
			$hex = substr($color, 1);
		} elseif (preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
			$hex = $color;
		} else {
			return '#229ac8';
		}

		$hex = strtolower($hex);

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}

	private function requestParam($key, $default = '') {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}

		if (isset($this->request->get[$key])) {
			return $this->request->get[$key];
		}

		return $default;
	}

	private function jsonOut(array $json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function buildOptions($category_id, array $params, array $counters, $manufacturer_id = 0, $special = false) {
		$options = [];

		if ($special) {
			$rows = $this->model_catalog_wt_filter->getOptionsBySpecial();
		} elseif ($manufacturer_id > 0) {
			$rows = $this->model_catalog_wt_filter->getOptionsByManufacturerId($manufacturer_id);
		} else {
			$rows = $this->model_catalog_wt_filter->getOptionsByCategoryId($category_id);
		}
		$this->load->model('tool/image');

		foreach ($rows as $option) {
			$type = $option['type'] ?: 'checkbox';

			if (wt_filter_is_slider_type($type)) {
				$selected = isset($params[$option['option_id']]) ? $params[$option['option_id']][0] : '';
				$min = (float)$option['slide_value_min'];
				$max = (float)$option['slide_value_max'];
				$from = $min;
				$to = $max;
				$scale = isset($option['slide_scale']) ? (int)$option['slide_scale'] : wt_filter_scale_from_values([$min, $max]);
				$step = wt_filter_step_from_scale($scale);

				if ($selected && wt_filter_is_range($selected)) {
					$range = wt_filter_range_parts($selected);
					if ($range) {
						$from = (float)$range['from'];
						$to = (float)$range['to'];
					}
				} elseif ($selected !== '' && is_numeric($selected) && ($type === 'slider_single' || $type === 'slide')) {
					// single: порог «до выбранного»
					$from = $min;
					$to = (float)$selected;
				}

				// Слайдер без диапазона (часто «Цвет» с картинками) — показываем как значения
				if ($max <= $min) {
					$type = !empty($option['image']) ? 'checkbox_image' : 'checkbox';
				} else {
					$options[] = [
						'option_id' => $option['option_id'],
						'name'      => $option['name'],
						'type'      => ($type === 'slide') ? 'slider_single' : (($type === 'slide_dual') ? 'slider_range' : $type),
						'min'       => wt_filter_format_number($min, $scale),
						'max'       => wt_filter_format_number($max, $scale),
						'from'      => wt_filter_format_number($from, $scale),
						'to'        => wt_filter_format_number($to, $scale),
						'step'      => $step,
						'scale'     => $scale,
						'values'    => [],
						'expanded_desktop' => isset($option['expanded_desktop']) ? (int)$option['expanded_desktop'] : 1,
						'expanded_mobile'  => isset($option['expanded_mobile']) ? (int)$option['expanded_mobile'] : 0,
					];
					continue;
				}
			}

			$values = [];
			$has_value_images = false;
			$has_value_colors = false;
			$show_counts = $this->showCounts();
			// Важно: в $counters почти всегда есть sin/sout/m* — из‑за этого НЕЛЬЗЯ
			// считать «счётчики опций есть» через !empty($counters). Иначе все
			// значения опций без своего ключа скрываются (остаётся только цена).

			foreach ($option['values'] as $value) {
				$key = (string)$option['option_id'] . (string)$value['value_id'];
				$has_value_counter = array_key_exists($key, $counters);
				$count = $has_value_counter ? (int)$counters[$key] : 0;
				$selected = !empty($params[$option['option_id']]) && in_array((string)$value['value_id'], array_map('strval', $params[$option['option_id']]));

				if ($show_counts && $has_value_counter && !$selected && $count < 1) {
					continue;
				}

				$image = !empty($value['image']) ? (string)$value['image'] : '';
				$thumb = '';
				$color = $this->normalizeValueColor(!empty($value['color']) ? $value['color'] : '');

				if ($color !== '') {
					$has_value_colors = true;
				}

				// Плашки/фото значений — всегда, если файл есть (checkbox и radio тоже)
				if ($image && is_file(DIR_IMAGE . $image)) {
					$thumb = $this->model_tool_image->resize($image, 40, 40);
					$has_value_images = true;
				}

				$values[] = [
					'value_id' => (string)$value['value_id'],
					'name'     => $value['name'],
					'count'    => ($show_counts && $has_value_counter) ? $count : 0,
					'selected' => $selected,
					'disabled' => ($show_counts && $has_value_counter && !$selected && $count < 1),
					'color'    => $color,
					'image'    => $image,
					'thumb'    => $thumb,
				];
			}

			if (!$values) {
				continue;
			}

			$any_value_counts = false;

			foreach ($values as $v) {
				if ((int)$v['count'] > 0) {
					$any_value_counts = true;
					break;
				}
			}

			if ($show_counts && $any_value_counts) {
				usort($values, static function ($a, $b) {
					$cmp = (int)$b['count'] - (int)$a['count'];

					if ($cmp !== 0) {
						return $cmp;
					}

					return strcasecmp((string)$a['name'], (string)$b['name']);
				});
			} else {
				usort($values, static function ($a, $b) {
					return strcasecmp((string)$a['name'], (string)$b['name']);
				});
			}

			// checkbox/radio с картинками или HEX-цветом → UI плашек
			$render_type = $type;
			$as_swatches = $has_value_images || $has_value_colors || !empty($option['color']);

			if ($as_swatches && $type === 'checkbox') {
				$render_type = 'checkbox_image';
			} elseif ($as_swatches && $type === 'radio') {
				$render_type = 'radio_image';
			}

			$options[] = [
				'option_id' => $option['option_id'],
				'name'      => $option['name'],
				'type'      => $render_type,
				'values'    => $values,
				'expanded_desktop' => isset($option['expanded_desktop']) ? (int)$option['expanded_desktop'] : 1,
				'expanded_mobile'  => isset($option['expanded_mobile']) ? (int)$option['expanded_mobile'] : 0,
			];
		}

		return $options;
	}

	private function buildPrice($category_id, array $params, $manufacturer_id = 0, $special = false) {
		$limits = $this->model_catalog_wt_filter->getPriceLimits($category_id, $params, $manufacturer_id, $special);
		$currency = $this->session->data['currency'];
		$value = (float)$this->currency->getValue($currency);

		if ($value <= 0) {
			$value = 1;
		}

		$min = floor($limits['min'] * $value);
		$max = ceil($limits['max'] * $value);
		$from = $min;
		$to = $max;

		if (!empty($params['p'][0]) && wt_filter_is_range($params['p'][0])) {
			$range = wt_filter_range_parts($params['p'][0]);
			if ($range) {
				$from = (float)$range['from'];
				$to = (float)$range['to'];
			}
		}

		return [
			'min'  => $min,
			'max'  => $max,
			'from' => $from,
			'to'   => $to,
		];
	}

	private function buildManufacturers($category_id, array $params, array $counters, $special = false) {
		$items = [];
		$show_counts = $this->showCounts();

		$rows = $special
			? $this->model_catalog_wt_filter->getManufacturersBySpecial()
			: $this->model_catalog_wt_filter->getManufacturersByCategoryId($category_id);

		foreach ($rows as $row) {
			$key = 'm' . $row['value_id'];
			$has_value_counter = array_key_exists($key, $counters);
			$count = $has_value_counter ? (int)$counters[$key] : 0;
			$selected = !empty($params['m']) && in_array((string)$row['value_id'], array_map('strval', $params['m']));

			if ($show_counts && $has_value_counter && !$selected && $count < 1) {
				continue;
			}

			$items[] = [
				'value_id' => (string)$row['value_id'],
				'name'     => $row['name'],
				'count'    => ($show_counts && $has_value_counter) ? $count : 0,
				'selected' => $selected,
				'disabled' => ($show_counts && $has_value_counter && !$selected && $count < 1),
			];
		}

		$any_counts = false;

		foreach ($items as $item) {
			if ((int)$item['count'] > 0) {
				$any_counts = true;
				break;
			}
		}

		if ($show_counts && $any_counts) {
			usort($items, static function ($a, $b) {
				$cmp = (int)$b['count'] - (int)$a['count'];

				if ($cmp !== 0) {
					return $cmp;
				}

				return strcasecmp((string)$a['name'], (string)$b['name']);
			});
		} else {
			usort($items, static function ($a, $b) {
				return strcasecmp((string)$a['name'], (string)$b['name']);
			});
		}

		return $items;
	}

	private function buildStock(array $params, array $counters) {
		$items = [];
		$show_counts = $this->showCounts();

		foreach (['in' => 'text_in_stock', 'out' => 'text_out_of_stock'] as $id => $lang) {
			$key = 's' . $id;
			$has_value_counter = array_key_exists($key, $counters);
			$count = $has_value_counter ? (int)$counters[$key] : 0;
			$selected = !empty($params['s']) && in_array($id, $params['s']);

			if ($show_counts && $has_value_counter && !$selected && $count < 1) {
				continue;
			}

			$items[] = [
				'value_id' => $id,
				'name'     => $this->language->get($lang),
				'count'    => ($show_counts && $has_value_counter) ? $count : 0,
				'selected' => $selected,
				'disabled' => ($show_counts && $has_value_counter && !$selected && $count < 1),
			];
		}

		return $items;
	}

	private function buildDiscount(array $params, array $counters) {
		if (!$this->config->get('module_wt_filter_discount')) {
			return [];
		}

		$show_counts = $this->showCounts();
		$count = isset($counters['d1']) ? (int)$counters['d1'] : 0;
		$selected = !empty($params['d']) && in_array('1', array_map('strval', $params['d']));

		if ($show_counts && !$selected && $count < 1) {
			return [];
		}

		return [[
			'value_id' => '1',
			'name'     => $this->language->get('text_discount_yes'),
			'count'    => $show_counts ? $count : 0,
			'selected' => $selected,
			'disabled' => ($show_counts && !$selected && $count < 1),
		]];
	}
}
