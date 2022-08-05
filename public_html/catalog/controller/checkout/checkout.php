<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutCheckout extends Controller {
	public function index() {
		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) ||

    (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$this->response->redirect($this->url->link('checkout/cart'));
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$this->response->redirect($this->url->link('checkout/cart'));
			}
		}

		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

		// Required by klarna
		if ($this->config->get('payment_klarna_account') || $this->config->get('payment_klarna_invoice')) {
			$this->document->addScript('http://cdn.klarna.com/public/kitt/toc/v1.0/js/klarna.terms.min.js');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['text_checkout_option'] = sprintf($this->language->get('text_checkout_option'), 1);
		$data['text_checkout_account'] = sprintf($this->language->get('text_checkout_account'), 2);
		$data['text_checkout_payment_address'] = sprintf($this->language->get('text_checkout_payment_address'), 2);
		$data['text_checkout_shipping_address'] = sprintf($this->language->get('text_checkout_shipping_address'), 3);
		$data['text_checkout_shipping_method'] = sprintf($this->language->get('text_checkout_shipping_method'), 4);

		if ($this->cart->hasShipping()) {
			$data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 5);
			$data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 6);
		} else {
			$data['text_checkout_payment_method'] = sprintf($this->language->get('text_checkout_payment_method'), 3);
			$data['text_checkout_confirm'] = sprintf($this->language->get('text_checkout_confirm'), 4);
		}

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['logged'] = $this->customer->isLogged();

		if (isset($this->session->data['account'])) {
			$data['account'] = $this->session->data['account'];
		} else {
			$data['account'] = '';
		}

    // Totals
    $this->load->model('setting/extension');

    $totals = array();
    $taxes = $this->cart->getTaxes();
    $total = 0;

    // Because __call can not keep var references so we put them into an array.
    $total_data = array(
      'totals' => &$totals,
      'taxes'  => &$taxes,
      'total'  => &$total
    );

    // Display prices
    if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
      $sort_order = array();

      $results = $this->model_setting_extension->getExtensions('total');

      foreach ($results as $key => $value) {
        $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
      }

      array_multisort($sort_order, SORT_ASC, $results);

      foreach ($results as $result) {
        if ($this->config->get('total_' . $result['code'] . '_status')) {
          $this->load->model('extension/total/' . $result['code']);

          // We have to put the totals in an array so that they pass by reference.
          $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
        }
      }

      $sort_order = array();

      foreach ($totals as $key => $value) {
        $sort_order[$key] = $value['sort_order'];
      }

      array_multisort($sort_order, SORT_ASC, $totals);
    }

    $data['totals'] = array();
		// print_r($totals);
    foreach ($totals as $total) {
			isset($total['initial_total']) ? $initial_total = $total['initial_total'] : $initial_total = $total['value'];
      $data['totals'][] = array(
        'title'              => $total['title'],
        'value'              => $total['value'],
        'text'               => $total['value'],
        'initial_total_text' => $initial_total ,
      );
    }
		$data['discount'] = $totals[0]['initial_total'] - $totals[0]['value'];

    // print_r($totals[0]['value']);

    // Самовывоз
    $data['shipping_pickup_status'] = $this->config->get('shipping_pickup_status');
    // Вкл или выкл самовывоз
    // Доставка
    $data['shipping_flat_status'] = $this->config->get('shipping_flat_status');
    // Вкл или выкл доставка
    $data['shipping_flat_cost'] = $this->config->get('shipping_flat_cost');
    $data['shipping_flat_cost'] = round($data['shipping_flat_cost']);

    $data['total_cost_with_flat'] = $data['shipping_flat_cost'] + $data['totals'][0]['value'];


    // Подсчет количества товаров в корзине)
    $cart_total = $this->cart->countProducts();
    $data["total_products"] = $cart_total;
    // Правильные склонения для количества товаров
    if (($data["total_products"] % 10) == 1 && ($data["total_products"] < 10 || $data["total_products"] > 20))
      $data["total_products"] .= " товар";
    elseif ((($data["total_products"] % 10) >= 2 && ($data["total_products"] % 10) < 5) && ($data["total_products"] < 10 || $data["total_products"] > 20))
      $data["total_products"] .= " товара";
    else
      $data["total_products"] .= " товаров";


		$data['shipping_required'] = $this->cart->hasShipping();

		//  Коды способов получения товара в шаблон
		$data['shipping_methods'] = array();
		$this->load->model('setting/extension');
		$results = $this->model_setting_extension->getExtensions('shipping');

		foreach ($results as $result) {
			if ($this->config->get('shipping_' . $result['code'] . '_status')) {
				$this->load->model('extension/shipping/' . $result['code']);
				$data['shipping_methods'][$result['code']] = $this->{'model_extension_shipping_' . $result['code']}->getCode();
			}
		}

		$data['min_free_cost'] = $this->config->get('shipping_free_total');

		// Коды способов оплаты
		$data['payment_methods'] = array();
		$results = $this->model_setting_extension->getExtensions('payment');
		foreach ($results as $result){
			$data['payment_methods'][$result['code']] = $result['code'];
		}
		// --------------------

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/checkout', $data));
	}

	public function country() {
		$json = array();

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function customfield() {
		$json = array();

		$this->load->model('account/custom_field');

		// Customer Group
		if (isset($this->request->get['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->get['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->get['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			$json[] = array(
				'custom_field_id' => $custom_field['custom_field_id'],
				'required'        => $custom_field['required']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
