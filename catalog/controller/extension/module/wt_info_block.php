<?php
class ControllerExtensionModuleWtInfoBlock extends Controller {
	public function index($setting) {
		static $module = 0;

		if (empty($setting['status'])) {
			return;
		}

		$this->load->language('extension/module/wt_info_block');
		$this->load->model('tool/image');

		$language_id = (int)$this->config->get('config_language_id');

		$data['heading_title'] = '';
		if (!empty($setting['module_description'][$language_id]['title'])) {
			$data['heading_title'] = html_entity_decode($setting['module_description'][$language_id]['title'], ENT_QUOTES, 'UTF-8');
		}

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
				if (!empty($item['image']) && is_file(DIR_IMAGE . $item['image'])) {
					$image = $this->model_tool_image->resize($item['image'], 400, 400);
				}

				$items[] = array(
					'title' => $title,
					'text'  => nl2br($text),
					'image' => $image
				);
			}
		}

		if (!$items) {
			return;
		}

		$data['items'] = $items;
		$data['module'] = $module++;

		return $this->load->view('extension/module/wt_info_block', $data);
	}
}
