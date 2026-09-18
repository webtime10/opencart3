<?php
class ControllerExtensionThemePlumStore extends Controller {
	/**
	 * catalog/view/common/header/before — схема и логотип для header.twig
	 */
	public function eventHeader(&$route, &$data, &$code) {
		if (!$this->config->get('theme_plum_store_status')) {
			return;
		}

		$scheme = (string)$this->config->get('theme_plum_store_color_scheme');
		$allowed = array('theme-dark', 'theme-plum', 'theme-light', 'theme-ocean', 'theme-olive', 'theme-emerald');

		if (!in_array($scheme, $allowed, true)) {
			$scheme = 'theme-plum';
		}

		$data['plum_color_scheme'] = $scheme;
		$data['plum_theme_logo'] = '';

		$logo = (string)$this->config->get('theme_plum_store_logo');

		if ($logo && is_file(DIR_IMAGE . $logo)) {
			if ($this->request->server['HTTPS']) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}

			$data['plum_theme_logo'] = $server . 'image/' . $logo;
		}
	}
}
