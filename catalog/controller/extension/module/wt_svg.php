<?php
/**
 * Catalog side: SVG resize bypass for storefront (OpenCart 3).
 * Event: catalog/model/tool/image/resize/before
 */
class ControllerExtensionModuleWtSvg extends Controller {
	public function eventResizeCatalog(&$route, &$args) {
		if (!$this->config->get('module_wt_svg_status')) {
			return;
		}

		$filename = isset($args[0]) ? $args[0] : '';

		if (strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION)) !== 'svg') {
			return;
		}

		$filename = ltrim(str_replace(array('\\', '..'), array('/', ''), $filename), '/');

		if (!is_file(DIR_IMAGE . $filename)) {
			return;
		}

		if (!empty($this->request->server['HTTPS'])) {
			return $this->config->get('config_ssl') . 'image/' . $filename;
		}

		return $this->config->get('config_url') . 'image/' . $filename;
	}
}
