<?php
/**
 * Модуль wt_filter (OpenCart 3).
 * Пока только: настройки статуса + AJAX copyFilters (опции/фильтры/атрибуты → свои таблицы).
 */
class ControllerExtensionModuleWtFilter extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_filter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_wt_filter', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
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
			'href' => $this->url->link('extension/module/wt_filter', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/wt_filter', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->post['module_wt_filter_status'])) {
			$data['module_wt_filter_status'] = $this->request->post['module_wt_filter_status'];
		} else {
			$data['module_wt_filter_status'] = $this->config->get('module_wt_filter_status');
		}

		if (isset($this->request->post['module_wt_filter_attribute_separator'])) {
			$data['module_wt_filter_attribute_separator'] = $this->request->post['module_wt_filter_attribute_separator'];
		} else {
			$separator = $this->config->get('module_wt_filter_attribute_separator');
			// По умолчанию запятая (если ещё не сохраняли настройку)
			$data['module_wt_filter_attribute_separator'] = ($separator !== null && $separator !== false) ? $separator : ',';
		}

		$data['types'] = array('checkbox', 'radio', 'select');

		$this->load->model('setting/store');
		$data['stores'] = $this->model_setting_store->getStores();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_filter', $data));
	}

	/**
	 * AJAX: один шаг копирования (для прогресс-бара).
	 * POST step = truncate|option|filter|attribute|finalize
	 */
	public function copyFilters() {
		$json = array();

		$this->load->language('extension/module/wt_filter');

		if (!$this->user->hasPermission('modify', 'extension/module/wt_filter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && empty($json['error'])) {
			if (!isset($this->request->post['copy_store'])) {
				$json['error'] = $this->language->get('error_copy_store');
			}

			if (empty($this->request->post['copy_type'])) {
				$json['error'] = $this->language->get('error_copy_type');
			}

			$step = isset($this->request->post['step']) ? (string)$this->request->post['step'] : '';
			$allowed = array('truncate', 'option', 'filter', 'attribute', 'finalize');

			if (!in_array($step, $allowed, true)) {
				$json['error'] = $this->language->get('error_copy_step');
			}

			if (empty($json['error'])) {
				$this->load->model('catalog/wt_filter');
				$this->model_catalog_wt_filter->copyFiltersStep($step, $this->request->post);

				$messages = array(
					'truncate'  => $this->language->get('text_progress_truncate'),
					'option'    => $this->language->get('text_progress_option'),
					'filter'    => $this->language->get('text_progress_filter'),
					'attribute' => $this->language->get('text_progress_attribute'),
					'finalize'  => $this->language->get('text_progress_finalize'),
				);

				$json['success'] = true;
				$json['step'] = $step;
				$json['message'] = isset($messages[$step]) ? $messages[$step] : $step;

				if ($step === 'finalize') {
					$json['complete'] = true;
					$json['message'] = $this->language->get('text_complete');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->model('catalog/wt_filter');
		$this->model_catalog_wt_filter->install();

		// Права для меню Каталог → WT Filter (OCMOD сам права не добавляет)
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'catalog/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'catalog/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/wt_filter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/wt_filter');

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_filter', array(
			'module_wt_filter_status'              => 1,
			'module_wt_filter_attribute_separator' => ',',
			'module_wt_filter_price_special'     => 0,
			'module_wt_filter_discount'            => 1,
			'module_wt_filter_seo_text_position'   => 'below',
			'module_wt_filter_show_counts'         => 1,
			'module_wt_filter_accent_color'        => '#229ac8',
			'module_wt_filter_group_expanded'      => json_encode(array(
				'p' => array('desktop' => 1, 'mobile' => 1),
				'm' => array('desktop' => 1, 'mobile' => 0),
				's' => array('desktop' => 1, 'mobile' => 0),
				'd' => array('desktop' => 1, 'mobile' => 0),
			)),
		));

		$this->ensureLayoutModules();
	}

	/**
	 * Вставить wt_filter в column_left для категории / производителя / акций.
	 */
	private function ensureLayoutModules() {
		$routes = array('product/category', 'product/manufacturer/info', 'product/special');

		foreach ($routes as $route) {
			$query = $this->db->query("SELECT layout_id FROM `" . DB_PREFIX . "layout_route`
				WHERE route = '" . $this->db->escape($route) . "' AND store_id = '0' LIMIT 1");

			if (!$query->num_rows) {
				continue;
			}

			$layout_id = (int)$query->row['layout_id'];
			$exists = $this->db->query("SELECT layout_module_id FROM `" . DB_PREFIX . "layout_module`
				WHERE layout_id = '" . $layout_id . "' AND code = 'wt_filter' AND position = 'column_left' LIMIT 1");

			if (!$exists->num_rows) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "layout_module` SET
					layout_id = '" . $layout_id . "',
					code = 'wt_filter',
					position = 'column_left',
					sort_order = '1'");
			}
		}
	}

	public function uninstall() {
		$this->load->model('catalog/wt_filter');
		$this->model_catalog_wt_filter->uninstall();

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_filter');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
