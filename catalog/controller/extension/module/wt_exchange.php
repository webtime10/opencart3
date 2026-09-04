<?php
class ControllerExtensionModuleWtExchange extends Controller {
	public function cron() {
		$this->load->model('setting/setting');
		$settings = $this->config->get('module_wt_exchange_data');
		if (!is_array($settings)) {
			$settings = array();
		}
		$scheduler = isset($settings['scheduler']) ? $settings['scheduler'] : array();
		$key = isset($this->request->get['key']) ? $this->request->get['key'] : '';

		if (empty($scheduler['cron_key']) || $key !== $scheduler['cron_key']) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput('Forbidden');
			return;
		}

		if (!class_exists('WtExchangeFieldRegistry', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/field_registry.php');
		}
		if (!class_exists('WtExchangeFormat', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/format.php');
		}
		if (!class_exists('WtExchangeXlsx', false)) {
			require_once(DIR_SYSTEM . 'library/wt_exchange/xlsx.php');
		}

		$this->load->model('extension/module/wt_exchange');
		$workdir = !empty($settings['workdir']) ? $settings['workdir'] : $this->model_extension_module_wt_exchange->getWorkDir();
		@mkdir(rtrim($workdir, '/\\') . '/exports', 0755, true);

		$results = array();
		if (!empty($scheduler['tasks']) && is_array($scheduler['tasks'])) {
			foreach ($scheduler['tasks'] as $task) {
				if (empty($task['enabled'])) {
					continue;
				}
				$entity = isset($task['entity']) ? $task['entity'] : 'product';
				$action = isset($task['action']) ? $task['action'] : 'export';
				if ($action !== 'export') {
					continue;
				}

				$export_key = $entity . '_export';
				$export = isset($settings[$export_key]) ? $settings[$export_key] : array();
				$mappings_key = ($entity === 'product') ? 'product_mappings' : ($entity . '_mappings');
				$mappings = isset($settings[$mappings_key]) ? $settings[$mappings_key] : array();
				$fields = !empty($export['fields']) ? $export['fields'] : ($entity === 'product' ? WtExchangeFieldRegistry::defaultProductExportFields() : WtExchangeFieldRegistry::defaultEntityExportFields($entity));
				$language_id = !empty($export['language_id']) ? (int)$export['language_id'] : (int)$this->config->get('config_language_id');
				$format = isset($export['format']) ? $export['format'] : 'csv';

				if ($entity === 'product') {
					$filter = array(
						'category_ids'       => !empty($export['category_ids']) ? $export['category_ids'] : array(),
						'include_children'   => isset($export['include_children']) ? (int)$export['include_children'] : 1,
						'category_delimiter' => isset($export['category_delimiter']) ? $export['category_delimiter'] : ' > '
					);
					$rows = $this->model_extension_module_wt_exchange->getProductRows($fields, $language_id, $filter);
				} else {
					$rows = $this->model_extension_module_wt_exchange->getEntityRows($entity, $fields, $language_id, array());
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

				$filename = 'scheduled_' . $entity . '_' . date('Y-m-d_H-i-s');
				$delimiter = !empty($export['delimiter']) ? $export['delimiter'] : ';';
				$encoding = !empty($export['encoding']) ? $export['encoding'] : 'UTF-8';
				if ($format === 'json') {
					$content = WtExchangeFormat::encodeJson($mapped_rows, $entity);
					$filename .= '.json';
				} elseif ($format === 'xlsx') {
					$content = WtExchangeXlsx::encode($mapped_rows, $columns);
					$filename .= '.xlsx';
				} else {
					$content = WtExchangeFormat::encodeCsv($mapped_rows, $columns, $delimiter, $encoding);
					$filename .= '.csv';
				}
				file_put_contents(rtrim($workdir, '/\\') . '/exports/' . $filename, $content);
				$results[] = $entity . ' -> ' . $filename;
			}
		}

		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput($results ? implode("\n", $results) : 'OK');
	}
}
