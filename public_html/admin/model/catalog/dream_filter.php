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
		if($this->_ionCube) {
			$this->load->model('extension/dream_filter');
		}
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
}