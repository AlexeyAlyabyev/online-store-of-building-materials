<?php
class ModelLocalisationColorClass extends Model {
	public function addColorClass($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "color_class SET value = '" . $data['value'] . "', name = '" . $data['name'] . "'");

		$color_class_id = $this->db->getLastId();

		// foreach ($data['length_class_description'] as $language_id => $value) {
		// 	$this->db->query("INSERT INTO " . DB_PREFIX . "length_class_description SET length_class_id = '" . (int)$length_class_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', unit = '" . $this->db->escape($value['unit']) . "'");
		// }

		$this->cache->delete('color_class');

		return $color_class_id;
	}

	public function editColorClass($color_class_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "color_class SET value = '" . $data['value'] . "', name = '" . $data['name'] . "' WHERE color_class_id = '" . (int)$color_class_id . "'");

		// $this->db->query("DELETE FROM " . DB_PREFIX . "length_class_description WHERE length_class_id = '" . (int)$length_class_id . "'");

		// foreach ($data['length_class_description'] as $language_id => $value) {
		// 	$this->db->query("INSERT INTO " . DB_PREFIX . "length_class_description SET length_class_id = '" . (int)$length_class_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', unit = '" . $this->db->escape($value['unit']) . "'");
		// }

		$this->cache->delete('color_class');
	}

	public function deleteColorClass($color_class_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "color_class WHERE color_class_id = '" . (int)$color_class_id . "'");
		// $this->db->query("DELETE FROM " . DB_PREFIX . "length_class_description WHERE length_class_id = '" . (int)$length_class_id . "'");

		$this->cache->delete('color_class');
	}

	public function getColorClasses($data = array()) {
		if ($data) {
			// $sql = "SELECT * FROM " . DB_PREFIX . "color_class lc LEFT JOIN " . DB_PREFIX . "length_class_description lcd ON (lc.length_class_id = lcd.length_class_id) WHERE lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
      $sql = "SELECT * FROM " . DB_PREFIX . "color_class";

			$sort_data = array(
				'title',
				'unit',
				'value'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY color_class_id";
			}

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}

			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}

				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$color_class_data = $this->cache->get('color_class.' . (int)$this->config->get('config_language_id'));

			if (!$color_class_data) {
				// $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class lc LEFT JOIN " . DB_PREFIX . "length_class_description lcd ON (lc.length_class_id = lcd.length_class_id) WHERE lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "color_class");


				$color_class_data = $query->rows;

				$this->cache->set('color_class.' . (int)$this->config->get('config_language_id'), $color_class_data);
			}

			return $color_class_data;
		}
	}

	public function getColorClass($color_class_id) {
		//$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class lc LEFT JOIN " . DB_PREFIX . "length_class_description lcd ON (lc.length_class_id = lcd.length_class_id) WHERE lc.length_class_id = '" . (int)$length_class_id . "' AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "color_class WHERE color_class_id = '" . (int)$color_class_id . "'");

		return $query->row;
	}

	// public function getLengthClassDescriptionByUnit($unit) {
	// 	$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class_description WHERE unit = '" . $this->db->escape($unit) . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

	// 	return $query->row;
	// }

	// public function getLengthClassDescriptions($length_class_id) {
	// 	$length_class_data = array();

	// 	$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class_description WHERE length_class_id = '" . (int)$length_class_id . "'");

	// 	foreach ($query->rows as $result) {
	// 		$length_class_data[$result['language_id']] = array(
	// 			'title' => $result['title'],
	// 			'unit'  => $result['unit']
	// 		);
	// 	}

	// 	return $length_class_data;
	// }

	public function getTotalColorClasses() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "color_class");

		return $query->row['total'];
	}
}
