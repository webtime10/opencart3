<?php
class ControllerExtensionModuleWtBenefits extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_benefits');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');
		$this->load->model('tool/image');
		$this->load->model('localisation/language');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('wt_benefits', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_benefits', 'user_token=' . $this->session->data['user_token'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_benefits', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_benefits', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_benefits', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$module_info = array();
		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $this->pick('name', $module_info, '');
		$data['status'] = $this->pick('status', $module_info, 1);

		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$raw_items = array();
		if (isset($this->request->post['item'])) {
			$raw_items = $this->request->post['item'];
		} elseif (!empty($module_info['item'])) {
			$raw_items = $module_info['item'];
		}

		$items = array();

		foreach ($raw_items as $item) {
			$image = isset($item['image']) ? $item['image'] : '';
			$thumb = $data['placeholder'];

			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = $this->model_tool_image->resize($image, 100, 100);
				if (!$thumb || preg_match('/\.svg$/i', $image)) {
					$thumb = HTTP_CATALOG . 'image/' . str_replace(' ', '%20', $image);
				}
			}

			$items[] = array(
				'description' => isset($item['description']) ? $item['description'] : array(),
				'image'       => $image,
				'thumb'       => $thumb,
				'sort_order'  => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
				'status'      => isset($item['status']) ? (int)$item['status'] : 1
			);
		}

		usort($items, function ($a, $b) {
			return $a['sort_order'] - $b['sort_order'];
		});

		$data['items'] = $items;
		$data['user_token'] = $this->session->data['user_token'];
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_benefits', $data));
	}

	protected function pick($key, $module_info, $default) {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}
		if (!empty($module_info) && isset($module_info[$key])) {
			return $module_info[$key];
		}
		return $default;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_benefits')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['name']) || (utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}
}
