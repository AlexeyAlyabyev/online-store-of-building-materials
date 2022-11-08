<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerProductCatalogs extends Controller {
	public function index() {
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => "Каталоги PDF",
			'href' => $this->url->link('product/catalogs')
		);

		
		$this->load->model('catalog/manufacturer');

		$data['manufacturers'] = array();

		$results = $this->model_catalog_manufacturer->getManufacturers();

		foreach ($results as $result) {
			if ($result['catalog_file']){
				$data['manufacturers'][] = array(
					'name' 					=> $result['name'],
					'catalog_file' 	=> $result['catalog_file']
				);
			}
		}
		
		$this->document->setTitle("Каталоги RUMGIPS и других производителей в PDF");
		$this->document->setDescription("Каталоги товаров RUMGIPS. Все доступные каталоги товаров наших поставщиков: DENKIRS, Technolight, Kraab, Flexy и другие");

		$data['heading_title'] = "Каталоги производителей в PDF";

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/catalogs', $data));
	}
}
