<?php
class ControllerExtensionModuleWtMenu extends Controller {
	public function index($setting = array()) {
		if (!empty($setting['__slot'])) {
			$setting = $this->getModuleBySlot((int)$setting['__slot']);
		} elseif (!$setting || !is_array($setting) || (!isset($setting['status']) && empty($setting['tree']) && empty($setting['item']))) {
			$setting = $this->getModuleBySlot(1);
		}

		if (empty($setting['status'])) {
			return '';
		}

		$tree = array();
		if (!empty($setting['tree']) && is_array($setting['tree'])) {
			$tree = $setting['tree'];
		} elseif (!empty($setting['item']) && is_array($setting['item'])) {
			foreach ($setting['item'] as $row) {
				if (empty($row['name'])) {
					continue;
				}
				$tree[] = array(
					'id'       => isset($row['id']) ? (int)$row['id'] : 0,
					'name'     => $row['name'],
					'href'     => !empty($row['href']) ? $row['href'] : '#',
					'status'   => 1,
					'image'    => '',
					'children' => array()
				);
			}
		}

		$this->load->language('common/menu');
		$this->load->model('tool/image');

		$data['items'] = $this->prepareItems($tree);
		$data['text_all'] = $this->language->get('text_all');

		if (!$data['items']) {
			return '';
		}

		$slot = isset($setting['menu_slot']) ? (int)$setting['menu_slot'] : 1;

		if ($slot === 2) {
			// Horizontal: max 5 top categories, max 5 columns each
			$data['items'] = $this->limitHorizontalTree($data['items'], 5, 5);
			return $this->load->view('extension/module/wt_menu_horizontal', $data);
		}

		return $this->load->view('extension/module/wt_menu', $data);
	}

	private function limitHorizontalTree(array $items, $maxTop = 5, $maxCols = 3) {
		$items = array_slice($items, 0, (int)$maxTop);

		foreach ($items as &$item) {
			if (!empty($item['children']) && is_array($item['children'])) {
				$item['children'] = array_slice($item['children'], 0, (int)$maxCols);
			}
		}
		unset($item);

		return $items;
	}

	private function getModuleBySlot($slot) {
		$slot = ((int)$slot === 2) ? 2 : 1;
		$query = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'wt_menu' ORDER BY `module_id` ASC");
		$fallback = array();

		foreach ($query->rows as $row) {
			$setting = json_decode($row['setting'], true);

			if (!is_array($setting) || empty($setting['status'])) {
				continue;
			}

			$item_slot = isset($setting['menu_slot']) ? (int)$setting['menu_slot'] : 1;

			if ($item_slot !== 2) {
				$item_slot = 1;
			}

			if ($item_slot === $slot) {
				return $setting;
			}

			// Old modules without menu_slot → Menu 1
			if ($slot === 1 && !$fallback) {
				$fallback = $setting;
			}
		}

		return ($slot === 1) ? $fallback : array();
	}

	private function prepareItems(array $tree, $depth = 1) {
		$result = array();
		$placeholders = array('добавить', 'add', 'название', 'title', 'название пункта', 'item title');

		foreach ($tree as $item) {
			if (isset($item['status']) && !(int)$item['status']) {
				continue;
			}

			$name = isset($item['name']) ? trim($item['name']) : '';
			$name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
			$name = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $name));
			if (strpos($name, '>') !== false) {
				$parts = preg_split('/\s*>\s*/u', $name);
				$name = trim((string)end($parts));
			}
			if ($name === '' || in_array(utf8_strtolower($name), $placeholders, true)) {
				continue;
			}

			$id = !empty($item['id']) ? (int)$item['id'] : (count($result) + 1);
			$image = isset($item['image']) ? $item['image'] : '';
			$thumb = '';

			if ($image && is_file(DIR_IMAGE . $image)) {
				if ($depth >= 2) {
					if (!empty($this->request->server['HTTPS'])) {
						$thumb = $this->config->get('config_ssl') . 'image/' . str_replace(' ', '%20', $image);
					} else {
						$thumb = $this->config->get('config_url') . 'image/' . str_replace(' ', '%20', $image);
					}
				} else {
					$thumb = $this->model_tool_image->resize($image, 48, 48);
				}
			}

			$children = array();
			if (!empty($item['children']) && is_array($item['children'])) {
				$children = $this->prepareItems($item['children'], $depth + 1);
			}

			$result[] = array(
				'id'       => $id,
				'name'     => $name,
				'href'     => $this->resolveHref(isset($item['href']) ? $item['href'] : ''),
				'image'    => $image,
				'thumb'    => $thumb,
				'children' => $children
			);
		}

		return $result;
	}

	private function resolveHref($href) {
		$href = html_entity_decode((string)$href, ENT_QUOTES, 'UTF-8');
		$href = trim($href);

		if ($href === '' || $href === '#') {
			return '#';
		}

		if (preg_match('#^https?://#i', $href) || strpos($href, '//') === 0) {
			return $href;
		}

		if (strpos($href, 'index.php?') === 0) {
			$query = substr($href, strlen('index.php?'));
			parse_str($query, $params);

			$route = isset($params['route']) ? $params['route'] : 'common/home';
			unset($params['route']);

			return $this->url->link($route, http_build_query($params));
		}

		if (strpos($href, 'route=') === 0) {
			parse_str($href, $params);
			$route = isset($params['route']) ? $params['route'] : 'common/home';
			unset($params['route']);

			return $this->url->link($route, http_build_query($params));
		}

		return $href;
	}
}
