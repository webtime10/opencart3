<?php
class ModelExtensionModuleWtExchange extends Model {
	private $category_delimiter = '|';

	public function getProductRows(array $fields, $language_id, array $filter = array()) {
		$registry = $this->registryFields();
		$selected = array();

		foreach ($fields as $code) {
			if (isset($registry[$code])) {
				$selected[] = $code;
			}
		}

		if (!$selected) {
			return array();
		}

		$this->category_delimiter = !empty($filter['category_delimiter']) ? $filter['category_delimiter'] : '|';

		$meta_h1_sql = $this->hasMetaH1Column() ? ', pd.meta_h1' : '';

		$sql = "SELECT p.*, pd.name, pd.description, pd.tag, pd.meta_title, pd.meta_description, pd.meta_keyword" . $meta_h1_sql . ",
			m.name AS manufacturer_name, ss.name AS stock_status_name
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
			LEFT JOIN `" . DB_PREFIX . "stock_status` ss ON (p.stock_status_id = ss.stock_status_id AND ss.language_id = '" . (int)$language_id . "')
			WHERE 1";

		$category_ids = $this->resolveCategoryFilter($filter);
		if ($category_ids) {
			$sql .= " AND p.product_id IN (
				SELECT p2c.product_id FROM `" . DB_PREFIX . "product_to_category` p2c
				WHERE p2c.category_id IN (" . implode(',', $category_ids) . ")
			)";
		}

		$sql .= " ORDER BY p.product_id ASC";

		$query = $this->db->query($sql);
		$rows = array();

		foreach ($query->rows as $product) {
			$row = array();
			foreach ($selected as $code) {
				$row[$code] = $this->mapProductValue($code, $product, $language_id);
			}
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Expand selected categories (optionally with children) to ID list.
	 */
	public function resolveCategoryFilter(array $filter) {
		if (empty($filter['category_ids']) || !is_array($filter['category_ids'])) {
			return array();
		}

		$ids = array();
		foreach ($filter['category_ids'] as $id) {
			$id = (int)$id;
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}

		if (!$ids) {
			return array();
		}

		$include_children = !isset($filter['include_children']) || $filter['include_children'];

		if ($include_children) {
			$query = $this->db->query("SELECT DISTINCT category_id FROM `" . DB_PREFIX . "category_path`
				WHERE path_id IN (" . implode(',', $ids) . ")");
			foreach ($query->rows as $row) {
				$ids[(int)$row['category_id']] = (int)$row['category_id'];
			}
		}

		return array_values($ids);
	}

	public function findProductIdByKey($key_field, $value) {
		$value = trim((string)$value);
		if ($value === '') {
			return 0;
		}

		if ($key_field === '_ID_') {
			$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$value . "' LIMIT 1");
		} elseif ($key_field === '_SKU_') {
			$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE sku = '" . $this->db->escape($value) . "' LIMIT 1");
		} else {
			$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE model = '" . $this->db->escape($value) . "' LIMIT 1");
		}

		return $query->num_rows ? (int)$query->row['product_id'] : 0;
	}

	public function resolveImportFields(array $row, array $preferred = array()) {
		$registry = $this->registryFields();
		$fields = array();

		foreach ($preferred as $code) {
			if (isset($registry[$code]) && !empty($registry[$code]['import']) && !in_array($code, $fields, true)) {
				$fields[] = $code;
			}
		}

		foreach (array_keys($row) as $code) {
			if (isset($registry[$code]) && !empty($registry[$code]['import']) && !in_array($code, $fields, true)) {
				$fields[] = $code;
			}
		}

		return $fields;
	}

	public function updateProductFromRow($product_id, array $row, array $fields, $language_id, array $options = array()) {
		$parsed = $this->parseProductRow($row, $fields, $options);

		if ($parsed['product_sets']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $parsed['product_sets']) . ", date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
		}

		$this->saveProductDescription($product_id, $language_id, $parsed['description'], isset($parsed['values']['name']) ? $parsed['values']['name'] : '');
		$this->applyProductRelations($product_id, $parsed['relations'], $language_id, $options);
	}

	public function insertProductFromRow(array $row, array $fields, $language_id, array $defaults = array(), array $options = array()) {
		$parsed = $this->parseProductRow($row, $fields, $options);
		$values = $parsed['values'];

		$model = isset($values['model']) ? $values['model'] : '';
		if ($model === '' && isset($row['_MODEL_'])) {
			$model = trim((string)$row['_MODEL_']);
		}
		if ($model === '') {
			$model = 'WT-' . substr(md5(uniqid('', true)), 0, 8);
		}

		$name = isset($values['name']) ? $values['name'] : $model;
		$minimum = isset($defaults['minimum']) ? (int)$defaults['minimum'] : 1;
		$subtract = isset($defaults['subtract']) ? (int)$defaults['subtract'] : 1;
		$shipping = isset($values['shipping']) ? (int)$values['shipping'] : (isset($defaults['shipping']) ? (int)$defaults['shipping'] : 1);
		$status = isset($values['status']) ? (int)$values['status'] : (isset($defaults['status']) ? (int)$defaults['status'] : 1);
		$stock_status_id = isset($values['stock_status_id']) ? (int)$values['stock_status_id'] : 0;

		$this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET
			model = '" . $this->db->escape($model) . "',
			sku = '" . $this->db->escape(isset($values['sku']) ? $values['sku'] : '') . "',
			upc = '" . $this->db->escape(isset($values['upc']) ? $values['upc'] : '') . "',
			ean = '" . $this->db->escape(isset($values['ean']) ? $values['ean'] : '') . "',
			jan = '" . $this->db->escape(isset($values['jan']) ? $values['jan'] : '') . "',
			isbn = '" . $this->db->escape(isset($values['isbn']) ? $values['isbn'] : '') . "',
			mpn = '" . $this->db->escape(isset($values['mpn']) ? $values['mpn'] : '') . "',
			location = '" . $this->db->escape(isset($values['location']) ? $values['location'] : '') . "',
			quantity = '" . (isset($values['quantity']) ? (int)$values['quantity'] : 0) . "',
			minimum = '" . (int)$minimum . "',
			subtract = '" . (int)$subtract . "',
			stock_status_id = '" . (int)$stock_status_id . "',
			date_available = NOW(),
			manufacturer_id = '" . (isset($values['manufacturer_id']) ? (int)$values['manufacturer_id'] : 0) . "',
			shipping = '" . (int)$shipping . "',
			price = '" . (isset($values['price']) ? (float)$values['price'] : 0) . "',
			points = '" . (isset($values['points']) ? (int)$values['points'] : 0) . "',
			weight = '" . (isset($values['weight']) ? (float)$values['weight'] : 0) . "',
			weight_class_id = '" . (int)$this->config->get('config_weight_class_id') . "',
			length = '" . (isset($values['length']) ? (float)$values['length'] : 0) . "',
			width = '" . (isset($values['width']) ? (float)$values['width'] : 0) . "',
			height = '" . (isset($values['height']) ? (float)$values['height'] : 0) . "',
			length_class_id = '" . (int)$this->config->get('config_length_class_id') . "',
			status = '" . (int)$status . "',
			tax_class_id = '0',
			sort_order = '" . (isset($values['sort_order']) ? (int)$values['sort_order'] : 0) . "',
			image = '" . $this->db->escape(isset($values['image']) ? $values['image'] : '') . "',
			date_added = NOW(),
			date_modified = NOW()");

		$product_id = (int)$this->db->getLastId();

		$description = $parsed['description'];
		if (!isset($description['name'])) {
			$description['name'] = $name;
		}
		$this->saveProductDescription($product_id, $language_id, $description, $name);

		$store_ids = !empty($parsed['relations']['store_ids']) ? $parsed['relations']['store_ids'] : array(0);
		foreach ($store_ids as $store_id) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . $product_id . "', store_id = '" . (int)$store_id . "'");
		}

		$this->applyProductRelations($product_id, $parsed['relations'], $language_id, $options, false);

		return $product_id;
	}

	/**
	 * Map CSV column names back to field codes using saved mappings.
	 */
	public function normalizeRowKeys(array $row, array $mappings) {
		if (!$mappings) {
			return $row;
		}

		$reverse = array();
		foreach ($mappings as $code => $csv_name) {
			$csv_name = trim((string)$csv_name);
			if ($csv_name !== '') {
				$reverse[$csv_name] = $code;
			}
		}

		$normalized = array();
		foreach ($row as $key => $value) {
			if (isset($reverse[$key])) {
				$normalized[$reverse[$key]] = $value;
			} else {
				$normalized[$key] = $value;
			}
		}

		return $normalized;
	}

	private function parseProductRow(array $row, array $fields, array $options = array()) {
		$registry = $this->registryFields();
		$product_sets = array();
		$description = array();
		$values = array();
		$relations = array(
			'category_ids'  => array(),
			'images'          => array(),
			'seo_keyword'     => '',
			'store_ids'       => array(),
			'attributes'      => array(),
			'stock_status'    => '',
			'discounts'       => array(),
			'specials'        => array(),
			'options'         => array(),
			'filters'         => array(),
			'rewards'         => array()
		);

		if (!$fields) {
			$fields = $this->resolveImportFields($row);
		}

		$row_language_id = isset($options['language_id']) ? (int)$options['language_id'] : (int)$this->config->get('config_language_id');
		$category_delimiter = !empty($options['category_delimiter']) ? $options['category_delimiter'] : ' > ';
		$create_categories = !empty($options['create_categories']);
		$download_images = !empty($options['download_images']);

		foreach ($fields as $code) {
			if (!isset($row[$code]) || $row[$code] === '' || !isset($registry[$code])) {
				continue;
			}

			$value = $this->castValue($row[$code], $registry[$code]['type']);

			switch ($code) {
				case '_MODEL_':
					$product_sets[] = "`model` = '" . $this->db->escape($value) . "'";
					$values['model'] = $value;
					break;
				case '_SKU_':
					$product_sets[] = "`sku` = '" . $this->db->escape($value) . "'";
					$values['sku'] = $value;
					break;
				case '_EAN_':
					$product_sets[] = "`ean` = '" . $this->db->escape($value) . "'";
					$values['ean'] = $value;
					break;
				case '_JAN_':
					$product_sets[] = "`jan` = '" . $this->db->escape($value) . "'";
					$values['jan'] = $value;
					break;
				case '_ISBN_':
					$product_sets[] = "`isbn` = '" . $this->db->escape($value) . "'";
					$values['isbn'] = $value;
					break;
				case '_MPN_':
					$product_sets[] = "`mpn` = '" . $this->db->escape($value) . "'";
					$values['mpn'] = $value;
					break;
				case '_UPC_':
					$product_sets[] = "`upc` = '" . $this->db->escape($value) . "'";
					$values['upc'] = $value;
					break;
				case '_LOCATION_':
					$product_sets[] = "`location` = '" . $this->db->escape($value) . "'";
					$values['location'] = $value;
					break;
				case '_PRICE_':
					$product_sets[] = "`price` = '" . (float)$value . "'";
					$values['price'] = (float)$value;
					break;
				case '_POINTS_':
					$product_sets[] = "`points` = '" . (int)$value . "'";
					$values['points'] = (int)$value;
					break;
				case '_QUANTITY_':
					$product_sets[] = "`quantity` = '" . (int)$value . "'";
					$values['quantity'] = (int)$value;
					break;
				case '_STOCK_STATUS_ID_':
					$product_sets[] = "`stock_status_id` = '" . (int)$value . "'";
					$values['stock_status_id'] = (int)$value;
					break;
				case '_STOCK_STATUS_':
					$relations['stock_status'] = $value;
					break;
				case '_SHIPPING_':
					$product_sets[] = "`shipping` = '" . (int)$value . "'";
					$values['shipping'] = (int)$value;
					break;
				case '_LENGTH_':
					$product_sets[] = "`length` = '" . (float)$value . "'";
					$values['length'] = (float)$value;
					break;
				case '_WIDTH_':
					$product_sets[] = "`width` = '" . (float)$value . "'";
					$values['width'] = (float)$value;
					break;
				case '_HEIGHT_':
					$product_sets[] = "`height` = '" . (float)$value . "'";
					$values['height'] = (float)$value;
					break;
				case '_STATUS_':
					$product_sets[] = "`status` = '" . (int)$value . "'";
					$values['status'] = (int)$value;
					break;
				case '_SORT_ORDER_':
					$product_sets[] = "`sort_order` = '" . (int)$value . "'";
					$values['sort_order'] = (int)$value;
					break;
				case '_WEIGHT_':
					$product_sets[] = "`weight` = '" . (float)$value . "'";
					$values['weight'] = (float)$value;
					break;
				case '_IMAGE_':
					$image = $download_images ? $this->resolveImagePath($value) : $value;
					$product_sets[] = "`image` = '" . $this->db->escape($image) . "'";
					$values['image'] = $image;
					break;
				case '_NAME_':
					$description['name'] = $value;
					$values['name'] = $value;
					break;
				case '_DESCRIPTION_':
					$description['description'] = $value;
					$values['description'] = $value;
					break;
				case '_PRODUCT_TAG_':
					$description['tag'] = $value;
					$values['tag'] = $value;
					break;
				case '_META_TITLE_':
					$description['meta_title'] = $value;
					$values['meta_title'] = $value;
					break;
				case '_META_DESCRIPTION_':
					$description['meta_description'] = $value;
					$values['meta_description'] = $value;
					break;
				case '_META_KEYWORDS_':
				case '_META_KEYWORD_':
					$description['meta_keyword'] = $value;
					$values['meta_keyword'] = $value;
					break;
				case '_META_H1_':
					if ($this->hasMetaH1Column()) {
						$description['meta_h1'] = $value;
						$values['meta_h1'] = $value;
					}
					break;
				case '_MANUFACTURER_':
					$manufacturer_id = $this->getOrCreateManufacturer($value);
					if ($manufacturer_id) {
						$product_sets[] = "`manufacturer_id` = '" . (int)$manufacturer_id . "'";
						$values['manufacturer_id'] = $manufacturer_id;
					}
					break;
				case '_CATEGORY_ID_':
					$relations['category_ids'] = array_merge($relations['category_ids'], $this->parseCategoryIds($value));
					break;
				case '_CATEGORY_':
					foreach ($this->splitListValues($value, '|') as $path) {
						$category_id = $this->resolveCategoryPathId($path, $row_language_id, $category_delimiter, $create_categories);
						if ($category_id) {
							$relations['category_ids'][] = $category_id;
						}
					}
					break;
				case '_MAIN_CATEGORY_':
					$category_id = $this->resolveCategoryPathId($value, $row_language_id, $category_delimiter, $create_categories);
					if ($category_id) {
						array_unshift($relations['category_ids'], $category_id);
					}
					break;
				case '_IMAGES_':
					$relations['images'] = $download_images ? WtExchangeImageFetch::resolveList($value) : $this->parseImageList($value, false);
					break;
				case '_PRODUCT_IMAGES_':
					$relations['images'] = $download_images ? WtExchangeImageFetch::resolveList($value) : $this->parseImageList($value, true);
					break;
				case '_SEO_KEYWORD_':
					$relations['seo_keyword'] = $value;
					break;
				case '_STORE_ID_':
					$relations['store_ids'] = $this->parseStoreIds($value);
					break;
				case '_ATTRIBUTES_':
					$relations['attributes'] = $this->parseAttributeLines($value);
					break;
				case '_DISCOUNT_':
					$relations['discounts'] = $this->parseDiscountLines($value);
					break;
				case '_SPECIAL_':
					$relations['specials'] = $this->parseSpecialLines($value);
					break;
				case '_OPTIONS_':
					$relations['options'] = $this->parseOptionLines($value);
					break;
				case '_FILTERS_':
					$relations['filters'] = $this->parseFilterList($value);
					break;
				case '_REWARD_POINTS_':
					$relations['rewards'] = $this->parseRewardList($value);
					break;
			}
		}

		$relations['category_ids'] = array_values(array_unique(array_map('intval', $relations['category_ids'])));

		return array(
			'product_sets' => $product_sets,
			'description'  => $description,
			'values'       => $values,
			'relations'    => $relations
		);
	}

	private function saveProductDescription($product_id, $language_id, array $description, $fallback_name = '') {
		if (!$description) {
			return;
		}

		if (!isset($description['name']) && $fallback_name !== '') {
			$description['name'] = $fallback_name;
		}

		if (!isset($description['meta_title']) && !empty($description['name'])) {
			$description['meta_title'] = $description['name'];
		}

		$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_description`
			WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

		if ($query->num_rows) {
			$sets = array();
			foreach ($description as $column => $value) {
				$sets[] = "`" . $column . "` = '" . $this->db->escape($value) . "'";
			}
			$this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET " . implode(', ', $sets) . "
				WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "'");
			return;
		}

		$name = isset($description['name']) ? $description['name'] : $fallback_name;
		$this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET
			product_id = '" . (int)$product_id . "',
			language_id = '" . (int)$language_id . "',
			name = '" . $this->db->escape($name) . "',
			description = '" . $this->db->escape(isset($description['description']) ? $description['description'] : '') . "',
			tag = '" . $this->db->escape(isset($description['tag']) ? $description['tag'] : '') . "',
			meta_title = '" . $this->db->escape(isset($description['meta_title']) ? $description['meta_title'] : $name) . "',
			meta_description = '" . $this->db->escape(isset($description['meta_description']) ? $description['meta_description'] : '') . "',
			meta_keyword = '" . $this->db->escape(isset($description['meta_keyword']) ? $description['meta_keyword'] : '') . "'
			" . ($this->hasMetaH1Column() && isset($description['meta_h1']) ? ", meta_h1 = '" . $this->db->escape($description['meta_h1']) . "'" : '') . "");
	}

	private function applyProductRelations($product_id, array $relations, $language_id, array $options = array(), $sync_stores = true) {
		if (!empty($relations['stock_status'])) {
			$stock_status_id = $this->findStockStatusIdByName($relations['stock_status'], $language_id);
			if ($stock_status_id) {
				$this->db->query("UPDATE `" . DB_PREFIX . "product` SET stock_status_id = '" . (int)$stock_status_id . "' WHERE product_id = '" . (int)$product_id . "'");
			}
		}

		if (!empty($relations['category_ids'])) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "'");
			foreach ($relations['category_ids'] as $category_id) {
				if ((int)$category_id > 0) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
				}
			}
		}

		if (!empty($relations['images'])) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "'");
			foreach ($relations['images'] as $image_row) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET
					product_id = '" . (int)$product_id . "',
					image = '" . $this->db->escape($image_row['image']) . "',
					sort_order = '" . (int)$image_row['sort_order'] . "'");
			}
		}

		if ($relations['seo_keyword'] !== '') {
			$this->setProductSeoKeyword($product_id, $relations['seo_keyword']);
		}

		if ($sync_stores && !empty($relations['store_ids'])) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$product_id . "'");
			foreach ($relations['store_ids'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		if (!empty($relations['attributes'])) {
			$this->applyProductAttributes($product_id, $relations['attributes'], $language_id);
		}

		if (!empty($relations['discounts'])) {
			$this->applyProductDiscounts($product_id, $relations['discounts']);
		}

		if (!empty($relations['specials'])) {
			$this->applyProductSpecials($product_id, $relations['specials']);
		}

		if (!empty($relations['rewards'])) {
			$this->applyProductRewards($product_id, $relations['rewards']);
		}

		if (!empty($relations['filters'])) {
			$this->applyProductFilters($product_id, $relations['filters'], $language_id);
		}

		if (!empty($relations['options'])) {
			$this->applyProductOptions($product_id, $relations['options'], $language_id);
		}
	}

	private function splitListValues($value, $delimiter = '|') {
		$parts = explode($delimiter, (string)$value);
		$result = array();
		foreach ($parts as $part) {
			$part = trim($part);
			if ($part !== '') {
				$result[] = $part;
			}
		}
		return $result;
	}

	private function parseCategoryIds($value) {
		$value = str_replace(',', '|', (string)$value);
		$ids = array();
		foreach ($this->splitListValues($value, '|') as $part) {
			$ids[] = (int)$part;
		}
		return $ids;
	}

	private function parseStoreIds($value) {
		$value = str_replace('|', ',', (string)$value);
		$ids = array();
		foreach (explode(',', $value) as $part) {
			$part = trim($part);
			if ($part !== '') {
				$ids[] = (int)$part;
			}
		}
		return $ids ? $ids : array(0);
	}

	private function parseImageList($value, $with_sort) {
		$images = array();
		$sort = 0;
		foreach ($this->splitListValues($value, '|') as $part) {
			if ($with_sort && strpos($part, ':') !== false) {
				list($image, $image_sort) = explode(':', $part, 2);
				$images[] = array(
					'image'      => trim($image),
					'sort_order' => (int)$image_sort
				);
			} else {
				$images[] = array(
					'image'      => $part,
					'sort_order' => $sort
				);
				$sort++;
			}
		}
		return $images;
	}

	private function parseAttributeLines($value) {
		$attributes = array();
		foreach (preg_split('/\r\n|\r|\n/', (string)$value) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line, 3);
			if (count($parts) < 3) {
				continue;
			}
			$attributes[] = array(
				'group'     => trim($parts[0]),
				'attribute' => trim($parts[1]),
				'text'      => trim($parts[2])
			);
		}
		return $attributes;
	}

	private function resolveCategoryPathId($path, $language_id, $delimiter, $create = false) {
		$category_id = $this->findCategoryIdByPath($path, $language_id, $delimiter);
		if ($category_id || !$create) {
			return $category_id;
		}
		return $this->createCategoryPath($path, $language_id, $delimiter);
	}

	private function createCategoryPath($path, $language_id, $delimiter = ' > ') {
		$names = array();
		foreach (explode($delimiter, (string)$path) as $name) {
			$name = trim($name);
			if ($name !== '') {
				$names[] = $name;
			}
		}
		if (!$names) {
			return 0;
		}

		$parent_id = 0;
		$category_id = 0;
		foreach ($names as $name) {
			$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c
				LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
				WHERE cd.name = '" . $this->db->escape($name) . "' AND c.parent_id = '" . (int)$parent_id . "' LIMIT 1");
			if ($query->num_rows) {
				$category_id = (int)$query->row['category_id'];
			} else {
				$this->entityExchange()->importRow('category', 0, array(
					'_NAME_' => $name,
					'_PARENT_ID_' => $parent_id,
					'_STATUS_' => 1
				), array('_NAME_', '_PARENT_ID_', '_STATUS_'), $language_id, true);
				$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c
					LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
					WHERE cd.name = '" . $this->db->escape($name) . "' AND c.parent_id = '" . (int)$parent_id . "' LIMIT 1");
				$category_id = $query->num_rows ? (int)$query->row['category_id'] : 0;
			}
			if (!$category_id) {
				return 0;
			}
			$parent_id = $category_id;
		}
		return $category_id;
	}

	private function resolveImagePath($value) {
		if (!class_exists('WtExchangeImageFetch', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/image_fetch.php');
		}
		return WtExchangeImageFetch::resolve($value);
	}

	private function parseDiscountLines($value) {
		$items = array();
		foreach (preg_split('/\r\n|\r|\n/', (string)$value) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line);
			if (count($parts) < 4) {
				continue;
			}
			$items[] = array(
				'customer_group_id' => (int)$parts[0],
				'quantity'          => (int)$parts[1],
				'priority'          => (int)$parts[2],
				'price'             => (float)str_replace(',', '.', $parts[3]),
				'date_start'        => isset($parts[4]) ? trim($parts[4]) : '0000-00-00',
				'date_end'          => isset($parts[5]) ? trim($parts[5]) : '0000-00-00'
			);
		}
		return $items;
	}

	private function parseSpecialLines($value) {
		$items = array();
		foreach (preg_split('/\r\n|\r|\n/', (string)$value) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line);
			if (count($parts) < 3) {
				continue;
			}
			$items[] = array(
				'customer_group_id' => (int)$parts[0],
				'priority'          => (int)$parts[1],
				'price'             => (float)str_replace(',', '.', $parts[2]),
				'date_start'        => isset($parts[3]) ? trim($parts[3]) : '0000-00-00',
				'date_end'          => isset($parts[4]) ? trim($parts[4]) : '0000-00-00'
			);
		}
		return $items;
	}

	private function parseRewardList($value) {
		$items = array();
		foreach ($this->splitListValues($value, '|') as $part) {
			if (strpos($part, ':') === false) {
				continue;
			}
			list($group_id, $points) = explode(':', $part, 2);
			$items[] = array(
				'customer_group_id' => (int)$group_id,
				'points'            => (int)$points
			);
		}
		return $items;
	}

	private function parseFilterList($value) {
		$items = array();
		foreach ($this->splitListValues($value, '|') as $part) {
			if (strpos($part, ':') === false) {
				continue;
			}
			list($group, $name) = explode(':', $part, 2);
			$items[] = array(
				'group' => trim($group),
				'name'  => trim($name)
			);
		}
		return $items;
	}

	private function parseOptionLines($value) {
		$items = array();
		foreach (preg_split('/\r\n|\r|\n/', (string)$value) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode(':', $line);
			if (count($parts) < 3) {
				continue;
			}
			$item = array(
				'option_name' => trim($parts[0]),
				'type'        => trim($parts[1]),
				'required'    => (int)$parts[2],
				'values'      => array()
			);
			if (count($parts) > 3) {
				$item['values'][] = array(
					'name'          => trim($parts[3]),
					'price_prefix'  => isset($parts[4]) ? substr(trim($parts[4]), 0, 1) : '+',
					'price'         => isset($parts[4]) ? (float)preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $parts[4])) : 0,
					'quantity'      => isset($parts[5]) ? (int)$parts[5] : 0,
					'subtract'      => isset($parts[6]) ? (int)$parts[6] : 0,
					'weight_prefix' => isset($parts[7]) ? substr(trim($parts[7]), 0, 1) : '+',
					'weight'        => isset($parts[7]) ? (float)preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $parts[7])) : 0
				);
			}
			$items[] = $item;
		}
		return $items;
	}

	private function applyProductDiscounts($product_id, array $discounts) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$product_id . "'");
		foreach ($discounts as $row) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET
				product_id = '" . (int)$product_id . "',
				customer_group_id = '" . (int)$row['customer_group_id'] . "',
				quantity = '" . (int)$row['quantity'] . "',
				priority = '" . (int)$row['priority'] . "',
				price = '" . (float)$row['price'] . "',
				date_start = '" . $this->db->escape($row['date_start']) . "',
				date_end = '" . $this->db->escape($row['date_end']) . "'");
		}
	}

	private function applyProductSpecials($product_id, array $specials) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$product_id . "'");
		foreach ($specials as $row) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_special` SET
				product_id = '" . (int)$product_id . "',
				customer_group_id = '" . (int)$row['customer_group_id'] . "',
				priority = '" . (int)$row['priority'] . "',
				price = '" . (float)$row['price'] . "',
				date_start = '" . $this->db->escape($row['date_start']) . "',
				date_end = '" . $this->db->escape($row['date_end']) . "'");
		}
	}

	private function applyProductRewards($product_id, array $rewards) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_reward` WHERE product_id = '" . (int)$product_id . "'");
		foreach ($rewards as $row) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_reward` SET
				product_id = '" . (int)$product_id . "',
				customer_group_id = '" . (int)$row['customer_group_id'] . "',
				points = '" . (int)$row['points'] . "'");
		}
	}

	private function applyProductFilters($product_id, array $filters, $language_id) {
		$filter_ids = array();
		foreach ($filters as $filter) {
			$filter_id = $this->findFilterIdByNames($filter['group'], $filter['name'], $language_id);
			if ($filter_id) {
				$filter_ids[] = $filter_id;
			}
		}
		if (!$filter_ids) {
			return;
		}
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_filter` WHERE product_id = '" . (int)$product_id . "'");
		foreach (array_unique($filter_ids) as $filter_id) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_filter` SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
		}
	}

	private function findFilterIdByNames($group_name, $filter_name, $language_id) {
		$query = $this->db->query("SELECT f.filter_id FROM `" . DB_PREFIX . "filter` f
			LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (f.filter_id = fd.filter_id AND fd.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . (int)$language_id . "')
			WHERE fd.name = '" . $this->db->escape($filter_name) . "' AND fgd.name = '" . $this->db->escape($group_name) . "'
			LIMIT 1");
		return $query->num_rows ? (int)$query->row['filter_id'] : 0;
	}

	private function applyProductOptions($product_id, array $options, $language_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_option` WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_option_value` WHERE product_id = '" . (int)$product_id . "'");

		foreach ($options as $option) {
			$option_id = $this->findOptionIdByName($option['option_name'], $language_id);
			if (!$option_id) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "product_option` SET
				product_id = '" . (int)$product_id . "',
				option_id = '" . (int)$option_id . "',
				value = '',
				required = '" . (int)$option['required'] . "'");
			$product_option_id = (int)$this->db->getLastId();

			foreach ($option['values'] as $value_row) {
				$option_value_id = $this->findOptionValueIdByName($option_id, $value_row['name'], $language_id);
				if (!$option_value_id) {
					continue;
				}
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_option_value` SET
					product_option_id = '" . (int)$product_option_id . "',
					product_id = '" . (int)$product_id . "',
					option_id = '" . (int)$option_id . "',
					option_value_id = '" . (int)$option_value_id . "',
					quantity = '" . (int)$value_row['quantity'] . "',
					subtract = '" . (int)$value_row['subtract'] . "',
					price = '" . (float)$value_row['price'] . "',
					price_prefix = '" . $this->db->escape($value_row['price_prefix']) . "',
					points = '0',
					points_prefix = '+',
					weight = '" . (float)$value_row['weight'] . "',
					weight_prefix = '" . $this->db->escape($value_row['weight_prefix']) . "'");
			}
		}
	}

	private function findOptionIdByName($name, $language_id) {
		$query = $this->db->query("SELECT o.option_id FROM `" . DB_PREFIX . "option` o
			LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id AND od.language_id = '" . (int)$language_id . "')
			WHERE od.name = '" . $this->db->escape($name) . "' LIMIT 1");
		return $query->num_rows ? (int)$query->row['option_id'] : 0;
	}

	private function findOptionValueIdByName($option_id, $name, $language_id) {
		$query = $this->db->query("SELECT ovd.option_value_id FROM `" . DB_PREFIX . "option_value` ov
			LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . (int)$language_id . "')
			WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.name = '" . $this->db->escape($name) . "' LIMIT 1");
		return $query->num_rows ? (int)$query->row['option_value_id'] : 0;
	}

	private function findCategoryIdByPath($path, $language_id, $delimiter = ' > ') {
		$names = array();
		foreach (explode($delimiter, (string)$path) as $name) {
			$name = trim($name);
			if ($name !== '') {
				$names[] = $name;
			}
		}

		if (!$names) {
			return 0;
		}

		$parent_id = 0;
		$category_id = 0;

		foreach ($names as $name) {
			$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c
				LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
				WHERE cd.name = '" . $this->db->escape($name) . "' AND c.parent_id = '" . (int)$parent_id . "'
				LIMIT 1");
			if (!$query->num_rows) {
				return 0;
			}
			$category_id = (int)$query->row['category_id'];
			$parent_id = $category_id;
		}

		return $category_id;
	}

	private function findStockStatusIdByName($name, $language_id) {
		$query = $this->db->query("SELECT stock_status_id FROM `" . DB_PREFIX . "stock_status`
			WHERE language_id = '" . (int)$language_id . "' AND name = '" . $this->db->escape(trim((string)$name)) . "'
			LIMIT 1");
		return $query->num_rows ? (int)$query->row['stock_status_id'] : 0;
	}

	private function setProductSeoKeyword($product_id, $keyword) {
		$query_str = 'product_id=' . (int)$product_id;
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query_str) . "' AND store_id = '0' AND language_id = '0'");
		$keyword = trim((string)$keyword);
		if ($keyword === '') {
			return;
		}
		$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
			store_id = '0',
			language_id = '0',
			`query` = '" . $this->db->escape($query_str) . "',
			keyword = '" . $this->db->escape($keyword) . "'");
	}

	private function applyProductAttributes($product_id, array $attributes, $language_id) {
		foreach ($attributes as $attribute) {
			$attribute_id = $this->findAttributeIdByNames($attribute['group'], $attribute['attribute'], $language_id);
			if (!$attribute_id) {
				continue;
			}

			$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_attribute`
				WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

			if ($query->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "product_attribute` SET text = '" . $this->db->escape($attribute['text']) . "'
					WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language_id . "'");
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute` SET
					product_id = '" . (int)$product_id . "',
					attribute_id = '" . (int)$attribute_id . "',
					language_id = '" . (int)$language_id . "',
					text = '" . $this->db->escape($attribute['text']) . "'");
			}
		}
	}

	private function findAttributeIdByNames($group_name, $attribute_name, $language_id) {
		$query = $this->db->query("SELECT a.attribute_id FROM `" . DB_PREFIX . "attribute` a
			LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (a.attribute_id = ad.attribute_id AND ad.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (a.attribute_group_id = agd.attribute_group_id AND agd.language_id = '" . (int)$language_id . "')
			WHERE ad.name = '" . $this->db->escape($attribute_name) . "' AND agd.name = '" . $this->db->escape($group_name) . "'
			LIMIT 1");
		return $query->num_rows ? (int)$query->row['attribute_id'] : 0;
	}

	public function getWorkDir() {
		$dir = DIR_STORAGE . 'wt_exchange/';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return $dir;
	}

	public function getEntityRows($entity, array $fields, $language_id, array $filter = array()) {
		return $this->entityExchange()->getRows($entity, $fields, $language_id, $filter);
	}

	public function resolveEntityImportFields($entity, array $row, array $preferred = array()) {
		return $this->entityExchange()->resolveImportFields($entity, $row, $preferred);
	}

	public function findEntityIdByKey($entity, $key_field, $value) {
		return $this->entityExchange()->findIdByKey($entity, $key_field, $value);
	}

	public function importEntityRow($entity, $entity_id, array $row, array $fields, $language_id, $is_insert = false) {
		return $this->entityExchange()->importRow($entity, $entity_id, $row, $fields, $language_id, $is_insert);
	}

	private function entityExchange() {
		if (!class_exists('WtExchangeEntityExchange', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/entity_exchange.php');
		}
		return new WtExchangeEntityExchange($this->registry);
	}

	private function mapProductValue($code, array $product, $language_id) {
		$product_id = (int)$product['product_id'];

		switch ($code) {
			case '_ID_':
				return $product_id;
			case '_MAIN_CATEGORY_':
				return $this->getMainCategoryName($product_id, $language_id);
			case '_CATEGORY_':
				return $this->getCategoryPath($product_id, $language_id);
			case '_CATEGORY_ID_':
				return $this->getCategoryIds($product_id);
			case '_NAME_':
				return $product['name'];
			case '_MODEL_':
				return $product['model'];
			case '_SKU_':
				return $product['sku'];
			case '_EAN_':
				return $product['ean'];
			case '_JAN_':
				return $product['jan'];
			case '_ISBN_':
				return $product['isbn'];
			case '_MPN_':
				return $product['mpn'];
			case '_UPC_':
				return $product['upc'];
			case '_MANUFACTURER_':
				return $product['manufacturer_name'];
			case '_LOCATION_':
				return $product['location'];
			case '_PRICE_':
				return $product['price'];
			case '_DISCOUNT_':
				return $this->getProductDiscounts($product_id);
			case '_SPECIAL_':
				return $this->getProductSpecials($product_id);
			case '_OPTIONS_':
				return $this->getProductOptions($product_id, $language_id);
			case '_FILTERS_':
				return $this->getProductFilters($product_id, $language_id);
			case '_POINTS_':
				return $product['points'];
			case '_REWARD_POINTS_':
				return $this->getProductRewards($product_id);
			case '_QUANTITY_':
				return $product['quantity'];
			case '_STOCK_STATUS_':
				return isset($product['stock_status_name']) ? $product['stock_status_name'] : '';
			case '_STOCK_STATUS_ID_':
				return $product['stock_status_id'];
			case '_SHIPPING_':
				return $product['shipping'];
			case '_LENGTH_':
				return $product['length'];
			case '_WIDTH_':
				return $product['width'];
			case '_HEIGHT_':
				return $product['height'];
			case '_WEIGHT_':
				return $product['weight'];
			case '_SEO_KEYWORD_':
				return $this->getSeoKeyword('product_id=' . $product_id);
			case '_META_H1_':
				return isset($product['meta_h1']) ? $product['meta_h1'] : '';
			case '_META_TITLE_':
				return $product['meta_title'];
			case '_META_KEYWORDS_':
			case '_META_KEYWORD_':
				return $product['meta_keyword'];
			case '_META_DESCRIPTION_':
				return $product['meta_description'];
			case '_DESCRIPTION_':
				return $product['description'];
			case '_ATTRIBUTES_':
				return $this->getProductAttributes($product_id, $language_id);
			case '_PRODUCT_TAG_':
				return isset($product['tag']) ? $product['tag'] : '';
			case '_IMAGE_':
				return $product['image'];
			case '_IMAGES_':
				return $this->getAdditionalImages($product_id, false);
			case '_PRODUCT_IMAGES_':
				return $this->getAdditionalImages($product_id, true);
			case '_SORT_ORDER_':
				return $product['sort_order'];
			case '_STATUS_':
				return $product['status'];
			case '_STORE_ID_':
				return $this->getProductStoreIds($product_id);
			case '_URL_':
				return $this->getProductUrl($product_id);
			default:
				return '';
		}
	}

	private function getProductDiscounts($product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$product_id . "' ORDER BY quantity, priority, price");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $row['customer_group_id'] . ':' . $row['quantity'] . ':' . $row['priority'] . ':' . $row['price'] . ':' . $row['date_start'] . ':' . $row['date_end'];
		}
		return implode("\n", $parts);
	}

	private function getProductSpecials($product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$product_id . "' ORDER BY priority, price");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $row['customer_group_id'] . ':' . $row['priority'] . ':' . $row['price'] . ':' . $row['date_start'] . ':' . $row['date_end'];
		}
		return implode("\n", $parts);
	}

	private function getProductOptions($product_id, $language_id) {
		$query = $this->db->query("SELECT o.type, od.name AS option_name, po.required, ovd.name AS value_name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.weight, pov.weight_prefix, pov.points, pov.points_prefix
			FROM `" . DB_PREFIX . "product_option` po
			LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id)
			LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id AND od.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON (po.product_option_id = pov.product_option_id)
			LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . (int)$language_id . "')
			WHERE po.product_id = '" . (int)$product_id . "'
			ORDER BY o.sort_order, pov.product_option_value_id");

		$parts = array();
		foreach ($query->rows as $row) {
			if ($row['value_name'] === null || $row['value_name'] === '') {
				$parts[] = $row['option_name'] . ':' . $row['type'] . ':' . (int)$row['required'];
			} else {
				$parts[] = $row['option_name'] . ':' . $row['type'] . ':' . (int)$row['required'] . ':' . $row['value_name'] . ':' . $row['price_prefix'] . $row['price'] . ':' . (int)$row['quantity'] . ':' . (int)$row['subtract'] . ':' . $row['weight_prefix'] . $row['weight'];
			}
		}
		return implode("\n", $parts);
	}

	private function getProductFilters($product_id, $language_id) {
		$query = $this->db->query("SELECT CONCAT(fgd.name, ':', fd.name) AS filter_name
			FROM `" . DB_PREFIX . "product_filter` pf
			LEFT JOIN `" . DB_PREFIX . "filter` f ON (pf.filter_id = f.filter_id)
			LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (f.filter_id = fd.filter_id AND fd.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . (int)$language_id . "')
			WHERE pf.product_id = '" . (int)$product_id . "'
			ORDER BY fgd.name, fd.name");
		$names = array();
		foreach ($query->rows as $row) {
			if ($row['filter_name'] !== '') {
				$names[] = $row['filter_name'];
			}
		}
		return implode('|', $names);
	}

	private function getProductRewards($product_id) {
		$query = $this->db->query("SELECT customer_group_id, points FROM `" . DB_PREFIX . "product_reward` WHERE product_id = '" . (int)$product_id . "'");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $row['customer_group_id'] . ':' . $row['points'];
		}
		return implode('|', $parts);
	}

	private function getProductAttributes($product_id, $language_id) {
		$query = $this->db->query("SELECT agd.name AS group_name, ad.name AS attribute_name, pa.text
			FROM `" . DB_PREFIX . "product_attribute` pa
			LEFT JOIN `" . DB_PREFIX . "attribute` a ON (pa.attribute_id = a.attribute_id)
			LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (a.attribute_id = ad.attribute_id AND ad.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (a.attribute_group_id = agd.attribute_group_id AND agd.language_id = '" . (int)$language_id . "')
			WHERE pa.product_id = '" . (int)$product_id . "' AND pa.language_id = '" . (int)$language_id . "'
			ORDER BY agd.name, ad.name");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $row['group_name'] . ':' . $row['attribute_name'] . ':' . $row['text'];
		}
		return implode("\n", $parts);
	}

	private function getAdditionalImages($product_id, $with_sort) {
		$query = $this->db->query("SELECT image, sort_order FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $with_sort ? ($row['image'] . ':' . $row['sort_order']) : $row['image'];
		}
		return implode('|', $parts);
	}

	private function getProductStoreIds($product_id) {
		$query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$product_id . "' ORDER BY store_id ASC");
		$ids = array();
		foreach ($query->rows as $row) {
			$ids[] = $row['store_id'];
		}
		return implode(',', $ids);
	}

	private function getProductUrl($product_id) {
		$keyword = $this->getSeoKeyword('product_id=' . (int)$product_id);
		$base = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : (defined('HTTP_CATALOG') ? HTTP_CATALOG : '');
		if ($keyword) {
			return rtrim($base, '/') . '/' . ltrim($keyword, '/');
		}
		return rtrim($base, '/') . '/index.php?route=product/product&product_id=' . (int)$product_id;
	}

	private function hasMetaH1Column() {
		static $has = null;
		if ($has !== null) {
			return $has;
		}
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product_description` LIKE 'meta_h1'");
		$has = (bool)$query->num_rows;
		return $has;
	}

	private function getMainCategoryName($product_id, $language_id) {
		$query = $this->db->query("SELECT cd.name FROM `" . DB_PREFIX . "product_to_category` p2c
			LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (p2c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
			WHERE p2c.product_id = '" . (int)$product_id . "'
			ORDER BY p2c.category_id ASC LIMIT 1");
		return $query->num_rows ? $query->row['name'] : '';
	}

	private function getCategoryPath($product_id, $language_id) {
		$query = $this->db->query("SELECT p2c.category_id FROM `" . DB_PREFIX . "product_to_category` p2c
			WHERE p2c.product_id = '" . (int)$product_id . "'
			ORDER BY p2c.category_id ASC");

		$paths = array();
		$sep = $this->category_delimiter;

		foreach ($query->rows as $row) {
			$path_query = $this->db->query("SELECT cd.name FROM `" . DB_PREFIX . "category_path` cp
				LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cp.path_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
				WHERE cp.category_id = '" . (int)$row['category_id'] . "'
				ORDER BY cp.level ASC");
			$names = array();
			foreach ($path_query->rows as $path_row) {
				if ($path_row['name'] !== '') {
					$names[] = $path_row['name'];
				}
			}
			if ($names) {
				$paths[] = implode($sep, $names);
			}
		}

		return implode('|', $paths);
	}

	private function getCategoryIds($product_id) {
		$query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category`
			WHERE product_id = '" . (int)$product_id . "'
			ORDER BY category_id ASC");
		$ids = array();
		foreach ($query->rows as $row) {
			$ids[] = (int)$row['category_id'];
		}
		return implode('|', $ids);
	}

	private function getSeoKeyword($query_str) {
		$query = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query_str) . "' LIMIT 1");
		return $query->num_rows ? $query->row['keyword'] : '';
	}

	private function getOrCreateManufacturer($name) {
		$name = trim((string)$name);
		if ($name === '') {
			return 0;
		}
		$query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($name) . "' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row['manufacturer_id'];
		}
		$this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($name) . "', sort_order = 0");
		$manufacturer_id = (int)$this->db->getLastId();
		$this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = '" . $manufacturer_id . "', store_id = '0'");
		return $manufacturer_id;
	}

	private function castValue($value, $type) {
		if ($type === 'int') {
			return (int)$value;
		}
		if ($type === 'float') {
			$value = str_replace(',', '.', (string)$value);
			$value = preg_replace('/[^0-9.\-]/', '', $value);
			return (float)$value;
		}
		return (string)$value;
	}

	private function registryFields() {
		$this->loadLibrary();
		return WtExchangeFieldRegistry::productFields();
	}

	private function loadLibrary() {
		if (!class_exists('WtExchangeFieldRegistry', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/field_registry.php');
		}
	}
}
