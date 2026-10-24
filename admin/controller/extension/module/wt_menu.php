<?php
class ControllerExtensionModuleWtMenu extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_menu');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');
		$this->load->model('tool/image');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;
			$items_data = isset($post['items']) && is_array($post['items']) ? $post['items'] : array();
			$tree_json = isset($post['tree_json']) ? html_entity_decode($post['tree_json'], ENT_QUOTES, 'UTF-8') : '[]';
			$structure = json_decode($tree_json, true);

			if (!is_array($structure)) {
				$structure = array();
			}

			$post['tree'] = $this->buildTree($structure, $items_data);
			unset($post['items'], $post['tree_json']);

			$post['menu_slot'] = (isset($post['menu_slot']) && (int)$post['menu_slot'] === 2) ? 2 : 1;

			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('wt_menu', $post);
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

		$url_module = 'user_token=' . $this->session->data['user_token'];
		if (isset($this->request->get['module_id'])) {
			$url_module .= '&module_id=' . (int)$this->request->get['module_id'];
		}

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/wt_menu', $url_module, true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/wt_menu', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/wt_menu', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['user_token'] = $this->session->data['user_token'];

		$module_info = array();
		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info['name'])) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = 'WT Menu';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (isset($module_info['status'])) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = 1;
		}

		if (isset($this->request->post['menu_slot'])) {
			$data['menu_slot'] = (int)$this->request->post['menu_slot'];
		} elseif (isset($module_info['menu_slot'])) {
			$data['menu_slot'] = (int)$module_info['menu_slot'];
		} else {
			$data['menu_slot'] = 1;
		}

		if ($data['menu_slot'] !== 2) {
			$data['menu_slot'] = 1;
		}

		$tree = array();
		if (isset($this->request->post['tree']) && is_array($this->request->post['tree'])) {
			$tree = $this->request->post['tree'];
		} elseif (!empty($module_info['tree']) && is_array($module_info['tree'])) {
			$tree = $this->filterPlaceholderItems($module_info['tree']);
		} elseif (!empty($module_info['item']) && is_array($module_info['item'])) {
			// migrate flat items from first version
			$id = 1;
			foreach ($module_info['item'] as $row) {
				$tree[] = array(
					'id'       => $id++,
					'name'     => isset($row['name']) ? $row['name'] : '',
					'href'     => isset($row['href']) ? $row['href'] : '',
					'status'   => 1,
					'image'    => '',
					'children' => array()
				);
			}
		}

		if (!$tree) {
			$tree = array(
				array(
					'id'       => 1,
					'name'     => 'Главная',
					'href'     => 'index.php?route=common/home',
					'status'   => 1,
					'image'    => '',
					'children' => array()
				)
			);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 40, 40);
		if (!$data['placeholder']) {
			$data['placeholder'] = $this->model_tool_image->resize('placeholder.png', 40, 40);
		}
		if (!$data['placeholder']) {
			$data['placeholder'] = '../image/no_image.png';
		}
		$data['tree_html'] = $this->renderTreeHtml($tree);
		$data['item_row'] = $this->maxTreeId($tree) + 1;
		$data['item_count'] = $this->countTree($tree);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$data = array_merge($this->language->all(), $data);

		$this->response->setOutput($this->load->view('extension/module/wt_menu', $data));
	}

	private function filterPlaceholderItems(array $tree) {
		$result = array();
		$placeholders = array('добавить', 'add', 'название', 'title', 'название пункта', 'item title');

		foreach ($tree as $item) {
			$name = isset($item['name']) ? trim($item['name']) : '';
			if ($name === '' || in_array(utf8_strtolower($name), $placeholders, true)) {
				continue;
			}

			if (!empty($item['children']) && is_array($item['children'])) {
				$item['children'] = $this->filterPlaceholderItems($item['children']);
			} else {
				$item['children'] = array();
			}

			$result[] = $item;
		}

		return $result;
	}

	private function buildTree(array $structure, array $items_data) {
		$result = array();
		$placeholders = array('добавить', 'add', 'название', 'title');

		foreach ($structure as $node) {
			if (!isset($node['id'])) {
				continue;
			}

			$id = (int)$node['id'];
			$row = isset($items_data[$id]) ? $items_data[$id] : array();

			$name = isset($row['name']) ? trim($row['name']) : '';
			$name_l = utf8_strtolower($name);

			// Пустые и «ложные» названия (плейсхолдер) не сохраняем и не показываем
			if ($name === '' || in_array($name_l, $placeholders, true)) {
				continue;
			}

			$item = array(
				'id'       => $id,
				'name'     => $name,
				'href'     => isset($row['href']) ? trim(html_entity_decode($row['href'], ENT_QUOTES, 'UTF-8')) : '',
				'status'   => isset($row['status']) ? (int)$row['status'] : 1,
				'image'    => isset($row['image']) ? $row['image'] : '',
				'children' => array()
			);

			if (!empty($node['children']) && is_array($node['children'])) {
				$item['children'] = $this->buildTree($node['children'], $items_data);
			}

			$result[] = $item;
		}

		return $result;
	}

	private function maxTreeId(array $tree) {
		$max = 0;
		foreach ($tree as $item) {
			$id = isset($item['id']) ? (int)$item['id'] : 0;
			if ($id > $max) {
				$max = $id;
			}
			if (!empty($item['children']) && is_array($item['children'])) {
				$child_max = $this->maxTreeId($item['children']);
				if ($child_max > $max) {
					$max = $child_max;
				}
			}
		}
		return $max;
	}

	private function countTree(array $tree) {
		$count = 0;
		foreach ($tree as $item) {
			$count++;
			if (!empty($item['children']) && is_array($item['children'])) {
				$count += $this->countTree($item['children']);
			}
		}
		return $count;
	}

	private function renderTreeHtml(array $tree) {
		$html = '<ol class="dd-list">';

		foreach ($tree as $item) {
			$html .= $this->renderTreeItem($item);
		}

		$html .= '</ol>';

		return $html;
	}

	private function renderTreeItem(array $item) {
		$id = (int)$item['id'];
		$name = isset($item['name']) ? $item['name'] : '';
		$href = isset($item['href']) ? $item['href'] : '';
		$status = isset($item['status']) ? (int)$item['status'] : 1;
		$image = isset($item['image']) ? $item['image'] : '';

		if ($image && is_file(DIR_IMAGE . $image)) {
			$thumb = $this->model_tool_image->resize($image, 40, 40);
		} else {
			$image = '';
			$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
			if (!$thumb) {
				$thumb = $this->model_tool_image->resize('placeholder.png', 40, 40);
			}
		}

		$placeholder = $this->model_tool_image->resize('no_image.png', 40, 40);
		if (!$placeholder) {
			$placeholder = $this->model_tool_image->resize('placeholder.png', 40, 40);
		}
		if (!$thumb) {
			$thumb = $placeholder;
		}

		$status_icon = $status ? 'fa-pause' : 'fa-play';
		$status_title = $status ? $this->language->get('text_enabled') : $this->language->get('text_disabled');

		$html  = '<li class="dd-item dd3-item" data-id="' . $id . '" id="wt-item-' . $id . '">';
		$html .= '<div class="dd-handle dd3-handle"></div>';
		$html .= '<div class="dd3-content dd-nodrag">';
		$html .= '<div class="input-group">';
		$html .= '<input type="text" name="items[' . $id . '][name]" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($this->language->get('entry_item_name'), ENT_QUOTES, 'UTF-8') . '" class="form-control wt-item-name" />';
		$html .= '<input type="hidden" name="items[' . $id . '][status]" value="' . $status . '" id="wt-status-' . $id . '" />';
		$html .= '<div class="input-group-btn">';
		$html .= '<button type="button" onclick="wtPickCategory(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_category'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-info"><i class="fa fa-folder"></i></button>';
		$html .= '<button type="button" onclick="wtAddChild(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_add_child'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="fa fa-sitemap"></i></button>';
		$html .= '<button type="button" onclick="wtToggleStatus(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($status_title, ENT_QUOTES, 'UTF-8') . '" class="btn btn-default" id="wt-status-btn-' . $id . '"><i class="fa ' . $status_icon . '"></i></button>';
		$html .= '<span class="wt-thumb-wrap">';
		$html .= '<a href="" id="wt-thumb-' . $id . '" data-toggle="image" class="img-thumbnail" data-original-title="' . htmlspecialchars($this->language->get('button_image'), ENT_QUOTES, 'UTF-8') . '"><img src="' . htmlspecialchars((string)$thumb, ENT_QUOTES, 'UTF-8') . '" alt="" data-placeholder="' . htmlspecialchars((string)$placeholder, ENT_QUOTES, 'UTF-8') . '" /></a>';
		$html .= '<input type="hidden" name="items[' . $id . '][image]" value="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" id="wt-image-' . $id . '" />';
		$html .= '</span>';
		$html .= '<button type="button" onclick="wtToggleSettings(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_settings'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-success"><i class="fa fa-cogs"></i></button>';
		$html .= '<button type="button" onclick="wtPickProduct(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_product'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-info"><i class="fa fa-shopping-cart"></i></button>';
		$html .= '<button type="button" onclick="wtDelete(' . $id . ');" data-toggle="tooltip" title="' . htmlspecialchars($this->language->get('button_remove'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-danger"><i class="fa fa-minus-circle"></i></button>';
		$html .= '</div></div>';

		$html .= '<div id="wt-extra-' . $id . '" class="wt-extra" style="display:none; margin-top:8px;">';
		$html .= '<input type="text" name="items[' . $id . '][href]" value="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($this->language->get('entry_item_href'), ENT_QUOTES, 'UTF-8') . '" class="form-control" id="wt-href-' . $id . '" />';
		$html .= '<input type="text" id="wt-autocomplete-' . $id . '" value="" placeholder="' . htmlspecialchars($this->language->get('entry_autocomplete'), ENT_QUOTES, 'UTF-8') . '" class="form-control" style="margin-top:5px; display:none;" />';
		$html .= '</div>';

		$html .= '</div>';

		if (!empty($item['children']) && is_array($item['children'])) {
			$html .= '<ol class="dd-list">';
			foreach ($item['children'] as $child) {
				$html .= $this->renderTreeItem($child);
			}
			$html .= '</ol>';
		}

		$html .= '</li>';

		return $html;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_menu')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['name']) || (utf8_strlen($this->request->post['name']) < 1) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}

	public function install() {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/wt_menu');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/wt_menu');
	}
}
