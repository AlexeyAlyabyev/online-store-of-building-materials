<?php
class ModelLocalisationmeasureClass extends Model {
	public function addMeasureClass($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "measure_class SET value = '" . $data['value'] . "'");

		$measure_class_id = $this->db->getLastId();

		$this->cache->delete('measure_class');
		
		return $measure_class_id;
	}

	public function editMeasureClass($measure_class_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "measure_class SET value = '" . $data['value'] . "' WHERE measure_class_id = '" . (int)$measure_class_id . "'");

		$this->cache->delete('measure_class');
	}

	public function deleteMeasureClass($measure_class_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "measure_class WHERE measure_class_id = '" . (int)$measure_class_id . "'");

		$this->cache->delete('measure_class');
	}

	public function getMeasureClasses($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "measure_class";

			$sort_data = array(
				'value'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY measure_class_id";
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
			$measure_class_data = $this->cache->get('measure_class.' . (int)$this->config->get('config_language_id'));

			if (!$measure_class_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "measure_class");

				$measure_class_data = $query->rows;

				$this->cache->set('measure_class.' . (int)$this->config->get('config_language_id'), $measure_class_data);
			}

			return $measure_class_data;
		}
	}

	public function getMeasureClass($measure_class_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "measure_class WHERE measure_class_id = '" . (int)$measure_class_id . "'");

		return $query->row;
	}

	public function getTotalMeasureClasses() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "measure_class");

		return $query->row['total'];
	}
}