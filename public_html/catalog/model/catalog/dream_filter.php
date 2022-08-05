<?php
/**
 * Dream Filter v2.5
 * @link http://dreamfilter.ru/
 * @license Commercial
 * @copyright Copyright (c) 2016-2020 reDream
 * @author Ivan Grigorev <ig@redream.ru>
 */
class ModelCatalogDreamFilter extends Model {

	/** @var bool */
	private $_ionCube;

	/**
	 * ModelCatalogDreamFilter constructor.
	 * @param $registry
	 */
	public function __construct($registry) {
        parent::__construct($registry);
		$this->_ionCube = (extension_loaded('ionCube Loader') && version_compare(ioncube_loader_version(), '10', '>='));
		if(version_compare(phpversion(), '5.6', '>=') && $this->_ionCube) {
			$this->load->model('extension/dream_filter');
		} else {
			$this->load->model('catalog/product');
		}
	}

	/**
	 * @param $setting
	 * @param $request
	 * @return array
	 */
	public function prepareFilters($setting, $request) {
		if(isset($request['path'])) {
			if(!$this->checkCategory($request['path'], $setting)) {
				return array();
			}
		}
		if(version_compare(phpversion(), '5.6', '<')) {
			return array(
				'errors' => array($this->language->get('error_php'))
			);
		}
		if($this->_ionCube) {
			return $this->model_extension_dream_filter->prepareFilters($setting, $request);
		}
		return array(
			'errors' => array($this->language->get('error_ioncube'))
		);
	}

	/**
	 * @return int|bool
	 */
	public function cleanCache() {
		if($this->_ionCube) {
			return $this->model_extension_dream_filter->cleanCache();
		}
		return false;
	}

	/**
	 * @param $path
	 * @return bool
	 */
	private function checkCategory($path, $setting) {
		$parts = explode('_', (string)$path);
		$category_id = (int)end($parts);
		if(!empty($setting['settings']['categories'])) {
			if(!empty($setting['settings']['categories_child'])) {
				$collisions = array_intersect($parts, $setting['settings']['categories']);
				if(empty($collisions)) {
					return false;
				}
			} else {
				if(!in_array($category_id, $setting['settings']['categories'])) {
					return false;
				}
			}
		}
		if(!empty($setting['settings']['excategories'])) {
			if(!empty($setting['settings']['excategories_child'])) {
				$collisions = array_intersect($parts, $setting['settings']['excategories']);
				if(!empty($collisions)) {
					return false;
				}
			} else {
				if(in_array($category_id, $setting['settings']['excategories'])) {
					return false;
				}
			}
		}
		return true;
	}
}
