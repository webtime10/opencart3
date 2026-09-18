<?php
class ControllerExtensionThemePlumStore extends Controller {
	private $error = array();

	/**
	 * При Install темы: пункт «Настройки темы» в боковом меню админки.
	 */
	public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('plum_store_menu');
		$this->model_setting_event->deleteEventByCode('plum_store_header');
		$this->model_setting_event->addEvent(
			'plum_store_menu',
			'admin/view/common/column_left/before',
			'extension/theme/plum_store/eventMenu',
			1,
			0
		);
		$this->model_setting_event->addEvent(
			'plum_store_header',
			'catalog/view/common/header/before',
			'extension/theme/plum_store/eventHeader',
			1,
			0
		);

		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/theme/plum_store');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/theme/plum_store');
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('plum_store_menu');
		$this->model_setting_event->deleteEventByCode('plum_store_header');
	}

	/**
	 * Event: вставить пункт меню сразу после Dashboard.
	 * Сигнатура как у view column_left before: (&$route, &$data, &$code)
	 */
	public function eventMenu(&$route, &$data, &$code) {
		if (!$this->user->hasPermission('access', 'extension/theme/plum_store')) {
			return;
		}

		if (!isset($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		// Уже добавлен (повторный trigger)
		foreach ($data['menus'] as $menu) {
			if (isset($menu['id']) && $menu['id'] === 'menu-plum-store') {
				return;
			}
		}

		$this->load->language('extension/theme/plum_store');

		$item = array(
			'id'       => 'menu-plum-store',
			'icon'     => 'fa-paint-brush',
			'name'     => $this->language->get('text_menu'),
			'href'     => $this->url->link('extension/theme/plum_store', 'user_token=' . $this->session->data['user_token'] . '&store_id=0', true),
			'children' => array()
		);

		array_splice($data['menus'], 1, 0, array($item));
	}

	public function index() {
		$this->load->language('extension/theme/plum_store');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('theme_plum_store', $this->request->post, $this->request->get['store_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['product_limit'])) {
			$data['error_product_limit'] = $this->error['product_limit'];
		} else {
			$data['error_product_limit'] = '';
		}

		if (isset($this->error['product_description_length'])) {
			$data['error_product_description_length'] = $this->error['product_description_length'];
		} else {
			$data['error_product_description_length'] = '';
		}

		if (isset($this->error['image_category'])) {
			$data['error_image_category'] = $this->error['image_category'];
		} else {
			$data['error_image_category'] = '';
		}

		if (isset($this->error['image_thumb'])) {
			$data['error_image_thumb'] = $this->error['image_thumb'];
		} else {
			$data['error_image_thumb'] = '';
		}

		if (isset($this->error['image_popup'])) {
			$data['error_image_popup'] = $this->error['image_popup'];
		} else {
			$data['error_image_popup'] = '';
		}

		if (isset($this->error['image_product'])) {
			$data['error_image_product'] = $this->error['image_product'];
		} else {
			$data['error_image_product'] = '';
		}

		if (isset($this->error['image_additional'])) {
			$data['error_image_additional'] = $this->error['image_additional'];
		} else {
			$data['error_image_additional'] = '';
		}

		if (isset($this->error['image_related'])) {
			$data['error_image_related'] = $this->error['image_related'];
		} else {
			$data['error_image_related'] = '';
		}

		if (isset($this->error['image_compare'])) {
			$data['error_image_compare'] = $this->error['image_compare'];
		} else {
			$data['error_image_compare'] = '';
		}

		if (isset($this->error['image_wishlist'])) {
			$data['error_image_wishlist'] = $this->error['image_wishlist'];
		} else {
			$data['error_image_wishlist'] = '';
		}

		if (isset($this->error['image_cart'])) {
			$data['error_image_cart'] = $this->error['image_cart'];
		} else {
			$data['error_image_cart'] = '';
		}

		if (isset($this->error['image_location'])) {
			$data['error_image_location'] = $this->error['image_location'];
		} else {
			$data['error_image_location'] = '';
		}
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/theme/plum_store', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->request->get['store_id'], true)
		);

		$data['action'] = $this->url->link('extension/theme/plum_store', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->request->get['store_id'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true);

		if (isset($this->request->get['store_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$setting_info = $this->model_setting_setting->getSetting('theme_plum_store', $this->request->get['store_id']);
		}
		
		if (isset($this->request->post['theme_plum_store_directory'])) {
			$data['theme_plum_store_directory'] = $this->request->post['theme_plum_store_directory'];
		} elseif (isset($setting_info['theme_plum_store_directory'])) {
			$data['theme_plum_store_directory'] = $setting_info['theme_plum_store_directory'];
		} else {
			$data['theme_plum_store_directory'] = 'plum_store';
		}		

		$data['directories'] = array();

		$directories = glob(DIR_CATALOG . 'view/theme/*', GLOB_ONLYDIR);

		foreach ($directories as $directory) {
			$data['directories'][] = basename($directory);
		}

		if (isset($this->request->post['theme_plum_store_product_limit'])) {
			$data['theme_plum_store_product_limit'] = $this->request->post['theme_plum_store_product_limit'];
		} elseif (isset($setting_info['theme_plum_store_product_limit'])) {
			$data['theme_plum_store_product_limit'] = $setting_info['theme_plum_store_product_limit'];
		} else {
			$data['theme_plum_store_product_limit'] = 15;
		}		
		
		if (isset($this->request->post['theme_plum_store_status'])) {
			$data['theme_plum_store_status'] = $this->request->post['theme_plum_store_status'];
		} elseif (isset($setting_info['theme_plum_store_status'])) {
			$data['theme_plum_store_status'] = $setting_info['theme_plum_store_status'];
		} else {
			$data['theme_plum_store_status'] = '';
		}

		if (isset($this->request->post['theme_plum_store_color_scheme'])) {
			$data['theme_plum_store_color_scheme'] = $this->request->post['theme_plum_store_color_scheme'];
		} elseif (isset($setting_info['theme_plum_store_color_scheme'])) {
			$data['theme_plum_store_color_scheme'] = $setting_info['theme_plum_store_color_scheme'];
		} else {
			$data['theme_plum_store_color_scheme'] = 'theme-plum';
		}

		$data['color_schemes'] = array(
			array(
				'value'  => 'theme-dark',
				'label'  => $this->language->get('text_scheme_dark'),
				'bg'     => '#1a1a1a',
				'accent' => '#e4b91c'
			),
			array(
				'value'  => 'theme-plum',
				'label'  => $this->language->get('text_scheme_plum'),
				'bg'     => '#3a2434',
				'accent' => '#b85a82'
			),
			array(
				'value'  => 'theme-light',
				'label'  => $this->language->get('text_scheme_light'),
				'bg'     => '#e8e3d9',
				'accent' => '#b86b4f'
			),
			array(
				'value'  => 'theme-ocean',
				'label'  => $this->language->get('text_scheme_ocean'),
				'bg'     => '#123645',
				'accent' => '#2a9fc7'
			),
			array(
				'value'  => 'theme-olive',
				'label'  => $this->language->get('text_scheme_olive'),
				'bg'     => '#e9e4d6',
				'accent' => '#8a8658'
			),
			array(
				'value'  => 'theme-emerald',
				'label'  => $this->language->get('text_scheme_emerald'),
				'bg'     => '#e6f2ec',
				'accent' => '#2f8f6b'
			)
		);

		if (isset($this->request->post['theme_plum_store_logo'])) {
			$data['theme_plum_store_logo'] = $this->request->post['theme_plum_store_logo'];
		} elseif (isset($setting_info['theme_plum_store_logo'])) {
			$data['theme_plum_store_logo'] = $setting_info['theme_plum_store_logo'];
		} else {
			$data['theme_plum_store_logo'] = '';
		}

		$this->load->model('tool/image');

		if ($data['theme_plum_store_logo'] && is_file(DIR_IMAGE . $data['theme_plum_store_logo'])) {
			$ext = strtolower(pathinfo($data['theme_plum_store_logo'], PATHINFO_EXTENSION));

			if ($ext === 'svg') {
				$data['logo_thumb'] = HTTP_CATALOG . 'image/' . $data['theme_plum_store_logo'];
			} else {
				$data['logo_thumb'] = $this->model_tool_image->resize($data['theme_plum_store_logo'], 150, 50);
			}
		} else {
			$data['logo_thumb'] = $this->model_tool_image->resize('no_image.png', 150, 50);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 150, 50);
		
		if (isset($this->request->post['theme_plum_store_product_description_length'])) {
			$data['theme_plum_store_product_description_length'] = $this->request->post['theme_plum_store_product_description_length'];
		} elseif (isset($setting_info['theme_plum_store_product_description_length'])) {
			$data['theme_plum_store_product_description_length'] = $setting_info['theme_plum_store_product_description_length'];
		} else {
			$data['theme_plum_store_product_description_length'] = 100;
		}
		
		if (isset($this->request->post['theme_plum_store_image_category_width'])) {
			$data['theme_plum_store_image_category_width'] = $this->request->post['theme_plum_store_image_category_width'];
		} elseif (isset($setting_info['theme_plum_store_image_category_width'])) {
			$data['theme_plum_store_image_category_width'] = $setting_info['theme_plum_store_image_category_width'];
		} else {
			$data['theme_plum_store_image_category_width'] = 80;		
		}
		
		if (isset($this->request->post['theme_plum_store_image_category_height'])) {
			$data['theme_plum_store_image_category_height'] = $this->request->post['theme_plum_store_image_category_height'];
		} elseif (isset($setting_info['theme_plum_store_image_category_height'])) {
			$data['theme_plum_store_image_category_height'] = $setting_info['theme_plum_store_image_category_height'];
		} else {
			$data['theme_plum_store_image_category_height'] = 80;
		}
		
		if (isset($this->request->post['theme_plum_store_image_thumb_width'])) {
			$data['theme_plum_store_image_thumb_width'] = $this->request->post['theme_plum_store_image_thumb_width'];
		} elseif (isset($setting_info['theme_plum_store_image_thumb_width'])) {
			$data['theme_plum_store_image_thumb_width'] = $setting_info['theme_plum_store_image_thumb_width'];
		} else {
			$data['theme_plum_store_image_thumb_width'] = 228;
		}
		
		if (isset($this->request->post['theme_plum_store_image_thumb_height'])) {
			$data['theme_plum_store_image_thumb_height'] = $this->request->post['theme_plum_store_image_thumb_height'];
		} elseif (isset($setting_info['theme_plum_store_image_thumb_height'])) {
			$data['theme_plum_store_image_thumb_height'] = $setting_info['theme_plum_store_image_thumb_height'];
		} else {
			$data['theme_plum_store_image_thumb_height'] = 228;		
		}
		
		if (isset($this->request->post['theme_plum_store_image_popup_width'])) {
			$data['theme_plum_store_image_popup_width'] = $this->request->post['theme_plum_store_image_popup_width'];
		} elseif (isset($setting_info['theme_plum_store_image_popup_width'])) {
			$data['theme_plum_store_image_popup_width'] = $setting_info['theme_plum_store_image_popup_width'];
		} else {
			$data['theme_plum_store_image_popup_width'] = 500;
		}
		
		if (isset($this->request->post['theme_plum_store_image_popup_height'])) {
			$data['theme_plum_store_image_popup_height'] = $this->request->post['theme_plum_store_image_popup_height'];
		} elseif (isset($setting_info['theme_plum_store_image_popup_height'])) {
			$data['theme_plum_store_image_popup_height'] = $setting_info['theme_plum_store_image_popup_height'];
		} else {
			$data['theme_plum_store_image_popup_height'] = 500;
		}
		
		if (isset($this->request->post['theme_plum_store_image_product_width'])) {
			$data['theme_plum_store_image_product_width'] = $this->request->post['theme_plum_store_image_product_width'];
		} elseif (isset($setting_info['theme_plum_store_image_product_width'])) {
			$data['theme_plum_store_image_product_width'] = $setting_info['theme_plum_store_image_product_width'];
		} else {
			$data['theme_plum_store_image_product_width'] = 228;
		}
		
		if (isset($this->request->post['theme_plum_store_image_product_height'])) {
			$data['theme_plum_store_image_product_height'] = $this->request->post['theme_plum_store_image_product_height'];
		} elseif (isset($setting_info['theme_plum_store_image_product_height'])) {
			$data['theme_plum_store_image_product_height'] = $setting_info['theme_plum_store_image_product_height'];
		} else {
			$data['theme_plum_store_image_product_height'] = 228;
		}
		
		if (isset($this->request->post['theme_plum_store_image_additional_width'])) {
			$data['theme_plum_store_image_additional_width'] = $this->request->post['theme_plum_store_image_additional_width'];
		} elseif (isset($setting_info['theme_plum_store_image_additional_width'])) {
			$data['theme_plum_store_image_additional_width'] = $setting_info['theme_plum_store_image_additional_width'];
		} else {
			$data['theme_plum_store_image_additional_width'] = 74;
		}
		
		if (isset($this->request->post['theme_plum_store_image_additional_height'])) {
			$data['theme_plum_store_image_additional_height'] = $this->request->post['theme_plum_store_image_additional_height'];
		} elseif (isset($setting_info['theme_plum_store_image_additional_height'])) {
			$data['theme_plum_store_image_additional_height'] = $setting_info['theme_plum_store_image_additional_height'];
		} else {
			$data['theme_plum_store_image_additional_height'] = 74;
		}
		
		if (isset($this->request->post['theme_plum_store_image_related_width'])) {
			$data['theme_plum_store_image_related_width'] = $this->request->post['theme_plum_store_image_related_width'];
		} elseif (isset($setting_info['theme_plum_store_image_related_width'])) {
			$data['theme_plum_store_image_related_width'] = $setting_info['theme_plum_store_image_related_width'];
		} else {
			$data['theme_plum_store_image_related_width'] = 80;
		}
		
		if (isset($this->request->post['theme_plum_store_image_related_height'])) {
			$data['theme_plum_store_image_related_height'] = $this->request->post['theme_plum_store_image_related_height'];
		} elseif (isset($setting_info['theme_plum_store_image_related_height'])) {
			$data['theme_plum_store_image_related_height'] = $setting_info['theme_plum_store_image_related_height'];
		} else {
			$data['theme_plum_store_image_related_height'] = 80;
		}
		
		if (isset($this->request->post['theme_plum_store_image_compare_width'])) {
			$data['theme_plum_store_image_compare_width'] = $this->request->post['theme_plum_store_image_compare_width'];
		} elseif (isset($setting_info['theme_plum_store_image_compare_width'])) {
			$data['theme_plum_store_image_compare_width'] = $setting_info['theme_plum_store_image_compare_width'];
		} else {
			$data['theme_plum_store_image_compare_width'] = 90;
		}
		
		if (isset($this->request->post['theme_plum_store_image_compare_height'])) {
			$data['theme_plum_store_image_compare_height'] = $this->request->post['theme_plum_store_image_compare_height'];
		} elseif (isset($setting_info['theme_plum_store_image_compare_height'])) {
			$data['theme_plum_store_image_compare_height'] = $setting_info['theme_plum_store_image_compare_height'];
		} else {
			$data['theme_plum_store_image_compare_height'] = 90;
		}
		
		if (isset($this->request->post['theme_plum_store_image_wishlist_width'])) {
			$data['theme_plum_store_image_wishlist_width'] = $this->request->post['theme_plum_store_image_wishlist_width'];
		} elseif (isset($setting_info['theme_plum_store_image_wishlist_width'])) {
			$data['theme_plum_store_image_wishlist_width'] = $setting_info['theme_plum_store_image_wishlist_width'];
		} else {
			$data['theme_plum_store_image_wishlist_width'] = 47;
		}
		
		if (isset($this->request->post['theme_plum_store_image_wishlist_height'])) {
			$data['theme_plum_store_image_wishlist_height'] = $this->request->post['theme_plum_store_image_wishlist_height'];
		} elseif (isset($setting_info['theme_plum_store_image_wishlist_height'])) {
			$data['theme_plum_store_image_wishlist_height'] = $setting_info['theme_plum_store_image_wishlist_height'];
		} else {
			$data['theme_plum_store_image_wishlist_height'] = 47;
		}
		
		if (isset($this->request->post['theme_plum_store_image_cart_width'])) {
			$data['theme_plum_store_image_cart_width'] = $this->request->post['theme_plum_store_image_cart_width'];
		} elseif (isset($setting_info['theme_plum_store_image_cart_width'])) {
			$data['theme_plum_store_image_cart_width'] = $setting_info['theme_plum_store_image_cart_width'];
		} else {
			$data['theme_plum_store_image_cart_width'] = 47;
		}
		
		if (isset($this->request->post['theme_plum_store_image_cart_height'])) {
			$data['theme_plum_store_image_cart_height'] = $this->request->post['theme_plum_store_image_cart_height'];
		} elseif (isset($setting_info['theme_plum_store_image_cart_height'])) {
			$data['theme_plum_store_image_cart_height'] = $setting_info['theme_plum_store_image_cart_height'];
		} else {
			$data['theme_plum_store_image_cart_height'] = 47;
		}
		
		if (isset($this->request->post['theme_plum_store_image_location_width'])) {
			$data['theme_plum_store_image_location_width'] = $this->request->post['theme_plum_store_image_location_width'];
		} elseif (isset($setting_info['theme_plum_store_image_location_width'])) {
			$data['theme_plum_store_image_location_width'] = $setting_info['theme_plum_store_image_location_width'];
		} else {
			$data['theme_plum_store_image_location_width'] = 268;
		}
		
		if (isset($this->request->post['theme_plum_store_image_location_height'])) {
			$data['theme_plum_store_image_location_height'] = $this->request->post['theme_plum_store_image_location_height'];
		} elseif (isset($setting_info['theme_plum_store_image_location_height'])) {
			$data['theme_plum_store_image_location_height'] = $setting_info['theme_plum_store_image_location_height'];
		} else {
			$data['theme_plum_store_image_location_height'] = 50;
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/theme/plum_store', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/theme/plum_store')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['theme_plum_store_product_limit']) {
			$this->error['product_limit'] = $this->language->get('error_limit');
		}

		if (!$this->request->post['theme_plum_store_product_description_length']) {
			$this->error['product_description_length'] = $this->language->get('error_limit');
		}

		if (!$this->request->post['theme_plum_store_image_category_width'] || !$this->request->post['theme_plum_store_image_category_height']) {
			$this->error['image_category'] = $this->language->get('error_image_category');
		}

		if (!$this->request->post['theme_plum_store_image_thumb_width'] || !$this->request->post['theme_plum_store_image_thumb_height']) {
			$this->error['image_thumb'] = $this->language->get('error_image_thumb');
		}

		if (!$this->request->post['theme_plum_store_image_popup_width'] || !$this->request->post['theme_plum_store_image_popup_height']) {
			$this->error['image_popup'] = $this->language->get('error_image_popup');
		}

		if (!$this->request->post['theme_plum_store_image_product_width'] || !$this->request->post['theme_plum_store_image_product_height']) {
			$this->error['image_product'] = $this->language->get('error_image_product');
		}

		if (!$this->request->post['theme_plum_store_image_additional_width'] || !$this->request->post['theme_plum_store_image_additional_height']) {
			$this->error['image_additional'] = $this->language->get('error_image_additional');
		}

		if (!$this->request->post['theme_plum_store_image_related_width'] || !$this->request->post['theme_plum_store_image_related_height']) {
			$this->error['image_related'] = $this->language->get('error_image_related');
		}

		if (!$this->request->post['theme_plum_store_image_compare_width'] || !$this->request->post['theme_plum_store_image_compare_height']) {
			$this->error['image_compare'] = $this->language->get('error_image_compare');
		}

		if (!$this->request->post['theme_plum_store_image_wishlist_width'] || !$this->request->post['theme_plum_store_image_wishlist_height']) {
			$this->error['image_wishlist'] = $this->language->get('error_image_wishlist');
		}

		if (!$this->request->post['theme_plum_store_image_cart_width'] || !$this->request->post['theme_plum_store_image_cart_height']) {
			$this->error['image_cart'] = $this->language->get('error_image_cart');
		}

		if (!$this->request->post['theme_plum_store_image_location_width'] || !$this->request->post['theme_plum_store_image_location_height']) {
			$this->error['image_location'] = $this->language->get('error_image_location');
		}

		return !$this->error;
	}
}
