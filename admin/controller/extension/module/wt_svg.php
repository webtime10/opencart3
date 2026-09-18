<?php
/**
 * WT SVG — поддержка SVG в админском File Manager (OpenCart 3).
 *
 * Без OCMOD: через Events подменяет common/filemanager index+upload
 * и перехватывает tool/image/resize для .svg (GD SVG не умеет).
 *
 * Install → пишет события в oc_event. Клиент: Extensions → Modules → Install.
 */
class ControllerExtensionModuleWtSvg extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/wt_svg');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_wt_svg', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

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
			'href' => $this->url->link('extension/module/wt_svg', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/wt_svg', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_wt_svg_status'])) {
			$data['module_wt_svg_status'] = $this->request->post['module_wt_svg_status'];
		} else {
			$data['module_wt_svg_status'] = $this->config->get('module_wt_svg_status');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wt_svg', $data));
	}

	public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_svg');

		// File Manager: список + загрузка (подмена route)
		$this->model_setting_event->addEvent('wt_svg', 'admin/controller/common/filemanager/before', 'extension/module/wt_svg/eventFilemanager', 1, 1);
		$this->model_setting_event->addEvent('wt_svg', 'admin/controller/common/filemanager/upload/before', 'extension/module/wt_svg/eventUpload', 1, 1);

		// Resize: не гонять SVG через GD
		$this->model_setting_event->addEvent('wt_svg', 'admin/model/tool/image/resize/before', 'extension/module/wt_svg/eventResize', 1, 0);
		$this->model_setting_event->addEvent('wt_svg', 'catalog/model/tool/image/resize/before', 'extension/module/wt_svg/eventResizeCatalog', 1, 0);

		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/wt_svg');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/wt_svg');

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_wt_svg', array(
			'module_wt_svg_status' => 1
		));
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('wt_svg');

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_wt_svg');
	}

	/**
	 * admin/controller/common/filemanager/before → свой список с *.svg
	 */
	public function eventFilemanager(&$route, &$data) {
		if (!$this->isEnabled()) {
			return;
		}

		$route = 'extension/module/wt_svg/filemanager';
	}

	/**
	 * admin/controller/common/filemanager/upload/before → upload с SVG
	 */
	public function eventUpload(&$route, &$data) {
		if (!$this->isEnabled()) {
			return;
		}

		$route = 'extension/module/wt_svg/upload';
	}

	/**
	 * admin/model/tool/image/resize/before
	 */
	public function eventResize(&$route, &$args) {
		if (!$this->isEnabled()) {
			return;
		}

		$filename = isset($args[0]) ? $args[0] : '';

		if (!$this->isSvgFilename($filename)) {
			return;
		}

		$filename = ltrim(str_replace('\\', '/', $filename), '/');

		if (!is_file(DIR_IMAGE . $filename)) {
			return;
		}

		return $this->svgPublicUrl($filename);
	}

	/**
	 * Список файлов File Manager (как ядро + svg в glob).
	 */
	public function filemanager() {
		$this->load->language('common/filemanager');

		if ($this->request->server['HTTPS']) {
			$server = HTTPS_CATALOG;
		} else {
			$server = HTTP_CATALOG;
		}

		if (isset($this->request->get['filter_name'])) {
			$filter_name = rtrim(str_replace(array('*', '/', '\\'), '', $this->request->get['filter_name']), '/');
		} else {
			$filter_name = '';
		}

		if (isset($this->request->get['directory'])) {
			$directory = rtrim(DIR_IMAGE . 'catalog/' . str_replace('*', '', $this->request->get['directory']), '/');
		} else {
			$directory = DIR_IMAGE . 'catalog';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['images'] = array();

		$this->load->model('tool/image');

		$directories = array();
		$files = array();

		if (substr(str_replace('\\', '/', realpath($directory) . '/' . $filter_name), 0, strlen(DIR_IMAGE . 'catalog')) == str_replace('\\', '/', DIR_IMAGE . 'catalog')) {
			$directories = glob($directory . '/' . $filter_name . '*', GLOB_ONLYDIR);

			if (!$directories) {
				$directories = array();
			}

			$files = glob($directory . '/' . $filter_name . '*.{jpg,jpeg,png,gif,webp,svg,JPG,JPEG,PNG,GIF,WEBP,SVG}', GLOB_BRACE);

			if (!$files) {
				$files = array();
			}
		}

		$images = array_merge($directories, $files);
		$image_total = count($images);
		$images = array_splice($images, ($page - 1) * 16, 16);

		foreach ($images as $image) {
			$name = str_split(basename($image), 14);

			if (is_dir($image)) {
				$url = '';

				if (isset($this->request->get['target'])) {
					$url .= '&target=' . $this->request->get['target'];
				}

				if (isset($this->request->get['thumb'])) {
					$url .= '&thumb=' . $this->request->get['thumb'];
				}

				$data['images'][] = array(
					'thumb' => '',
					'name'  => implode(' ', $name),
					'type'  => 'directory',
					'path'  => utf8_substr($image, utf8_strlen(DIR_IMAGE)),
					'href'  => $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'] . '&directory=' . urlencode(utf8_substr($image, utf8_strlen(DIR_IMAGE . 'catalog/'))) . $url, true)
				);
			} elseif (is_file($image)) {
				$rel = utf8_substr($image, utf8_strlen(DIR_IMAGE));
				$thumb = $this->model_tool_image->resize($rel, 100, 100);

				// SVG / пустой resize — прямая ссылка на файл (иначе превью пустое)
				if (!$thumb || preg_match('/\.svg$/i', $rel)) {
					$thumb = $server . 'image/' . str_replace(' ', '%20', $rel);
				}

				$data['images'][] = array(
					'thumb' => $thumb,
					'name'  => implode(' ', $name),
					'type'  => 'image',
					'path'  => $rel,
					'href'  => $server . 'image/' . $rel
				);
			}
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->get['directory'])) {
			$data['directory'] = urlencode($this->request->get['directory']);
		} else {
			$data['directory'] = '';
		}

		if (isset($this->request->get['filter_name'])) {
			$data['filter_name'] = $this->request->get['filter_name'];
		} else {
			$data['filter_name'] = '';
		}

		if (isset($this->request->get['target'])) {
			$data['target'] = $this->request->get['target'];
		} else {
			$data['target'] = '';
		}

		if (isset($this->request->get['thumb'])) {
			$data['thumb'] = $this->request->get['thumb'];
		} else {
			$data['thumb'] = '';
		}

		$url = '';

		if (isset($this->request->get['directory'])) {
			$pos = strrpos($this->request->get['directory'], '/');

			if ($pos) {
				$url .= '&directory=' . urlencode(substr($this->request->get['directory'], 0, $pos));
			}
		}

		if (isset($this->request->get['target'])) {
			$url .= '&target=' . $this->request->get['target'];
		}

		if (isset($this->request->get['thumb'])) {
			$url .= '&thumb=' . $this->request->get['thumb'];
		}

		$data['parent'] = $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$url = '';

		if (isset($this->request->get['directory'])) {
			$url .= '&directory=' . urlencode($this->request->get['directory']);
		}

		if (isset($this->request->get['target'])) {
			$url .= '&target=' . $this->request->get['target'];
		}

		if (isset($this->request->get['thumb'])) {
			$url .= '&thumb=' . $this->request->get['thumb'];
		}

		$data['refresh'] = $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$url = '';

		if (isset($this->request->get['directory'])) {
			$url .= '&directory=' . urlencode(html_entity_decode($this->request->get['directory'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['target'])) {
			$url .= '&target=' . $this->request->get['target'];
		}

		if (isset($this->request->get['thumb'])) {
			$url .= '&thumb=' . $this->request->get['thumb'];
		}

		$pagination = new Pagination();
		$pagination->total = $image_total;
		$pagination->page = $page;
		$pagination->limit = 16;
		$pagination->url = $this->url->link('common/filemanager', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$this->response->setOutput($this->load->view('common/filemanager', $data));
	}

	/**
	 * Upload как в ядре + svg / image/svg+xml + лёгкая санитизация.
	 */
	public function upload() {
		$this->load->language('common/filemanager');

		$json = array();

		if (!$this->user->hasPermission('modify', 'common/filemanager')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['directory'])) {
			$directory = rtrim(DIR_IMAGE . 'catalog/' . $this->request->get['directory'], '/');
		} else {
			$directory = DIR_IMAGE . 'catalog';
		}

		if (!is_dir($directory) || substr(str_replace('\\', '/', realpath($directory)), 0, strlen(DIR_IMAGE . 'catalog')) != str_replace('\\', '/', DIR_IMAGE . 'catalog')) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			$files = array();

			if (!empty($this->request->files['file']['name']) && is_array($this->request->files['file']['name'])) {
				foreach (array_keys($this->request->files['file']['name']) as $key) {
					$files[] = array(
						'name'     => $this->request->files['file']['name'][$key],
						'type'     => $this->request->files['file']['type'][$key],
						'tmp_name' => $this->request->files['file']['tmp_name'][$key],
						'error'    => $this->request->files['file']['error'][$key],
						'size'     => $this->request->files['file']['size'][$key]
					);
				}
			} elseif (!empty($this->request->files['file']['name'])) {
				$files[] = $this->request->files['file'];
			}

			foreach ($files as $file) {
				if (is_file($file['tmp_name'])) {
					$filename = basename(html_entity_decode($file['name'], ENT_QUOTES, 'UTF-8'));

					if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 255)) {
						$json['error'] = $this->language->get('error_filename');
					}

					$ext = utf8_strtolower(utf8_substr(strrchr($filename, '.'), 1));

					$allowed_ext = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'svg');

					if (!in_array($ext, $allowed_ext)) {
						$json['error'] = $this->language->get('error_filetype');
					}

					$mime = strtolower((string)$file['type']);
					$allowed_mime = array(
						'image/jpeg',
						'image/pjpeg',
						'image/png',
						'image/x-png',
						'image/gif',
						'image/webp',
						'image/svg+xml',
						'image/svg',
						'text/xml',
						'application/xml',
						'text/plain'
					);

					$mime_ok = in_array($mime, $allowed_mime) || ($ext === 'svg' && (strpos($mime, 'svg') !== false || $mime === ''));

					if (!$mime_ok) {
						$json['error'] = $this->language->get('error_filetype');
					}

					if ($ext === 'svg' && !$this->isValidSvgUpload($file['tmp_name'])) {
						$json['error'] = $this->language->get('error_filetype');
					}

					if ($file['size'] > $this->config->get('config_file_max_size')) {
						$json['error'] = $this->language->get('error_filesize');
					}

					if ($file['error'] != UPLOAD_ERR_OK) {
						$json['error'] = $this->language->get('error_upload_' . $file['error']);
					}
				} else {
					$json['error'] = $this->language->get('error_upload');
				}

				if (!$json) {
					$dest = $directory . '/' . $filename;

					if ($ext === 'svg') {
						$svg = file_get_contents($file['tmp_name']);
						$svg = $this->sanitizeSvg($svg);
						file_put_contents($dest, $svg);
					} else {
						move_uploaded_file($file['tmp_name'], $dest);
					}
				}
			}
		}

		if (!$json) {
			$json['success'] = $this->language->get('text_uploaded');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function isEnabled() {
		return (bool)$this->config->get('module_wt_svg_status');
	}

	private function isSvgFilename($filename) {
		return strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION)) === 'svg';
	}

	private function svgPublicUrl($filename) {
		$filename = ltrim(str_replace(array('\\', '..'), array('/', ''), $filename), '/');

		if ($this->request->server['HTTPS']) {
			return HTTPS_CATALOG . 'image/' . $filename;
		}

		return HTTP_CATALOG . 'image/' . $filename;
	}

	private function isValidSvgUpload($tmp) {
		$head = @file_get_contents($tmp, false, null, 0, 4096);

		if ($head === false) {
			return false;
		}

		return (bool)preg_match('/<svg\b/i', $head);
	}

	/**
	 * Убирает типичные XSS-векторы в SVG (не полный санитайзер, но лучше чем ничего).
	 */
	private function sanitizeSvg($svg) {
		$svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
		$svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg);
		$svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
		$svg = preg_replace('/(xlink:href|href)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1=$2$2', $svg);

		return $svg;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wt_svg')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
