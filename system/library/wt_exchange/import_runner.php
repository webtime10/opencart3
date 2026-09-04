<?php
class WtExchangeImportRunner {
	private $registry;
	private $model;
	private $logger;

	public function __construct($registry, WtExchangeLogger $logger = null) {
		$this->registry = $registry;
		$this->registry->get('load')->model('extension/module/wt_exchange');
		$this->model = $this->registry->get('model_extension_module_wt_exchange');
		$this->logger = $logger;
	}

	public function processProductRow(array $row, array $config, $row_num = 0) {
		$this->loadLibraries();
		$mappings = isset($config['mappings']) ? $config['mappings'] : array();
		$macros = isset($config['macros']) ? $config['macros'] : array();
		$key = !empty($config['key']) ? $config['key'] : '_MODEL_';
		$mode = !empty($config['mode']) ? $config['mode'] : 'update';
		$language_id = (int)$config['language_id'];
		$defaults = isset($config['defaults']) ? $config['defaults'] : array();
		$preferred_fields = !empty($config['fields']) ? $config['fields'] : WtExchangeFieldRegistry::defaultProductExportFields();

		$row = $this->model->normalizeRowKeys($row, $mappings);
		$row = WtExchangeMacro::apply($row, $macros);
		$row_fields = $this->model->resolveImportFields($row, $preferred_fields);

		if (!isset($row[$key]) || $row[$key] === '') {
			$this->logLine($row_num, 'skipped', 'Empty key ' . $key);
			return array('action' => 'skipped', 'error' => 'Empty key');
		}

		$import_options = array(
			'language_id'        => $language_id,
			'category_delimiter' => !empty($config['category_delimiter']) ? $config['category_delimiter'] : ' > ',
			'create_categories'  => !empty($config['create_categories']),
			'download_images'    => !empty($config['download_images'])
		);

		$product_id = $this->model->findProductIdByKey($key, $row[$key]);

		try {
			if ($product_id) {
				if ($mode === 'insert') {
					$this->logLine($row_num, 'skipped', 'Exists: ' . $row[$key]);
					return array('action' => 'skipped', 'error' => 'Already exists');
				}
				$this->model->updateProductFromRow($product_id, $row, $row_fields, $language_id, $import_options);
				$this->logLine($row_num, 'updated', $key . '=' . $row[$key]);
				return array('action' => 'updated', 'error' => '');
			}

			if ($mode === 'update') {
				$this->logLine($row_num, 'skipped', 'Not found: ' . $row[$key]);
				return array('action' => 'skipped', 'error' => 'Not found');
			}

			$this->model->insertProductFromRow($row, $row_fields, $language_id, $defaults, $import_options);
			$this->logLine($row_num, 'inserted', $key . '=' . $row[$key]);
			return array('action' => 'inserted', 'error' => '');
		} catch (Exception $e) {
			$this->logLine($row_num, 'error', $e->getMessage());
			return array('action' => 'skipped', 'error' => $e->getMessage());
		}
	}

	public function processEntityRow($entity, array $row, array $config, $row_num = 0) {
		$this->loadLibraries();
		$mappings = isset($config['mappings']) ? $config['mappings'] : array();
		$key = !empty($config['key']) ? $config['key'] : '_ID_';
		$mode = !empty($config['mode']) ? $config['mode'] : 'update';
		if ($entity === 'order') {
			$mode = 'update';
		}
		$language_id = (int)$config['language_id'];
		$preferred_fields = !empty($config['fields']) ? $config['fields'] : WtExchangeFieldRegistry::defaultEntityExportFields($entity);

		$row = $this->model->normalizeRowKeys($row, $mappings);
		$row_fields = $this->model->resolveEntityImportFields($entity, $row, $preferred_fields);

		if (!isset($row[$key]) || $row[$key] === '') {
			$this->logLine($row_num, 'skipped', 'Empty key ' . $key);
			return array('action' => 'skipped', 'error' => 'Empty key');
		}

		$entity_id = $this->model->findEntityIdByKey($entity, $key, $row[$key]);

		if ($entity_id) {
			if ($mode === 'insert') {
				$this->logLine($row_num, 'skipped', 'Exists');
				return array('action' => 'skipped', 'error' => 'Already exists');
			}
			$ok = $this->model->importEntityRow($entity, $entity_id, $row, $row_fields, $language_id, false);
			if ($ok) {
				$this->logLine($row_num, 'updated', $entity . ' ' . $row[$key]);
				return array('action' => 'updated', 'error' => '');
			}
			$this->logLine($row_num, 'skipped', 'Update failed');
			return array('action' => 'skipped', 'error' => 'Update failed');
		}

		if ($mode === 'update' || $entity === 'order') {
			$this->logLine($row_num, 'skipped', 'Not found');
			return array('action' => 'skipped', 'error' => 'Not found');
		}

		$ok = $this->model->importEntityRow($entity, 0, $row, $row_fields, $language_id, true);
		if ($ok) {
			$this->logLine($row_num, 'inserted', $entity . ' ' . $row[$key]);
			return array('action' => 'inserted', 'error' => '');
		}
		$this->logLine($row_num, 'skipped', 'Insert failed');
		return array('action' => 'skipped', 'error' => 'Insert failed');
	}

	private function logLine($row_num, $action, $message) {
		if ($this->logger) {
			$this->logger->write('Row ' . (int)$row_num . ' [' . $action . '] ' . $message);
		}
	}

	private function loadLibraries() {
		if (!class_exists('WtExchangeFieldRegistry', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/field_registry.php');
		}
		if (!class_exists('WtExchangeMacro', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/macro.php');
		}
	}
}
