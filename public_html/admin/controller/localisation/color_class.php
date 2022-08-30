<?php
class ControllerLocalisationColorClass extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('localisation/color_class');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/color_class');

		$this->getList();
	}

	public function add() {
		$this->load->language('localisation/color_class');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/color_class');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_localisation_color_class->addColorClass($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('localisation/color_class');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/color_class');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_localisation_color_class->editColorClass($this->request->get['color_class_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('localisation/color_class');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/color_class');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $color_class_id) {
				$this->model_localisation_color_class->deleteColorClass($color_class_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'color_class_id';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('localisation/color_class/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('localisation/color_class/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['color_classes'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$color_class_total = $this->model_localisation_color_class->getTotalColorClasses();

		$results = $this->model_localisation_color_class->getColorClasses($filter_data);

		foreach ($results as $result) {
			$data['color_classes'][] = array(
				'color_class_id' => $result['color_class_id'],
				//'title'           => $result['title'] . (($result['color_class_id'] == $this->config->get('config_color_class_id')) ? $this->language->get('text_default') : null),
				// 'unit'            => $result['unit'],
				'name'           => $result['name'],
				'value'           => $result['value'],
				'edit'            => $this->url->link('localisation/color_class/edit', 'user_token=' . $this->session->data['user_token'] . '&color_class_id=' . $result['color_class_id'] . $url, true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_title'] = $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $url, true);
		// $data['sort_unit'] = $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . '&sort=unit' . $url, true);
		$data['sort_value'] = $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . '&sort=value' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $color_class_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($color_class_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($color_class_total - $this->config->get('config_limit_admin'))) ? $color_class_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $color_class_total, ceil($color_class_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('localisation/color_class_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['color_class_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = array();
		}

		if (isset($this->error['unit'])) {
			$data['error_unit'] = $this->error['unit'];
		} else {
			$data['error_unit'] = array();
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['color_class_id'])) {
			$data['action'] = $this->url->link('localisation/color_class/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('localisation/color_class/edit', 'user_token=' . $this->session->data['user_token'] . '&color_class_id=' . $this->request->get['color_class_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('localisation/color_class', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['color_class_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$color_class_info = $this->model_localisation_color_class->getColorClass($this->request->get['color_class_id']);
		}

    // print_r($this->request->get['color_class_id']);

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		// if (isset($this->request->post['color_class_description'])) {
		// 	$data['color_class_description'] = $this->request->post['color_class_description'];
		// } elseif (isset($this->request->get['color_class_id'])) {
		// 	$data['color_class_description'] = $this->model_localisation_color_class->getColorClassDescriptions($this->request->get['color_class_id']);
		// } else {
		// 	$data['color_class_description'] = array();
		// }

		if (isset($this->request->post['value'])) {
			$data['value'] = $this->request->post['value'];
		} elseif (!empty($color_class_info)) {
			$data['value'] = $color_class_info['value'];
		} else {
			$data['value'] = '';
		}

    if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($color_class_info)) {
			$data['name'] = $color_class_info['name'];
		} else {
			$data['name'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('localisation/color_class_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'localisation/color_class')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// foreach ($this->request->post['color_class_description'] as $language_id => $value) {
		// 	if ((utf8_strlen($value['title']) < 3) || (utf8_strlen($value['title']) > 32)) {
		// 		$this->error['title'][$language_id] = $this->language->get('error_title');
		// 	}

		// 	if (!$value['unit'] || (utf8_strlen($value['unit']) > 4)) {
		// 		$this->error['unit'][$language_id] = $this->language->get('error_unit');
		// 	}
		// }

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'localisation/color_class')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('catalog/product');

		foreach ($this->request->post['selected'] as $color_class_id) {
			if ($this->config->get('config_color_class_id') == $color_class_id) {
				$this->error['warning'] = $this->language->get('error_default');
			}

			$product_total = $this->model_catalog_product->getTotalProductsByColorClassId($color_class_id);

			if ($product_total) {
				$this->error['warning'] = sprintf($this->language->get('error_product'), $product_total);
			}
		}

		return !$this->error;
	}
}
