<?php
/**
 * WT AI — перевод + SEO (Gemini / DeepSeek / ChatGPT).
 */
class ControllerExtensionModuleWtAi extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_ai');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			foreach (array(
				'module_wt_ai_do_translate',
				'module_wt_ai_do_meta_title',
				'module_wt_ai_do_meta_description',
				'module_wt_ai_do_meta_h1',
				'module_wt_ai_do_meta_keyword',
				'module_wt_ai_overwrite',
				'module_wt_ai_product',
				'module_wt_ai_category',
				'module_wt_ai_article',
			) as $flag) {
				if (!isset($this->request->post[$flag])) {
					$this->request->post[$flag] = 0;
				}
			}

			$provider = isset($this->request->post['module_wt_ai_provider']) ? strtolower((string)$this->request->post['module_wt_ai_provider']) : 'gemini';
			if (!in_array($provider, array('gemini', 'deepseek', 'openai'), true)) {
				$provider = 'gemini';
			}
			$this->request->post['module_wt_ai_provider'] = $provider;

			// Back-compat: keep legacy gemini keys in sync with per-provider fields.
			if (isset($this->request->post['module_wt_ai_api_key_gemini'])) {
				$this->request->post['module_wt_ai_api_key'] = $this->request->post['module_wt_ai_api_key_gemini'];
			}
			if (isset($this->request->post['module_wt_ai_model_gemini'])) {
				$this->request->post['module_wt_ai_model'] = $this->request->post['module_wt_ai_model_gemini'];
			}

			$this->model_setting_setting->editSetting('module_wt_ai', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data = array();
		foreach ($this->language->all() as $key => $value) {
			$data[$key] = $value;
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/wt_ai', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/wt_ai', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['test'] = str_replace('&amp;', '&', $this->url->link('extension/module/wt_ai/test', 'user_token=' . $this->session->data['user_token'], true));
		$data['user_token'] = $this->session->data['user_token'];

		$legacy_key = (string)$this->config->get('module_wt_ai_api_key');
		$legacy_model = (string)$this->config->get('module_wt_ai_model');
		if ($legacy_model === '') {
			$legacy_model = 'gemini-3.6-flash';
		}

		$keys = array(
			'module_wt_ai_status'              => 0,
			'module_wt_ai_provider'            => 'gemini',
			'module_wt_ai_api_key_gemini'      => $legacy_key,
			'module_wt_ai_api_key_deepseek'    => '',
			'module_wt_ai_api_key_openai'      => '',
			'module_wt_ai_model_gemini'        => $legacy_model,
			'module_wt_ai_model_deepseek'      => 'deepseek-chat',
			'module_wt_ai_model_openai'        => 'gpt-4o-mini',
			'module_wt_ai_prompt_translate'    => $this->defaultTranslatePrompt(),
			'module_wt_ai_prompt_seo'          => $this->defaultSeoPrompt(),
			'module_wt_ai_do_translate'        => 1,
			'module_wt_ai_do_meta_title'       => 1,
			'module_wt_ai_do_meta_description' => 1,
			'module_wt_ai_do_meta_h1'          => 1,
			'module_wt_ai_do_meta_keyword'     => 1,
			'module_wt_ai_overwrite'           => 0,
			'module_wt_ai_product'             => 1,
			'module_wt_ai_category'            => 1,
			'module_wt_ai_article'             => 1,
		);

		foreach ($keys as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif ($this->config->get($key) !== null && $this->config->get($key) !== '') {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $default;
			}
		}

		if (!in_array($data['module_wt_ai_provider'], array('gemini', 'deepseek', 'openai'), true)) {
			$data['module_wt_ai_provider'] = 'gemini';
		}

		$retired = array('gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.0-flash-lite', 'gemini-1.5-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro', 'gemini-1.5-pro-latest');
		if (in_array($data['module_wt_ai_model_gemini'], $retired, true)) {
			$data['module_wt_ai_model_gemini'] = 'gemini-3.6-flash';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_ai', $data));
	}

	public function install() {
		$this->load->model('setting/event');
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/wt_ai');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/wt_ai');

		$this->model_setting_event->deleteEventByCode('wt_ai');
		$this->model_setting_event->addEvent('wt_ai', 'admin/view/catalog/product_form/after', 'extension/module/wt_ai/eventForm', 1, 0);
		$this->model_setting_event->addEvent('wt_ai', 'admin/view/catalog/category_form/after', 'extension/module/wt_ai/eventForm', 1, 0);
		$this->model_setting_event->addEvent('wt_ai', 'admin/view/blog/article_form/after', 'extension/module/wt_ai/eventForm', 1, 0);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_ai', array(
			'module_wt_ai_status'              => 1,
			'module_wt_ai_provider'            => 'gemini',
			'module_wt_ai_api_key'             => '',
			'module_wt_ai_api_key_gemini'      => '',
			'module_wt_ai_api_key_deepseek'    => '',
			'module_wt_ai_api_key_openai'      => '',
			'module_wt_ai_model'               => 'gemini-3.6-flash',
			'module_wt_ai_model_gemini'        => 'gemini-3.6-flash',
			'module_wt_ai_model_deepseek'      => 'deepseek-chat',
			'module_wt_ai_model_openai'        => 'gpt-4o-mini',
			'module_wt_ai_prompt_translate'    => $this->defaultTranslatePrompt(),
			'module_wt_ai_prompt_seo'          => $this->defaultSeoPrompt(),
			'module_wt_ai_do_translate'        => 1,
			'module_wt_ai_do_meta_title'       => 1,
			'module_wt_ai_do_meta_description' => 1,
			'module_wt_ai_do_meta_h1'          => 1,
			'module_wt_ai_do_meta_keyword'     => 1,
			'module_wt_ai_overwrite'           => 0,
			'module_wt_ai_product'             => 1,
			'module_wt_ai_category'            => 1,
			'module_wt_ai_article'             => 1,
		));
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_ai');
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_ai');
	}

	/**
	 * Вставка кнопки «WT AI» в шапку формы товара / категории / статьи.
	 */
	public function eventForm(&$route, &$data, &$output) {
		if (!$this->config->get('module_wt_ai_status')) {
			return;
		}

		$entity = '';
		$id = 0;

		if (strpos($route, 'catalog/product') !== false) {
			if (!$this->config->get('module_wt_ai_product')) {
				return;
			}
			$entity = 'product';
			$id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
			$needle = '<div class="pull-right">';
		} elseif (strpos($route, 'catalog/category') !== false) {
			if (!$this->config->get('module_wt_ai_category')) {
				return;
			}
			$entity = 'category';
			$id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
			$needle = '<div class="pull-right">';
		} elseif (strpos($route, 'blog/article') !== false) {
			if (!$this->config->get('module_wt_ai_article')) {
				return;
			}
			$entity = 'article';
			$id = isset($this->request->get['article_id']) ? (int)$this->request->get['article_id'] : 0;
			$needle = '<div class="pull-right">';
		} else {
			return;
		}

		$this->load->language('extension/module/wt_ai');

		$btn = $this->load->view('extension/module/wt_ai_button', array(
			'entity'     => $entity,
			'entity_id'  => $id,
			'action'     => $this->url->link('extension/module/wt_ai/run', 'user_token=' . $this->session->data['user_token'], true),
			'user_token' => $this->session->data['user_token'],
			'button_run' => $this->language->get('button_run'),
			'text_need_save' => $this->language->get('text_need_save'),
			'text_running'   => $this->language->get('text_running'),
			'text_done'      => $this->language->get('text_done'),
		));

		if ($needle && strpos($output, $needle) !== false) {
			$output = str_replace($needle, $needle . $btn, $output);
		}
	}

	/**
	 * AJAX: проверка ключа выбранного провайдера.
	 */
	public function test() {
		$this->load->language('extension/module/wt_ai');
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', 'extension/module/wt_ai')) {
			$this->response->setOutput(json_encode(array(
				'ok'      => false,
				'status'  => 'error',
				'message' => $this->language->get('error_permission'),
			)));
			return;
		}

		$provider = isset($this->request->post['provider']) ? (string)$this->request->post['provider'] : '';
		$api_key = isset($this->request->post['api_key']) ? (string)$this->request->post['api_key'] : '';
		$model = isset($this->request->post['model']) ? (string)$this->request->post['model'] : '';

		$this->load->model('extension/module/wt_ai');
		$result = $this->model_extension_module_wt_ai->testConnection($provider, $api_key, $model);

		if (!empty($result['ok'])) {
			$result['message'] = $this->language->get('text_connected');
		} elseif ($result['message'] === 'API key is empty') {
			$result['message'] = $this->language->get('error_api_key');
		}

		$this->response->setOutput(json_encode($result));
	}

	/**
	 * AJAX: перевод + SEO по галочкам настроек.
	 */
	public function run() {
		$this->load->language('extension/module/wt_ai');
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', 'extension/module/wt_ai')) {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => $this->language->get('error_permission'))));
			return;
		}

		if (!$this->config->get('module_wt_ai_status')) {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => $this->language->get('error_disabled'))));
			return;
		}

		$this->load->model('extension/module/wt_ai');

		if ($this->model_extension_module_wt_ai->getProviderApiKey() === '') {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => $this->language->get('error_api_key'))));
			return;
		}

		$entity = isset($this->request->post['entity']) ? (string)$this->request->post['entity'] : '';
		$id = isset($this->request->post['entity_id']) ? (int)$this->request->post['entity_id'] : 0;

		if ($id <= 0 || !in_array($entity, array('product', 'category', 'article'), true)) {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => $this->language->get('text_need_save'))));
			return;
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(300);
		}

		try {
			$result = $this->model_extension_module_wt_ai->process($entity, $id);
			$this->response->setOutput(json_encode(array(
				'ok'      => true,
				'message' => $result['message'],
				'log'     => $result['log'],
			)));
		} catch (Exception $e) {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => $e->getMessage())));
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_ai')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function defaultTranslatePrompt() {
		return "Ты профессиональный переводчик для интернет-магазина.\n"
			. "Переведи поля JSON с языка {source_lang} на язык {target_lang}.\n"
			. "HTML-теги в description не трогай. Не добавляй SEO-текст в описание.\n"
			. "Верни ТОЛЬКО валидный JSON с теми же ключами, что пришли на вход.";
	}

	private function defaultSeoPrompt() {
		return "Ты SEO-копирайтер интернет-магазина.\n"
			. "Язык ответа: {target_lang}. По содержимому товара/категории/статьи сгенерируй SEO-поля.\n"
			. "Правила: meta_title ≠ meta_h1; не вставляй H1 и title в текст description.\n"
			. "meta_title до ~60 символов, meta_description до ~160, meta_keyword через запятую.\n"
			. "Верни ТОЛЬКО JSON с запрошенными ключами.";
	}
}
