<?php
class ControllerExtensionModuleWtBenefits extends Controller {
	public function index($setting) {
		static $module = 0;

		if (empty($setting['status'])) {
			return '';
		}

		$this->load->model('tool/image');

		$language_id = (int)$this->config->get('config_language_id');
		$items = array();

		if (!empty($setting['item']) && is_array($setting['item'])) {
			$raw = $setting['item'];

			uasort($raw, function ($a, $b) {
				$as = isset($a['sort_order']) ? (int)$a['sort_order'] : 0;
				$bs = isset($b['sort_order']) ? (int)$b['sort_order'] : 0;
				return $as - $bs;
			});

			foreach ($raw as $item) {
				if (isset($item['status']) && !(int)$item['status']) {
					continue;
				}

				$title = '';
				$text = '';

				if (!empty($item['description'][$language_id]['title'])) {
					$title = html_entity_decode($item['description'][$language_id]['title'], ENT_QUOTES, 'UTF-8');
				}

				if (!empty($item['description'][$language_id]['text'])) {
					$text = html_entity_decode($item['description'][$language_id]['text'], ENT_QUOTES, 'UTF-8');
				}

				if ($title === '' && $text === '') {
					continue;
				}

				$image = '';
				$path = isset($item['image']) ? (string)$item['image'] : '';

				if ($path !== '' && is_file(DIR_IMAGE . $path)) {
					if (preg_match('/\.svg$/i', $path)) {
						if ($this->request->server['HTTPS']) {
							$base = $this->config->get('config_ssl');
						} else {
							$base = $this->config->get('config_url');
						}
						$image = rtrim($base, '/') . '/image/' . str_replace(' ', '%20', $path);
					} else {
						$image = $this->model_tool_image->resize($path, 64, 64);
					}
				}

				$items[] = array(
					'title' => $title,
					'text'  => $text,
					'image' => $image
				);
			}
		}

		if (!$items) {
			return '';
		}

		$this->document->addStyle('catalog/view/theme/plum_store/stylesheet/wt_benefits.css?v=2');

		$data['items'] = $items;
		$data['module'] = $module++;

		return $this->load->view('extension/module/wt_benefits', $data);
	}
}
