<?php
class ControllerCatalogWtFilter extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/wt_filter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('catalog/wt_filter');
		$this->model_catalog_wt_filter->ensureSchema();
		$this->getList();
	}

	public function edit() {
		$this->load->language('catalog/wt_filter');
		$this->load->model('catalog/wt_filter');

		if (isset($this->request->post['option_id']) && isset($this->request->post['field'])) {
			$json = array('status' => false);

			if ($this->user->hasPermission('modify', 'catalog/wt_filter')) {
				$field = (string)$this->request->post['field'];
				$value = isset($this->request->post['value']) ? urldecode((string)$this->request->post['value']) : '';
				$json['status'] = (bool)$this->model_catalog_wt_filter->editOptionField((int)$this->request->post['option_id'], $field, $value);
			}

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_wt_filter->editOption((int)$this->request->get['option_id'], $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $this->urlSuffix(), true));
		}

		$this->getForm();
	}

	public function callback() {
		$json = array(
			'message' => '',
			'options' => array(),
		);

		$this->load->language('catalog/wt_filter');
		$this->load->model('catalog/wt_filter');

		if (!isset($this->request->get['category_id'])) {
			$json['message'] = $this->language->get('text_select_category');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$product_values = array();

		if (isset($this->request->get['product_id'])) {
			$product_values = $this->model_catalog_wt_filter->getProductValues($this->request->get['product_id']);
		}

		$results = $this->model_catalog_wt_filter->getOptionsByCategoryId($this->request->get['category_id']);

		if (!$results) {
			$json['message'] = $this->language->get('text_no_options');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		foreach (array_values($results) as $key => $option) {
			$values = array();

			if ($option['type'] != 'slide' && $option['type'] != 'slide_dual' && $option['type'] != 'text') {
				foreach ($option['values'] as $_key => $value) {
					$values[$_key] = array(
						'value_id' => (string)$value['value_id'],
						'name'     => $value['name'],
						'selected' => isset($product_values[$option['option_id']][(string)$value['value_id']]),
					);
				}
			}

			$json['options'][$key] = array(
				'option_id'       => (string)$option['option_id'],
				'name'            => $option['name'],
				'postfix'         => isset($option['postfix']) ? $option['postfix'] : '',
				'status'          => (int)$option['status'],
				'type'            => $option['type'],
				'slide_value_min' => '',
				'slide_value_max' => '',
				'values'          => $values,
			);

			if (isset($product_values[$option['option_id']]['0'])) {
				$pv = $product_values[$option['option_id']]['0'];
				$json['options'][$key]['slide_value_min'] = ((float)$pv['slide_value_min'] ? preg_replace('!(0+?$)|(\.0+?$)!', '', $pv['slide_value_min']) : '');
				$json['options'][$key]['slide_value_max'] = ((float)$pv['slide_value_max'] ? preg_replace('!(0+?$)|(\.0+?$)!', '', $pv['slide_value_max']) : '');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function delete() {
		$this->load->language('catalog/wt_filter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('catalog/wt_filter');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $option_id) {
				$this->model_catalog_wt_filter->deleteOption($option_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $this->urlSuffix(), true));
	}

	protected function getList() {
		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$filter_category_id = isset($this->request->get['filter_category_id']) ? $this->request->get['filter_category_id'] : '';
		$filter_type = isset($this->request->get['filter_type']) ? (string)$this->request->get['filter_type'] : '';
		$filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
		$sort = isset($this->request->get['sort']) ? (string)$this->request->get['sort'] : 'o.sort_order';
		$order = (isset($this->request->get['order']) && $this->request->get['order'] == 'DESC') ? 'DESC' : 'ASC';
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$url = $this->urlSuffix();

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $url, true)
			)
		);

		$data['delete'] = $this->url->link('catalog/wt_filter/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['module'] = $this->url->link('extension/module/wt_filter', 'user_token=' . $this->session->data['user_token'], true);
		$data['seo_pages'] = $this->url->link('extension/module/wt_filter', 'user_token=' . $this->session->data['user_token'] . '&tab=seo', true);
		$data['button_seo_pages'] = $this->language->get('button_seo_pages');

		$limit = (int)$this->config->get('config_limit_admin');
		if ($limit < 1) {
			$limit = 20;
		}

		$filter_data = array(
			'filter_name'        => $filter_name,
			'filter_category_id' => $filter_category_id,
			'filter_type'        => $filter_type,
			'filter_status'      => $filter_status,
			'sort'               => $sort,
			'order'              => $order,
			'start'              => ($page - 1) * $limit,
			'limit'              => $limit,
		);

		$option_total = $this->model_catalog_wt_filter->getTotalOptions($filter_data);
		$results = $this->model_catalog_wt_filter->getOptions($filter_data);
		$visible = 3;
		$types = $this->getTypes();

		$data['options'] = array();

		foreach ($results as $result) {
			$all_values = array();
			foreach ($result['values'] as $value) {
				$name = html_entity_decode($value['name'], ENT_QUOTES, 'UTF-8');
				if (!empty($result['postfix'])) {
					$name .= $result['postfix'];
				}
				$all_values[] = $name;
			}

			$values_shown = array_slice($all_values, 0, $visible);
			$values_rest = array_slice($all_values, $visible);

			$all_categories = array();
			foreach ($result['categories'] as $category) {
				$all_categories[] = $category['name'];
			}

			$categories_shown = array_slice($all_categories, 0, $visible);
			$categories_rest = array_slice($all_categories, $visible);

			$type_key = (string)$result['type'];

			$data['options'][] = array(
				'option_id'             => $result['option_id'],
				'name'                  => $result['name'],
				'type'                  => $type_key,
				'type_label'            => isset($types[$type_key]) ? $types[$type_key] : $type_key,
				'sort_order'            => $result['sort_order'],
				'status'                => (int)$result['status'],
				'expanded_desktop'      => isset($result['expanded_desktop']) ? (int)$result['expanded_desktop'] : 1,
				'expanded_mobile'       => isset($result['expanded_mobile']) ? (int)$result['expanded_mobile'] : 0,
				'selected'              => isset($this->request->post['selected']) && in_array($result['option_id'], $this->request->post['selected']),
				'values'                => $values_shown,
				'values_extra'          => count($values_rest),
				'values_more_label'     => sprintf($this->language->get('text_more'), count($values_rest)),
				'values_more_title'     => implode(', ', $values_rest),
				'categories'            => $categories_shown,
				'categories_extra'      => count($categories_rest),
				'categories_more_label' => sprintf($this->language->get('text_more'), count($categories_rest)),
				'categories_more_title' => implode(', ', $categories_rest),
				'edit'                  => $this->url->link('catalog/wt_filter/edit', 'user_token=' . $this->session->data['user_token'] . '&option_id=' . $result['option_id'] . $url, true),
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_more'] = $this->language->get('text_more');
		$data['text_drag'] = $this->language->get('text_drag');
		$data['text_hint_copy'] = sprintf(
			$this->language->get('text_hint_copy'),
			$this->url->link('extension/module/wt_filter', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['column_name'] = $this->language->get('column_name');
		$data['column_values'] = $this->language->get('column_values');
		$data['column_categories'] = $this->language->get('column_categories');
		$data['column_type'] = $this->language->get('column_type');
		$data['column_sort_order'] = $this->language->get('column_sort_order');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_expanded'] = $this->language->get('column_expanded');
		$data['column_expanded_desktop'] = $this->language->get('column_expanded_desktop');
		$data['column_expanded_mobile'] = $this->language->get('column_expanded_mobile');
		$data['text_expanded_open'] = $this->language->get('text_expanded_open');
		$data['text_expanded_closed'] = $this->language->get('text_expanded_closed');
		$data['help_expanded_desktop'] = $this->language->get('help_expanded_desktop');
		$data['help_expanded_mobile'] = $this->language->get('help_expanded_mobile');
		$data['column_action'] = $this->language->get('column_action');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_all'] = $this->language->get('text_all');
		$data['button_filter'] = $this->language->get('button_filter');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_module'] = $this->language->get('button_module');

		$data['types'] = $types;
		$data['user_token'] = $this->session->data['user_token'];
		$data['filter_name'] = $filter_name;
		$data['filter_category_id'] = $filter_category_id;
		$data['filter_type'] = $filter_type;
		$data['filter_status'] = $filter_status;

		$this->load->model('catalog/category');
		$data['categories'] = $this->model_catalog_category->getCategories(array('sort' => 'name'));

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

		$url_sort = $this->urlSuffix(array('sort', 'order', 'page'));
		$url_sort .= ($order == 'ASC') ? '&order=DESC' : '&order=ASC';

		$data['sort_name'] = $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . '&sort=od.name' . $url_sort, true);
		$data['sort_order'] = $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . '&sort=o.sort_order' . $url_sort, true);

		$url_page = $this->urlSuffix(array('page'));
		$pagination = new Pagination();
		$pagination->total = $option_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $url_page . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			($option_total) ? (($page - 1) * $limit) + 1 : 0,
			((($page - 1) * $limit) > ($option_total - $limit)) ? $option_total : ((($page - 1) * $limit) + $limit),
			$option_total,
			ceil($option_total / $limit)
		);

		$data['sort'] = $sort;
		$data['order'] = $order;

		$this->document->addStyle('view/stylesheet/wt_filter/wt_filter.css');
		$this->document->addScript('view/javascript/jquery/Sortable.js');
		$this->document->addScript('view/javascript/jquery/jquery-sortable.js');
		$this->document->addScript('view/javascript/wt_filter/wt_filter.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wt_filter_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = $this->language->get('text_edit');
		$data['heading_title'] = $this->language->get('heading_title');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';

		$url = $this->urlSuffix();
		$option_id = isset($this->request->get['option_id']) ? (int)$this->request->get['option_id'] : 0;

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $url, true)
			)
		);

		$data['action'] = $this->url->link('catalog/wt_filter/edit', 'user_token=' . $this->session->data['user_token'] . '&option_id=' . $option_id . $url, true);
		$data['cancel'] = $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$option_info = $option_id ? $this->model_catalog_wt_filter->getOption($option_id) : array();

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['option_description'])) {
			$data['option_description'] = $this->request->post['option_description'];
		} elseif ($option_id) {
			$data['option_description'] = $this->model_catalog_wt_filter->getOptionDescriptions($option_id);
		} else {
			$data['option_description'] = array();
		}

		foreach (array('keyword', 'type', 'sort_order', 'status', 'selectbox', 'color', 'image') as $key) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($option_info) {
				$data[$key] = $option_info[$key];
			} else {
				$data[$key] = ($key === 'type') ? 'checkbox' : (($key === 'status') ? 1 : 0);
			}
		}

		if (isset($this->request->post['category_id'])) {
			$data['option_categories'] = $this->request->post['category_id'];
		} elseif ($option_id) {
			$data['option_categories'] = $this->model_catalog_wt_filter->getOptionCategoryIds($option_id);
		} else {
			$data['option_categories'] = array();
		}

		$this->load->model('catalog/category');
		$data['categories'] = $this->model_catalog_category->getCategories(array('sort' => 'name'));
		$this->load->model('tool/image');
		$placeholder = $this->model_tool_image->resize('no_image.png', 40, 40);
		$raw_values = $option_id ? $this->model_catalog_wt_filter->getOptionValues($option_id) : array();
		$data['values'] = array();

		foreach ($raw_values as $value) {
			$image = !empty($value['image']) ? $value['image'] : '';
			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = $this->model_tool_image->resize($image, 40, 40);
			} else {
				$thumb = $placeholder;
			}

			$value['image'] = $image;
			$value['thumb'] = $thumb;
			$data['values'][] = $value;
		}

		$data['placeholder'] = $placeholder;
		$data['types'] = $this->getTypes();

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_values'] = $this->language->get('tab_values');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_keyword'] = $this->language->get('entry_keyword');
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_is_color'] = $this->language->get('entry_is_color');
		$data['column_name'] = $this->language->get('column_name');
		$data['column_keyword'] = $this->language->get('column_keyword');
		$data['column_image'] = $this->language->get('column_image');
		$data['column_color'] = $this->language->get('column_color');
		$data['column_numeric'] = $this->language->get('column_numeric');
		$data['help_value_image'] = $this->language->get('help_value_image');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['text_select_all'] = $this->language->get('text_select_all');
		$data['text_unselect_all'] = $this->language->get('text_unselect_all');

		$this->document->addStyle('view/stylesheet/wt_filter/wt_filter.css');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wt_filter_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/wt_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['option_description'])) {
			foreach ($this->request->post['option_description'] as $language_id => $value) {
				if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 128)) {
					$this->error['name'][$language_id] = $this->language->get('error_name');
				}
			}
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/wt_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function getTypes() {
		return array(
			'checkbox'       => $this->language->get('text_checkbox'),
			'checkbox_image' => $this->language->get('text_checkbox_image'),
			'radio'          => $this->language->get('text_radio'),
			'radio_image'    => $this->language->get('text_radio_image'),
			'slider_range'   => $this->language->get('text_slider_range'),
			'slide'          => $this->language->get('text_slide'),
			'slide_dual'     => $this->language->get('text_slide_dual'),
			'select'         => $this->language->get('text_select'),
		);
	}

	private function urlSuffix($exclude = array()) {
		$url = '';
		$keys = array('filter_name', 'filter_category_id', 'filter_type', 'filter_status', 'sort', 'order', 'page');

		foreach ($keys as $key) {
			if (in_array($key, $exclude, true)) {
				continue;
			}

			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$url .= '&' . $key . '=' . urlencode($this->request->get[$key]);
			}
		}

		return $url;
	}
}
