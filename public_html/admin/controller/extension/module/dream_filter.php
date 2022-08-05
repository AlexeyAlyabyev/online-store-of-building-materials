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
	 * @var string
	 */
	protected $pluginpath = 'view/javascript/jquery/dream-filter/';

	/**
	 * @var array
	 */
	private $error = array();

	/**
	 * Страница "Спасибо за покупку"
	 */
	public function index() {
		$language = $this->getLanguage();

		$this->document->setTitle($language['heading_title']);

		$token = $this->session->data['user_token'];
		$data['breadcrumbs'] = array(
			array(
				'text' => $language['text_home'],
				'href' => $this->url->link('common/dashboard', 'user_token=' . $token, true)
			),
			array(
				'text' => $language['text_modules'],
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true)
			)
		);
		if(isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $language['heading_title'],
				'href' => $this->url->link('extension/module/dream_filter', 'user_token=' . $token . '&module_id=' . $this->request->get['module_id'], true)
			);
			$location = $this->url->link('extension/module/dream_filter/edit', 'module_id=' . $this->request->get['module_id'] . '&user_token=' . $token, true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $language['heading_title'],
				'href' => $this->url->link('extension/module/dream_filter', 'user_token=' . $token, true)
			);
			$location = $this->url->link('extension/module/dream_filter/edit', 'user_token=' . $token, true);
		}

		$data['errors'] = array();
        $data['activate'] = false;
        $modification_applied = false;

        if(version_compare(phpversion(), '5.6', '<')) {
            $data['errors'][] = $language['error_php'];
        } elseif(!extension_loaded('ionCube Loader') || version_compare(ioncube_loader_version(), '10', '<')) {
            $data['errors'][] = $language['error_ioncube'];
        } elseif(!$modification_applied) {
            $data['errors'][] = sprintf($language['error_modification'], $this->url->link('marketplace/modification', 'user_token=' . $token, true));
        } else {
            $this->load->model('extension/dream_filter');
            if($this->model_extension_dream_filter->checkLicense()) {
                header('Location: ' . htmlspecialchars_decode($location));
            } else {
                $data['errors'] = array_merge($data['errors'], $this->model_extension_dream_filter->getErrors());
            }
            $data['activate'] = html_entity_decode($this->url->link('extension/module/dream_filter/activate', 'user_token=' . $token, true));
        }

        $data['cancel'] = html_entity_decode($this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true));

		$data['language'] = $language;
		$data['location'] = html_entity_decode($location);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dream_filter_home', $data));
	}

	/**
	 * Редактирование настроек модуля
	 */
	public function edit() {
		$this->document->addStyle($this->pluginpath.'css/dream.filter.admin.css');
		$this->document->addStyle($this->pluginpath.'css/jui/jquery-ui.min.css');
		$this->document->addStyle($this->pluginpath.'codemirror/lib/codemirror.css');
		$this->document->addStyle($this->pluginpath.'codemirror/theme/material.css');
		$this->document->addScript($this->pluginpath.'js/dream.filter.admin.js');
		$this->document->addScript($this->pluginpath.'js/jui/jquery-ui.min.js');
		$this->document->addScript($this->pluginpath.'codemirror/lib/codemirror.js');
		$this->document->addScript($this->pluginpath.'codemirror/mode/javascript/javascript.js');
		$this->document->addScript($this->pluginpath.'codemirror/addon/edit/matchbrackets.js');
		$this->document->addScript($this->pluginpath.'codemirror/addon/comment/continuecomment.js');
		$this->document->addScript($this->pluginpath.'codemirror/addon/comment/comment.js');

		$token = $this->session->data['user_token'];

		$language = $this->getLanguage();

		$this->document->setTitle($language['heading_title']);

		$this->load->model('setting/module');
		$this->load->model('extension/dream_filter');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');
		$this->load->model('setting/setting');
		$module_id = isset($this->request->get['module_id']) ? $this->request->get['module_id'] : null;

		$module_info = array();
		if ($this->request->server['REQUEST_METHOD'] !== 'POST' && $module_id) {
			$module_info = $this->model_setting_module->getModule($module_id);
		}

		$languages = $this->model_localisation_language->getLanguages();
		$data = $this->getData($module_info, $language, $languages);
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate($this->request->post, $languages)) {
			$this->model_extension_dream_filter->saveModule($data, $module_id);
			$this->session->data['success'] = $language['text_success'];
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true));
		}

        if (isset($module_info['filters'])) {
            $this->session->data['rdr-filters'][$module_id ? $module_id : 'new'] = $module_info['filters'];
        } else {
            unset($this->session->data['rdr-filters'][$module_id ? $module_id : 'new']);
        }

		$filter_layouts = $this->model_extension_dream_filter->getLayouts($module_id);
		if($module_id && empty($filter_layouts)) {
			if(empty($this->error['warning'])) {
				$this->error['warning'] = sprintf($language['error_layout'], $this->url->link('design/layout', 'user_token=' . $token, true));
			}
		}

		if (isset($this->error)) {
			foreach ($this->error as $key => $error) {
				$data['errors'][$key] = $error;
			}
		}
		$data['languages'] = $languages;

		$data['breadcrumbs'] = array(
			array(
				'text' => $language['text_home'],
				'href' => $this->url->link('common/dashboard', 'user_token=' . $token, true)
			),
			array(
				'text' => $language['text_modules'],
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true)
			),
			array(
				'text' => $language['heading_title'],
				'href' => $this->url->link('extension/module/dream_filter/edit', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : ''), true)
			)
		);
		$data['action'] = html_entity_decode($this->url->link('extension/module/dream_filter/edit', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : ''), true));
		$data['home'] = html_entity_decode($this->url->link('extension/module/dream_filter', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : ''), true));
		$data['apply'] = $module_id ? html_entity_decode($this->url->link('extension/module/dream_filter/apply', 'module_id=' . $module_id . '&user_token=' . $token, true)) : false;
        $data['version'] = $this->model_extension_dream_filter->getVersion();

		$data['note'] = !empty($this->session->data['rdrf-note']) ? $this->session->data['rdrf-note'] : '';
		unset($this->session->data['rdrf-note']);

		$data['cancel'] = html_entity_decode($this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true));
		$data['activate'] = html_entity_decode($this->url->link('extension/module/dream_filter/activate', 'user_token=' . $token, true));
		$data['cache_btn'] = html_entity_decode($this->url->link('extension/module/dream_filter/cache', 'user_token=' . $token, true));
		$data['reset_btn'] = html_entity_decode($this->url->link('extension/module/dream_filter/reset', 'user_token=' . $token, true));
		$data['updates_btn'] = html_entity_decode($this->url->link('extension/module/dream_filter/updates', 'user_token=' . $token, true));
		$data['token'] = $token;

		$data['templates'] = array(
			'vertical' => $language['template_vertical'],
			'horizontal' => $language['template_horizontal']
		);
		$data['skins'] = array(
			'default' => $language['skin_default'],
			'nice' => $language['skin_nice'],
			'simple' => $language['skin_simple'],
			'material' => $language['skin_material'],
			'clean' => $language['skin_clean'],
			'shaped' => $language['skin_shaped'],
			'outline' => $language['skin_outline'],
			'raised' => $language['skin_raised'],
			'light' => $language['skin_light']
		);
		$data['color_schemes'] = array(
			'default' => $language['color_default'],
			'deep-purple' => $language['color_deep_purple'],
			'sapphire' => $language['color_sapphire'],
			'larimar' => $language['color_larimar'],
			'tsavorite' => $language['color_tsavorite'],
			'topaz' => $language['color_topaz'],
			'spessartite' => $language['color_spessartite'],
			'space-gray' => $language['color_space_gray'],
			'rose' => $language['color_rose']
		);
		$data['buttons'] = array(
			'btn-default' => $language['btn_default'],
			'btn-material' => $language['btn_material'],
			'btn-clean' => $language['btn_clean'],
			'btn-flat' => $language['btn_flat'],
			'btn-shaped' => $language['btn_shaped'],
			'btn-outline' => $language['btn_outline'],
			'btn-raised' => $language['btn_raised'],
			'btn-light' => $language['btn_light']
		);
		$data['loaders'] = array(
			'ball-pulse' => array('title' => $language['loader_ball_pulse'], 'count' => 3),
			'ball-pulse-sync' => array('title' => $language['loader_ball_pulse_sync'], 'count' => 3),
			'ball-beat' => array('title' => $language['loader_ball_beat'], 'count' => 3),
			'ball-rotate' => array('title' => $language['loader_ball_rotate'], 'count' => 1),
			'ball-zig-zag' => array('title' => $language['loader_ball_zig_zag'], 'count' => 2),
			'ball-zig-zag-deflect' => array('title' => $language['loader_ball_zig_zag_deflect'], 'count' => 2),
			'ball-triangle-path' => array('title' => $language['loader_ball_triangle_path'], 'count' => 3),
			'ball-pulse-rise' => array('title' => $language['loader_ball_pulse_rise'], 'count' => 5),
			'ball-clip-rotate' => array('title' => $language['loader_ball_clip_rotate'], 'count' => 1),
			'ball-clip-rotate-pulse' => array('title' => $language['loader_ball_clip_rotate_pulse'], 'count' => 1),
			'ball-clip-rotate-multiple' => array('title' => $language['loader_ball_clip_rotate_multiple'], 'count' => 2),
			'ball-scale' => array('title' => $language['loader_ball_scale'], 'count' => 1),
			'ball-scale-multiple' => array('title' => $language['loader_ball_scale_multiple'], 'count' => 3),
			'ball-scale-ripple' => array('title' => $language['loader_ball_scale_ripple'], 'count' => 1),
			'ball-scale-ripple-multiple' => array('title' => $language['loader_ball_scale_ripple_multiple'], 'count' => 3),
			'ball-grid-beat' => array('title' => $language['loader_ball_grid_beat'], 'count' => 9),
			'ball-grid-pulse' => array('title' => $language['loader_ball_grid_pulse'], 'count' => 9),
			'ball-spin-fade-loader' => array('title' => $language['loader_ball_spin_fade_loader'], 'count' => 8),
			'ball-scale-random' => array('title' => $language['loader_ball_scale_random'], 'count' => 3),
			'line-scale' => array('title' => $language['loader_line_scale'], 'count' => 5),
			'line-scale-party' => array('title' => $language['loader_line_scale_party'], 'count' => 4),
			'line-scale-pulse-out' => array('title' => $language['loader_line_scale_pulse_out'], 'count' => 5),
			'line-scale-pulse-out-rapid' => array('title' => $language['loader_line_scale_pulse_out_rapid'], 'count' => 5),
			'line-spin-fade-loader' => array('title' => $language['loader_line_spin_fade_loader'], 'count' => 8),
			'cube-transition' => array('title' => $language['loader_cube_transition'], 'count' => 2),
			'square-spin' => array('title' => $language['loader_square_spin'], 'count' => 1),
			'triangle-skew-spin' => array('title' => $language['loader_triangle_skew_spin'], 'count' => 1),
			'pacman' => array('title' => $language['loader_pacman'], 'count' => 5),
			'semi-circle-spin' => array('title' => $language['loader_semi_circle_spin'], 'count' => 1)
		);

		// Categories
		$data['categories'] = array();
		$data['excategories'] = array();
		
		if(!empty($data['settings']['categories']) || !empty($data['settings']['excategories'])) {
			$this->load->model('catalog/category');
			if(!empty($data['settings']['categories'])) {
				foreach ($data['settings']['categories'] as $category_id) {
					$category_info = $this->model_catalog_category->getCategory($category_id);
					if (!empty($category_info)) {
						$data['categories'][$category_id] = ($category_info['path'] ? $category_info['path'] . '&nbsp;&nbsp;&gt;&nbsp;&nbsp;' : '') . $category_info['name'];
					}
				}
			}
			if(!empty($data['settings']['excategories'])) {
				foreach ($data['settings']['excategories'] as $category_id) {
					$category_info = $this->model_catalog_category->getCategory($category_id);
					if (!empty($category_info)) {
						$data['excategories'][$category_id] = ($category_info['path'] ? $category_info['path'] . '&nbsp;&nbsp;&gt;&nbsp;&nbsp;' : '') . $category_info['name'];
					}
				}
			}
		}

		$stores = array(array(
			'name'     => $this->config->get('config_name') . ' ' . $language['text_default'],
			'config'   => $this->model_setting_setting->getSetting('rdrf')
		));

		$results = $this->model_setting_store->getStores();
		foreach ($results as $result) {
			$stores[intval($result['store_id'])] = array(
				'name'     => $result['name'],
				'config'   => $this->model_setting_setting->getSetting('rdrf', $result['store_id'])
			);
		}

		$data['hpm_module'] = !empty($this->config->get('hpmodel_key'));
		$config_default = array(
			'rdrf_product_mode' => 'default',
			'rdrf_sub_categories' => 0,
			'rdrf_notavailable' => 'none',
			'rdrf_option_qty' => 0,
			'rdrf_group_hpm_counters' => 0,
			'rdrf_ignore_space' => 1,
			'rdrf_cachestatus' => 1,
			'rdrf_cachetime' => 72,
			'rdrf_license' => null
		);
		foreach ($stores as $store_id => $store) {
			foreach ($config_default as $key => $value) {
				if(!isset($store['config'][$key])) {
					$stores[$store_id]['config'][$key] = $value;
				}
			}
		}
		$data['stores'] = $stores;
		$data['params'] = $this->model_extension_dream_filter->getParams($language);
		
		$form_new = array(
			'id' => 'new',
			'action' => html_entity_decode($this->url->link('extension/module/dream_filter/filter', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : ''), true)),
			'token' => $token,
			'modal_title' => $language['text_add_filter'],
			'language' => $language,
			'languages' => $languages,
			'params' => $data['params'],
			'filter' => array()
		);
		$data['filter_form_new'] = $this->load->view('extension/module/dream_filter_form', $form_new);

		$filter_forms = array();
		if(!empty($data['filters']) && is_array($data['filters'])) {
		    $dividers = (array)$this->config->get('rdrf_attr_dividers');
		    foreach ($data['filters'] as $id => $filter) {
                if($this->request->server['REQUEST_METHOD'] !== 'POST' && $filter['filter_by'] == 'attributes' && isset($dividers[$filter['item_id']])) {
                    $filter['add']['divider'] = $dividers[$filter['item_id']];
                    $data['filters'][$id] = $filter;
                }
				$filter_forms[] = $this->load->view('extension/module/dream_filter_form', array(
					'id' => $id,
					'action' => html_entity_decode($this->url->link('extension/module/dream_filter/filter', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : '') . '&id=' . $id, true)),
					'token' => $token,
					'modal_title' => current($filter['name']),
					'language' => $language,
					'languages' => $languages,
					'params' => $data['params'],
					'filter' => $filter
				));
			}
		}
		
		$data['filter_forms'] = $filter_forms;

		$data['pluginpath'] = $this->pluginpath;
		$data['language'] = $language;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$data['filters_tab'] = $this->load->view('extension/module/dream_filter_filters', $data);
		$data['view_tab'] = $this->load->view('extension/module/dream_filter_view', $data);
		$data['settings_tab'] = $this->load->view('extension/module/dream_filter_settings', $data);
		$data['ajax_tab'] = $this->load->view('extension/module/dream_filter_ajax', $data);
		$data['config_tab'] = $this->load->view('extension/module/dream_filter_config', $data);
		$data['info_tab'] = $this->load->view('extension/module/dream_filter_info', $data);

		$this->response->setOutput($this->load->view('extension/module/dream_filter', $data));
	}

	/**
	 * Ajax сохранение
	 */
	public function apply() {
		$this->load->model('extension/dream_filter');
		$this->load->model('localisation/language');

		$json = array();
		$language = $this->getLanguage();
		$languages = $this->model_localisation_language->getLanguages();

		if ($this->request->post && $this->validate($this->request->post, $languages)) {
			$this->model_extension_dream_filter->saveModule($this->request->post, isset($this->request->get['module_id']) ? $this->request->get['module_id'] : false);
			$json['success'] = $language['text_success'];
		}
		if (isset($this->error)) {
			foreach ($this->error as $error) {
				$json['errors'][] = $error;
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Ajax очистка кэша
	 */
	public function cache() {
		$store_id = isset($this->request->get['store_id']) ? $this->request->get['store_id'] : 0;
		$language = $this->getLanguage();

		$this->load->model('extension/dream_filter');
		$this->model_extension_dream_filter->cleanCache($store_id);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array(
			'success' => $language['text_cache_cleared']
		)));
	}

	/**
	 * Ajax активация
	 */
	public function activate() {
		$store_id = isset($this->request->get['store_id']) ? $this->request->get['store_id'] : 0;

		$this->load->model('extension/dream_filter');
		$this->load->model('setting/setting');
		$this->load->language('extension/module/dream_filter');

        $json = $this->model_extension_dream_filter->getLicense($store_id);
		if(!isset($this->request->get['module_id'])) {
			$this->session->data['rdrf-note'] = $json['result'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Ajax проверка обновлений
	 */
	public function updates() {
		$this->load->model('extension/dream_filter');
        $json = $this->model_extension_dream_filter->getUpdates();
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Ajax сброс лицензии
	 */
	public function reset() {
		$store_id = isset($this->request->get['store_id']) ? $this->request->get['store_id'] : 0;
		$this->load->model('extension/dream_filter');
		$this->model_extension_dream_filter->resetLicense($store_id);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(true);
	}

	/**
	 * Ajax добавление фильтра
	 */
	public function filter() {
		$this->load->model('localisation/language');

		$json = $this->request->post;
		$language = $this->getLanguage();
		$languages = $this->model_localisation_language->getLanguages();
		$token = $this->session->data['user_token'];
		$module_id = isset($this->request->get['module_id']) ? $this->request->get['module_id'] : null;
		$rdr_filters = isset($this->session->data['rdr-filters'][$module_id ? $module_id : 'new']) ? $this->session->data['rdr-filters'][$module_id ? $module_id : 'new'] : [];

		if(empty($json['filter_by'])) {
			$json['errors']['filter_by'] = $language['error_filterby'];
		}

		if(isset($this->request->post['item_id']) && $this->request->post['item_id'] == '') {
			$item = '';
			switch ($this->request->post['filter_by']){
				case 'attributes':
					$item = $language['entry_attribute'];
					break;
				case 'options':
					$item = $language['entry_option'];
					break;
				case 'filters':
					$item = $language['entry_filter_group'];
					break;
			}
			$json['errors']['item_id'] = sprintf($language['error_item'], $item);
		}

		if(!isset($this->request->get['id']) && $rdr_filters) {
			$used = array();
			foreach ($rdr_filters as $filter) {
				$k = isset($filter['item_id']) ? $filter['filter_by'] . '-' . $filter['item_id'] : $filter['filter_by'];
				if(!isset($used[$k])) {
					$used[$k] = true;
				}
			}
			$key = isset($json['item_id']) ? $json['filter_by'] . '-' . $json['item_id'] : $json['filter_by'];
			if(isset($used[$key])) {
				$json['errors']['filter_by'] = $language['error_used'];
			}
		}

		foreach ($languages as $lang) {
			if(empty($json['errors']['name']) && isset($json['name'][$lang['language_id']]) && ((utf8_strlen($json['name'][$lang['language_id']]) < 3) || (utf8_strlen($json['name'][$lang['language_id']]) > 64))) {
				$json['errors']['name'] = $language['error_name'];
			}
		}

		if(!isset($this->request->get['id']) && !isset($json['errors'])) {
			$this->load->model('extension/dream_filter');
			$this->session->data['rdr-filters'][$module_id ? $module_id : 'new'][] = $this->request->post;

			$json['modal'] = $this->load->view('extension/module/dream_filter_form', array(
				'id' => $json['id'],
				'action' => html_entity_decode($this->url->link('extension/module/dream_filter/filter', 'user_token=' . $token . ($module_id ? '&module_id=' . $module_id : '') . '&id=' . $json['id'], true)),
				'token' => $token,
				'modal_title' => current($json['name']),
				'language' => $language,
				'languages' => $languages,
				'params' => $this->model_extension_dream_filter->getParams($language),
				'filter' => $json
			));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Ajax автозаполнение (фильтры, атрибуты, опции, категории)
	 */
	public function autocomplete() {
		$json = array();
		if (isset($this->request->get['filter_name']) && isset($this->request->get['type'])) {
			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 10
			);
			switch ($this->request->get['type']) {
				case 'filter':
					$this->load->model('extension/dream_filter');
					$groups = $this->model_extension_dream_filter->getFilterGroups($filter_data);
					foreach ($groups as $group) {
						$json[] = array(
							'id' 	=> $group['filter_group_id'],
							'name'	=> strip_tags(html_entity_decode($group['name'], ENT_QUOTES, 'UTF-8')),
						);
					}
					break;
				case 'attribute':
					$this->load->model('catalog/attribute');
					$attributes = $this->model_catalog_attribute->getAttributes($filter_data);
					foreach ($attributes as $attribute) {
						$json[] = array(
							'id'				=> $attribute['attribute_id'],
							'name'				=> strip_tags(html_entity_decode($attribute['name'], ENT_QUOTES, 'UTF-8')),
							'attribute_group'	=> $attribute['attribute_group']
						);
					}
					break;
				case 'option':
					$this->language->load('catalog/option');
					$this->load->model('catalog/option');

					$options = $this->model_catalog_option->getOptions($filter_data);
					foreach ($options as $option) {
						if (in_array($option['type'], array('select', 'radio', 'checkbox', 'image'))) {
							$type = $this->language->get('text_choose');
							$json[] = array(
								'id'			=> $option['option_id'],
								'name'			=> strip_tags(html_entity_decode($option['name'], ENT_QUOTES, 'UTF-8')),
								'category'		=> $type,
								'type'			=> $option['type'],
							);
						}
					}
					break;
				case 'category':
					$this->load->model('catalog/category');

					$filter_data['sort'] = 'name';
					$filter_data['order'] = 'ASC';
					$categories = $this->model_catalog_category->getCategories($filter_data);
					foreach ($categories as $category) {
						$json[] = array(
							'category_id' => $category['category_id'],
							'name'        => strip_tags(html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'))
						);
					}
					break;
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Языковые переменные
	 *
	 * @return array
	 */
	protected function getLanguage() {
		$this->load->language('extension/module/dream_filter');
		return $this->language->all();
	}
	
	/**
	 * Получение и смерживание данных
	 *
	 * @param array $module_info
	 * @param array $language
	 * @param array $language
	 * @return array
	 */
	protected function getData($module_info, $language, $languages) {
		$template = $this->config->get('config_template');
		$template_configs = require DIR_CONFIG . 'dream_filter_templates.php';

		$default_config = require DIR_CONFIG . 'dream_filter_defaults.php';
		$template_config = !empty($template_configs[$template]) ? $template_configs[$template] : array();
		$lang_config = array();

		$name_price = $name_manufacturers = array();
		foreach ($languages as $lang) {
			$name_price[$lang['language_id']] = $language['name_price'];
			$name_manufacturers[$lang['language_id']] = $language['name_manufacturers'];

			$lang_config['title'][$lang['language_id']] = $language['text_filter'];
			$lang_config['settings']['search_btn_text'][$lang['language_id']] = $language['text_search'];
			$lang_config['settings']['reset_btn_text'][$lang['language_id']] = $language['text_reset'];
			$lang_config['view']['truncate_text_show'][$lang['language_id']] = $language['text_showmore'];
			$lang_config['view']['truncate_text_hide'][$lang['language_id']] = $language['text_hide'];
			$lang_config['view']['mobile']['button_text'][$lang['language_id']] = '<svg width="22px" height="22px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 25">
  <path d="M21.47,7.58a0.94,0.94,0,0,0-.83-0.5H18a2.67,2.67,0,0,0-5.26,0H10a2.06,2.06,0,1,0-3.46,0H4.36a0.94,0.94,0,0,0-.78,1.46L9.87,17.9V24.4a0.47,0.47,0,0,0,.66.43L14.84,23a0.47,0.47,0,0,0,.28-0.43V17.9l6.29-9.36A0.94,0.94,0,0,0,21.47,7.58ZM8.29,7.08A1.13,1.13,0,1,1,9.41,6,1.13,1.13,0,0,1,8.29,7.08Zm5.39,0a1.73,1.73,0,0,1,3.34,0H13.68Z"/>
  <path d="M13.65,0.13a2.06,2.06,0,1,0,2.06,2.06A2.07,2.07,0,0,0,13.65.13Zm0,3.19a1.13,1.13,0,1,1,1.13-1.13A1.13,1.13,0,0,1,13.65,3.31Z"/>
  <path d="M18.72,2.33a0.47,0.47,0,0,0-.47.47V5.46a0.47,0.47,0,1,0,.94,0V2.8a0.47,0.47,0,0,0-.47-0.47h0Z"/>
  <path d="M9.95,0.53A0.47,0.47,0,0,0,9.48,1V2.92a0.47,0.47,0,1,0,.94,0V1A0.47,0.47,0,0,0,9.95.53h0Z"/>
</svg>';
		}
		$config = $this->mergeConfigs($default_config, $template_config, $lang_config, $module_info, $this->request->post);

		if(empty($config['filters']) && empty($module_info) && empty($this->request->post)) {
			$config['filters'] = array(
				array(
					'filter_by' => 'price',
					'type' => 'slider',
					'name' => $name_price,
					'open' => 1
				),
				array(
					'filter_by' => 'manufacturers',
					'type' => 'checkbox',
					'name' => $name_manufacturers,
					'open' => 1,
					'sort_order' => 'default'
				)
			);
		}
		return $config;
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @return array
	 */
	protected function mergeConfigs($a, $b) {
		$args = func_get_args();
		$res = array_shift($args);
		while (!empty($args)) {
			foreach (array_shift($args) as $k => $v) {
				if (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
					$res[$k] = $this->mergeConfigs($res[$k], $v);
				} else {
					$res[$k] = $v;
				}
			}
		}
		return $res;
	}

    /**
     * Валидация формы на ошибки
     *
     * @param array $post
     * @param array $languages
     * @return bool
     */
	protected function validate($post, $languages) {
		if (!$this->user->hasPermission('modify', 'extension/module/dream_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($languages as $lang) {
			if (utf8_strlen($post['title'][$lang['language_id']]) > 64) {
				$this->error['title'][$lang['language_id']] = $this->language->get('error_title');
			}
			if ((utf8_strlen($post['view']['truncate_text_show'][$lang['language_id']]) < 3) || (utf8_strlen($post['view']['truncate_text_show'][$lang['language_id']]) > 64)) {
				$this->error['truncate_text_show'][$lang['language_id']] = $this->language->get('error_text');
			}
			if ((utf8_strlen($post['view']['truncate_text_hide'][$lang['language_id']]) < 3) || (utf8_strlen($post['view']['truncate_text_hide'][$lang['language_id']]) > 64)) {
				$this->error['truncate_text_hide'][$lang['language_id']] = $this->language->get('error_text');
			}
		}

		if ((utf8_strlen($post['name']) < 3) || (utf8_strlen($post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}
		
		if ((int)$post['view']['truncate_height'] < 100) {
			$this->error['truncate_height'] = $this->language->get('error_truncate_height');
		}
		if ((int)$post['view']['truncate_elements'] < 1) {
			$this->error['truncate_elements'] = $this->language->get('error_truncate_elements');
		}
		if ((int)$post['view']['mobile']['width'] < 300 || (int)$post['view']['mobile']['width'] > 1200) {
			$this->error['screen_width'] = $this->language->get('error_screen_width');
		}
		if ((utf8_strlen($post['settings']['selector']) < 3) || (utf8_strlen($post['settings']['selector']) > 64)) {
			$this->error['selector'] = $this->language->get('error_selector');
		}
		if ((utf8_strlen($post['settings']['pagination_selector']) < 3) || (utf8_strlen($post['settings']['pagination_selector']) > 64)) {
			$this->error['pagination_selector'] = $this->language->get('error_selector');
		}
		if ((utf8_strlen($post['settings']['sorter_selector']) < 3) || (utf8_strlen($post['settings']['sorter_selector']) > 64)) {
			$this->error['sorter_selector'] = $this->language->get('error_selector');
		}
		if ((utf8_strlen($post['settings']['limit_selector']) < 3) || (utf8_strlen($post['settings']['limit_selector']) > 64)) {
			$this->error['limit_selector'] = $this->language->get('error_selector');
		}
		if ((int)$post['view']['mobile']['indenting_top'] < 0 || (int)$post['view']['mobile']['indenting_top'] > 300) {
			$this->error['indenting_top'] = sprintf($this->language->get('error_value'), 0, 300);
		}
		if ((int)$post['view']['mobile']['indenting_bottom'] < 0 || (int)$post['view']['mobile']['indenting_bottom'] > 300) {
			$this->error['indenting_bottom'] = sprintf($this->language->get('error_value'), 0, 300);
		}
		if ((int)$post['settings']['scroll_offset'] < 0 || (int)$post['settings']['scroll_offset'] > 1000) {
			$this->error['scroll_offset'] = sprintf($this->language->get('error_value'), 0, 1000);
		}
		if ((int)$post['settings']['min_values'] < 1) {
			$this->error['min_values'] = $this->language->get('error_minvalues');
		}
		foreach ($post['config'] as $store_id => $config) {
			if ((int)$config['rdrf_cachetime'] < 1 || (int)$config['rdrf_cachetime'] > 720) {
				$this->error['cache_time'][$store_id] = sprintf($this->language->get('error_value'), 1, 720);
			}
		}
		return !$this->error;
	}
}