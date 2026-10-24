<?php
class ControllerExtensionModuleWtMiniBanner extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_mini_banner');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->load->model('catalog/product');
			$post = $this->request->post;
			if (!empty($post['item']) && is_array($post['item'])) {
				foreach ($post['item'] as $k => $item) {
					unset($post['item'][$k]['image']);
					$resolved = $this->resolveItemProduct($item);
					$post['item'][$k]['product_id'] = $resolved['product_id'];
					if ($resolved['product_name'] !== '') {
						$post['item'][$k]['product_name'] = $resolved['product_name'];
					}
				}
			}

			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('wt_mini_banner', $post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $post);
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
				'href' => $this->url->link('extension/module/wt_mini_banner', 'user_token=' . $this->session->data['user_token'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_mini_banner', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_mini_banner', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_mini_banner', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$module_info = array();
		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['name'] = $this->pick('name', $module_info, '');
		$data['status'] = $this->pick('status', $module_info, 1);
		$data['per_view'] = (int)$this->pick('per_view', $module_info, 1);
		$data['autoplay'] = (int)$this->pick('autoplay', $module_info, 0);

		$raw_items = array();
		if (isset($this->request->post['item'])) {
			$raw_items = $this->request->post['item'];
		} elseif (!empty($module_info['item'])) {
			$raw_items = $module_info['item'];
		}

		$items = array();

		foreach ($raw_items as $item) {
			$items[] = array(
				'title'        => isset($item['title']) ? $item['title'] : '',
				'description'  => isset($item['description']) ? $item['description'] : '',
				'percent'      => isset($item['percent']) ? $item['percent'] : '',
				'product_id'   => isset($item['product_id']) ? (int)$item['product_id'] : 0,
				'product_name' => isset($item['product_name']) ? $item['product_name'] : '',
				'sort_order'   => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
				'status'       => isset($item['status']) ? (int)$item['status'] : 1
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

		$this->response->setOutput($this->load->view('extension/module/wt_mini_banner', $data));
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

	/**
	 * @param array<string, mixed> $item
	 * @return array{product_id:int,product_name:string}
	 */
	protected function resolveItemProduct(array $item) {
		$pid = isset($item['product_id']) ? (int)$item['product_id'] : 0;
		$name = isset($item['product_name']) ? trim(html_entity_decode((string)$item['product_name'], ENT_QUOTES, 'UTF-8')) : '';

		if ($pid < 1) {
			foreach (array('product_name', 'link') as $key) {
				if (empty($item[$key])) {
					continue;
				}
				$str = html_entity_decode((string)$item[$key], ENT_QUOTES, 'UTF-8');
				if (preg_match('/product_id=(\d+)/', $str, $m)) {
					$pid = (int)$m[1];
					break;
				}
			}
		}

		if ($pid < 1 && $name !== '') {
			$found = $this->model_catalog_product->getProducts(array(
				'filter_name' => $name,
				'start'       => 0,
				'limit'       => 20
			));
			foreach ($found as $row) {
				if (utf8_strtolower(trim($row['name'])) === utf8_strtolower($name)) {
					$pid = (int)$row['product_id'];
					$name = $row['name'];
					break;
				}
			}
			if ($pid < 1 && count($found) === 1) {
				$pid = (int)$found[0]['product_id'];
				$name = $found[0]['name'];
			}
		}

		if ($pid > 0 && $name === '') {
			$info = $this->model_catalog_product->getProduct($pid);
			if ($info) {
				$name = $info['name'];
			}
		}

		return array(
			'product_id'   => $pid,
			'product_name' => $name
		);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_mini_banner')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}
		return !$this->error;
	}
}
