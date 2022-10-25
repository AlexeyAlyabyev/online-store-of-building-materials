<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCommonHeader extends Controller {
	public function index() {
		// Analytics
		$this->load->model('setting/extension');

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		$data['title'] = $this->document->getTitle();

		$data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['links'] = $this->document->getLinks();
		$data['robots'] = $this->document->getRobots();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');


		// Убираем из индекска тестовый поддомен
		$in_dev = strpos($_SERVER['HTTP_HOST'], 'develop');
		if ($in_dev !== false || (isset($_POST['no_index']) && $_POST['no_index'])) $data['noindex'] = true;


		$this->load->language('common/header');

		$host = isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1')) ? HTTPS_SERVER : HTTP_SERVER;
		if ($this->request->server['REQUEST_URI'] == '/') {
			$data['og_url'] = $this->url->link('common/home');
		} else {
			$data['og_url'] = $host . substr($this->request->server['REQUEST_URI'], 1, (strlen($this->request->server['REQUEST_URI'])-1));
		}

		$data['og_image'] = $this->document->getOgImage();

		// Категории в мобильном меню

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$data['categories'] = array();

		$categories = $this->model_catalog_category->getCategories(0);

		foreach ($categories as $category) {
			// if ($category['top']) {
				$filter = array(
					'filter_category_id'  => $category['category_id'],
					'filter_sub_category' => true
				);

				// $total = $this->model_catalog_product->getTotalProducts($filter);
				// // Правильные склонения для количества товаров
				// if (($total % 10) == 1 && ($total < 10 || $total > 20))
				// 	$total .= " товар";
				// elseif ((($total % 10) >= 2 && ($total % 10) < 5) && ($total < 10 || $total > 20))
				// 	$total .= " товара";
				// else
				// 	$total .= " товаров";

				$data['categories'][] = array(
					'name'     	=> $category['name'],
					// 'total'			=> $total,
					'href'     	=> $this->url->link('product/category', 'path=' . $category['category_id']),
					'image'			=> "image/".$category['image']
				);
			// }
		}


		// Производители в мобильном меню

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

		// Количество товаров в корзине
		$data['products_in_cart'] = $this->cart->countProducts();

		// Wishlist

		$data['home'] = $this->url->link('common/home');
		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		$data['whatsapp'] = $this->config->get('config_whatsapp_telephone');


		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		if (isset($this->session->data['user_token']))
			$data['refresh'] = HTTPS_SERVER . "admin/index.php?route=marketplace/modification/refresh&user_token=" . $this->session->data['user_token'];

		return $this->load->view('common/header', $data);
	}
}
