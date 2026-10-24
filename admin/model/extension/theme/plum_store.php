<?php
class ModelExtensionThemePlumStore extends Model {
	public function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "plum_information` (
			`information_id` int(11) NOT NULL,
			`top` tinyint(1) NOT NULL DEFAULT 0,
			`bottom` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`information_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	public function getFlags($information_id) {
		$information_id = (int)$information_id;

		if ($information_id <= 0) {
			return array('top' => 0, 'bottom' => 0);
		}

		$query = $this->db->query("SELECT `top`, `bottom` FROM `" . DB_PREFIX . "plum_information` WHERE information_id = '" . $information_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return array('top' => 0, 'bottom' => 0);
		}

		return array(
			'top'    => (int)$query->row['top'] ? 1 : 0,
			'bottom' => (int)$query->row['bottom'] ? 1 : 0
		);
	}

	public function setFlags($information_id, $top, $bottom = null) {
		$information_id = (int)$information_id;

		if ($information_id <= 0) {
			return;
		}

		$flags = $this->getFlags($information_id);
		$top = $top ? 1 : 0;
		$bottom = ($bottom === null) ? (int)$flags['bottom'] : ($bottom ? 1 : 0);

		$this->db->query("REPLACE INTO `" . DB_PREFIX . "plum_information` SET information_id = '" . $information_id . "', `top` = '" . (int)$top . "', `bottom` = '" . (int)$bottom . "'");
	}

	public function delete($information_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "plum_information` WHERE information_id = '" . (int)$information_id . "'");
	}
}
