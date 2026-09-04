<?php
class ControllerExtensionModuleWtLang extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_lang');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post = $this->request->post;

			if (isset($post['module_wt_lang_default'])) {
				$default = $post['module_wt_lang_default'];

				if (isset($post['module_wt_lang_language']) && is_array($post['module_wt_lang_language'])) {
					foreach ($post['module_wt_lang_language'] as $code => $row) {
						$post['module_wt_lang_language'][$code]['default'] = ($code === $default) ? 1 : 0;
					}
				}
			}

			$this->model_setting_setting->editSetting('module_wt_lang', $post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data = $this->loadLanguageStrings();

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_lang', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/module/wt_lang', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['module_wt_lang_status'] = $this->posted('module_wt_lang_status', 0);
		$data['module_wt_lang_hreflang'] = $this->posted('module_wt_lang_hreflang', 1);
		$data['module_wt_lang_xdefault'] = $this->posted('module_wt_lang_xdefault', 1);

		$saved = $this->config->get('module_wt_lang_language');
		if (!is_array($saved)) {
			$saved = array();
		}

		$data['module_wt_lang_default'] = $this->config->get('config_language');
		$data['languages'] = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if (!$language['status']) {
				continue;
			}

			$code = $language['code'];
			$row = isset($saved[$code]) ? $saved[$code] : array();

			if (!empty($row['default'])) {
				$data['module_wt_lang_default'] = $code;
			}

			$data['languages'][] = array(
				'language_id' => $language['language_id'],
				'code'        => $code,
				'name'        => $language['name'],
				'status'      => isset($row['status']) ? (int)$row['status'] : 1,
				'prefix'      => isset($row['prefix']) ? $row['prefix'] : $this->defaultPrefix($code),
				'hreflang'    => isset($row['hreflang']) ? $row['hreflang'] : $this->defaultHreflang($code),
			);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_lang', $data));
	}

	public function install() {
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if (!$language['status']) {
				continue;
			}

			$code = $language['code'];
			$is_default = ($code === $this->config->get('config_language'));

			$languages[$code] = array(
				'status'   => 1,
				'prefix'   => $is_default ? '' : $this->defaultPrefix($code),
				'hreflang' => $this->defaultHreflang($code),
				'default'  => $is_default ? 1 : 0,
			);
		}

		$this->model_setting_setting->editSetting('module_wt_lang', array(
			'module_wt_lang_status'    => 1,
			'module_wt_lang_hreflang' => 1,
			'module_wt_lang_xdefault'  => 1,
			'module_wt_lang_language'  => $languages,
		));
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_lang');
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_lang')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$prefixes = array();
		$default = isset($this->request->post['module_wt_lang_default']) ? $this->request->post['module_wt_lang_default'] : '';

		if (isset($this->request->post['module_wt_lang_language']) && is_array($this->request->post['module_wt_lang_language'])) {
			foreach ($this->request->post['module_wt_lang_language'] as $code => $row) {
				if (empty($row['status'])) {
					continue;
				}

				$prefix = isset($row['prefix']) ? trim(strtolower($row['prefix']), '/') : '';

				if ($code === $default) {
					$prefix = '';
				}

				if ($prefix !== '') {
					if (isset($prefixes[$prefix])) {
						$this->error['warning'] = $this->language->get('error_prefix_duplicate');
						break;
					}

					$prefixes[$prefix] = $code;
				}
			}
		}

		return !$this->error;
	}

	private function posted($key, $default = '') {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}

		$value = $this->config->get($key);

		return ($value !== null && $value !== false) ? $value : $default;
	}

	private function defaultPrefix($code) {
		if ($code === 'en-gb' || $code === 'english') {
			return 'en';
		}

		if ($code === 'uk-ua' || $code === 'ukraine') {
			return 'uk';
		}

		if ($code === 'ru-ru' || $code === 'russian') {
			return 'ru';
		}

		return substr($code, 0, 2);
	}

	private function defaultHreflang($code) {
		if ($code === 'en-gb' || $code === 'english') {
			return 'en';
		}

		if ($code === 'uk-ua' || $code === 'ukraine') {
			return 'uk';
		}

		if ($code === 'ru-ru' || $code === 'russian') {
			return 'ru';
		}

		return substr($code, 0, 2);
	}

	private function loadLanguageStrings() {
		$keys = array(
			'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no',
			'entry_status', 'entry_hreflang', 'entry_xdefault', 'entry_default_language',
			'column_language', 'column_prefix', 'column_hreflang', 'column_status',
			'help_prefix', 'help_hreflang', 'help_default', 'button_save', 'button_cancel',
		);

		$data = array();

		foreach ($keys as $key) {
			$data[$key] = $this->language->get($key);
		}

		return $data;
	}
}
