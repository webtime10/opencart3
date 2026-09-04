<?php
/**
 * Import/export for categories, manufacturers, customers, orders.
 */
class WtExchangeEntityExchange {
	private $db;
	private $config;
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->db = $registry->get('db');
		$this->config = $registry->get('config');
	}

	public function getRows($entity, array $fields, $language_id, array $filter = array()) {
		switch ($entity) {
			case 'category':
				return $this->getCategoryRows($fields, $language_id, $filter);
			case 'manufacturer':
				return $this->getManufacturerRows($fields, $language_id, $filter);
			case 'customer':
				return $this->getCustomerRows($fields, $language_id, $filter);
			case 'order':
				return $this->getOrderRows($fields, $language_id, $filter);
		}
		return array();
	}

	public function resolveImportFields($entity, array $row, array $preferred = array()) {
		$registry = WtExchangeFieldRegistry::entityFields($entity);
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

	public function findIdByKey($entity, $key_field, $value) {
		$value = trim((string)$value);
		if ($value === '') {
			return 0;
		}
		switch ($entity) {
			case 'category':
				if ($key_field === '_ID_') {
					$query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE category_id = '" . (int)$value . "' LIMIT 1");
					return $query->num_rows ? (int)$query->row['category_id'] : 0;
				}
				$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c
					LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')
					WHERE cd.name = '" . $this->db->escape($value) . "' LIMIT 1");
				return $query->num_rows ? (int)$query->row['category_id'] : 0;
			case 'customer':
				if ($key_field === '_ID_') {
					$query = $this->db->query("SELECT customer_id FROM `" . DB_PREFIX . "customer` WHERE customer_id = '" . (int)$value . "' LIMIT 1");
					return $query->num_rows ? (int)$query->row['customer_id'] : 0;
				}
				$query = $this->db->query("SELECT customer_id FROM `" . DB_PREFIX . "customer` WHERE email = '" . $this->db->escape($value) . "' LIMIT 1");
				return $query->num_rows ? (int)$query->row['customer_id'] : 0;
			case 'order':
				$query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$value . "' LIMIT 1");
				return $query->num_rows ? (int)$query->row['order_id'] : 0;
			case 'manufacturer':
				if ($key_field === '_ID_') {
					$query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int)$value . "' LIMIT 1");
					return $query->num_rows ? (int)$query->row['manufacturer_id'] : 0;
				}
				$query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($value) . "' LIMIT 1");
				return $query->num_rows ? (int)$query->row['manufacturer_id'] : 0;
		}
		return 0;
	}

	public function importRow($entity, $entity_id, array $row, array $fields, $language_id, $is_insert = false) {
		switch ($entity) {
			case 'category':
				return $this->importCategoryRow($entity_id, $row, $fields, $language_id, $is_insert);
			case 'manufacturer':
				return $this->importManufacturerRow($entity_id, $row, $fields, $language_id, $is_insert);
			case 'customer':
				return $this->importCustomerRow($entity_id, $row, $fields, $language_id, $is_insert);
			case 'order':
				return $this->importOrderRow($entity_id, $row, $fields, $language_id);
		}
		return false;
	}

	private function getCategoryRows(array $fields, $language_id, array $filter = array()) {
		$meta_h1 = $this->hasCategoryMetaH1() ? ', cd.meta_h1' : '';
		$sql = "SELECT c.*, cd.name, cd.description, cd.meta_title, cd.meta_description, cd.meta_keyword" . $meta_h1 . "
			FROM `" . DB_PREFIX . "category` c
			LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
			WHERE 1";
		if (!empty($filter['parent_id'])) {
			$sql .= " AND c.parent_id = '" . (int)$filter['parent_id'] . "'";
		}
		if (isset($filter['status']) && $filter['status'] !== '') {
			$sql .= " AND c.status = '" . (int)$filter['status'] . "'";
		}
		$sql .= " ORDER BY c.category_id ASC";
		$query = $this->db->query($sql);
		$rows = array();
		foreach ($query->rows as $item) {
			$row = array();
			foreach ($fields as $code) {
				$row[$code] = $this->mapCategoryValue($code, $item, $language_id);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function mapCategoryValue($code, array $item, $language_id) {
		switch ($code) {
			case '_ID_': return (int)$item['category_id'];
			case '_PARENT_ID_': return (int)$item['parent_id'];
			case '_NAME_': return $item['name'];
			case '_DESCRIPTION_': return $item['description'];
			case '_META_TITLE_': return $item['meta_title'];
			case '_META_H1_': return isset($item['meta_h1']) ? $item['meta_h1'] : '';
			case '_META_KEYWORDS_': return $item['meta_keyword'];
			case '_META_DESCRIPTION_': return $item['meta_description'];
			case '_IMAGE_': return $item['image'];
			case '_TOP_': return $item['top'];
			case '_COLUMN_': return $item['column'];
			case '_SORT_ORDER_': return $item['sort_order'];
			case '_STATUS_': return $item['status'];
			case '_SEO_KEYWORD_': return $this->getSeoKeyword('category_id=' . (int)$item['category_id']);
			case '_STORE_ID_': return $this->getCategoryStoreIds((int)$item['category_id']);
			default: return '';
		}
	}

	private function getCustomerRows(array $fields, $language_id, array $filter = array()) {
		$sql = "SELECT c.*, cgd.name AS group_name,
			a.company, a.address_1, a.address_2, a.city, a.postcode,
			co.name AS country_name
			FROM `" . DB_PREFIX . "customer` c
			LEFT JOIN `" . DB_PREFIX . "customer_group_description` cgd ON (c.customer_group_id = cgd.customer_group_id AND cgd.language_id = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "address` a ON (c.address_id = a.address_id)
			LEFT JOIN `" . DB_PREFIX . "country` co ON (a.country_id = co.country_id)
			WHERE 1";
		if (!empty($filter['customer_group_id'])) {
			$sql .= " AND c.customer_group_id = '" . (int)$filter['customer_group_id'] . "'";
		}
		if (isset($filter['status']) && $filter['status'] !== '') {
			$sql .= " AND c.status = '" . (int)$filter['status'] . "'";
		}
		$sql .= " ORDER BY c.customer_id ASC";
		$query = $this->db->query($sql);
		$rows = array();
		foreach ($query->rows as $item) {
			$row = array();
			foreach ($fields as $code) {
				$row[$code] = $this->mapCustomerValue($code, $item);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function mapCustomerValue($code, array $item) {
		switch ($code) {
			case '_ID_': return (int)$item['customer_id'];
			case '_FIRST_NAME_': return $item['firstname'];
			case '_LAST_NAME_': return $item['lastname'];
			case '_NAME_': return trim($item['firstname'] . ' ' . $item['lastname']);
			case '_EMAIL_': return $item['email'];
			case '_TELEPHONE_': return $item['telephone'];
			case '_FAX_': return isset($item['fax']) ? $item['fax'] : '';
			case '_COMPANY_': return $item['company'];
			case '_COUNTRY_': return $item['country_name'];
			case '_CITY_': return $item['city'];
			case '_POSTCODE_': return $item['postcode'];
			case '_ADDRESS_1_': return $item['address_1'];
			case '_ADDRESS_2_': return $item['address_2'];
			case '_GROUP_': return $item['group_name'];
			case '_STATUS_': return $item['status'];
			case '_NEWSLETTER_': return $item['newsletter'];
			default: return '';
		}
	}

	private function getOrderRows(array $fields, $language_id, array $filter = array()) {
		$sql = "SELECT o.*, os.name AS status_name
			FROM `" . DB_PREFIX . "order` o
			LEFT JOIN `" . DB_PREFIX . "order_status` os ON (o.order_status_id = os.order_status_id AND os.language_id = '" . (int)$language_id . "')
			WHERE 1";
		if (!empty($filter['order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$filter['order_status_id'] . "'";
		}
		if (!empty($filter['date_from'])) {
			$sql .= " AND DATE(o.date_added) >= '" . $this->db->escape($filter['date_from']) . "'";
		}
		if (!empty($filter['date_to'])) {
			$sql .= " AND DATE(o.date_added) <= '" . $this->db->escape($filter['date_to']) . "'";
		}
		$sql .= " ORDER BY o.order_id ASC";
		$query = $this->db->query($sql);
		$rows = array();
		foreach ($query->rows as $item) {
			$row = array();
			foreach ($fields as $code) {
				$row[$code] = $this->mapOrderValue($code, $item, $language_id);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function mapOrderValue($code, array $item, $language_id) {
		switch ($code) {
			case '_ID_': return (int)$item['order_id'];
			case '_INVOICE_NO_': return $item['invoice_no'];
			case '_CUSTOMER_ID_': return (int)$item['customer_id'];
			case '_CUSTOMER_NAME_': return trim($item['firstname'] . ' ' . $item['lastname']);
			case '_EMAIL_': return $item['email'];
			case '_TELEPHONE_': return $item['telephone'];
			case '_STATUS_': return $item['status_name'];
			case '_STATUS_ID_': return (int)$item['order_status_id'];
			case '_TOTAL_': return $item['total'];
			case '_CURRENCY_': return $item['currency_code'];
			case '_DATE_ADDED_': return $item['date_added'];
			case '_PAYMENT_METHOD_': return $item['payment_method'];
			case '_SHIPPING_METHOD_': return $item['shipping_method'];
			case '_COMMENT_': return $item['comment'];
			case '_STORE_ID_': return (int)$item['store_id'];
			case '_PRODUCTS_': return $this->getOrderProductsText((int)$item['order_id']);
			default: return '';
		}
	}

	private function getOrderProductsText($order_id) {
		$query = $this->db->query("SELECT model, name, quantity, price FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");
		$parts = array();
		foreach ($query->rows as $row) {
			$parts[] = $row['model'] . ':' . $row['name'] . ':' . (int)$row['quantity'] . ':' . $row['price'];
		}
		return implode("\n", $parts);
	}

	private function getManufacturerRows(array $fields, $language_id, array $filter = array()) {
		$desc_sql = '';
		if ($this->hasManufacturerDescription()) {
			$meta_h1 = $this->hasManufacturerMetaH1() ? ', md.meta_h1' : '';
			$desc_sql = " LEFT JOIN `" . DB_PREFIX . "manufacturer_description` md ON (m.manufacturer_id = md.manufacturer_id AND md.language_id = '" . (int)$language_id . "')";
			$select_desc = ", md.description, md.meta_title, md.meta_description, md.meta_keyword" . $meta_h1;
		} else {
			$select_desc = '';
		}

		$query = $this->db->query("SELECT m.*" . $select_desc . "
			FROM `" . DB_PREFIX . "manufacturer` m" . $desc_sql . "
			ORDER BY m.manufacturer_id ASC");

		$rows = array();
		foreach ($query->rows as $item) {
			$row = array();
			foreach ($fields as $code) {
				$row[$code] = $this->mapManufacturerValue($code, $item, $language_id);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function mapManufacturerValue($code, array $item, $language_id) {
		switch ($code) {
			case '_ID_': return (int)$item['manufacturer_id'];
			case '_NAME_': return $item['name'];
			case '_DESCRIPTION_': return isset($item['description']) ? $item['description'] : '';
			case '_META_TITLE_': return isset($item['meta_title']) ? $item['meta_title'] : '';
			case '_META_H1_': return isset($item['meta_h1']) ? $item['meta_h1'] : '';
			case '_META_KEYWORDS_': return isset($item['meta_keyword']) ? $item['meta_keyword'] : '';
			case '_META_DESCRIPTION_': return isset($item['meta_description']) ? $item['meta_description'] : '';
			case '_IMAGE_': return isset($item['image']) ? $item['image'] : '';
			case '_SORT_ORDER_': return isset($item['sort_order']) ? $item['sort_order'] : 0;
			case '_SEO_KEYWORD_': return $this->getSeoKeyword('manufacturer_id=' . (int)$item['manufacturer_id']);
			case '_STORE_ID_': return $this->getManufacturerStoreIds((int)$item['manufacturer_id']);
			default: return '';
		}
	}

	private function importManufacturerRow($manufacturer_id, array $row, array $fields, $language_id, $is_insert) {
		$data = array(
			'name' => '',
			'sort_order' => 0,
			'noindex' => 0,
			'image' => '',
			'manufacturer_description' => array(
				$language_id => array(
					'description' => '',
					'meta_title' => '',
					'meta_h1' => '',
					'meta_description' => '',
					'meta_keyword' => ''
				)
			),
			'manufacturer_store' => array(0),
			'manufacturer_seo_url' => array()
		);

		$seo_keyword = '';
		$store_ids = array(0);

		foreach ($fields as $code) {
			if (!isset($row[$code]) || $row[$code] === '') {
				continue;
			}
			$value = trim((string)$row[$code]);
			switch ($code) {
				case '_NAME_': $data['name'] = $value; break;
				case '_DESCRIPTION_': $data['manufacturer_description'][$language_id]['description'] = $value; break;
				case '_META_TITLE_': $data['manufacturer_description'][$language_id]['meta_title'] = $value; break;
				case '_META_H1_': $data['manufacturer_description'][$language_id]['meta_h1'] = $value; break;
				case '_META_DESCRIPTION_': $data['manufacturer_description'][$language_id]['meta_description'] = $value; break;
				case '_META_KEYWORDS_': $data['manufacturer_description'][$language_id]['meta_keyword'] = $value; break;
				case '_IMAGE_': $data['image'] = $value; break;
				case '_SORT_ORDER_': $data['sort_order'] = (int)$value; break;
				case '_SEO_KEYWORD_': $seo_keyword = $value; break;
				case '_STORE_ID_': $store_ids = $this->parseIds($value); break;
			}
		}

		if ($data['name'] === '' && isset($row['_NAME_'])) {
			$data['name'] = trim((string)$row['_NAME_']);
		}
		if ($data['manufacturer_description'][$language_id]['meta_title'] === '') {
			$data['manufacturer_description'][$language_id]['meta_title'] = $data['name'];
		}

		$data['manufacturer_store'] = $store_ids;
		if ($seo_keyword !== '') {
			$data['manufacturer_seo_url'][0][$language_id] = $seo_keyword;
		}

		$loader = $this->registry->get('load');
		$loader->model('catalog/manufacturer');

		if ($is_insert) {
			if ($data['name'] === '') {
				return false;
			}
			if (!$this->hasManufacturerDescription()) {
				unset($data['manufacturer_description']);
			}
			$this->registry->get('model_catalog_manufacturer')->addManufacturer($data);
		} else {
			$existing = $this->registry->get('model_catalog_manufacturer')->getManufacturer($manufacturer_id);
			if ($existing) {
				if ($data['name'] === '' && !empty($existing['name'])) {
					$data['name'] = $existing['name'];
				}
				if ($data['image'] === '' && !empty($existing['image'])) {
					$data['image'] = $existing['image'];
				}
				if (!$this->hasManufacturerDescription()) {
					unset($data['manufacturer_description']);
				} else {
					$model = $this->registry->get('model_catalog_manufacturer');
					if (method_exists($model, 'getManufacturerDescriptions')) {
						$descriptions = $model->getManufacturerDescriptions($manufacturer_id);
						if (!empty($descriptions[$language_id])) {
							$desc = $data['manufacturer_description'][$language_id];
							$old = $descriptions[$language_id];
							if ($desc['description'] === '') {
								$data['manufacturer_description'][$language_id]['description'] = $old['description'];
							}
							if ($desc['meta_title'] === '') {
								$data['manufacturer_description'][$language_id]['meta_title'] = $old['meta_title'];
							}
							if ($desc['meta_h1'] === '') {
								$data['manufacturer_description'][$language_id]['meta_h1'] = $old['meta_h1'];
							}
							if ($desc['meta_description'] === '') {
								$data['manufacturer_description'][$language_id]['meta_description'] = $old['meta_description'];
							}
							if ($desc['meta_keyword'] === '') {
								$data['manufacturer_description'][$language_id]['meta_keyword'] = $old['meta_keyword'];
							}
						}
					}
				}
			}
			if ($data['name'] === '') {
				return false;
			}
			$this->registry->get('model_catalog_manufacturer')->editManufacturer($manufacturer_id, $data);
		}
		return true;
	}

	private function getManufacturerStoreIds($manufacturer_id) {
		$query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "manufacturer_to_store` WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
		$ids = array();
		foreach ($query->rows as $row) {
			$ids[] = (int)$row['store_id'];
		}
		return implode(',', $ids);
	}

	private function hasManufacturerDescription() {
		static $has = null;
		if ($has !== null) {
			return $has;
		}
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "manufacturer_description'");
		$has = (bool)$query->num_rows;
		return $has;
	}

	private function hasManufacturerMetaH1() {
		static $has = null;
		if ($has !== null) {
			return $has;
		}
		if (!$this->hasManufacturerDescription()) {
			$has = false;
			return $has;
		}
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "manufacturer_description` LIKE 'meta_h1'");
		$has = (bool)$query->num_rows;
		return $has;
	}

	private function importCategoryRow($category_id, array $row, array $fields, $language_id, $is_insert) {
		$data = array(
			'parent_id' => 0,
			'top' => 0,
			'column' => 1,
			'sort_order' => 0,
			'status' => 1,
			'noindex' => 0,
			'image' => '',
			'category_description' => array(
				$language_id => array(
					'name' => '',
					'description' => '',
					'meta_title' => '',
					'meta_h1' => '',
					'meta_description' => '',
					'meta_keyword' => ''
				)
			),
			'category_store' => array(0),
			'category_seo_url' => array()
		);

		$seo_keyword = '';
		$store_ids = array(0);

		foreach ($fields as $code) {
			if (!isset($row[$code]) || $row[$code] === '') {
				continue;
			}
			$value = trim((string)$row[$code]);
			switch ($code) {
				case '_PARENT_ID_': $data['parent_id'] = (int)$value; break;
				case '_NAME_': $data['category_description'][$language_id]['name'] = $value; break;
				case '_DESCRIPTION_': $data['category_description'][$language_id]['description'] = $value; break;
				case '_META_TITLE_': $data['category_description'][$language_id]['meta_title'] = $value; break;
				case '_META_H1_': $data['category_description'][$language_id]['meta_h1'] = $value; break;
				case '_META_DESCRIPTION_': $data['category_description'][$language_id]['meta_description'] = $value; break;
				case '_META_KEYWORDS_': $data['category_description'][$language_id]['meta_keyword'] = $value; break;
				case '_IMAGE_': $data['image'] = $value; break;
				case '_TOP_': $data['top'] = (int)$value; break;
				case '_COLUMN_': $data['column'] = (int)$value; break;
				case '_SORT_ORDER_': $data['sort_order'] = (int)$value; break;
				case '_STATUS_': $data['status'] = (int)$value; break;
				case '_SEO_KEYWORD_': $seo_keyword = $value; break;
				case '_STORE_ID_': $store_ids = $this->parseIds($value); break;
			}
		}

		if ($data['category_description'][$language_id]['name'] === '' && isset($row['_NAME_'])) {
			$data['category_description'][$language_id]['name'] = trim((string)$row['_NAME_']);
		}
		if ($data['category_description'][$language_id]['meta_title'] === '') {
			$data['category_description'][$language_id]['meta_title'] = $data['category_description'][$language_id]['name'];
		}

		$data['category_store'] = $store_ids;
		if ($seo_keyword !== '') {
			$data['category_seo_url'][0][$language_id] = $seo_keyword;
		}

		$loader = $this->registry->get('load');
		$loader->model('catalog/category');

		if ($is_insert) {
			$this->registry->get('model_catalog_category')->addCategory($data);
		} else {
			$existing = $this->registry->get('model_catalog_category')->getCategory($category_id);
			if ($existing) {
				if ($data['category_description'][$language_id]['name'] === '' && !empty($existing['name'])) {
					$data['category_description'][$language_id]['name'] = $existing['name'];
				}
				if ($data['category_description'][$language_id]['description'] === '' && !empty($existing['description'])) {
					$data['category_description'][$language_id]['description'] = $existing['description'];
				}
				if ($data['category_description'][$language_id]['meta_title'] === '' && !empty($existing['meta_title'])) {
					$data['category_description'][$language_id]['meta_title'] = $existing['meta_title'];
				}
				if ($data['category_description'][$language_id]['meta_description'] === '' && !empty($existing['meta_description'])) {
					$data['category_description'][$language_id]['meta_description'] = $existing['meta_description'];
				}
				if ($data['category_description'][$language_id]['meta_keyword'] === '' && !empty($existing['meta_keyword'])) {
					$data['category_description'][$language_id]['meta_keyword'] = $existing['meta_keyword'];
				}
				if ($data['category_description'][$language_id]['meta_h1'] === '' && !empty($existing['meta_h1'])) {
					$data['category_description'][$language_id]['meta_h1'] = $existing['meta_h1'];
				}
				if ($data['image'] === '' && !empty($existing['image'])) {
					$data['image'] = $existing['image'];
				}
				if (!$data['parent_id'] && !empty($existing['parent_id'])) {
					$data['parent_id'] = (int)$existing['parent_id'];
				}
			}
			$this->registry->get('model_catalog_category')->editCategory($category_id, $data);
		}
		return true;
	}

	private function importCustomerRow($customer_id, array $row, array $fields, $language_id, $is_insert) {
		$data = array(
			'customer_group_id' => (int)$this->config->get('config_customer_group_id'),
			'firstname' => '',
			'lastname' => '',
			'email' => '',
			'telephone' => '',
			'newsletter' => 0,
			'status' => 1,
			'safe' => 0,
			'password' => '',
			'address' => array()
		);

		$address = array(
			'firstname' => '',
			'lastname' => '',
			'company' => '',
			'address_1' => '',
			'address_2' => '',
			'city' => '',
			'postcode' => '',
			'country_id' => (int)$this->config->get('config_country_id'),
			'zone_id' => (int)$this->config->get('config_zone_id'),
			'default' => 1
		);

		foreach ($fields as $code) {
			if (!isset($row[$code]) || $row[$code] === '') {
				continue;
			}
			$value = trim((string)$row[$code]);
			switch ($code) {
				case '_FIRST_NAME_': $data['firstname'] = $value; $address['firstname'] = $value; break;
				case '_LAST_NAME_': $data['lastname'] = $value; $address['lastname'] = $value; break;
				case '_NAME_':
					$parts = preg_split('/\s+/', $value, 2);
					$data['firstname'] = $parts[0];
					$data['lastname'] = isset($parts[1]) ? $parts[1] : '';
					$address['firstname'] = $data['firstname'];
					$address['lastname'] = $data['lastname'];
					break;
				case '_EMAIL_': $data['email'] = $value; break;
				case '_TELEPHONE_': $data['telephone'] = $value; break;
				case '_COMPANY_': $address['company'] = $value; break;
				case '_COUNTRY_':
					$country_id = $this->findCountryIdByName($value);
					if ($country_id) {
						$address['country_id'] = $country_id;
					}
					break;
				case '_CITY_': $address['city'] = $value; break;
				case '_POSTCODE_': $address['postcode'] = $value; break;
				case '_ADDRESS_1_': $address['address_1'] = $value; break;
				case '_ADDRESS_2_': $address['address_2'] = $value; break;
				case '_GROUP_':
					$group_id = $this->findCustomerGroupIdByName($value, $language_id);
					if ($group_id) {
						$data['customer_group_id'] = $group_id;
					}
					break;
				case '_STATUS_': $data['status'] = (int)$value; break;
				case '_NEWSLETTER_': $data['newsletter'] = (int)$value; break;
				case '_PASSWORD_': $data['password'] = $value; break;
			}
		}

		if ($data['password'] === '' && $is_insert) {
			$data['password'] = substr(md5(uniqid('', true)), 0, 10);
		}

		if ($address['address_1'] !== '' || $address['city'] !== '') {
			$data['address'] = array($address);
		}

		$loader = $this->registry->get('load');
		$loader->model('customer/customer');

		if ($is_insert) {
			$this->registry->get('model_customer_customer')->addCustomer($data);
		} else {
			$this->registry->get('model_customer_customer')->editCustomer($customer_id, $data);
		}
		return true;
	}

	private function importOrderRow($order_id, array $row, array $fields, $language_id) {
		$sets = array();
		foreach ($fields as $code) {
			if (!isset($row[$code]) || $row[$code] === '') {
				continue;
			}
			$value = trim((string)$row[$code]);
			switch ($code) {
				case '_STATUS_ID_':
					$sets[] = "order_status_id = '" . (int)$value . "'";
					break;
				case '_STATUS_':
					$status_id = $this->findOrderStatusIdByName($value, $language_id);
					if ($status_id) {
						$sets[] = "order_status_id = '" . (int)$status_id . "'";
					}
					break;
				case '_COMMENT_':
					$sets[] = "comment = '" . $this->db->escape($value) . "'";
					break;
			}
		}
		if (!$sets) {
			return false;
		}
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET " . implode(', ', $sets) . ", date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
		return true;
	}

	private function getCategoryStoreIds($category_id) {
		$query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "category_to_store` WHERE category_id = '" . (int)$category_id . "'");
		$ids = array();
		foreach ($query->rows as $row) {
			$ids[] = (int)$row['store_id'];
		}
		return implode(',', $ids);
	}

	private function getSeoKeyword($query_str) {
		$query = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query_str) . "' LIMIT 1");
		return $query->num_rows ? $query->row['keyword'] : '';
	}

	private function parseIds($value) {
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

	private function findCountryIdByName($name) {
		$query = $this->db->query("SELECT country_id FROM `" . DB_PREFIX . "country` WHERE name = '" . $this->db->escape($name) . "' LIMIT 1");
		return $query->num_rows ? (int)$query->row['country_id'] : 0;
	}

	private function findCustomerGroupIdByName($name, $language_id) {
		$query = $this->db->query("SELECT customer_group_id FROM `" . DB_PREFIX . "customer_group_description`
			WHERE language_id = '" . (int)$language_id . "' AND name = '" . $this->db->escape($name) . "' LIMIT 1");
		return $query->num_rows ? (int)$query->row['customer_group_id'] : 0;
	}

	private function findOrderStatusIdByName($name, $language_id) {
		$query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order_status`
			WHERE language_id = '" . (int)$language_id . "' AND name = '" . $this->db->escape($name) . "' LIMIT 1");
		return $query->num_rows ? (int)$query->row['order_status_id'] : 0;
	}

	private function hasCategoryMetaH1() {
		static $has = null;
		if ($has !== null) {
			return $has;
		}
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "category_description` LIKE 'meta_h1'");
		$has = (bool)$query->num_rows;
		return $has;
	}
}
