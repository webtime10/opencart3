<?php
class ControllerStartupMaintenance extends Controller {
	public function index() {

		// WT Lang start
		if (is_file(DIR_APPLICATION . 'controller/extension/module/wt_lang.php')) {
			$this->load->controller('extension/module/wt_lang/initialise');
		}
		// WT Lang end
      

		// WT Filter start
		if (is_file(DIR_APPLICATION . 'controller/extension/module/wt_filter.php')) {
			$this->load->controller('extension/module/wt_filter/initialise');
		}
		// WT Filter end
      
		// WT Filter start
		if (is_file(DIR_APPLICATION . 'controller/extension/module/wt_filter.php')) {
			$this->load->controller('extension/module/wt_filter/initialise');
		}
		// WT Filter end

		// WT Lang start
		if (is_file(DIR_APPLICATION . 'controller/extension/module/wt_lang.php')) {
			$this->load->controller('extension/module/wt_lang/initialise');
		}
		// WT Lang end

		if ($this->config->get('config_maintenance')) {
			// Route
			if (isset($this->request->get['route']) && $this->request->get['route'] != 'startup/router') {
				$route = $this->request->get['route'];
			} else {
				$route = $this->config->get('action_default');
			}			
			
			$ignore = array(
				'common/language/language',
				'common/currency/currency'
			);
			
			// Show site if logged in as admin
			$this->user = new Cart\User($this->registry);

			if ((substr($route, 0, 17) != 'extension/payment' && substr($route, 0, 3) != 'api') && !in_array($route, $ignore) && !$this->user->isLogged()) {
				return new Action('common/maintenance');
			}
		}
	}
}
