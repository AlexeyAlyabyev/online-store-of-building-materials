<?php
class ControllerExtensionModuleCategory extends Controller {
	public function index() {
		$this->load->language('extension/module/category');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$data['categories'] = array();
		$categories = $this->model_catalog_category->getAllCategories();
		foreach ($categories as $category) {
			// Основные категории
			if ($category['top']) {
				$filter = array(
					'filter_category_id'  => $category['category_id'],
					'filter_sub_category' => true
				);

				$total = $this->model_catalog_product->getTotalProducts($filter);
				// Правильные склонения для количества товаров
				if (($total % 10) == 1 && ($total < 10 || $total > 20)) 
					$total .= " товар";
				elseif ((($total % 10) >= 2 && ($total % 10) < 5) && ($total < 10 || $total > 20)) 
					$total .= " товара";
				else
					$total .= " товаров";

				$category['menu_name'] ? $name = $category['menu_name'] : $name = $category['name'];

				$data['categories'][] = array(
					'name'     			=> $name,
					'name_addition' => $category['menu_name_addition'],
					'total'					=> $total,
					'href'     			=> $this->url->link('product/category', 'path=' . $category['category_id']),
					'image'					=> "image/".$category['image']
				);
			}
		}

		return $this->load->view('extension/module/category', $data);
	}
}