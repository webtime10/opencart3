<?php
class ControllerExtensionModuleWtLang extends Controller {
	public function initialise() {
		if ($this->registry->get('wt_lang')) {
			return;
		}

		require_once(DIR_SYSTEM . 'library/wt_lang/wt_lang.php');
		$this->registry->set('wt_lang', new WtLang($this->registry));
	}

	public function detectRoute() {
		$this->initialise();

		$wt_lang = $this->registry->get('wt_lang');

		if ($wt_lang) {
			$wt_lang->detectRoute();
		}
	}

	public function rewrite($link) {
		$wt_lang = $this->registry->get('wt_lang');

		if (!$wt_lang) {
			return $link;
		}

		return $wt_lang->rewrite($link);
	}

	public function hreflang() {
		$this->initialise();

		$wt_lang = $this->registry->get('wt_lang');

		if ($wt_lang) {
			$wt_lang->addHreflangLinks();
		}
	}
}
