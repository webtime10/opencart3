<?php
/**
 * Витринная модель WT Filter (OpenCart 3).
 * Счётчики по денорм. category_id (KEY cat_opt_val), SEO keyword, SQL листинга.
 */
class ModelCatalogWtFilter extends Model {
	/** Ленивая миграция схемы на витрине */
	private $schema_ready = false;

	public function ensureSchema() {
		if ($this->schema_ready) {
			return;
		}

		$this->load->helper('wt_filter');
		$this->ensureSeoPageTable();

		$table = DB_PREFIX . 'wt_filter_option_value_to_product';
		$value_table = DB_PREFIX . 'wt_filter_option_value';
		$exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

		if (!$exists->num_rows) {
			$this->schema_ready = true;
			return;
		}

		$col = $this->db->query("SHOW COLUMNS FROM `" . $table . "` LIKE 'category_id'");

		if (!$col->num_rows) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD COLUMN `category_id` int(11) NOT NULL DEFAULT '0' AFTER `value_id`");
		}

		$pk = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'PRIMARY'");
		$pk_cols = [];

		foreach ($pk->rows as $row) {
			$pk_cols[(int)$row['Seq_in_index']] = $row['Column_name'];
		}

		ksort($pk_cols);

		if (array_values($pk_cols) !== ['product_id', 'option_id', 'value_id', 'category_id']) {
			@$this->db->query("ALTER TABLE `" . $table . "` DROP PRIMARY KEY");
			$this->db->query("ALTER TABLE `" . $table . "` ADD PRIMARY KEY (`product_id`,`option_id`,`value_id`,`category_id`)");
		}

		$idx = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'cat_opt_val'");

		if (!$idx->num_rows) {
			$this->db->query("ALTER TABLE `" . $table . "` ADD KEY `cat_opt_val` (`category_id`,`option_id`,`value_id`)");
		}

		$vn = $this->db->query("SHOW COLUMNS FROM `" . $value_table . "` LIKE 'value_numeric'");

		if (!$vn->num_rows) {
			$this->db->query("ALTER TABLE `" . $value_table . "`
				ADD COLUMN `value_numeric` decimal(15,4) DEFAULT NULL AFTER `image`,
				ADD KEY `value_numeric` (`option_id`,`value_numeric`)");
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'slider_single' WHERE `type` = 'slide'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'slider_range' WHERE `type` = 'slide_dual'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'checkbox_image' WHERE `type` = 'image_checkbox'");
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'radio_image' WHERE `type` = 'image_radio'");
		// Слайдер + картинки значений (часто «Цвет») — иначе группа скрывается (min=max=0)
		$this->db->query("UPDATE `" . DB_PREFIX . "wt_filter_option` SET `type` = 'checkbox_image'
			WHERE `image` = '1' AND `type` IN ('slider_range', 'slider_single', 'slide', 'slide_dual')");

		$opt_table = DB_PREFIX . 'wt_filter_option';
		$ed = $this->db->query("SHOW COLUMNS FROM `" . $opt_table . "` LIKE 'expanded_desktop'");

		if (!$ed->num_rows) {
			$this->db->query("ALTER TABLE `" . $opt_table . "`
				ADD COLUMN `expanded_desktop` tinyint(1) NOT NULL DEFAULT '1' AFTER `image`,
				ADD COLUMN `expanded_mobile` tinyint(1) NOT NULL DEFAULT '0' AFTER `expanded_desktop`");
		}

		$need = $this->db->query("SELECT COUNT(*) AS total FROM `" . $table . "` WHERE category_id = '0'");

		if (!empty($need->row['total'])) {
			$this->db->query("INSERT IGNORE INTO `" . $table . "`
				(`product_id`, `option_id`, `value_id`, `category_id`, `slide_value_min`, `slide_value_max`)
				SELECT w.product_id, w.option_id, w.value_id, p2c.category_id, w.slide_value_min, w.slide_value_max
				FROM `" . $table . "` w
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = w.product_id)
				WHERE w.category_id = '0' AND p2c.category_id > '0'");
			$this->db->query("DELETE FROM `" . $table . "` WHERE category_id = '0'");
		}

		// Если в админке есть опции, а option_to_category пустая — на витрине пусто. Чиним.
		$this->repairOptionCategoryAndStoreLinks();

		$this->schema_ready = true;
	}

	/**
	 * Восстанавливает связи option→category и option→store=0 для сирот.
	 * Без них getOptionsByCategoryId ничего не отдаёт (INNER JOIN).
	 * Также дотягивает product_option_value → индекс, если копировали с quantity>0.
	 */
	public function repairOptionCategoryAndStoreLinks() {
		$table = DB_PREFIX . 'wt_filter_option_value_to_product';
		$exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

		if (!$exists->num_rows) {
			return;
		}

		// Опции OC есть в словаре, а в индексе товаров — нет / категории не привязаны (часто quantity=0 при копировании)
		$oc_opts = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			INNER JOIN `" . DB_PREFIX . "option` o ON (o.option_id = oo.option_id)");
		$oc_links = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . $table . "` oov2p
			INNER JOIN `" . DB_PREFIX . "option` o ON (o.option_id = oov2p.option_id)");
		$o2c = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "wt_filter_option_to_category`");
		$opts = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "wt_filter_option`");

		$need_option_resync = (!empty($oc_opts->row['total']) && empty($oc_links->row['total']))
			|| (!empty($opts->row['total']) && empty($o2c->row['total']));

		if ($need_option_resync) {
			$this->db->query("INSERT INTO `" . $table . "` (product_id, value_id, option_id, category_id, slide_value_min, slide_value_max)
				SELECT pov.product_id, pov.option_value_id, pov.option_id, p2c.category_id, 0, 0
				FROM `" . DB_PREFIX . "product_option_value` pov
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = pov.product_id)
				INNER JOIN `" . DB_PREFIX . "wt_filter_option` oo ON (oo.option_id = pov.option_id)
				WHERE p2c.category_id > '0'
				ON DUPLICATE KEY UPDATE
					`slide_value_min` = VALUES(`slide_value_min`),
					`slide_value_max` = VALUES(`slide_value_max`)");
			$this->cache->delete('wt_filter');
		}

		if (!empty($opts->row['total']) && empty($o2c->row['total'])) {
			$this->cache->delete('wt_filter');
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` (option_id, category_id)
			SELECT oov2p.option_id, oov2p.category_id
			FROM `" . $table . "` oov2p
			WHERE oov2p.category_id > '0'
			GROUP BY oov2p.option_id, oov2p.category_id
			ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_category` (option_id, category_id)
			SELECT oov2p.option_id, p2c.category_id
			FROM `" . $table . "` oov2p
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = oov2p.product_id)
			WHERE p2c.category_id > '0'
			GROUP BY oov2p.option_id, p2c.category_id
			ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`)");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "wt_filter_option_to_store` (option_id, store_id)
			SELECT oo.option_id, '0'
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s ON (o2s.option_id = oo.option_id)
			WHERE o2s.option_id IS NULL");
	}

	public function ensureSeoPageTable() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wt_filter_seo_page` (
				`seo_page_id` INT(11) NOT NULL AUTO_INCREMENT,
				`category_id` INT(11) NOT NULL DEFAULT '0',
				`manufacturer_id` INT(11) NOT NULL DEFAULT '0',
				`url` VARCHAR(255) NOT NULL,
				`filter_params` VARCHAR(255) NOT NULL,
				`h1` VARCHAR(255) NOT NULL,
				`meta_title` VARCHAR(255) NOT NULL,
				`meta_description` TEXT NOT NULL,
				`meta_keyword` VARCHAR(255) NOT NULL,
				`description` MEDIUMTEXT NOT NULL,
				`status` TINYINT(1) NOT NULL DEFAULT '1',
				PRIMARY KEY (`seo_page_id`),
				KEY `category_filter` (`category_id`, `filter_params`),
				KEY `manufacturer_filter` (`manufacturer_id`, `filter_params`),
				KEY `url` (`url`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
		");

		$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "wt_filter_seo_page` LIKE 'manufacturer_id'");

		if (!$col->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "wt_filter_seo_page`
				ADD COLUMN `manufacturer_id` INT(11) NOT NULL DEFAULT '0' AFTER `category_id`,
				ADD KEY `manufacturer_filter` (`manufacturer_id`, `filter_params`)");
		}
	}

	/**
	 * Каноничная SEO-строка фильтра (keywords, без s:), для поиска посадочной.
	 */
	public function buildSeoFilterParamsSignature(array $params) {
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		unset($params['s']);

		$part_sep = $this->config->get('wt_filter_part_separator') ?: ';';
		$opt_sep = $this->config->get('wt_filter_option_separator') ?: ':';
		$val_sep = $this->config->get('wt_filter_option_value_separator') ?: ',';
		$parts = [];

		ksort($params, SORT_STRING);

		foreach ($params as $option_id => $values) {
			if (!is_array($values) || !$values) {
				continue;
			}

			if ($option_id === 'p' || $option_id === 's' || $option_id === 'm' || $option_id === 'd' || wt_filter_is_range((string)$values[0])) {
				$vals = $values;
				sort($vals);
				$parts[] = $option_id . $opt_sep . implode($val_sep, $vals);
				continue;
			}

			$oq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option`
				WHERE option_id = '" . (int)$option_id . "' LIMIT 1");
			$okey = ($oq->num_rows && $oq->row['keyword'] !== '') ? (string)$oq->row['keyword'] : (string)$option_id;

			$vkeys = [];

			foreach ($values as $value_id) {
				$vq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option_value`
					WHERE value_id = '" . $this->db->escape((string)$value_id) . "' LIMIT 1");
				$vkeys[] = ($vq->num_rows && $vq->row['keyword'] !== '')
					? (string)$vq->row['keyword']
					: (string)$value_id;
			}

			sort($vkeys);
			$parts[] = $okey . $opt_sep . implode($val_sep, $vkeys);
		}

		sort($parts, SORT_STRING);

		return implode($part_sep, $parts);
	}

	/**
	 * Активная SEO-посадочная для category_id или manufacturer_id + текущих params.
	 */
	public function getSeoPageByFilter($category_id, $params = [], $manufacturer_id = 0) {
		$this->ensureSchema();
		$this->ensureSeoPageTable();

		$category_id = (int)$category_id;
		$manufacturer_id = (int)$manufacturer_id;

		if ($category_id < 1 && $manufacturer_id < 1) {
			return null;
		}

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		if ($manufacturer_id > 0) {
			unset($params['m']);
		}

		$signature = $this->buildSeoFilterParamsSignature($params);

		if ($signature === '') {
			return null;
		}

		if ($manufacturer_id > 0) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_seo_page`
				WHERE manufacturer_id = '" . $manufacturer_id . "'
					AND category_id = '0'
					AND filter_params = '" . $this->db->escape($signature) . "'
					AND status = '1'
				LIMIT 1");
		} else {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wt_filter_seo_page`
				WHERE category_id = '" . $category_id . "'
					AND manufacturer_id = '0'
					AND filter_params = '" . $this->db->escape($signature) . "'
					AND status = '1'
				LIMIT 1");
		}

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Популярные SEO-посадочные категории (для мобильного меню фильтра).
	 */
	public function getPopularSeoLinks($category_id, $limit = 8) {
		$this->ensureSchema();
		$this->ensureSeoPageTable();

		$category_id = (int)$category_id;
		$limit = max(1, (int)$limit);

		if ($category_id < 1) {
			return [];
		}

		$query = $this->db->query("SELECT h1, url FROM `" . DB_PREFIX . "wt_filter_seo_page`
			WHERE category_id = '" . $category_id . "'
				AND manufacturer_id = '0'
				AND status = '1'
				AND h1 <> ''
				AND url <> ''
			ORDER BY seo_page_id DESC
			LIMIT " . $limit);

		$rows = [];

		foreach ($query->rows as $row) {
			$url = html_entity_decode((string)$row['url'], ENT_QUOTES, 'UTF-8');

			if ($url !== '' && strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
				$url = '/' . ltrim($url, '/');
			}

			$rows[] = [
				'name' => (string)$row['h1'],
				'href' => $url,
			];
		}

		return $rows;
	}

	public function formatSeoPagePayload($seo_page) {
		if (!$seo_page) {
			return null;
		}

		return [
			'title'       => (string)$seo_page['meta_title'],
			'description' => (string)$seo_page['meta_description'],
			'keywords'    => (string)$seo_page['meta_keyword'],
			'h1'          => (string)$seo_page['h1'],
			'breadcrumb'  => (string)$seo_page['h1'],
			'seo_text'    => html_entity_decode((string)$seo_page['description'], ENT_QUOTES, 'UTF-8'),
		];
	}

	/**
	 * Строка params / keywords → массив option_id => [value_ids].
	 * Поддерживает id и keyword из wt_filter_option_value.keyword.
	 */
	public function decodeParams($string) {
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		$string = trim((string)$string);

		if ($string === '') {
			return [];
		}

		// Сначала обычный id-формат
		$params = wt_filter_decode_params($string, $this->config);

		if ($params) {
			return $params;
		}

		// SEO: keyword:keyword или просто цепочка keywords через ;
		$part_sep = $this->config->get('wt_filter_part_separator');
		$opt_sep = $this->config->get('wt_filter_option_separator');
		$val_sep = $this->config->get('wt_filter_option_value_separator');
		$decode = [];

		foreach (explode($part_sep, $string) as $part) {
			$part = trim($part);

			if ($part === '') {
				continue;
			}

			if (strpos($part, $opt_sep) !== false) {
				list($left, $right) = explode($opt_sep, $part, 2);
			} else {
				$left = '';
				$right = $part;
			}

			$left = trim($left);
			$value_tokens = array_filter(array_map('trim', explode($val_sep, $right)));

			if ($left === 'p' || $left === 'm' || $left === 's' || $left === 'd' || wt_filter_is_id($left)) {
				if ($left === 'd') {
					$decode[$left] = ['1'];
				} else {
					$decode[$left] = array_values($value_tokens);
				}
				continue;
			}

			// keyword опции → option_id
			$option_id = $left;

			if ($left !== '' && !wt_filter_is_id($left)) {
				$oq = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "wt_filter_option`
					WHERE keyword = '" . $this->db->escape($left) . "' LIMIT 1");
				$option_id = $oq->num_rows ? (string)$oq->row['option_id'] : '';
			}

			if ($option_id === '') {
				continue;
			}

			$ids = [];

			foreach ($value_tokens as $token) {
				if (wt_filter_is_range($token) || ctype_digit((string)$token)) {
					$ids[] = $token;
					continue;
				}

				$vq = $this->db->query("SELECT value_id FROM `" . DB_PREFIX . "wt_filter_option_value`
					WHERE option_id = '" . (int)$option_id . "'
						AND keyword = '" . $this->db->escape($token) . "'
					LIMIT 1");

				if ($vq->num_rows) {
					$ids[] = (string)$vq->row['value_id'];
				}
			}

			if ($ids) {
				$decode[$option_id] = $ids;
			}
		}

		return $decode;
	}

	/**
	 * Ссылка категории с фильтром.
	 * 1) Чистое ЧПУ категории через SeoUrl/SeoPro (без filter_wt_filter)
	 * 2) Дописываем SEO-сегмент фильтра: /obuv/razmer-obuvi:39;sezon:fall/
	 */
	public function buildUrl($path, $params = [], $manufacturer_id = 0, $special = false) {
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		// s:in не для публичного URL
		unset($params['s']);

		// На странице производителя — не фильтруем по бренду в URL
		if ((int)$manufacturer_id > 0) {
			unset($params['m']);
		}

		// На странице акций «Акции» уже в scope — не дублируем d:1 в URL
		if ($special) {
			unset($params['d']);
		}

		$url_key = $this->config->get('wt_filter_url_index') ?: 'filter_wt_filter';

		// 1) ЧПУ базы (категория / производитель / акции) — без filter_wt_filter
		if ($special) {
			$base_link = str_replace('&amp;', '&', $this->url->link('product/special', ''));
		} elseif ((int)$manufacturer_id > 0) {
			$base_link = str_replace('&amp;', '&', $this->url->link('product/manufacturer.info', 'manufacturer_id=' . (int)$manufacturer_id));
		} else {
			$base_link = str_replace('&amp;', '&', $this->url->link('product/category', 'path=' . $path));
		}

		$url_info = parse_url($base_link);

		$base = '';

		if (!empty($url_info['scheme']) && !empty($url_info['host'])) {
			$base = $url_info['scheme'] . '://' . $url_info['host'];

			if (!empty($url_info['port'])) {
				$base .= ':' . $url_info['port'];
			}
		} else {
			$base = rtrim(defined('HTTP_SERVER') ? HTTP_SERVER : '', '/');
		}

		$base_path = isset($url_info['path']) ? (string)$url_info['path'] : '';
		$is_index = (strpos($base_path, 'index.php') !== false);
		$seo_base = $is_index ? '' : rtrim(str_replace('/index.php', '', $base_path), '/');

		// 2) SEO-сегмент фильтра
		list($seo_segment, $query_params) = $this->splitSeoParams($params);
		unset($query_params['s']);

		if ($query_params) {
			$tail = wt_filter_encode_params($query_params, $this->config);

			if ($tail !== '') {
				$seo_segment = $seo_segment !== '' ? ($seo_segment . ';' . $tail) : $tail;
			}
		}

		$seo_segment = preg_replace('/(;)?s:(in|out)\b/', '', (string)$seo_segment);
		$seo_segment = trim((string)$seo_segment, ';');

		$query = [];

		if (!empty($url_info['query'])) {
			parse_str($url_info['query'], $query);
		}

		unset($query[$url_key], $query['s']);

		// Нет ЧПУ у категории/бренда/акций — не теряем path/manufacturer_id/route в query
		if ($seo_base === '') {
			if ($special) {
				$query['route'] = 'product/special';
				unset($query['path'], $query['manufacturer_id']);
			} elseif ((int)$manufacturer_id > 0) {
				$query['route'] = 'product/manufacturer.info';
				$query['manufacturer_id'] = (int)$manufacturer_id;
				unset($query['path']);
			} else {
				$query['route'] = 'product/category';
				$query['path'] = (string)$path;
				unset($query['manufacturer_id']);
			}

			if ($seo_segment !== '') {
				$query[$url_key] = $seo_segment;
			}

			// Сырой URL для pushState/JSON — не &amp; (иначе PHP не видит path → 404)
			return $base . '/index.php?' . http_build_query($query);
		}

		unset($query['route'], $query['path'], $query['manufacturer_id']);

		$path_out = $seo_base;

		if ($seo_segment !== '') {
			$path_out = rtrim($path_out, '/') . '/' . $seo_segment;
		}

		$path_out = rtrim($path_out, '/') . '/';
		$result = $base . $path_out;

		if ($query) {
			$result .= '?' . http_build_query($query);
		}

		return $result;
	}

	/**
	 * Делит params на SEO-сегмент пути и остаток для query.
	 * Цена (p) и диапазоны остаются в query.
	 *
	 * @return array{0:string,1:array}
	 */
	public function splitSeoParams(array $params) {
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		$seo_parts = [];
		$query_params = [];

		foreach ($params as $option_id => $values) {
			if (!is_array($values) || !$values) {
				continue;
			}

			if ($option_id === 'p' || $option_id === 's') {
				$query_params[$option_id] = $values;
				continue;
			}

			if (wt_filter_is_range((string)$values[0])) {
				$query_params[$option_id] = $values;
				continue;
			}

			if ($option_id === 'd') {
				$seo_parts[] = 'd:1';
				continue;
			}

			if ($option_id === 'm') {
				$seo_parts[] = 'm:' . implode(',', $values);
				continue;
			}

			$oq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option`
				WHERE option_id = '" . (int)$option_id . "' LIMIT 1");
			$okey = ($oq->num_rows && $oq->row['keyword'] !== '') ? (string)$oq->row['keyword'] : '';

			if ($okey === '') {
				$query_params[$option_id] = $values;
				continue;
			}

			$vkeys = [];

			foreach ($values as $value_id) {
				$vq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "wt_filter_option_value`
					WHERE value_id = '" . $this->db->escape((string)$value_id) . "' LIMIT 1");

				if (!$vq->num_rows || $vq->row['keyword'] === '') {
					$vkeys = [];
					break;
				}

				$vkeys[] = (string)$vq->row['keyword'];
			}

			if (!$vkeys) {
				$query_params[$option_id] = $values;
				continue;
			}

			$seo_parts[] = $okey . ':' . implode(',', $vkeys);
		}

		return [implode(';', $seo_parts), $query_params];
	}

	/**
	 * Хвостовой сегмент пути похож на SEO-фильтр (option:value[;…]).
	 */
	public function isSeoFilterSegment($segment) {
		$segment = trim((string)$segment);

		if ($segment === '' || strpos($segment, ':') === false) {
			return false;
		}

		$params = $this->decodeParams($segment);

		return !empty($params);
	}

	/**
	 * Текущая категория + все дочерние любого уровня (через category_path).
	 * Для листа без детей возвращает [свой id].
	 *
	 * @param  int $category_id
	 * @return int[]
	 */
	public function getCategoryBranchIds($category_id) {
		$category_id = (int)$category_id;

		if ($category_id < 1) {
			return [];
		}

		static $branch_cache = [];

		if (isset($branch_cache[$category_id])) {
			return $branch_cache[$category_id];
		}

		$query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category_path`
			WHERE path_id = '" . $category_id . "'
			ORDER BY level ASC, category_id ASC");

		$ids = [];

		foreach ($query->rows as $row) {
			$ids[] = (int)$row['category_id'];
		}

		if (!$ids) {
			$ids = [$category_id];
		}

		$branch_cache[$category_id] = $ids;

		return $ids;
	}

	/**
	 * Нормализует category_id или массив ID в список ветки.
	 *
	 * @param  int|int[] $category_id
	 * @return int[]
	 */
	public function normalizeCategoryIds($category_id) {
		if (is_array($category_id)) {
			$ids = array_values(array_unique(array_filter(array_map('intval', $category_id))));

			return $ids;
		}

		return $this->getCategoryBranchIds((int)$category_id);
	}

	/**
	 * SQL-условие: column = 'N' или column IN (...).
	 *
	 * @param  string    $column
	 * @param  int|int[] $category_id
	 * @return string
	 */
	public function categoryIdCondition($column, $category_id) {
		$ids = $this->normalizeCategoryIds($category_id);

		if (!$ids) {
			return $column . " = '0'";
		}

		if (count($ids) === 1) {
			return $column . " = '" . (int)$ids[0] . "'";
		}

		return $column . " IN (" . implode(',', $ids) . ")";
	}

	public function getOptionsByCategoryId($category_id) {
		$this->ensureSchema();

		$category_ids = $this->normalizeCategoryIds($category_id);
		$root_id = (int)$category_id;

		if (!$category_ids) {
			return [];
		}

		$cache_key = 'wt_filter.option.branch.' . $root_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$data = $this->cache->get($cache_key);

		// Пустой [] тоже miss: иначе после одного пустого ответа опции «умирают» в кеше
		if (is_array($data) && $data) {
			return $data;
		}

		$data = [];
		$language_id = (int)$this->config->get('config_language_id');
		$store_id = (int)$this->config->get('config_store_id');
		$category_sql = $this->categoryIdCondition('o2c.category_id', $category_ids);

		$options_query = $this->db->query("SELECT oo.*, ood.name
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_to_category` o2c ON (oo.option_id = o2c.option_id)
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s ON (oo.option_id = o2s.option_id)
			WHERE oo.status = '1'
				AND " . $category_sql . "
				AND o2s.store_id = '" . $store_id . "'
				AND ood.language_id = '" . $language_id . "'
			GROUP BY oo.option_id
			ORDER BY oo.sort_order ASC, ood.name ASC");

		// Фоллбек: товары категории есть в индексе, а option_to_category — нет / не для этой ветки
		if (!$options_query->num_rows) {
			$p2c_sql = $this->categoryIdCondition('p2c.category_id', $category_ids);
			$options_query = $this->db->query("SELECT oo.*, ood.name
				FROM `" . DB_PREFIX . "wt_filter_option` oo
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
				LEFT JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s
					ON (oo.option_id = o2s.option_id AND o2s.store_id = '" . $store_id . "')
				WHERE oo.status = '1'
					AND ood.language_id = '" . $language_id . "'
					AND (o2s.option_id IS NOT NULL OR NOT EXISTS (
						SELECT 1 FROM `" . DB_PREFIX . "wt_filter_option_to_store` x WHERE x.option_id = oo.option_id
					))
					AND EXISTS (
						SELECT 1
						FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
						INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = oov2p.product_id)
						WHERE oov2p.option_id = oo.option_id
							AND " . $p2c_sql . "
					)
				GROUP BY oo.option_id
				ORDER BY oo.sort_order ASC, ood.name ASC");
		}

		if (!$options_query->num_rows) {
			// Не кэшируем пустой список — иначе после временного сбоя опции «пропадают навсегда»
			return $data;
		}

		$option_ids = [];

		foreach ($options_query->rows as $option) {
			$option_ids[] = (int)$option['option_id'];
			$data[$option['option_id']] = $option;
			$data[$option['option_id']]['values'] = [];
			$data[$option['option_id']]['slide_value_min'] = 0;
			$data[$option['option_id']]['slide_value_max'] = 0;
		}

		$values_query = $this->db->query("SELECT oov.*, oovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` oov
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_description` oovd ON (oov.value_id = oovd.value_id)
			WHERE oov.option_id IN (" . implode(',', $option_ids) . ")
				AND oovd.language_id = '" . $language_id . "'
			ORDER BY oov.sort_order ASC, oovd.name ASC");

		foreach ($values_query->rows as $value) {
			$data[$value['option_id']]['values'][] = $value;
		}

		$slider_ids = [];

		foreach ($data as $option_id => $option) {
			if (wt_filter_is_slider_type($option['type'])) {
				$slider_ids[] = (int)$option_id;
			}
		}

		if ($slider_ids) {
			$ids_sql = implode(',', $slider_ids);
			$oov2p_cat_sql = $this->categoryIdCondition('oov2p.category_id', $category_ids);
			$wfv_cat_sql = $this->categoryIdCondition('category_id', $category_ids);

			foreach ($slider_ids as $sid) {
				$data[$sid]['slide_scale'] = 0;
			}

			// Сначала min/max из числовых значений словаря в ветке категорий
			$num_query = $this->db->query("SELECT oov.option_id,
					MIN(oov.value_numeric) AS `min`,
					MAX(oov.value_numeric) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND " . $oov2p_cat_sql . "
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id");

			foreach ($num_query->rows as $row) {
				if (isset($data[$row['option_id']])) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			$slide_query = $this->db->query("SELECT option_id,
					MIN(slide_value_min) AS `min`,
					GREATEST(MAX(slide_value_max), MAX(slide_value_min)) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
				WHERE option_id IN (" . $ids_sql . ")
					AND " . $wfv_cat_sql . "
				GROUP BY option_id");

			foreach ($slide_query->rows as $row) {
				if (!isset($data[$row['option_id']])) {
					continue;
				}

				// Фоллбек, если value_numeric ещё пуст
				if ((float)$data[$row['option_id']]['slide_value_max'] <= (float)$data[$row['option_id']]['slide_value_min']) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			// Точность шага: целые → 0; 4.4 → 1; 10.99 → 2
			$scale_query = $this->db->query("SELECT oov.option_id, oov.value_numeric AS num
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND " . $oov2p_cat_sql . "
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id, oov.value_numeric");

			$scale_map = [];

			foreach ($scale_query->rows as $row) {
				$oid = (int)$row['option_id'];

				if (!isset($scale_map[$oid])) {
					$scale_map[$oid] = [];
				}

				$scale_map[$oid][] = $row['num'];
			}

			$slide_scale_query = $this->db->query("SELECT option_id, slide_value_min AS num
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
				WHERE option_id IN (" . $ids_sql . ")
					AND " . $wfv_cat_sql . "
				GROUP BY option_id, slide_value_min
				UNION
				SELECT option_id, slide_value_max AS num
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product`
				WHERE option_id IN (" . $ids_sql . ")
					AND " . $wfv_cat_sql . "
				GROUP BY option_id, slide_value_max");

			foreach ($slide_scale_query->rows as $row) {
				$oid = (int)$row['option_id'];

				if (!isset($scale_map[$oid])) {
					$scale_map[$oid] = [];
				}

				$scale_map[$oid][] = $row['num'];
			}

			foreach ($scale_map as $oid => $nums) {
				if (isset($data[$oid])) {
					$data[$oid]['slide_scale'] = wt_filter_scale_from_values($nums);
				}
			}
		}

		$this->cache->set($cache_key, $data);

		return $data;
	}

	/**
	 * Опции фильтра по товарам производителя (без привязки к одной категории).
	 */
	public function getOptionsByManufacturerId($manufacturer_id) {
		$this->ensureSchema();

		$manufacturer_id = (int)$manufacturer_id;
		$cache_key = 'wt_filter.option.m.' . $manufacturer_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$data = $this->cache->get($cache_key);

		// OC4 File cache на miss возвращает [], не false
		if (is_array($data) && $data) {
			return $data;
		}

		$data = [];
		$language_id = (int)$this->config->get('config_language_id');
		$store_id = (int)$this->config->get('config_store_id');

		if ($manufacturer_id < 1) {
			return $data;
		}

		$options_query = $this->db->query("SELECT oo.*, ood.name
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s ON (oo.option_id = o2s.option_id)
			WHERE oo.status = '1'
				AND o2s.store_id = '" . $store_id . "'
				AND ood.language_id = '" . $language_id . "'
				AND EXISTS (
					SELECT 1
					FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
					WHERE oov2p.option_id = oo.option_id
						AND p.manufacturer_id = '" . $manufacturer_id . "'
						AND p.status = '1'
						AND p.date_available <= NOW()
				)
			ORDER BY oo.sort_order ASC, ood.name ASC");

		if (!$options_query->num_rows) {
			// Не кэшируем пустой список — иначе после временного сбоя опции «пропадают навсегда»
			return $data;
		}

		$option_ids = [];

		foreach ($options_query->rows as $option) {
			$option_ids[] = (int)$option['option_id'];
			$data[$option['option_id']] = $option;
			$data[$option['option_id']]['values'] = [];
			$data[$option['option_id']]['slide_value_min'] = 0;
			$data[$option['option_id']]['slide_value_max'] = 0;
		}

		$values_query = $this->db->query("SELECT oov.*, oovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` oov
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_description` oovd ON (oov.value_id = oovd.value_id)
			WHERE oov.option_id IN (" . implode(',', $option_ids) . ")
				AND oovd.language_id = '" . $language_id . "'
				AND EXISTS (
					SELECT 1
					FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
					WHERE oov2p.value_id = oov.value_id
						AND oov2p.option_id = oov.option_id
						AND p.manufacturer_id = '" . $manufacturer_id . "'
						AND p.status = '1'
						AND p.date_available <= NOW()
				)
			ORDER BY oov.sort_order ASC, oovd.name ASC");

		foreach ($values_query->rows as $value) {
			$data[$value['option_id']]['values'][] = $value;
		}

		$slider_ids = [];

		foreach ($data as $option_id => $option) {
			if (wt_filter_is_slider_type($option['type'])) {
				$slider_ids[] = (int)$option_id;
			}
		}

		if ($slider_ids) {
			$ids_sql = implode(',', $slider_ids);

			foreach ($slider_ids as $sid) {
				$data[$sid]['slide_scale'] = 0;
			}

			$num_query = $this->db->query("SELECT oov.option_id,
					MIN(oov.value_numeric) AS `min`,
					MAX(oov.value_numeric) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND p.manufacturer_id = '" . $manufacturer_id . "'
					AND p.status = '1'
					AND p.date_available <= NOW()
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id");

			foreach ($num_query->rows as $row) {
				if (isset($data[$row['option_id']])) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			$slide_query = $this->db->query("SELECT oov2p.option_id,
					MIN(oov2p.slide_value_min) AS `min`,
					GREATEST(MAX(oov2p.slide_value_max), MAX(oov2p.slide_value_min)) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov2p.option_id IN (" . $ids_sql . ")
					AND p.manufacturer_id = '" . $manufacturer_id . "'
					AND p.status = '1'
					AND p.date_available <= NOW()
				GROUP BY oov2p.option_id");

			foreach ($slide_query->rows as $row) {
				if (!isset($data[$row['option_id']])) {
					continue;
				}

				if ((float)$data[$row['option_id']]['slide_value_max'] <= (float)$data[$row['option_id']]['slide_value_min']) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			$scale_query = $this->db->query("SELECT oov.option_id, oov.value_numeric AS num
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND p.manufacturer_id = '" . $manufacturer_id . "'
					AND p.status = '1'
					AND p.date_available <= NOW()
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id, oov.value_numeric");

			$scale_map = [];

			foreach ($scale_query->rows as $row) {
				$oid = (int)$row['option_id'];

				if (!isset($scale_map[$oid])) {
					$scale_map[$oid] = [];
				}

				$scale_map[$oid][] = $row['num'];
			}

			foreach ($scale_map as $oid => $nums) {
				if (isset($data[$oid])) {
					$data[$oid]['slide_scale'] = wt_filter_scale_from_values($nums);
				}
			}
		}

		$this->cache->set($cache_key, $data);

		return $data;
	}

	/**
	 * Опции фильтра по акционным товарам (страница product/special).
	 */
	public function getOptionsBySpecial() {
		$this->ensureSchema();

		$cache_key = 'wt_filter.option.special.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_customer_group_id');
		$data = $this->cache->get($cache_key);

		if ($data !== false && is_array($data) && $data) {
			return $data;
		}

		$data = [];
		$language_id = (int)$this->config->get('config_language_id');
		$store_id = (int)$this->config->get('config_store_id');
		$special_sql = $this->getSpecialExistsSql('p');

		$options_query = $this->db->query("SELECT oo.*, ood.name
			FROM `" . DB_PREFIX . "wt_filter_option` oo
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_description` ood ON (oo.option_id = ood.option_id)
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_to_store` o2s ON (oo.option_id = o2s.option_id)
			WHERE oo.status = '1'
				AND o2s.store_id = '" . $store_id . "'
				AND ood.language_id = '" . $language_id . "'
				AND EXISTS (
					SELECT 1
					FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
					WHERE oov2p.option_id = oo.option_id
						AND p.status = '1'
						AND p.date_available <= NOW()
						AND " . $special_sql . "
				)
			ORDER BY oo.sort_order ASC, ood.name ASC");

		if (!$options_query->num_rows) {
			// Не кэшируем пустой список — иначе после временного сбоя опции «пропадают навсегда»
			return $data;
		}

		$option_ids = [];

		foreach ($options_query->rows as $option) {
			$option_ids[] = (int)$option['option_id'];
			$data[$option['option_id']] = $option;
			$data[$option['option_id']]['values'] = [];
			$data[$option['option_id']]['slide_value_min'] = 0;
			$data[$option['option_id']]['slide_value_max'] = 0;
		}

		$values_query = $this->db->query("SELECT oov.*, oovd.name
			FROM `" . DB_PREFIX . "wt_filter_option_value` oov
			INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_description` oovd ON (oov.value_id = oovd.value_id)
			WHERE oov.option_id IN (" . implode(',', $option_ids) . ")
				AND oovd.language_id = '" . $language_id . "'
				AND EXISTS (
					SELECT 1
					FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
					WHERE oov2p.value_id = oov.value_id
						AND oov2p.option_id = oov.option_id
						AND p.status = '1'
						AND p.date_available <= NOW()
						AND " . $special_sql . "
				)
			ORDER BY oov.sort_order ASC, oovd.name ASC");

		foreach ($values_query->rows as $value) {
			$data[$value['option_id']]['values'][] = $value;
		}

		$slider_ids = [];

		foreach ($data as $option_id => $option) {
			if (wt_filter_is_slider_type($option['type'])) {
				$slider_ids[] = (int)$option_id;
			}
		}

		if ($slider_ids) {
			$ids_sql = implode(',', $slider_ids);

			foreach ($slider_ids as $sid) {
				$data[$sid]['slide_scale'] = 0;
			}

			$num_query = $this->db->query("SELECT oov.option_id,
					MIN(oov.value_numeric) AS `min`,
					MAX(oov.value_numeric) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND p.status = '1'
					AND p.date_available <= NOW()
					AND " . $special_sql . "
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id");

			foreach ($num_query->rows as $row) {
				if (isset($data[$row['option_id']])) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			$slide_query = $this->db->query("SELECT oov2p.option_id,
					MIN(oov2p.slide_value_min) AS `min`,
					GREATEST(MAX(oov2p.slide_value_max), MAX(oov2p.slide_value_min)) AS `max`
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov2p.option_id IN (" . $ids_sql . ")
					AND p.status = '1'
					AND p.date_available <= NOW()
					AND " . $special_sql . "
				GROUP BY oov2p.option_id");

			foreach ($slide_query->rows as $row) {
				if (!isset($data[$row['option_id']])) {
					continue;
				}

				if ((float)$data[$row['option_id']]['slide_value_max'] <= (float)$data[$row['option_id']]['slide_value_min']) {
					$data[$row['option_id']]['slide_value_min'] = (float)$row['min'];
					$data[$row['option_id']]['slide_value_max'] = (float)$row['max'];
				}
			}

			$scale_query = $this->db->query("SELECT oov.option_id, oov.value_numeric AS num
				FROM `" . DB_PREFIX . "wt_filter_option_value` oov
				INNER JOIN `" . DB_PREFIX . "wt_filter_option_value_to_product` oov2p
					ON (oov2p.value_id = oov.value_id AND oov2p.option_id = oov.option_id)
				INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id = oov2p.product_id)
				WHERE oov.option_id IN (" . $ids_sql . ")
					AND p.status = '1'
					AND p.date_available <= NOW()
					AND " . $special_sql . "
					AND oov.value_numeric IS NOT NULL
				GROUP BY oov.option_id, oov.value_numeric");

			$scale_map = [];

			foreach ($scale_query->rows as $row) {
				$oid = (int)$row['option_id'];

				if (!isset($scale_map[$oid])) {
					$scale_map[$oid] = [];
				}

				$scale_map[$oid][] = $row['num'];
			}

			foreach ($scale_map as $oid => $nums) {
				if (isset($data[$oid])) {
					$data[$oid]['slide_scale'] = wt_filter_scale_from_values($nums);
				}
			}
		}

		$this->cache->set($cache_key, $data);

		return $data;
	}

	/**
	 * Опции + счётчики. values[value_id] = ['t' => N]
	 */
	public function getOptionsWithCounts($category_id, $params = []) {
		$this->ensureSchema();
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		$options = $this->getOptionsByCategoryId($category_id);
		$counters = $this->getCounters($category_id, $params);
		$result = [];

		foreach ($options as $option) {
			$item = [
				'option_id' => $option['option_id'],
				'name'      => $option['name'],
				'type'      => $option['type'],
				'keyword'   => isset($option['keyword']) ? $option['keyword'] : '',
				'values'    => [],
			];

			if (wt_filter_is_slider_type($option['type'])) {
				$item['min'] = (float)$option['slide_value_min'];
				$item['max'] = (float)$option['slide_value_max'];
				$result[] = $item;
				continue;
			}

			foreach ($option['values'] as $value) {
				$key = $option['option_id'] . $value['value_id'];
				$t = isset($counters[$key]) ? (int)$counters[$key] : 0;
				$selected = !empty($params[$option['option_id']]) && in_array((string)$value['value_id'], array_map('strval', $params[$option['option_id']]));

				if (!$selected && $t < 1) {
					continue;
				}

				$item['values'][] = [
					'value_id' => (string)$value['value_id'],
					'name'     => $value['name'],
					'keyword'  => isset($value['keyword']) ? $value['keyword'] : '',
					't'        => $t,
					'count'    => $t,
					'selected' => $selected,
					'color'    => !empty($value['color']) ? $value['color'] : '',
					'image'    => !empty($value['image']) ? $value['image'] : '',
				];
			}

			if ($item['values'] || wt_filter_is_slider_type($option['type'])) {
				$result[] = $item;
			}
		}

		return [
			'options'  => $result,
			'counters' => $counters,
			'values'   => $this->countersToValuesMap($counters),
		];
	}

	/**
	 * Карта для callback: value_id => ['t' => N]
	 */
	public function countersToValuesMap(array $counters) {
		$map = [];

		foreach ($counters as $key => $total) {
			$key = (string)$key;

			if (strpos($key, 'm') === 0 || strpos($key, 's') === 0 || strpos($key, 'd') === 0) {
				$map[$key] = ['t' => (int)$total];
				continue;
			}

			// Ключ = option_id . value_id — отдаём по value_id (уникален в схеме)
			// и дублируем полный ключ для совместимости
			$map[$key] = ['t' => (int)$total];
		}

		return $map;
	}

	public function getTotalProducts($category_id, $params = [], $manufacturer_id = 0, $special = false) {
		$this->load->model('catalog/product');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if ($special) {
			unset($params['d']);
			$encoded = wt_filter_encode_params($params, $this->config);

			return (int)$this->model_catalog_product->getTotalSpecials([
				'filter_wt_filter' => $encoded,
			]);
		}

		$encoded = is_string($params) ? $params : wt_filter_encode_params($params, $this->config);

		$filter_data = [
			'filter_wt_filter' => $encoded,
		];

		if ((int)$manufacturer_id > 0) {
			$filter_data['filter_manufacturer_id'] = (int)$manufacturer_id;
		} else {
			$filter_data['filter_category_id'] = (int)$category_id;
			$filter_data['filter_sub_category'] = true;
		}

		return (int)$this->model_catalog_product->getTotalProducts($filter_data);
	}

	public function getManufacturersByCategoryId($category_id) {
		$category_ids = $this->normalizeCategoryIds($category_id);

		if (!$category_ids) {
			return [];
		}

		$cache_key = 'wt_filter.manufacturer.branch.' . (int)$category_id . '.' . (int)$this->config->get('config_store_id');
		$data = $this->cache->get($cache_key);

		if (is_array($data) && $data) {
			return $data;
		}

		$query = $this->db->query("SELECT m.manufacturer_id AS value_id, m.name, 'm' AS option_id
			FROM `" . DB_PREFIX . "manufacturer` m
			INNER JOIN `" . DB_PREFIX . "manufacturer_to_store` m2s ON (m.manufacturer_id = m2s.manufacturer_id)
			INNER JOIN `" . DB_PREFIX . "product` p ON (m.manufacturer_id = p.manufacturer_id)
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
			WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND " . $this->categoryIdCondition('p2c.category_id', $category_ids) . "
			GROUP BY m.manufacturer_id
			ORDER BY m.name ASC");

		$data = $query->rows;
		$this->cache->set($cache_key, $data);

		return $data;
	}

	/**
	 * Производители среди акционных товаров магазина.
	 */
	public function getManufacturersBySpecial() {
		$cache_key = 'wt_filter.manufacturer.special.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_customer_group_id');
		$data = $this->cache->get($cache_key);

		if (is_array($data) && $data) {
			return $data;
		}

		$query = $this->db->query("SELECT m.manufacturer_id AS value_id, m.name, 'm' AS option_id
			FROM `" . DB_PREFIX . "manufacturer` m
			INNER JOIN `" . DB_PREFIX . "manufacturer_to_store` m2s ON (m.manufacturer_id = m2s.manufacturer_id)
			INNER JOIN `" . DB_PREFIX . "product` p ON (m.manufacturer_id = p.manufacturer_id)
			INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND " . $this->getSpecialExistsSql('p') . "
			GROUP BY m.manufacturer_id
			ORDER BY m.name ASC");

		$data = $query->rows;
		$this->cache->set($cache_key, $data);

		return $data;
	}

	/**
	 * Условие периода скидки/акции без литерала '0000-00-00'
	 * (MySQL 8 + NO_ZERO_DATE даёт ERROR 1525 и ломает SELECT опций на /special).
	 */
	private function getDiscountDateSql($alias) {
		$alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);

		if ($alias === '') {
			$alias = 'ps';
		}

		// Пустая дата в OC часто хранится как 0000-00-00 — сравниваем через CHAR, чтобы не падать на NO_ZERO_DATE
		return "(CAST(" . $alias . ".date_start AS CHAR) IN ('0000-00-00', '0000-00-00 00:00:00') OR " . $alias . ".date_start <= NOW())"
			. " AND (CAST(" . $alias . ".date_end AS CHAR) IN ('0000-00-00', '0000-00-00 00:00:00') OR " . $alias . ".date_end >= NOW())";
	}

	/**
	 * SQL-выражение цены товара: базовая или со Special (если включено в настройках).
	 */
	public function getProductPriceExpression($alias = 'p') {
		$alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);

		if ($alias === '') {
			$alias = 'p';
		}

		if (!$this->config->get('module_wt_filter_price_special')) {
			return $alias . '.price';
		}

		$group_id = (int)$this->config->get('config_customer_group_id');

		// OC3: акции в product_special, скидки по количеству — product_discount (без колонки special/type как в OC4)
		$special = "(SELECT ps.price FROM `" . DB_PREFIX . "product_special` ps"
			. " WHERE ps.product_id = " . $alias . ".product_id"
			. " AND ps.customer_group_id = '" . $group_id . "'"
			. " AND " . $this->getDiscountDateSql('ps')
			. " ORDER BY ps.priority ASC, ps.price ASC LIMIT 1)";

		$discount = "(SELECT pd2.price FROM `" . DB_PREFIX . "product_discount` pd2"
			. " WHERE pd2.product_id = " . $alias . ".product_id"
			. " AND pd2.customer_group_id = '" . $group_id . "'"
			. " AND pd2.quantity = '1'"
			. " AND " . $this->getDiscountDateSql('pd2')
			. " ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1)";

		return "COALESCE(" . $special . ", " . $discount . ", " . $alias . ".price)";
	}

	/**
	 * EXISTS активной акции (OC3: product_special).
	 */
	public function getSpecialExistsSql($alias = 'p') {
		$alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);

		if ($alias === '') {
			$alias = 'p';
		}

		$group_id = (int)$this->config->get('config_customer_group_id');

		return "EXISTS ("
			. "SELECT 1 FROM `" . DB_PREFIX . "product_special` ps"
			. " WHERE ps.product_id = " . $alias . ".product_id"
			. " AND ps.customer_group_id = '" . $group_id . "'"
			. " AND " . $this->getDiscountDateSql('ps')
			. ")";
	}

	public function getPriceLimits($category_id, $params = '', $manufacturer_id = 0, $special = false) {
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		unset($params['p']);

		if ($special) {
			unset($params['d']);
		}

		$manufacturer_id = (int)$manufacturer_id;
		$store_id = (int)$this->config->get('config_store_id');
		$price_sql = $this->getProductPriceExpression('p');

		if ($manufacturer_id > 0 || $special) {
			$sql = "SELECT MIN(" . $price_sql . ") AS `min`, MAX(" . $price_sql . ") AS `max`
				FROM `" . DB_PREFIX . "product` p
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				WHERE p.status = '1'
					AND p.date_available <= NOW()
					AND p2s.store_id = '" . $store_id . "'";

			if ($special) {
				$sql .= " AND " . $this->getSpecialExistsSql('p');
			} else {
				$sql .= " AND p.manufacturer_id = '" . $manufacturer_id . "'";
			}

			$sql .= $this->getProductSQL($params, 0);
		} else {
			$category_ids = $this->normalizeCategoryIds($category_id);

			$sql = "SELECT MIN(" . $price_sql . ") AS `min`, MAX(" . $price_sql . ") AS `max`
				FROM `" . DB_PREFIX . "product` p
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				WHERE p.status = '1'
					AND p.date_available <= NOW()
					AND p2s.store_id = '" . $store_id . "'
					AND " . $this->categoryIdCondition('p2c.category_id', $category_ids);

			$sql .= $this->getProductSQL($params, $category_ids);
		}

		$query = $this->db->query($sql);

		return [
			'min' => isset($query->row['min']) ? (float)$query->row['min'] : 0,
			'max' => isset($query->row['max']) ? (float)$query->row['max'] : 0,
		];
	}

	/**
	 * SQL-фрагмент AND … для model/catalog/product и getCounters.
	 *
	 * Важно: фрагмент вставляется в середину WHERE ($sql .= …), поэтому нельзя
	 * возвращать INNER JOIN к p — синтаксически сломается запрос. Вместо тяжёлого
	 * DEPENDENT SUBQUERY `p.product_id IN (SELECT … JOIN …)` — серия EXISTS
	 * (semi-join): по одной опции, с category_id + option_id + value_id IN (…).
	 * Для строки товара срабатывает PK (product_id, option_id, value_id, category_id);
	 * при старте от фильтра оптимизатор может взять KEY cat_opt_val.
	 *
	 * @param  string|array $params
	 * @param  int|int[]    $category_id  категория или ветка (родитель+дети); 0 — без ограничения
	 */
	public function getProductSQL($params = [], $category_id = 0) {
		$this->ensureSchema();
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		$category_ids = [];

		if (is_array($category_id)) {
			$category_ids = $this->normalizeCategoryIds($category_id);
		} elseif ((int)$category_id > 0) {
			$category_ids = $this->getCategoryBranchIds((int)$category_id);
		}

		$sql = '';

		foreach ($params as $option_id => $values) {
			if ($option_id === 'p') {
				$range = wt_filter_range_parts($values[0]);

				if ($range) {
					$currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
					$value = (float)$this->currency->getValue($currency);

					if ($value <= 0) {
						$value = 1;
					}

					$price_from = floor((float)$range['from'] / $value);
					$price_to = ceil((float)$range['to'] / $value);
					$price_sql = $this->getProductPriceExpression('p');
					$sql .= " AND (" . $price_sql . ") BETWEEN '" . (float)$price_from . "' AND '" . (float)$price_to . "'";
				}

				unset($params[$option_id]);
			} elseif ($option_id === 'm') {
				$ids = array_map('intval', $values);

				if ($ids) {
					$sql .= " AND p.manufacturer_id IN (" . implode(',', $ids) . ")";
				}

				unset($params[$option_id]);
			} elseif ($option_id === 's') {
				$stock = $values[0];

				if ($stock === 'in') {
					$sql .= " AND p.quantity > '0'";
				} elseif ($stock === 'out') {
					$sql .= " AND p.quantity < '1'";
				}

				unset($params[$option_id]);
			} elseif ($option_id === 'd') {
				if (in_array('1', array_map('strval', $values))) {
					$sql .= " AND " . $this->getSpecialExistsSql('p');
				}

				unset($params[$option_id]);
			} elseif (!wt_filter_is_id($option_id)) {
				unset($params[$option_id]);
			}
		}

		if ($params) {
			$count = 1;

			foreach ($params as $option_id => $values) {
				$alias = 'wfv' . $count;
				$conditions = [];

				// Порядок: category_id → option_id → value — под KEY cat_opt_val
				if ($category_ids) {
					$conditions[] = $this->categoryIdCondition($alias . '.category_id', $category_ids);
				}

				$conditions[] = $alias . ".option_id = '" . (int)$option_id . "'";

				if (wt_filter_is_range($values[0])) {
					$range = wt_filter_range_parts($values[0]);

					if (!$range) {
						$count++;
						continue;
					}

					$conditions[] = $alias . ".slide_value_min BETWEEN '" . (float)$range['from'] . "' AND '" . (float)$range['to'] . "'";
					$conditions[] = $alias . ".slide_value_max BETWEEN '" . (float)$range['from'] . "' AND '" . (float)$range['to'] . "'";
				} else {
					$ids = [];

					foreach ($values as $value_id) {
						$ids[] = "'" . $this->db->escape((string)$value_id) . "'";
					}

					if (!$ids) {
						$count++;
						continue;
					}

					$conditions[] = $alias . ".value_id IN (" . implode(',', $ids) . ")";
				}

				$sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` " . $alias
					. " WHERE " . $alias . ".product_id = p.product_id"
					. " AND " . implode(' AND ', $conditions) . ")";

				$count++;
			}
		}

		return $sql;
	}

	public function getCounters($category_id, $params = '', $manufacturer_id = 0, $special = false) {
		$this->ensureSchema();
		$this->load->config('wt_filter');
		$this->load->helper('wt_filter');

		if (!is_array($params)) {
			$params = $this->decodeParams($params);
		}

		$category_id = (int)$category_id;
		$manufacturer_id = (int)$manufacturer_id;
		$special = (bool)$special;
		$store_id = (int)$this->config->get('config_store_id');
		$category_ids = ($manufacturer_id > 0 || $special || $category_id < 1) ? [] : $this->getCategoryBranchIds($category_id);
		$sql_category_ids = $category_ids;

		if ($manufacturer_id > 0) {
			unset($params['m']);
		}

		if ($special) {
			unset($params['d']);
		}

		// Счётчики не кэшируем: остаток, special, статус и бренд меняются часто,
		// а устаревшие цифры у чекбоксов хуже, чем лишний COUNT по индексу.
		$counters = [];

		if ($manufacturer_id > 0) {
			$scope_sql = " AND p.manufacturer_id = '" . $manufacturer_id . "'";
		} elseif ($special) {
			$scope_sql = " AND " . $this->getSpecialExistsSql('p');
		} else {
			$scope_sql = " AND " . $this->categoryIdCondition('wfv.category_id', $category_ids);
		}

		// Значения опций
		$sql = "SELECT wfv.option_id, wfv.value_id, COUNT(DISTINCT p.product_id) AS total
			FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` wfv
			INNER JOIN `" . DB_PREFIX . "product` p ON (wfv.product_id = p.product_id)
			INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			WHERE p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . $store_id . "'
				AND wfv.value_id > '0'" . $scope_sql;

		$sql .= $this->getProductSQL($params, $sql_category_ids);
		$sql .= " GROUP BY wfv.option_id, wfv.value_id";

		$query = $this->db->query($sql);

		foreach ($query->rows as $row) {
			$counters[$row['option_id'] . $row['value_id']] = (int)$row['total'];
		}

		foreach ($params as $option_id => $values) {
			if ($option_id === 'p' || $option_id === 'm' || $option_id === 's' || $option_id === 'd' || wt_filter_is_range($values[0])) {
				continue;
			}

			$subset = $params;
			unset($subset[$option_id]);

			$sql = "SELECT wfv.option_id, wfv.value_id, COUNT(DISTINCT p.product_id) AS total
				FROM `" . DB_PREFIX . "wt_filter_option_value_to_product` wfv
				INNER JOIN `" . DB_PREFIX . "product` p ON (wfv.product_id = p.product_id)
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				WHERE p.status = '1'
					AND p.date_available <= NOW()
					AND p2s.store_id = '" . $store_id . "'
					AND wfv.option_id = '" . (int)$option_id . "'
					AND wfv.value_id > '0'" . $scope_sql;

			$sql .= $this->getProductSQL($subset, $sql_category_ids);
			$sql .= " GROUP BY wfv.option_id, wfv.value_id";

			$query = $this->db->query($sql);

			foreach ($query->rows as $row) {
				$counters[$row['option_id'] . $row['value_id']] = (int)$row['total'];
			}
		}

		// Производители — на категориях и акциях
		if ($manufacturer_id < 1) {
			$m_params = $params;
			unset($m_params['m']);

			if ($special) {
				$sql = "SELECT p.manufacturer_id AS value_id, COUNT(DISTINCT p.product_id) AS total
					FROM `" . DB_PREFIX . "product` p
					INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
					WHERE p.status = '1'
						AND p.date_available <= NOW()
						AND p2s.store_id = '" . $store_id . "'
						AND p.manufacturer_id > '0'
						AND " . $this->getSpecialExistsSql('p');

				$sql .= $this->getProductSQL($m_params, 0);
			} else {
				$sql = "SELECT p.manufacturer_id AS value_id, COUNT(DISTINCT p.product_id) AS total
					FROM `" . DB_PREFIX . "product` p
					INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
					INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
					WHERE p.status = '1'
						AND p.date_available <= NOW()
						AND p2s.store_id = '" . $store_id . "'
						AND " . $this->categoryIdCondition('p2c.category_id', $category_ids) . "
						AND p.manufacturer_id > '0'";

				$sql .= $this->getProductSQL($m_params, $category_ids);
			}

			$sql .= " GROUP BY p.manufacturer_id";

			$query = $this->db->query($sql);

			foreach ($query->rows as $row) {
				$counters['m' . $row['value_id']] = (int)$row['total'];
			}
		}

		$s_params = $params;
		unset($s_params['s']);

		if ($manufacturer_id > 0 || $special) {
			$sql = "SELECT IF(p.quantity > 0, 'in', 'out') AS value_id, COUNT(DISTINCT p.product_id) AS total
				FROM `" . DB_PREFIX . "product` p
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				WHERE p.status = '1'
					AND p.date_available <= NOW()
					AND p2s.store_id = '" . $store_id . "'";

			if ($special) {
				$sql .= " AND " . $this->getSpecialExistsSql('p');
			} else {
				$sql .= " AND p.manufacturer_id = '" . $manufacturer_id . "'";
			}

			$sql .= $this->getProductSQL($s_params, 0);
		} else {
			$sql = "SELECT IF(p.quantity > 0, 'in', 'out') AS value_id, COUNT(DISTINCT p.product_id) AS total
				FROM `" . DB_PREFIX . "product` p
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				WHERE p.status = '1'
					AND p.date_available <= NOW()
					AND p2s.store_id = '" . $store_id . "'
					AND " . $this->categoryIdCondition('p2c.category_id', $category_ids);

			$sql .= $this->getProductSQL($s_params, $category_ids);
		}

		$sql .= " GROUP BY value_id";

		$query = $this->db->query($sql);

		foreach ($query->rows as $row) {
			$counters['s' . $row['value_id']] = (int)$row['total'];
		}

		// Акции — не на странице акций
		if (!$special && $this->config->get('module_wt_filter_discount')) {
			$d_params = $params;
			unset($d_params['d']);

			if ($manufacturer_id > 0) {
				$sql = "SELECT COUNT(DISTINCT p.product_id) AS total
					FROM `" . DB_PREFIX . "product` p
					INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
					WHERE p.status = '1'
						AND p.date_available <= NOW()
						AND p2s.store_id = '" . $store_id . "'
						AND p.manufacturer_id = '" . $manufacturer_id . "'
						AND " . $this->getSpecialExistsSql('p');

				$sql .= $this->getProductSQL($d_params, 0);
			} else {
				$sql = "SELECT COUNT(DISTINCT p.product_id) AS total
					FROM `" . DB_PREFIX . "product` p
					INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
					INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
					WHERE p.status = '1'
						AND p.date_available <= NOW()
						AND p2s.store_id = '" . $store_id . "'
						AND " . $this->categoryIdCondition('p2c.category_id', $category_ids) . "
						AND " . $this->getSpecialExistsSql('p');

				$sql .= $this->getProductSQL($d_params, $category_ids);
			}

			$query = $this->db->query($sql);
			$counters['d1'] = isset($query->row['total']) ? (int)$query->row['total'] : 0;
		}

		return $counters;
	}
}