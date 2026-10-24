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

	/**
	 * @return array<int, array{title:string,href:string}>
	 */
	public function getTopInformations() {
		$this->ensureSchema();

		$sql = "SELECT i.information_id, id.title
			FROM `" . DB_PREFIX . "plum_information` pi
			INNER JOIN `" . DB_PREFIX . "information` i ON (i.information_id = pi.information_id)
			INNER JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id)
			INNER JOIN `" . DB_PREFIX . "information_to_store` i2s ON (i.information_id = i2s.information_id)
			WHERE pi.`top` = '1'
				AND i.status = '1'
				AND id.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
			ORDER BY i.sort_order ASC, LCASE(id.title) ASC";

		$query = $this->db->query($sql);
		$result = array();

		foreach ($query->rows as $row) {
			$result[] = array(
				'title' => $row['title'],
				'href'  => $this->url->link('information/information', 'information_id=' . (int)$row['information_id'])
			);
		}

		return $result;
	}
}
