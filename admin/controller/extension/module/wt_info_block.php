<?php
/**
 * Админ-контроллер модуля WT Info Block (OpenCart 3).
 *
 * Путь: admin/controller/extension/module/wt_info_block.php
 * Маршрут: extension/module/wt_info_block
 *
 * Модуль — блок информационных карточек (иконка/картинка + заголовок + текст)
 * для вывода на витрине через Layout (content_top и т.п.).
 *
 * Здесь только админка: форма настроек экземпляра модуля (add/edit).
 * Витрина — в catalog/controller/extension/module/wt_info_block.php
 */
class ControllerExtensionModuleWtInfoBlock extends Controller {
	/** Ошибки валидации формы: warning, name … */
	private $error = array();

	/**
	 * Страница настроек модуля (новый или уже созданный instance).
	 *
	 * Без module_id в URL — создание нового экземпляра.
	 * С module_id — редактирование записи в oc_module.
	 */
	public function index() {
		$this->load->language('extension/module/wt_info_block');

		$this->document->setTitle($this->language->get('heading_title'));

		// model/setting/module — CRUD экземпляров модулей (не global setting)
		$this->load->model('setting/module');
		// ресайз превью картинок в форме
		$this->load->model('tool/image');

		// Сохранение формы (POST + проверка прав/имени)
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			
		/*
// Как это делалось бы в "обычном" PHP:
$moduleModel = new ModelSettingModule($registry);
$moduleModel->addModule('wt_info_block', $this->request->post);
		*/
		
		if (!isset($this->request->get['module_id'])) {
				// Новый экземпляр: code = wt_info_block, данные = весь POST
				$this->model_setting_module->addModule('wt_info_block', $this->request->post);
			} else {
				// Обновить существующий по module_id
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			// После сохранения — назад к списку модулей
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		// Ошибки для twig
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		// Хлебные крошки админки
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		// action — куда сабмитится форма (с module_id или без)
		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_info_block', 'user_token=' . $this->session->data['user_token'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_info_block', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/wt_info_block', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
			$data['action'] = $this->url->link('extension/module/wt_info_block', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		// Загрузка сохранённых настроек (только GET, не после неудачного POST —
		// после POST с ошибкой берём данные из $this->request->post ниже)
		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		// Имя экземпляра в списке модулей (не заголовок на витрине)
		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info)) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}

		// Вкл/выкл модуля
		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info)) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = 1;
		}

		// Общее описание модуля по языкам (если используется в форме)
		if (isset($this->request->post['module_description'])) {
			$data['module_description'] = $this->request->post['module_description'];
		} elseif (!empty($module_info['module_description'])) {
			$data['module_description'] = $module_info['module_description'];
		} else {
			$data['module_description'] = array();
		}

		// ——— Карточки блока ———
		// item[] = массив: image, sort_order, status, description[language_id][title|text]
		$items = array();

		if (isset($this->request->post['item'])) {
			$raw_items = $this->request->post['item'];
		} elseif (!empty($module_info['item'])) {
			$raw_items = $module_info['item'];
		} else {
			$raw_items = array();
		}

		foreach ($raw_items as $item) {
			$image = isset($item['image']) ? $item['image'] : '';

			// Превью 100×100 для админки (filemanager путь относительно DIR_IMAGE)
			if ($image && is_file(DIR_IMAGE . $image)) {
				$thumb = $this->model_tool_image->resize($image, 100, 100);

				if (!$thumb || preg_match('/\.svg$/i', $image)) {
					if ($this->request->server['HTTPS']) {
						$thumb = HTTPS_CATALOG . 'image/' . $image;
					} else {
						$thumb = HTTP_CATALOG . 'image/' . $image;
					}
				}
			} else {
				$thumb = $this->model_tool_image->resize('no_image.png', 100, 100);
			}

			$items[] = array(
				'description' => isset($item['description']) ? $item['description'] : array(),
				'image'       => $image,
				'thumb'       => $thumb,
				'sort_order'  => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
				'status'      => isset($item['status']) ? (int)$item['status'] : 1,
				// Для свёрнутой строки в UI — первый непустой title/text среди языков
				'preview_title' => '',
				'preview_text'  => ''
			);

			$last = count($items) - 1;
			if (!empty($items[$last]['description']) && is_array($items[$last]['description'])) {
				foreach ($items[$last]['description'] as $desc) {
					if ($items[$last]['preview_title'] === '' && !empty($desc['title'])) {
						$items[$last]['preview_title'] = $desc['title'];
					}
					if ($items[$last]['preview_text'] === '' && !empty($desc['text'])) {
						$items[$last]['preview_text'] = $desc['text'];
					}
					// Оба найдены — дальше языки не смотрим
					if ($items[$last]['preview_title'] !== '' && $items[$last]['preview_text'] !== '') {
						break;
					}
				}
			}
		}

		// Сортировка карточек по sort_order для отображения в форме
		usort($items, function ($a, $b) {
			return $a['sort_order'] - $b['sort_order'];
		});

		$data['items'] = $items;

		// Вкладки языков в форме (title/text на каждый language_id)
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['user_token'] = $this->session->data['user_token'];

		// Оболочка админки OC3
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_info_block', $data));
	}

	/**
	 * Проверка перед сохранением.
	 * @return bool true — ошибок нет, можно писать в БД
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_info_block')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Имя экземпляра: 3–64 символа (требование OC для module name)
		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}
}
