<?php
/**
 * WT Lang — lightweight multilanguage URL prefixes + hreflang (OpenCart 3).
 */
class WtLang {
	/** @var Registry */
	private $registry;

	/** @var Config */
	private $config;

	/** @var Request */
	private $request;

	/** @var Session */
	private $session;

	/** @var DB */
	private $db;

	/** @var array<string, array> */
	private $languages = array();

	/** @var array<string, string> */
	private $prefix_to_code = array();

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->request = $registry->get('request');
		$this->session = $registry->get('session');
		$this->db = $registry->get('db');

		$this->loadLanguages();
	}

	public function isActive() {
		return (bool)$this->config->get('module_wt_lang_status') && !empty($this->languages);
	}

	private function loadLanguages() {
		if (!(bool)$this->config->get('module_wt_lang_status')) {
			return;
		}

		$settings = $this->config->get('module_wt_lang_language');

		if (!is_array($settings)) {
			$settings = array();
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "language WHERE status = '1' ORDER BY sort_order, name");

		foreach ($query->rows as $row) {
			$code = $row['code'];
			$lang = isset($settings[$code]) && is_array($settings[$code]) ? $settings[$code] : array();

			if (empty($lang['status'])) {
				continue;
			}

			$prefix = isset($lang['prefix']) ? trim(strtolower($lang['prefix']), '/') : '';
			$hreflang = !empty($lang['hreflang']) ? $lang['hreflang'] : substr($code, 0, 2);

			$this->languages[$code] = array(
				'language_id' => (int)$row['language_id'],
				'code'        => $code,
				'name'        => $row['name'],
				'prefix'      => $prefix,
				'hreflang'    => $hreflang,
				'default'     => !empty($lang['default']),
			);

			if ($prefix !== '') {
				$this->prefix_to_code[$prefix] = $code;
			}
		}
	}

	public function detectRoute() {
		if (!$this->isActive() || $this->isAssetRequest()) {
			return;
		}

		$route_path = '';

		if (isset($this->request->get['_route_'])) {
			$route_path = trim($this->request->get['_route_'], '/');
		}

		$parts = ($route_path !== '') ? explode('/', $route_path) : array();
		$first = isset($parts[0]) ? strtolower($parts[0]) : '';

		$target_code = null;

		if ($first !== '' && isset($this->prefix_to_code[$first])) {
			$target_code = $this->prefix_to_code[$first];
			array_shift($parts);
			$new_route = implode('/', $parts);

			if ($new_route === '') {
				unset($this->request->get['_route_'], $_GET['_route_']);
			} else {
				$this->request->get['_route_'] = $new_route;
				$_GET['_route_'] = $new_route;
			}
		} else {
			$target_code = $this->getDefaultCode();
		}

		if ($target_code) {
			$this->applyLanguage($target_code);
		}
	}

	public function rewrite($link) {
		if (!$this->isActive() || $this->shouldSkipLink($link)) {
			return $link;
		}

		$code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->getDefaultCode();

		if (!$code || !isset($this->languages[$code])) {
			return $link;
		}

		$prefix = $this->languages[$code]['prefix'];

		$link = str_replace('&amp;', '&', $link);
		$parsed = parse_url($link);

		if (!isset($parsed['scheme'], $parsed['host'])) {
			return $link;
		}

		$path = isset($parsed['path']) ? $parsed['path'] : '/';

		if (strpos($path, 'index.php') !== false) {
			$path = str_replace('/index.php', '', $path);
		}

		$path = $this->stripKnownPrefixes($path);

		if ($prefix !== '') {
			$base = rtrim($path, '/');

			if ($base === '' || $base === '/') {
				$path = '/' . $prefix . '/';
			} else {
				$path = '/' . $prefix . $base;
			}
		} elseif ($path === '') {
			$path = '/';
		}

		$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
		$query = isset($parsed['query']) ? '?' . str_replace('&', '&amp;', $parsed['query']) : '';
		$fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

		return $parsed['scheme'] . '://' . $parsed['host'] . $port . $path . $query . $fragment;
	}

	public function getSwitcherLinks() {
		if (!$this->isActive()) {
			return array();
		}

		$route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
		$params = $this->request->get;
		unset($params['route'], $params['_route_']);

		$current = isset($this->session->data['language']) ? $this->session->data['language'] : $this->getDefaultCode();
		$links = array();

		foreach ($this->languages as $code => $lang) {
			$links[] = array(
				'code'    => $code,
				'name'    => $lang['name'],
				'href'    => $this->buildLanguageUrl($code, $route, $params),
				'current' => ($code === $current),
			);
		}

		return $links;
	}

	public function addHreflangLinks() {
		if (!$this->isActive() || !(bool)$this->config->get('module_wt_lang_hreflang')) {
			return;
		}

		$route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';

		if ($route === 'error/not_found') {
			return;
		}

		$params = $this->request->get;
		unset($params['route'], $params['_route_']);

		$document = $this->registry->get('document');
		$default_code = $this->getDefaultCode();

		foreach ($this->languages as $code => $lang) {
			$href = $this->buildLanguageUrl($code, $route, $params);

			if (method_exists($document, 'addHreflang')) {
				$document->addHreflang($href, $lang['hreflang']);
			}
		}

		if ((bool)$this->config->get('module_wt_lang_xdefault') && $default_code) {
			$href = $this->buildLanguageUrl($default_code, $route, $params);

			if (method_exists($document, 'addHreflang')) {
				$document->addHreflang($href, 'x-default');
			}
		}
	}

	public function buildLanguageUrl($code, $route, $params = array()) {
		if (!isset($this->languages[$code])) {
			return $this->registry->get('url')->link('common/home', '', true);
		}

		$saved_session = isset($this->session->data['language']) ? $this->session->data['language'] : null;
		$saved_id = $this->config->get('config_language_id');
		$saved_lang = $this->registry->get('language');

		$this->applyLanguage($code);

		$args = $params ? urldecode(http_build_query($params)) : '';
		$link = $this->registry->get('url')->link($route, $args, true);

		if ($saved_session !== null) {
			$this->applyLanguage($saved_session);
		} else {
			$this->registry->set('language', $saved_lang);
			$this->config->set('config_language_id', $saved_id);
		}

		return $link;
	}

	private function getDefaultCode() {
		foreach ($this->languages as $code => $lang) {
			if ($lang['default']) {
				return $code;
			}
		}

		$codes = array_keys($this->languages);

		return $codes ? $codes[0] : null;
	}

	private function applyLanguage($code) {
		if (!isset($this->languages[$code])) {
			return;
		}

		$lang = $this->languages[$code];

		$this->session->data['language'] = $code;
		$this->config->set('config_language_id', $lang['language_id']);
		$this->config->set('config_language', $code);

		$language = new Language($code);
		$language->load($code);
		$this->registry->set('language', $language);

		if (isset($this->request->server['HTTP_HOST'])) {
			setcookie('language', $code, time() + 60 * 60 * 24 * 30, '/', $this->request->server['HTTP_HOST']);
		}
	}

	private function stripKnownPrefixes($path) {
		$path = '/' . trim($path, '/');

		if ($path === '/') {
			return '/';
		}

		foreach ($this->prefix_to_code as $prefix => $code) {
			if ($path === '/' . $prefix || $path === '/' . $prefix . '/') {
				return '/';
			}

			if (strpos($path, '/' . $prefix . '/') === 0) {
				return substr($path, strlen($prefix) + 1) ?: '/';
			}
		}

		return $path;
	}

	private function shouldSkipLink($link) {
		if (stripos($link, '/admin') !== false) {
			return true;
		}

		if (stripos($link, 'route=extension/payment') !== false || stripos($link, 'route=api/') !== false) {
			return true;
		}

		foreach (array('.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.woff', '.woff2', '.ttf') as $ext) {
			if (stripos($link, $ext) !== false) {
				return true;
			}
		}

		return false;
	}

	private function isAssetRequest() {
		$uri = isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '';

		return (bool)preg_match('/\.(css|js|png|jpe?g|gif|webp|ico|svg|woff2?|ttf)(\?|$)/i', $uri);
	}
}
