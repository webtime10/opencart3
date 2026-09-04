<?php
class ControllerExtensionModuleWtExchange extends Controller {
	private $error = array();

	public function index() {
		$this->response->redirect($this->link('general'));
	}

	public function general() {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = $this->getStoredSettings();
			$settings['workdir'] = isset($this->request->post['workdir']) ? trim($this->request->post['workdir']) : '';
			$settings['ajax_timeout'] = isset($this->request->post['ajax_timeout']) ? (int)$this->request->post['ajax_timeout'] : 55;
			$settings['import_mode_ajax'] = isset($this->request->post['import_mode_ajax']) ? (int)$this->request->post['import_mode_ajax'] : 1;
			$settings['product_log'] = isset($this->request->post['product_log']) ? (int)$this->request->post['product_log'] : 0;
			if (isset($this->request->post['profile_name']) && trim($this->request->post['profile_name']) !== '') {
				$profiles = isset($settings['profiles']) ? $settings['profiles'] : array();
				$profiles[] = array(
					'name'   => trim($this->request->post['profile_name']),
					'entity' => isset($this->request->post['profile_entity']) ? $this->request->post['profile_entity'] : 'product',
					'type'   => isset($this->request->post['profile_type']) ? $this->request->post['profile_type'] : 'export',
					'data'   => isset($this->request->post['profile_data']) ? json_decode(html_entity_decode($this->request->post['profile_data']), true) : array()
				);
				$settings['profiles'] = $profiles;
			}
			$this->saveSettings($settings);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link('general'));
		}

		$data = $this->commonData('general');
		$settings = $this->getStoredSettings();

		$data['workdir'] = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		$data['ajax_timeout'] = isset($settings['ajax_timeout']) ? (int)$settings['ajax_timeout'] : 55;
		$data['import_mode_ajax'] = isset($settings['import_mode_ajax']) ? (int)$settings['import_mode_ajax'] : 1;
		$data['product_log'] = isset($settings['product_log']) ? (int)$settings['product_log'] : 0;
		$data['profiles'] = isset($settings['profiles']) ? $settings['profiles'] : array();
		$data['entry_profile_name'] = $this->language->get('entry_profile_name');
		$data['text_profiles_help'] = $this->language->get('text_profiles_help');
		$data['action'] = $this->link('general');

		$this->renderPage('extension/module/wt_exchange/general', $data);
	}

	public function product() {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));

		$tab = isset($this->request->get['tab']) ? $this->request->get['tab'] : 'setting';
		$allowed = array('setting', 'fields', 'export', 'import', 'macros');
		if (!in_array($tab, $allowed)) {
			$tab = 'setting';
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = $this->getStoredSettings();

			if ($tab === 'setting') {
				$settings['product_default'] = isset($this->request->post['product_default']) ? $this->request->post['product_default'] : array();
			} elseif ($tab === 'fields') {
				$settings['product_mappings'] = isset($this->request->post['product_mappings']) ? $this->request->post['product_mappings'] : array();
			} elseif ($tab === 'export') {
				$settings['product_export'] = array(
					'language_id'         => isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id'),
					'encoding'            => isset($this->request->post['encoding']) ? $this->request->post['encoding'] : 'UTF-8',
					'delimiter'           => isset($this->request->post['delimiter']) ? $this->request->post['delimiter'] : ';',
					'format'              => isset($this->request->post['format']) ? $this->request->post['format'] : 'csv',
					'fields'              => isset($this->request->post['fields']) ? $this->request->post['fields'] : array(),
					'category_ids'        => isset($this->request->post['category_ids']) ? array_map('intval', (array)$this->request->post['category_ids']) : array(),
					'include_children'    => isset($this->request->post['include_children']) ? (int)$this->request->post['include_children'] : 1,
					'category_export'     => isset($this->request->post['category_export']) ? $this->request->post['category_export'] : 'disabled',
					'category_delimiter'  => isset($this->request->post['category_delimiter']) ? $this->request->post['category_delimiter'] : ' > '
				);
			} elseif ($tab === 'import') {
				$settings['product_import'] = array(
					'language_id' => isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id'),
					'encoding'  => isset($this->request->post['encoding']) ? $this->request->post['encoding'] : 'UTF-8',
					'delimiter' => isset($this->request->post['delimiter']) ? $this->request->post['delimiter'] : ';',
					'mode'      => isset($this->request->post['mode']) ? $this->request->post['mode'] : 'update',
					'key'       => isset($this->request->post['key']) ? $this->request->post['key'] : '_MODEL_',
					'fields'    => isset($this->request->post['fields']) ? $this->request->post['fields'] : array(),
					'create_categories' => isset($this->request->post['create_categories']) ? (int)$this->request->post['create_categories'] : 0,
					'download_images'   => isset($this->request->post['download_images']) ? (int)$this->request->post['download_images'] : 0
				);
			} elseif ($tab === 'macros') {
				$settings['product_macros'] = isset($this->request->post['product_macros']) ? $this->request->post['product_macros'] : array();
			}

			$this->saveSettings($settings);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link('product', 'tab=' . $tab));
		}

		$data = $this->commonData('product');
		$settings = $this->getStoredSettings();
		$fields = WtExchangeFieldRegistry::productFields();

		$export = isset($settings['product_export']) ? $settings['product_export'] : array();
		$import = isset($settings['product_import']) ? $settings['product_import'] : array();
		$default = isset($settings['product_default']) ? $settings['product_default'] : array();
		$mappings = isset($settings['product_mappings']) ? $settings['product_mappings'] : array();

		$data['tab'] = $tab;
		$data['action'] = $this->link('product', 'tab=' . $tab);
		$data['export_run'] = $this->link('exportProduct');
		$data['import_run'] = $this->link('importProduct');
		$data['import_start'] = $this->link('importStart');
		$data['import_step'] = $this->link('importStep');
		$data['import_mode_ajax'] = isset($settings['import_mode_ajax']) ? (int)$settings['import_mode_ajax'] : 1;
		$data['ajax_timeout'] = isset($settings['ajax_timeout']) ? (int)$settings['ajax_timeout'] : 55;

		$data['product_tabs'] = array(
			'setting' => array('text' => $this->language->get('tab_product_setting'), 'href' => $this->link('product', 'tab=setting')),
			'fields'  => array('text' => $this->language->get('tab_product_fields'), 'href' => $this->link('product', 'tab=fields')),
			'export'  => array('text' => $this->language->get('tab_export'), 'href' => $this->link('product', 'tab=export')),
			'import'  => array('text' => $this->language->get('tab_import'), 'href' => $this->link('product', 'tab=import')),
			'macros'  => array('text' => $this->language->get('tab_macros'), 'href' => $this->link('product', 'tab=macros'))
		);

		$data['field_list'] = array();
		$selected_export = !empty($export['fields']) ? $export['fields'] : WtExchangeFieldRegistry::defaultProductExportFields();
		foreach ($fields as $code => $meta) {
			$data['field_list'][] = array(
				'code'     => $code,
				'label'    => $meta['label'],
				'selected' => in_array($code, $selected_export),
				'csv_name' => isset($mappings[$code]) ? $mappings[$code] : $code
			);
		}

		$data['encoding'] = isset($export['encoding']) ? $export['encoding'] : 'UTF-8';
		$data['delimiter'] = isset($export['delimiter']) ? $export['delimiter'] : ';';
		$data['format'] = isset($export['format']) ? $export['format'] : 'csv';
		$data['encodings'] = WtExchangeFieldRegistry::encodings();
		$data['export_language_id'] = $this->resolveLanguageId(isset($export['language_id']) ? $export['language_id'] : 0);
		$data['import_language_id'] = $this->resolveLanguageId(isset($import['language_id']) ? $import['language_id'] : 0);

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['include_children'] = isset($export['include_children']) ? (int)$export['include_children'] : 1;
		$data['category_export'] = isset($export['category_export']) ? $export['category_export'] : 'disabled';
		$data['category_delimiter'] = isset($export['category_delimiter']) ? $export['category_delimiter'] : ' > ';
		$data['category_export_options'] = array(
			'disabled' => $this->language->get('text_disabled'),
			'id'       => $this->language->get('text_category_as_id'),
			'path'     => $this->language->get('text_category_as_path')
		);
		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('catalog/category');
		$data['export_categories'] = array();
		if (!empty($export['category_ids']) && is_array($export['category_ids'])) {
			$all = array();
			foreach ($this->model_catalog_category->getCategories(array('sort' => 'name')) as $category) {
				$all[(int)$category['category_id']] = html_entity_decode(str_replace('&nbsp;', ' ', $category['name']), ENT_QUOTES, 'UTF-8');
			}
			foreach ($export['category_ids'] as $category_id) {
				$category_id = (int)$category_id;
				if ($category_id && isset($all[$category_id])) {
					$data['export_categories'][] = array(
						'category_id' => $category_id,
						'name'        => $all[$category_id]
					);
				}
			}
		}

		$data['import_encoding'] = isset($import['encoding']) ? $import['encoding'] : 'UTF-8';
		$data['import_delimiter'] = isset($import['delimiter']) ? $import['delimiter'] : ';';
		$data['import_mode'] = isset($import['mode']) ? $import['mode'] : 'update';
		$data['import_key'] = isset($import['key']) ? $import['key'] : '_MODEL_';
		$data['create_categories'] = isset($import['create_categories']) ? (int)$import['create_categories'] : 0;
		$data['download_images'] = isset($import['download_images']) ? (int)$import['download_images'] : 0;
		$data['product_macros'] = isset($settings['product_macros']) ? $settings['product_macros'] : array();
		$data['macro_fields'] = array('_PRICE_', '_QUANTITY_', '_NAME_', '_MODEL_', '_SKU_');
		$data['text_macros_help'] = $this->language->get('text_macros_help');
		$data['entry_create_categories'] = $this->language->get('entry_create_categories');
		$data['entry_download_images'] = $this->language->get('entry_download_images');
		$data['entry_macro_formula'] = $this->language->get('entry_macro_formula');
		$data['text_import_progress'] = $this->language->get('text_import_progress');
		$data['key_options'] = WtExchangeFieldRegistry::productKeyOptions();
		$data['import_modes'] = array(
			'update' => $this->language->get('text_mode_update'),
			'insert' => $this->language->get('text_mode_insert'),
			'both'   => $this->language->get('text_mode_both')
		);

		$data['product_default'] = array_merge(array(
			'minimum'  => 1,
			'subtract' => 1,
			'shipping' => 1,
			'status'   => 1
		), $default);

		$data['wt_exchange_job_id'] = isset($this->session->data['wt_exchange_job_id']) ? $this->session->data['wt_exchange_job_id'] : '';
		unset($this->session->data['wt_exchange_job_id']);

		$this->load->model('setting/store');
		$data['stores'] = array();
		$data['stores'][] = array('store_id' => 0, 'name' => $this->language->get('text_default'));
		foreach ($this->model_setting_store->getStores() as $store) {
			$data['stores'][] = $store;
		}

		$this->renderPage('extension/module/wt_exchange/product', $data);
	}

	public function exportProduct() {
		$this->bootstrap();
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link('product', 'tab=export'));
		}

		$settings = $this->getStoredSettings();
		$export = isset($settings['product_export']) ? $settings['product_export'] : array();
		$mappings = isset($settings['product_mappings']) ? $settings['product_mappings'] : array();
		$fields = !empty($export['fields']) ? $export['fields'] : WtExchangeFieldRegistry::defaultProductExportFields();
		$delimiter = !empty($export['delimiter']) ? $export['delimiter'] : ';';
		$encoding = !empty($export['encoding']) ? $export['encoding'] : 'UTF-8';
		$format = isset($this->request->get['format']) ? $this->request->get['format'] : (isset($export['format']) ? $export['format'] : 'csv');
		$category_export = isset($export['category_export']) ? $export['category_export'] : 'disabled';

		if ($category_export === 'id' && !in_array('_CATEGORY_ID_', $fields)) {
			$fields[] = '_CATEGORY_ID_';
		} elseif ($category_export === 'path' && !in_array('_CATEGORY_', $fields)) {
			$fields[] = '_CATEGORY_';
		}

		$filter = array(
			'category_ids'       => !empty($export['category_ids']) ? $export['category_ids'] : array(),
			'include_children'   => isset($export['include_children']) ? (int)$export['include_children'] : 1,
			'category_delimiter' => isset($export['category_delimiter']) ? $export['category_delimiter'] : ' > '
		);

		$language_id = $this->resolveLanguageId(isset($export['language_id']) ? $export['language_id'] : 0);
		$rows = $this->model_extension_module_wt_exchange->getProductRows($fields, $language_id, $filter);

		$columns = array();
		foreach ($fields as $code) {
			$columns[] = (!empty($mappings[$code])) ? $mappings[$code] : $code;
		}

		if ($format === 'json') {
			$content = WtExchangeFormat::encodeJson($rows, 'product');
			$filename = 'wt_products_' . date('Y-m-d_H-i-s') . '.json';
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		} elseif ($format === 'xml') {
			$content = WtExchangeFormat::encodeXml($rows, $fields, 'product', $encoding);
			$filename = 'wt_products_' . date('Y-m-d_H-i-s') . '.xml';
			$this->response->addHeader('Content-Type: ' . WtExchangeFormat::xmlContentType($encoding));
		} elseif ($format === 'xlsx') {
			$mapped_rows = array();
			foreach ($rows as $row) {
				$mapped = array();
				foreach ($fields as $i => $code) {
					$mapped[$columns[$i]] = isset($row[$code]) ? $row[$code] : '';
				}
				$mapped_rows[] = $mapped;
			}
			$content = WtExchangeXlsx::encode($mapped_rows, $columns);
			$filename = 'wt_products_' . date('Y-m-d_H-i-s') . '.xlsx';
			$this->response->addHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		} else {
			$mapped_rows = array();
			foreach ($rows as $row) {
				$mapped = array();
				foreach ($fields as $i => $code) {
					$mapped[$columns[$i]] = isset($row[$code]) ? $row[$code] : '';
				}
				$mapped_rows[] = $mapped;
			}
			$content = WtExchangeFormat::encodeCsv($mapped_rows, $columns, $delimiter, $encoding);
			$filename = 'wt_products_' . date('Y-m-d_H-i-s') . '.csv';
			$this->response->addHeader('Content-Type: ' . WtExchangeFormat::csvContentType($encoding));
		}

		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($content);
	}

	public function importProduct() {
		$this->bootstrap();
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link('product', 'tab=import'));
		}

		if (empty($this->request->files['import_file']['tmp_name']) || !is_uploaded_file($this->request->files['import_file']['tmp_name'])) {
			$this->session->data['error'] = $this->language->get('error_file');
			$this->response->redirect($this->link('product', 'tab=import'));
		}

		$settings = $this->getStoredSettings();
		$import = isset($settings['product_import']) ? $settings['product_import'] : array();
		$delimiter = !empty($import['delimiter']) ? $import['delimiter'] : ';';
		$encoding = !empty($import['encoding']) ? $import['encoding'] : 'UTF-8';
		$mappings = isset($settings['product_mappings']) ? $settings['product_mappings'] : array();

		$content = file_get_contents($this->request->files['import_file']['tmp_name']);
		$name = strtolower($this->request->files['import_file']['name']);

		if (substr($name, -5) === '.json') {
			$rows = WtExchangeFormat::decodeJson($content);
		} elseif (substr($name, -4) === '.xml') {
			$rows = WtExchangeFormat::decodeXml($content, $encoding);
		} elseif (substr($name, -5) === '.xlsx') {
			$rows = WtExchangeXlsx::decode($content);
		} else {
			$rows = WtExchangeFormat::decodeCsv($content, $delimiter, $encoding);
		}

		if (!$rows) {
			$this->session->data['error'] = $this->language->get('error_file');
			$this->response->redirect($this->link('product', 'tab=import'));
		}

		if (!empty($settings['import_mode_ajax'])) {
			$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
			$job_api = new WtExchangeImportJob($workdir);
			$config = $this->buildImportConfig('product', $settings);
			$job = $job_api->create(array('entity' => 'product', 'config' => $config, 'total' => count($rows), 'rows' => $rows));
			$this->session->data['wt_exchange_job_id'] = $job['id'];
			$this->session->data['success'] = $this->language->get('text_import_started');
			$this->response->redirect($this->link('product', 'tab=import'));
		}

		$logger = new WtExchangeLogger(
			!empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir(),
			!empty($settings['product_log'])
		);
		$runner = new WtExchangeImportRunner($this->registry, $logger);
		$config = $this->buildImportConfig('product', $settings);

		$updated = 0;
		$inserted = 0;
		$skipped = 0;
		$row_num = 0;

		foreach ($rows as $row) {
			$row_num++;
			$row = $this->model_extension_module_wt_exchange->normalizeRowKeys($row, $mappings);
			$result = $runner->processProductRow($row, $config, $row_num);
			if ($result['action'] === 'updated') {
				$updated++;
			} elseif ($result['action'] === 'inserted') {
				$inserted++;
			} else {
				$skipped++;
			}
		}

		$this->session->data['success'] = sprintf($this->language->get('text_import_done'), $updated, $inserted, $skipped);
		$this->response->redirect($this->link('product', 'tab=import'));
	}

	public function category() {
		$this->entityPage('category');
	}

	public function manufacturer() {
		$this->entityPage('manufacturer');
	}

	public function customer() {
		$this->entityPage('customer');
	}

	public function order() {
		$this->entityPage('order');
	}

	public function exportCategory() {
		$this->exportEntity('category');
	}

	public function importCategory() {
		$this->importEntity('category');
	}

	public function exportManufacturer() {
		$this->exportEntity('manufacturer');
	}

	public function importManufacturer() {
		$this->importEntity('manufacturer');
	}

	public function exportCustomer() {
		$this->exportEntity('customer');
	}

	public function importCustomer() {
		$this->importEntity('customer');
	}

	public function exportOrder() {
		$this->exportEntity('order');
	}

	public function importOrder() {
		$this->importEntity('order');
	}

	public function scheduler() {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = $this->getStoredSettings();
			$settings['scheduler'] = array(
				'cron_key' => isset($this->request->post['cron_key']) ? trim($this->request->post['cron_key']) : '',
				'tasks'    => isset($this->request->post['tasks']) ? $this->request->post['tasks'] : array()
			);
			if ($settings['scheduler']['cron_key'] === '') {
				$settings['scheduler']['cron_key'] = substr(md5(uniqid('', true)), 0, 20);
			}
			$this->saveSettings($settings);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link('scheduler'));
		}

		$data = $this->commonData('scheduler');
		$settings = $this->getStoredSettings();
		$scheduler = isset($settings['scheduler']) ? $settings['scheduler'] : array();
		$data['cron_key'] = !empty($scheduler['cron_key']) ? $scheduler['cron_key'] : substr(md5(uniqid('', true)), 0, 20);
		$data['tasks'] = !empty($scheduler['tasks']) ? $scheduler['tasks'] : array();
		$data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/module/wt_exchange/cron&key=' . urlencode($data['cron_key']);
		$data['action'] = $this->link('scheduler');
		$data['entities'] = array('product', 'category', 'manufacturer', 'customer', 'order');
		$data['menu_scheduler'] = $this->language->get('menu_scheduler');
		$this->renderPage('extension/module/wt_exchange/scheduler', $data);
	}

	public function log() {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));
		$data = $this->commonData('log');
		$settings = $this->getStoredSettings();
		$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		$data['logs'] = WtExchangeLogger::listLogs($workdir);
		$data['user_token'] = $this->session->data['user_token'];
		$data['log_base'] = $this->link('log');
		$data['view_log'] = '';
		$data['text_no_logs'] = $this->language->get('text_no_logs');
		$data['text_select_log'] = $this->language->get('text_select_log');
		$data['menu_log'] = $this->language->get('menu_log');
		if (isset($this->request->get['file'])) {
			$data['view_log'] = WtExchangeLogger::readLog($workdir, $this->request->get['file']);
			$data['view_file'] = basename($this->request->get['file']);
		}
		$this->renderPage('extension/module/wt_exchange/log', $data);
	}

	public function about() {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));
		$data = $this->commonData('about');
		$data['text_about'] = $this->language->get('text_about');
		$data['menu_support'] = $this->language->get('menu_support');
		$this->renderPage('extension/module/wt_exchange/about', $data);
	}

	public function cron() {
		$this->response->redirect(HTTP_CATALOG . 'index.php?route=extension/module/wt_exchange/cron&key=' . urlencode(isset($this->request->get['key']) ? $this->request->get['key'] : ''));
	}

	public function importStart() {
		$this->bootstrap();
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->response->setOutput(json_encode(array('error' => $this->language->get('error_permission'))));
			return;
		}
		if (empty($this->request->files['import_file']['tmp_name'])) {
			$this->response->setOutput(json_encode(array('error' => $this->language->get('error_file'))));
			return;
		}

		$entity = isset($this->request->post['entity']) ? $this->request->post['entity'] : 'product';
		$settings = $this->getStoredSettings();
		$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		$job_api = new WtExchangeImportJob($workdir);
		$config = $this->buildImportConfig($entity, $settings);
		$job = $job_api->create(array(
			'entity' => $entity,
			'config' => $config,
			'total'  => 0
		));
		$file = $job_api->storeUpload($job['id'], $this->request->files['import_file']['tmp_name'], $this->request->files['import_file']['name']);
		$job['file'] = $file;
		$job['total'] = $job_api->countRows($job);
		$job_api->save($job['id'], $job);
		$this->response->setOutput(json_encode(array(
			'job_id' => $job['id'],
			'total'  => $job['total']
		)));
	}

	public function importStep() {
		$this->bootstrap();
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange')) {
			$this->response->setOutput(json_encode(array('error' => $this->language->get('error_permission'))));
			return;
		}

		$job_id = isset($this->request->get['job_id']) ? $this->request->get['job_id'] : '';
		$batch = isset($this->request->get['batch']) ? (int)$this->request->get['batch'] : 25;
		if ($batch < 1) {
			$batch = 25;
		}

		$settings = $this->getStoredSettings();
		$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		$job_api = new WtExchangeImportJob($workdir);
		$job = $job_api->get($job_id);
		if (!$job) {
			$this->response->setOutput(json_encode(array('error' => 'Job not found')));
			return;
		}

		$logger = new WtExchangeLogger($workdir, !empty($settings['product_log']), 'import_' . $job['entity']);
		$runner = new WtExchangeImportRunner($this->registry, $logger);
		$rows = $job_api->loadBatch($job, $job['offset'], $batch);
		$row_num = $job['offset'];

		foreach ($rows as $row) {
			$row_num++;
			if ($job['entity'] === 'product') {
				$result = $runner->processProductRow($row, $job['config'], $row_num);
			} else {
				$result = $runner->processEntityRow($job['entity'], $row, $job['config'], $row_num);
			}
			if ($result['action'] === 'updated') {
				$job['updated']++;
			} elseif ($result['action'] === 'inserted') {
				$job['inserted']++;
			} else {
				$job['skipped']++;
				if (!empty($result['error'])) {
					$job['errors'][] = array('row' => $row_num, 'error' => $result['error']);
				}
			}
		}

		$job['offset'] += count($rows);
		$job['finished'] = ($job['offset'] >= $job['total']);
		$job_api->save($job['id'], $job);

		$this->response->setOutput(json_encode(array(
			'offset'   => $job['offset'],
			'total'    => $job['total'],
			'updated'  => $job['updated'],
			'inserted' => $job['inserted'],
			'skipped'  => $job['skipped'],
			'finished' => $job['finished'],
			'log_file' => $logger->getFile()
		)));
	}

	public function install() {
		$this->ensureLibraries();
		$this->load->model('setting/setting');
		$this->load->model('user/user_group');
		$this->load->model('extension/module/wt_exchange');

		$this->model_setting_setting->editSetting('module_wt_exchange', array(
			'module_wt_exchange_status' => 1,
			'module_wt_exchange_data'   => array(
				'workdir'          => $this->model_extension_module_wt_exchange->getWorkDir(),
				'ajax_timeout'     => 55,
				'import_mode_ajax' => 1,
				'product_export'   => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'format'    => 'csv',
					'fields'    => WtExchangeFieldRegistry::defaultProductExportFields()
				),
				'product_import'   => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'mode'      => 'update',
					'key'       => '_MODEL_'
				),
				'category_export'  => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'format'    => 'csv',
					'fields'    => WtExchangeFieldRegistry::defaultCategoryExportFields()
				),
				'category_import'  => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'mode'      => 'both',
					'key'       => '_ID_'
				),
				'manufacturer_export' => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'format'    => 'csv',
					'fields'    => WtExchangeFieldRegistry::defaultManufacturerExportFields()
				),
				'manufacturer_import' => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'mode'      => 'both',
					'key'       => '_NAME_'
				),
				'customer_export'  => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'format'    => 'csv',
					'fields'    => WtExchangeFieldRegistry::defaultCustomerExportFields()
				),
				'customer_import'  => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'mode'      => 'both',
					'key'       => '_EMAIL_'
				),
				'order_export'     => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'format'    => 'csv',
					'fields'    => WtExchangeFieldRegistry::defaultOrderExportFields()
				),
				'order_import'     => array(
					'language_id' => (int)$this->config->get('config_language_id'),
					'encoding'  => 'UTF-8',
					'delimiter' => ';',
					'mode'      => 'update',
					'key'       => '_ID_'
				)
			)
		));

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/wt_exchange');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/wt_exchange');
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_exchange');
	}

	private function stubSection($section) {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));
		$data = $this->commonData($section);
		$data['stub_text'] = $this->language->get('text_stub');
		$this->renderPage('extension/module/wt_exchange/stub', $data);
	}

	private function entityPage($entity) {
		$this->bootstrap();
		$this->document->setTitle($this->language->get('heading_title'));

		$tab = isset($this->request->get['tab']) ? $this->request->get['tab'] : 'export';
		$allowed = array('export', 'import', 'fields');
		if (!in_array($tab, $allowed)) {
			$tab = 'export';
		}

		$export_key = $entity . '_export';
		$import_key = $entity . '_import';
		$mappings_key = $entity . '_mappings';

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = $this->getStoredSettings();

			if ($tab === 'fields') {
				$settings[$mappings_key] = isset($this->request->post['entity_mappings']) ? $this->request->post['entity_mappings'] : array();
			} elseif ($tab === 'export') {
				$settings[$export_key] = array(
					'language_id' => isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id'),
					'encoding'    => isset($this->request->post['encoding']) ? $this->request->post['encoding'] : 'UTF-8',
					'delimiter'   => isset($this->request->post['delimiter']) ? $this->request->post['delimiter'] : ';',
					'format'      => isset($this->request->post['format']) ? $this->request->post['format'] : 'csv',
					'fields'      => isset($this->request->post['fields']) ? $this->request->post['fields'] : array(),
					'parent_id'   => isset($this->request->post['parent_id']) ? (int)$this->request->post['parent_id'] : 0,
					'status'      => isset($this->request->post['status']) ? $this->request->post['status'] : '',
					'customer_group_id' => isset($this->request->post['customer_group_id']) ? (int)$this->request->post['customer_group_id'] : 0,
					'order_status_id' => isset($this->request->post['order_status_id']) ? (int)$this->request->post['order_status_id'] : 0,
					'date_from'   => isset($this->request->post['date_from']) ? $this->request->post['date_from'] : '',
					'date_to'     => isset($this->request->post['date_to']) ? $this->request->post['date_to'] : ''
				);
			} else {
				$settings[$import_key] = array(
					'language_id' => isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id'),
					'encoding'  => isset($this->request->post['encoding']) ? $this->request->post['encoding'] : 'UTF-8',
					'delimiter' => isset($this->request->post['delimiter']) ? $this->request->post['delimiter'] : ';',
					'mode'      => isset($this->request->post['mode']) ? $this->request->post['mode'] : ($entity === 'order' ? 'update' : 'update'),
					'key'       => isset($this->request->post['key']) ? $this->request->post['key'] : '_ID_'
				);
			}

			$this->saveSettings($settings);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link($entity, 'tab=' . $tab));
		}

		$data = $this->commonData($entity);
		$settings = $this->getStoredSettings();
		$fields = WtExchangeFieldRegistry::entityFields($entity);

		$export = isset($settings[$export_key]) ? $settings[$export_key] : array();
		$import = isset($settings[$import_key]) ? $settings[$import_key] : array();

		$data['entity'] = $entity;
		$data['tab'] = $tab;
		$data['action'] = $this->link($entity, 'tab=' . $tab);
		$data['export_run'] = $this->link('export' . ucfirst($entity));
		$data['import_run'] = $this->link('import' . ucfirst($entity));
		$data['import_start'] = $this->link('importStart');
		$data['import_step'] = $this->link('importStep');
		$data['import_mode_ajax'] = isset($settings['import_mode_ajax']) ? (int)$settings['import_mode_ajax'] : 1;

		$data['entity_tabs'] = array(
			'export' => array('text' => $this->language->get('tab_export'), 'href' => $this->link($entity, 'tab=export')),
			'import' => array('text' => $this->language->get('tab_import'), 'href' => $this->link($entity, 'tab=import')),
			'fields' => array('text' => $this->language->get('tab_product_fields'), 'href' => $this->link($entity, 'tab=fields'))
		);

		$mappings = isset($settings[$mappings_key]) ? $settings[$mappings_key] : array();

		$data['field_list'] = array();
		$selected_export = !empty($export['fields']) ? $export['fields'] : WtExchangeFieldRegistry::defaultEntityExportFields($entity);
		foreach ($fields as $code => $meta) {
			$data['field_list'][] = array(
				'code'     => $code,
				'label'    => $meta['label'],
				'selected' => in_array($code, $selected_export),
				'csv_name' => isset($mappings[$code]) ? $mappings[$code] : $code
			);
		}

		$data['export_filter_parent_id'] = isset($export['parent_id']) ? (int)$export['parent_id'] : 0;
		$data['export_filter_status'] = isset($export['status']) ? $export['status'] : '';
		$data['export_filter_customer_group_id'] = isset($export['customer_group_id']) ? (int)$export['customer_group_id'] : 0;
		$data['export_filter_order_status_id'] = isset($export['order_status_id']) ? (int)$export['order_status_id'] : 0;
		$data['export_filter_date_from'] = isset($export['date_from']) ? $export['date_from'] : '';
		$data['export_filter_date_to'] = isset($export['date_to']) ? $export['date_to'] : '';
		$data['entry_parent_id'] = $this->language->get('entry_parent_id');
		$data['entry_date_from'] = $this->language->get('entry_date_from');
		$data['entry_date_to'] = $this->language->get('entry_date_to');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_customer_group'] = $this->language->get('entry_customer_group');
		$data['text_import_progress'] = $this->language->get('text_import_progress');

		$this->load->model('customer/customer_group');
		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['encoding'] = isset($export['encoding']) ? $export['encoding'] : 'UTF-8';
		$data['delimiter'] = isset($export['delimiter']) ? $export['delimiter'] : ';';
		$data['format'] = isset($export['format']) ? $export['format'] : 'csv';
		$data['encodings'] = WtExchangeFieldRegistry::encodings();
		$data['export_language_id'] = $this->resolveLanguageId(isset($export['language_id']) ? $export['language_id'] : 0);
		$data['import_language_id'] = $this->resolveLanguageId(isset($import['language_id']) ? $import['language_id'] : 0);

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['import_encoding'] = isset($import['encoding']) ? $import['encoding'] : 'UTF-8';
		$data['import_delimiter'] = isset($import['delimiter']) ? $import['delimiter'] : ';';
		$data['import_mode'] = isset($import['mode']) ? $import['mode'] : 'update';
		$data['import_key'] = isset($import['key']) ? $import['key'] : '_ID_';
		$data['key_options'] = WtExchangeFieldRegistry::entityKeyOptions($entity);
		$data['import_modes'] = array(
			'update' => $this->language->get('text_mode_update'),
			'insert' => $this->language->get('text_mode_insert'),
			'both'   => $this->language->get('text_mode_both')
		);
		$data['order_import_only'] = ($entity === 'order');
		$data['entity_title'] = $this->language->get('menu_' . $this->entityMenuKey($entity));
		$data['wt_exchange_job_id'] = isset($this->session->data['wt_exchange_job_id']) ? $this->session->data['wt_exchange_job_id'] : '';
		unset($this->session->data['wt_exchange_job_id']);

		$this->renderPage('extension/module/wt_exchange/entity', $data);
	}

	private function exportEntity($entity) {
		$this->bootstrap();
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link($entity, 'tab=export'));
		}

		$settings = $this->getStoredSettings();
		$export_key = $entity . '_export';
		$export = isset($settings[$export_key]) ? $settings[$export_key] : array();
		$fields = !empty($export['fields']) ? $export['fields'] : WtExchangeFieldRegistry::defaultEntityExportFields($entity);
		$delimiter = !empty($export['delimiter']) ? $export['delimiter'] : ';';
		$encoding = !empty($export['encoding']) ? $export['encoding'] : 'UTF-8';
		$format = isset($this->request->get['format']) ? $this->request->get['format'] : (isset($export['format']) ? $export['format'] : 'csv');

		$language_id = $this->resolveLanguageId(isset($export['language_id']) ? $export['language_id'] : 0);
		$filter = $this->entityExportFilter($entity, $export);
		$mappings = isset($settings[$entity . '_mappings']) ? $settings[$entity . '_mappings'] : array();
		$rows = $this->model_extension_module_wt_exchange->getEntityRows($entity, $fields, $language_id, $filter);

		$columns = array();
		foreach ($fields as $code) {
			$columns[] = (!empty($mappings[$code])) ? $mappings[$code] : $code;
		}
		$prefix_map = array(
			'category'     => 'wt_categories',
			'manufacturer' => 'wt_manufacturers',
			'customer'     => 'wt_customers',
			'order'        => 'wt_orders'
		);
		$prefix = isset($prefix_map[$entity]) ? $prefix_map[$entity] : ('wt_' . $entity);

		if ($format === 'json') {
			$content = WtExchangeFormat::encodeJson($rows, $entity);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.json';
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		} elseif ($format === 'xml') {
			$content = WtExchangeFormat::encodeXml($rows, $fields, $entity, $encoding);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.xml';
			$this->response->addHeader('Content-Type: ' . WtExchangeFormat::xmlContentType($encoding));
		} elseif ($format === 'xlsx') {
			$mapped_rows = array();
			foreach ($rows as $row) {
				$mapped = array();
				foreach ($fields as $i => $code) {
					$mapped[$columns[$i]] = isset($row[$code]) ? $row[$code] : '';
				}
				$mapped_rows[] = $mapped;
			}
			$content = WtExchangeXlsx::encode($mapped_rows, $columns);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.xlsx';
			$this->response->addHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		} else {
			$mapped_rows = array();
			foreach ($rows as $row) {
				$mapped = array();
				foreach ($fields as $i => $code) {
					$mapped[$columns[$i]] = isset($row[$code]) ? $row[$code] : '';
				}
				$mapped_rows[] = $mapped;
			}
			$content = WtExchangeFormat::encodeCsv($mapped_rows, $columns, $delimiter, $encoding);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.csv';
			$this->response->addHeader('Content-Type: ' . WtExchangeFormat::csvContentType($encoding));
		}

		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($content);
	}

	private function importEntity($entity) {
		$this->bootstrap();
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange') || $this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link($entity, 'tab=import'));
		}

		if (empty($this->request->files['import_file']['tmp_name']) || !is_uploaded_file($this->request->files['import_file']['tmp_name'])) {
			$this->session->data['error'] = $this->language->get('error_file');
			$this->response->redirect($this->link($entity, 'tab=import'));
		}

		$settings = $this->getStoredSettings();
		$export_key = $entity . '_export';
		$import_key = $entity . '_import';
		$import = isset($settings[$import_key]) ? $settings[$import_key] : array();
		$export = isset($settings[$export_key]) ? $settings[$export_key] : array();

		$delimiter = !empty($import['delimiter']) ? $import['delimiter'] : ';';
		$encoding = !empty($import['encoding']) ? $import['encoding'] : 'UTF-8';
		$key = !empty($import['key']) ? $import['key'] : '_ID_';
		$mode = !empty($import['mode']) ? $import['mode'] : 'update';
		if ($entity === 'order') {
			$mode = 'update';
		}
		$preferred_fields = !empty($export['fields']) ? $export['fields'] : WtExchangeFieldRegistry::defaultEntityExportFields($entity);

		$mappings = isset($settings[$entity . '_mappings']) ? $settings[$entity . '_mappings'] : array();

		$content = file_get_contents($this->request->files['import_file']['tmp_name']);
		$name = strtolower($this->request->files['import_file']['name']);

		if (substr($name, -5) === '.json') {
			$rows = WtExchangeFormat::decodeJson($content);
		} elseif (substr($name, -4) === '.xml') {
			$rows = WtExchangeFormat::decodeXml($content, $encoding);
		} elseif (substr($name, -5) === '.xlsx') {
			$rows = WtExchangeXlsx::decode($content);
		} else {
			$rows = WtExchangeFormat::decodeCsv($content, $delimiter, $encoding);
		}

		if (!$rows) {
			$this->session->data['error'] = $this->language->get('error_file');
			$this->response->redirect($this->link($entity, 'tab=import'));
		}

		if (!empty($settings['import_mode_ajax'])) {
			$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
			$job_api = new WtExchangeImportJob($workdir);
			$config = $this->buildImportConfig($entity, $settings);
			$job = $job_api->create(array('entity' => $entity, 'config' => $config, 'total' => count($rows), 'rows' => $rows));
			$this->session->data['wt_exchange_job_id'] = $job['id'];
			$this->session->data['success'] = $this->language->get('text_import_started');
			$this->response->redirect($this->link($entity, 'tab=import'));
		}

		$logger = new WtExchangeLogger(
			!empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir(),
			!empty($settings['product_log'])
		);
		$runner = new WtExchangeImportRunner($this->registry, $logger);
		$config = $this->buildImportConfig($entity, $settings);

		$updated = 0;
		$inserted = 0;
		$skipped = 0;
		$row_num = 0;

		foreach ($rows as $row) {
			$row_num++;
			$row = $this->model_extension_module_wt_exchange->normalizeRowKeys($row, $mappings);
			$result = $runner->processEntityRow($entity, $row, $config, $row_num);
			if ($result['action'] === 'updated') {
				$updated++;
			} elseif ($result['action'] === 'inserted') {
				$inserted++;
			} else {
				$skipped++;
			}
		}

		$this->session->data['success'] = sprintf($this->language->get('text_import_done'), $updated, $inserted, $skipped);
		$this->response->redirect($this->link($entity, 'tab=import'));
	}

	private function entityMenuKey($entity) {
		$map = array(
			'category'     => 'categories',
			'manufacturer' => 'manufacturers',
			'customer'     => 'customers',
			'order'        => 'orders'
		);
		return isset($map[$entity]) ? $map[$entity] : $entity;
	}

	private function buildImportConfig($entity, array $settings) {
		$export_key = $entity . '_export';
		$import_key = $entity . '_import';
		$mappings_key = $entity . '_mappings';
		if ($entity === 'product') {
			$mappings_key = 'product_mappings';
		}

		$import = isset($settings[$import_key]) ? $settings[$import_key] : array();
		$export = isset($settings[$export_key]) ? $settings[$export_key] : array();
		$defaults = isset($settings['product_default']) ? $settings['product_default'] : array();

		return array(
			'entity'             => $entity,
			'language_id'        => $this->resolveLanguageId(isset($import['language_id']) ? $import['language_id'] : 0),
			'delimiter'          => !empty($import['delimiter']) ? $import['delimiter'] : ';',
			'encoding'           => !empty($import['encoding']) ? $import['encoding'] : 'UTF-8',
			'mode'               => !empty($import['mode']) ? $import['mode'] : 'update',
			'key'                => !empty($import['key']) ? $import['key'] : ($entity === 'product' ? '_MODEL_' : '_ID_'),
			'fields'             => !empty($import['fields']) ? $import['fields'] : (!empty($export['fields']) ? $export['fields'] : ($entity === 'product' ? WtExchangeFieldRegistry::defaultProductExportFields() : WtExchangeFieldRegistry::defaultEntityExportFields($entity))),
			'mappings'           => isset($settings[$mappings_key]) ? $settings[$mappings_key] : array(),
			'macros'             => isset($settings['product_macros']) ? $settings['product_macros'] : array(),
			'defaults'           => $defaults,
			'category_delimiter' => !empty($export['category_delimiter']) ? $export['category_delimiter'] : ' > ',
			'create_categories'  => !empty($import['create_categories']),
			'download_images'    => !empty($import['download_images'])
		);
	}

	private function runScheduledExport($entity, array $settings) {
		$export_key = $entity . '_export';
		$export = isset($settings[$export_key]) ? $settings[$export_key] : array();
		$mappings_key = $entity . '_mappings';
		if ($entity === 'product') {
			$mappings_key = 'product_mappings';
		}
		$mappings = isset($settings[$mappings_key]) ? $settings[$mappings_key] : array();
		$fields = !empty($export['fields']) ? $export['fields'] : ($entity === 'product' ? WtExchangeFieldRegistry::defaultProductExportFields() : WtExchangeFieldRegistry::defaultEntityExportFields($entity));
		$language_id = $this->resolveLanguageId(isset($export['language_id']) ? $export['language_id'] : 0);
		$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		$format = isset($export['format']) ? $export['format'] : 'csv';

		if ($entity === 'product') {
			$filter = array(
				'category_ids'       => !empty($export['category_ids']) ? $export['category_ids'] : array(),
				'include_children'   => isset($export['include_children']) ? (int)$export['include_children'] : 1,
				'category_delimiter' => isset($export['category_delimiter']) ? $export['category_delimiter'] : ' > '
			);
			$rows = $this->model_extension_module_wt_exchange->getProductRows($fields, $language_id, $filter);
		} else {
			$filter = $this->entityExportFilter($entity, $export);
			$rows = $this->model_extension_module_wt_exchange->getEntityRows($entity, $fields, $language_id, $filter);
		}

		$columns = array();
		foreach ($fields as $code) {
			$columns[] = (!empty($mappings[$code])) ? $mappings[$code] : $code;
		}
		$mapped_rows = array();
		foreach ($rows as $row) {
			$mapped = array();
			foreach ($fields as $i => $code) {
				$mapped[$columns[$i]] = isset($row[$code]) ? $row[$code] : '';
			}
			$mapped_rows[] = $mapped;
		}

		$prefix = 'scheduled_' . $entity;
		$delimiter = !empty($export['delimiter']) ? $export['delimiter'] : ';';
		$encoding = !empty($export['encoding']) ? $export['encoding'] : 'UTF-8';
		if ($format === 'json') {
			$content = WtExchangeFormat::encodeJson($mapped_rows, $entity);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.json';
		} elseif ($format === 'xlsx') {
			$content = WtExchangeXlsx::encode($mapped_rows, $columns);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.xlsx';
		} else {
			$content = WtExchangeFormat::encodeCsv($mapped_rows, $columns, $delimiter, $encoding);
			$filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.csv';
		}
		@file_put_contents(rtrim($workdir, '/\\') . '/exports/' . $filename, $content);
	}

	private function entityExportFilter($entity, array $export) {
		$filter = array();
		if ($entity === 'category') {
			if (!empty($export['parent_id'])) {
				$filter['parent_id'] = (int)$export['parent_id'];
			}
			if (isset($export['status']) && $export['status'] !== '') {
				$filter['status'] = (int)$export['status'];
			}
		} elseif ($entity === 'customer') {
			if (!empty($export['customer_group_id'])) {
				$filter['customer_group_id'] = (int)$export['customer_group_id'];
			}
			if (isset($export['status']) && $export['status'] !== '') {
				$filter['status'] = (int)$export['status'];
			}
		} elseif ($entity === 'order') {
			if (!empty($export['order_status_id'])) {
				$filter['order_status_id'] = (int)$export['order_status_id'];
			}
			if (!empty($export['date_from'])) {
				$filter['date_from'] = $export['date_from'];
			}
			if (!empty($export['date_to'])) {
				$filter['date_to'] = $export['date_to'];
			}
		}
		return $filter;
	}

	private function bootstrap() {
		$this->load->language('extension/module/wt_exchange');
		$this->load->model('extension/module/wt_exchange');
		$this->document->addStyle('view/stylesheet/extension/module/wt_exchange.css');
		$this->ensureLibraries();
	}

	private function ensureLibraries() {
		if (!class_exists('WtExchangeFieldRegistry', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/field_registry.php');
		}
		if (!class_exists('WtExchangeFormat', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/format.php');
		}
		if (!class_exists('WtExchangeEntityExchange', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/entity_exchange.php');
		}
		if (!class_exists('WtExchangeLogger', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/logger.php');
		}
		if (!class_exists('WtExchangeImportJob', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/import_job.php');
		}
		if (!class_exists('WtExchangeImportRunner', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/import_runner.php');
		}
		if (!class_exists('WtExchangeMacro', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/macro.php');
		}
		if (!class_exists('WtExchangeXlsx', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/xlsx.php');
		}
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_exchange')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

	private function resolveLanguageId($language_id) {
		$language_id = (int)$language_id;
		if ($language_id > 0) {
			return $language_id;
		}
		return (int)$this->config->get('config_language_id');
	}

	private function getStoredSettings() {
		$data = $this->config->get('module_wt_exchange_data');
		return is_array($data) ? $data : array();
	}

	private function saveSettings(array $data) {
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_exchange', array(
			'module_wt_exchange_status' => 1,
			'module_wt_exchange_data'   => $data
		));
	}

	private function link($method, $extra = '') {
		$url = 'user_token=' . $this->session->data['user_token'];
		if ($extra) {
			$url .= '&' . ltrim($extra, '&');
		}
		return $this->url->link('extension/module/wt_exchange/' . $method, $url, true);
	}

	private function commonData($active) {
		$data = array();
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_home'] = $this->language->get('text_home');
		$data['text_extension'] = $this->language->get('text_extension');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_export'] = $this->language->get('button_export');
		$data['button_import'] = $this->language->get('button_import');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['entry_encoding'] = $this->language->get('entry_encoding');
		$data['entry_delimiter'] = $this->language->get('entry_delimiter');
		$data['entry_format'] = $this->language->get('entry_format');
		$data['entry_fields'] = $this->language->get('entry_fields');
		$data['entry_key'] = $this->language->get('entry_key');
		$data['entry_mode'] = $this->language->get('entry_mode');
		$data['entry_file'] = $this->language->get('entry_file');
		$data['entry_workdir'] = $this->language->get('entry_workdir');
		$data['entry_ajax_timeout'] = $this->language->get('entry_ajax_timeout');
		$data['entry_import_ajax'] = $this->language->get('entry_import_ajax');
		$data['entry_product_log'] = $this->language->get('entry_product_log');
		$data['entry_languages'] = $this->language->get('entry_languages');
		$data['help_language'] = $this->language->get('help_language');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_default'] = $this->language->get('text_default');
		$data['text_select_all'] = $this->language->get('text_select_all');
		$data['text_unselect_all'] = $this->language->get('text_unselect_all');
		$data['text_help_general'] = $this->language->get('text_help_general');
		$data['text_stub'] = $this->language->get('text_stub');
		$data['text_stub_options'] = $this->language->get('text_stub_options');
		$data['text_default_product'] = $this->language->get('text_default_product');
		$data['text_default_options'] = $this->language->get('text_default_options');
		$data['text_fields_help'] = $this->language->get('text_fields_help');
		$data['entry_minimum'] = $this->language->get('entry_minimum');
		$data['entry_subtract'] = $this->language->get('entry_subtract');
		$data['entry_shipping'] = $this->language->get('entry_shipping');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_field_code'] = $this->language->get('entry_field_code');
		$data['entry_csv_name'] = $this->language->get('entry_csv_name');
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_category_export'] = $this->language->get('entry_category_export');
		$data['entry_category_delimiter'] = $this->language->get('entry_category_delimiter');
		$data['entry_include_children'] = $this->language->get('entry_include_children');
		$data['help_category_filter'] = $this->language->get('help_category_filter');
		$data['text_order_import_note'] = $this->language->get('text_order_import_note');
		$data['tab_general_settings'] = $this->language->get('tab_general_settings');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		}
		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->link('general')
		);

		$data['top_menu'] = array(
			array('id' => 'general', 'text' => $this->language->get('menu_general'), 'url' => $this->link('general'), 'active' => $active === 'general'),
			array('id' => 'product', 'text' => $this->language->get('menu_products'), 'url' => $this->link('product'), 'active' => $active === 'product'),
			array('id' => 'category', 'text' => $this->language->get('menu_categories'), 'url' => $this->link('category'), 'active' => $active === 'category'),
			array('id' => 'manufacturer', 'text' => $this->language->get('menu_manufacturers'), 'url' => $this->link('manufacturer'), 'active' => $active === 'manufacturer'),
			array('id' => 'customer', 'text' => $this->language->get('menu_customers'), 'url' => $this->link('customer'), 'active' => $active === 'customer'),
			array('id' => 'order', 'text' => $this->language->get('menu_orders'), 'url' => $this->link('order'), 'active' => $active === 'order'),
			array('id' => 'scheduler', 'text' => $this->language->get('menu_scheduler'), 'url' => $this->link('scheduler'), 'active' => $active === 'scheduler'),
			array('id' => 'log', 'text' => $this->language->get('menu_log'), 'url' => $this->link('log'), 'active' => $active === 'log'),
			array('id' => 'about', 'text' => $this->language->get('menu_support'), 'url' => $this->link('about'), 'active' => $active === 'about')
		);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		return $data;
	}

	private function renderPage($route, $data) {
		$this->response->setOutput($this->load->view($route, $data));
	}
}
