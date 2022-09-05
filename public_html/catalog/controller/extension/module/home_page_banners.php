<?php
class ControllerExtensionModuleHomePageBanners extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/home_page_banners');
		
		$this->load->model('extension/module/home_page_banners');
				
		$data['heading_title'] = $this->language->get('heading_title');
		return $this->load->view('extension/module/home_page_banners', $data);
	}}