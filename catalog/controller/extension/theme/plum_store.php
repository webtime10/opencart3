<?php
class ControllerExtensionThemePlumStore extends Controller {
	/**
	 * catalog/view/common/header/before — схема, логотип, layout 1/2
	 */
	public function eventHeader(&$route, &$data, &$code) {
		if (!$this->config->get('theme_plum_store_status')) {
			return;
		}

		$layout = (string)$this->config->get('theme_plum_store_header_layout');

		if ($layout === '2') {
			$route = 'common/header_2';
		}

		$scheme = (string)$this->config->get('theme_plum_store_color_scheme');
		$allowed = array('theme-dark', 'theme-plum', 'theme-light', 'theme-ocean', 'theme-olive', 'theme-emerald');

		if (!in_array($scheme, $allowed, true)) {
			$scheme = 'theme-plum';
		}

		$data['plum_color_scheme'] = $scheme;
		$data['plum_theme_logo'] = '';
		$data['plum_informations'] = array();
		$data['search_action'] = $this->url->link('product/search');
		$data['search_value'] = isset($this->request->get['search']) ? (string)$this->request->get['search'] : '';
		$data['compare'] = $this->url->link('product/compare');

		$current_route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';
		$data['plum_is_home'] = ($current_route === '' || $current_route === 'common/home');

		try {
			$this->load->model('extension/theme/plum_store');
			$data['plum_informations'] = $this->model_extension_theme_plum_store->getTopInformations();
		} catch (Exception $e) {
			$data['plum_informations'] = array();
		}

		if (!isset($data['contact'])) {
			$data['contact'] = $this->url->link('information/contact');
		}

		$logo = $this->resolveThemeLogoPath((string)$this->config->get('theme_plum_store_logo'));

		if ($logo !== '') {
			if (!empty($this->request->server['HTTPS'])) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}

			// Как в админке — так и на витрине (чёрный SVG остаётся чёрным через <img>)
			$data['plum_theme_logo'] = rtrim((string)$server, '/') . '/image/' . str_replace(' ', '%20', $logo);
		}
		// Пусто / no_image → в twig встроенный SVG Plum
	}

	/**
	 * Путь к файлу логотипа темы или '' если пусто / заглушка OpenCart.
	 */
	private function resolveThemeLogoPath($logo) {
		$logo = trim(str_replace('\\', '/', (string)$logo));
		$logo = ltrim($logo, '/');

		if (strncmp($logo, 'image/', 6) === 0) {
			$logo = substr($logo, 6);
		}

		if ($logo === '') {
			return '';
		}

		$basename = strtolower(basename($logo));

		// Плашка no_image / placeholder в админке = «логотипа нет»
		if (in_array($basename, array('no_image.png', 'no_image.jpg', 'placeholder.png'), true)) {
			return '';
		}

		return $logo;
	}
}
