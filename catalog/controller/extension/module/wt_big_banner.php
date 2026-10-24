<?php
class ControllerExtensionModuleWtBigBanner extends Controller {
	public function index($setting) {
		if (empty($setting) || (empty($setting['status']) && empty($setting['item']))) {
			return;
		}

		if (isset($setting['status']) && !(int)$setting['status']) {
			return;
		}

		$this->load->language('extension/module/wt_big_banner');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$items = array();
		$raw = !empty($setting['item']) && is_array($setting['item']) ? $setting['item'] : array();

		uasort($raw, function ($a, $b) {
			$as = isset($a['sort_order']) ? (int)$a['sort_order'] : 0;
			$bs = isset($b['sort_order']) ? (int)$b['sort_order'] : 0;
			return $as - $bs;
		});

		foreach ($raw as $item) {
			if (isset($item['status']) && !(int)$item['status']) {
				continue;
			}

			$product_id = $this->resolveProductId($item);
			if ($product_id < 1) {
				continue;
			}

			$product = $this->model_catalog_product->getProduct($product_id);
			if (!$product) {
				continue;
			}

			$thumb = '';
			if (!empty($product['image']) && is_file(DIR_IMAGE . $product['image'])) {
				$thumb = $this->model_tool_image->resize($product['image'], 300, 300);
			}

			$title = isset($item['title']) ? trim((string)$item['title']) : '';
			if ($title === '') {
				$title = (string)$product['name'];
			}

			$description = isset($item['description']) ? trim((string)$item['description']) : '';

			$percent = isset($item['percent']) ? trim((string)$item['percent']) : '';
			if ($percent === '' && !empty($product['special']) && (float)$product['price'] > 0) {
				$percent = '-' . (int)round((1 - (float)$product['special'] / (float)$product['price']) * 100) . '%';
			} elseif ($percent !== '' && isset($percent[0]) && $percent[0] !== '-' && ctype_digit(str_replace('%', '', $percent))) {
				$percent = '-' . rtrim($percent, '%') . '%';
			}

			$items[] = array(
				'title'       => $title,
				'description' => $description,
				'image'       => $thumb,
				'percent'     => $percent,
				'href'        => $this->url->link('product/product', 'product_id=' . $product_id)
			);
		}

		if (!$items) {
			return;
		}

		$per_view = isset($setting['per_view']) ? (int)$setting['per_view'] : 1;
		if ($per_view < 1) {
			$per_view = 1;
		}
		if ($per_view > 6) {
			$per_view = 6;
		}

		$autoplay = isset($setting['autoplay']) ? (int)$setting['autoplay'] : 5000;
		if ($autoplay < 0) {
			$autoplay = 0;
		}

		static $module = 0;

		$data['items'] = $items;
		$data['per_view'] = $per_view;
		$data['autoplay'] = $autoplay;
		$data['text_more'] = $this->language->get('text_more');
		$data['module'] = $module++;

		$this->document->addStyle('catalog/view/theme/plum_store/stylesheet/swiper.css');
		$this->document->addScript('catalog/view/theme/plum_store/js/swiper.js', 'footer');

		return $this->load->view('extension/module/wt_big_banner', $data);
	}

	/**
	 * product_id из поля, либо из старой ссылки в product_name / link.
	 */
	private function resolveProductId(array $item) {
		$id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
		if ($id > 0) {
			return $id;
		}

		foreach (array('product_name', 'link', 'href') as $key) {
			if (empty($item[$key])) {
				continue;
			}
			$str = html_entity_decode((string)$item[$key], ENT_QUOTES, 'UTF-8');
			if (preg_match('/product_id=(\d+)/', $str, $m)) {
				return (int)$m[1];
			}
		}

		return 0;
	}
}
