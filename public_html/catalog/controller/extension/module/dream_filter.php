<?php

/**
 * Dream Filter v2.5
 * @link http://dreamfilter.ru/
 * @license Commercial
 * @copyright Copyright (c) 2016-2020 reDream
 * @author Ivan Grigorev <ig@redream.ru>
 */
class ControllerExtensionModuleDreamFilter extends Controller {

	/**
	 * @var string Путь до папки со стилями и скриптами
	 */
	protected $pluginpath = 'catalog/view/javascript/jquery/dream-filter/';

	/**
	 * @param $setting
	 * @return mixed|string
	 */
	public function index($setting) {
		$this->load->model('catalog/dream_filter');

		$popper = ($setting['settings']['search_mode'] == 'manual' && $setting['settings']['use_popper']);
		$template = isset($setting['view']['template']) ? $setting['view']['template'] : 'vertical';
		$data['template'] = $template;

		//Имя фильтра и настройки отображения
		$lang_id = (int)$this->config->get('config_language_id');
		$data['title'] = $setting['title'][$lang_id];
		$text = array(
			'reset_btn_text',
			'search_btn_text',
			'truncate_text_show',
			'truncate_text_hide'
		);
		foreach ($text as $t) {
			if(isset($setting['settings'][$t][$lang_id])) {
				$data[$t] = $setting['settings'][$t][$lang_id];
			} elseif(isset($setting['view'][$t][$lang_id])) {
				$data[$t] = $setting['view'][$t][$lang_id];
			} else {
				$data[$t] = '';
			}
		}
		$data['mobile_button_text'] = html_entity_decode($setting['view']['mobile']['button_text'][$lang_id]);

		//Языковые настройки
		$this->load->language('extension/module/dream_filter');
		$data['language'] = array(
			'text_loading' => $this->language->get('text_loading'),
			'text_none' => $this->language->get('text_none')
		);

		$request = $this->parseRequest($this->request->get);

		$data = array_merge($data, $this->model_catalog_dream_filter->prepareFilters($setting, $request));

		$data['view'] = $setting['view'];
		$data['settings'] = $setting['settings'];
		$module_id = $data['settings']['module_id'];

		if(isset($setting['view']['button']) && $setting['view']['button'] !== 'btn-default') {
			$data['view']['btn-primary'] = $setting['view']['button'];
			$data['view']['btn-reset'] = $setting['view']['button'] . '-reset';
		} else {
			$data['view']['btn-primary'] = 'btn-primary';
			$data['view']['btn-reset'] = 'btn-default';
		}

		$popper_action = $this->url->link('extension/module/dream_filter/' . ((isset($this->request->get['route']) && $this->request->get['route'] == 'product/special') ? 'countspecial' : 'count'), '', (bool)$this->request->server['HTTPS']);
		$popper_action .= (strpos($popper_action, '?') !== false ? '&' : '?' ). $this->parseArguments($this->request->get, ['path' => 'rdrf_path', 'manufacturer_id' => 'filter_manufacturer_id']);

		$data['popper'] = array(
			'enable' => $popper,
			'id' => 'rdrf-popper' . $module_id,
			'button_id' => 'rdrf-popper-btn' . $module_id,
			'button' => $this->language->get('popper_button'),
			'action' => $popper_action
		);

		//SETTINGS
		$data['settings']['widget_id'] = 'rdrf' . $module_id;
		$data['settings']['form_id'] = 'rdrf-form' . $module_id;
		$data['settings']['reset_id'] = 'rdrf-reset' . $module_id;
		$data['view']['mobile']['button_id'] = 'dream_filter_toggler';

		$data['settings']['form_action'] = $this->url->link($this->request->get['route'], $this->parseArguments($this->request->get), (bool)$this->request->server['HTTPS']);

		$hidden = array(
			'sort',
			'order',
			'limit'
		);

		$data['hidden'] = array();
		foreach ($hidden as $get) {
            $data['hidden'][$get] = isset($this->request->get[$get]) ? $this->request->get[$get] : '';
		}

		if(!empty($data['errors'])) {
			return '<div class="alert alert-danger">'.implode('<br/>', $data['errors']).'</div>';
		} elseif(!empty($data['filters'])) {
			$this->registerAssets($setting);

			return $this->load->view('extension/module/dream_filter_' . $template, $data);
		}
		return '';
	}

	public function count($request = array()) {
	    $total = 0;
        if(extension_loaded('ionCube Loader') && version_compare(ioncube_loader_version(), '10', '>=')) {
            $this->load->model('extension/dream_filter');
            $this->load->language('extension/module/dream_filter');

            if(empty($request)) {
                $request = $this->parseRequest($this->request->get);
            }
            $total = $this->model_extension_dream_filter->getTotalProducts($request);
        }
		$this->response->setOutput(sprintf($this->language->get('popper_title'), $total));
	}

	public function countspecial() {
        $request = $this->parseRequest($this->request->get);
        $request['special'] = true;
        return $this->count($request);
	}

	protected function registerAssets($setting) {
		$skin = isset($setting['view']['skin']) ? $setting['view']['skin'] : 'default';
		$color = isset($setting['view']['color']) ? $setting['view']['color'] : 'default';
        if($setting['view']['bootstrap']) {
            $this->document->addStyle($this->pluginpath.'css/bootstrap/bootstrap.min.css');
            $this->document->addScript($this->pluginpath.'js/bootstrap/bootstrap.min.js');
        }

		$this->document->addStyle($this->pluginpath.'css/' . $skin . '/dream.filter.' . $color . '.css');
		$this->document->addScript($this->pluginpath.'js/dream.filter.js');
		$this->document->addScript($this->pluginpath.'js/ion-rangeSlider/ion.rangeSlider.min.js');

		if($setting['view']['truncate']['scrollbar'] || $setting['view']['mobile']['mode'] == 'fixed') {
			$this->document->addStyle($this->pluginpath.'css/scrollbar/jquery.scrollbar.css');
			$this->document->addScript($this->pluginpath.'js/scrollbar/jquery.scrollbar.min.js');
		}
		if($setting['settings']['ajax']['pushstate']) {
			$this->document->addScript($this->pluginpath.'js/history/history.min.js');
		}
		if($setting['settings']['search_mode'] == 'manual' && $setting['settings']['use_popper']) {
			$this->document->addScript($this->pluginpath.'js/popper/popper.js');
		}
	}

	/**
	 * @param array $get
	 * @return array
	 */
	protected function parseRequest($get) {
		$request = array();
        $request['rdrf'] = isset($get['rdrf']) ? $get['rdrf'] : array();

        if(isset($get['rdrf_path'])) {
        	$path = $get['rdrf_path'];
        } elseif (isset($get['path'])) {
	        $path = $get['path'];
        } else {
	        $path = null;
        }

		if ($path !== null) {
			$parts = explode('_', (string)$path);
			$category_id = (int)array_pop($parts);

			$request['path'] = $path;
			$request['filter_category_id'] = $category_id;
		} elseif (isset($get['category_id'])) {
			$request['filter_category_id'] = $get['category_id'];
		}
		if (isset($get['route']) && $get['route'] == 'product/special') {
			$request['special'] = true;
		}
		if (isset($get['sub_category'])) {
			$request['filter_sub_category'] = $get['sub_category'];
		} elseif($this->config->get('rdrf_sub_categories')) {
            $request['filter_sub_category'] = 1;
        }
		if (isset($get['manufacturer_id'])) {
			$request['filter_manufacturer_id'] = $get['manufacturer_id'];
		}
		if (isset($get['filter_filter'])) {
			$request['filter_filter'] = $get['filter_filter'];
		}
		if (isset($get['tag'])) {
			$request['filter_tag'] = $get['tag'];
		}
		if (isset($get['search'])) {
			$request['filter_name'] = $get['search'];
			if(empty($request['filter_tag'])) {
				$request['filter_tag'] = $get['search'];
			}
		}
		if (isset($get['description'])) {
			$request['filter_description'] = $get['description'];
		}
		return $request;
	}

	/**
	 * @param array $get
	 * @param array $replaces
	 * @return string
	 */
	protected function parseArguments($get, $replaces = array()) {
	    $arguments = '';
	    $args = array(
	        'path',
	        'manufacturer_id',
	        'search',
	        'tag',
	        'description',
            'category_id',
            'sub_category'
        );
	    $i = 0;
	    foreach ($args as $arg) {
	        if(isset($get[$arg])) {
	        	$name = isset($replaces[$arg]) ? $replaces[$arg] : $arg;
	        	$val = $get[$arg];
                $arguments .= ($i > 0 ? '&' : '') . $name . '=' . $val;
                $i++;
            }
        }
		return $arguments;
	}
}
