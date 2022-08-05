<?php
class ControllerCommonHome extends Controller {
	public function index() {
		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

		if (isset($this->request->get['route'])) {
			$canonical = $this->url->link('common/home');
			if ($this->config->get('config_seo_pro') && !$this->config->get('config_seopro_addslash')) {
				$canonical = rtrim($canonical, '/');
			}
			$this->document->addLink($canonical, 'canonical');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// Слайдер производителей
		$data['manufacturers'] = array();
		$this->load->model('catalog/manufacturer');
		$manufacturers = $this->model_catalog_manufacturer->getManufacturers();
		foreach ($manufacturers as $manufacturer){
			$data['manufacturers'][] = array(
				'name' 		=> $manufacturer['name'],
				'image' 	=> "image/".$manufacturer['image'],
				'href' 		=> $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id'])
			);
		}

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
