<?php
namespace Opencart\Admin\Controller\Extension\Parcora\Shipping;

require_once DIR_EXTENSION . 'parcora/system/library/parcora/autoload.php';

use Parcora\OpenCart\ApiException;
use Parcora\OpenCart\Client;
use Parcora\OpenCart\Pricing;
use Parcora\OpenCart\Shipper;
use Parcora\OpenCart\ShipmentStore;
use Parcora\OpenCart\Weight;

/**
 * Class Parcora
 *
 * @package Opencart\Admin\Controller\Extension\Parcora\Shipping
 */
class Parcora extends \Opencart\System\Engine\Controller {
	/**
	 * Events the extension hooks into; registered on install, removed on uninstall.
	 */
	private const EVENTS = [
		'parcora_checkout' => ['catalog/view/checkout/shipping_method/after', 'extension/parcora/shipping/parcora.checkout'],
		'parcora_history'  => ['catalog/model/checkout/order.addHistory/after', 'extension/parcora/shipping/parcora.history'],
		'parcora_order'    => ['admin/view/sale/order_info/before', 'extension/parcora/shipping/parcora.order'],
	];

	private const WEBHOOK_EVENTS = ['shipment.delivered', 'shipment.exception', 'shipment.cancelled', 'tracking.updated'];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/parcora/shipping/parcora', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/parcora/shipping/parcora.save', 'user_token=' . $this->session->data['user_token']);
		$data['webhook'] = $this->url->link('extension/parcora/shipping/parcora.webhook', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$fields = [
			'api_key', 'base_url', 'tax_class_id', 'geo_zone_id', 'status', 'sort_order',
			'sender_name', 'sender_phone', 'sender_email', 'sender_line1', 'sender_city', 'sender_postcode', 'sender_country',
			'default_weight', 'create_status_id', 'delivered_status_id', 'label_format'
		];

		foreach ($fields as $field) {
			$data['shipping_parcora_' . $field] = $this->config->get('shipping_parcora_' . $field);
		}

		if ($data['shipping_parcora_default_weight'] === null) {
			$data['shipping_parcora_default_weight'] = 1000;
		}

		// Services: every service the account can use, with the merchant's pricing for each
		$saved = (array)$this->config->get('shipping_parcora_services');

		$data['services'] = [];
		$data['error_carriers'] = '';

		if ($this->config->get('shipping_parcora_api_key')) {
			try {
				foreach ($this->client()->carriers() as $carrier) {
					foreach ($carrier['services'] as $code) {
						$data['services'][] = [
							'code'      => $code,
							'carrier'   => $carrier['name'],
							'connected' => $carrier['connected'],
							'setting'   => ($saved[$code] ?? []) + ['enabled' => 0, 'title' => '', 'mode' => Pricing::MODE_API, 'markup_percent' => 0, 'markup_amount' => 0, 'fixed' => 0, 'free_from' => 0]
						];
					}
				}
			} catch (ApiException $e) {
				$data['error_carriers'] = sprintf($this->language->get('error_carriers'), $e->getMessage());
			}
		}

		// Carriers refuse pickup point parcels without the receiver's phone
		$data['warning_telephone'] = $this->config->get('config_telephone_required') ? '' : $this->language->get('warning_telephone');

		$data['webhook_id'] = $this->config->get('parcora_webhook_id');

		$this->load->model('localisation/tax_class');

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['label_formats'] = ['pdf_a4' => 'PDF A4', 'pdf_label' => 'PDF label (10x15)', 'zpl' => 'ZPL'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/parcora/shipping/parcora', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/parcora/shipping/parcora')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['shipping_parcora_api_key'])) {
			$json['error']['api-key'] = $this->language->get('error_api_key');
		}

		foreach (['name', 'line1', 'city', 'postcode', 'country'] as $field) {
			if (empty($this->request->post['shipping_parcora_sender_' . $field])) {
				$json['error']['warning'] = $this->language->get('error_sender');
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_parcora', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping')) {
			(new ShipmentStore($this->db, DB_PREFIX))->install();

			$this->load->model('setting/event');

			foreach (self::EVENTS as $code => [$trigger, $action]) {
				$this->model_setting_event->deleteEventByCode($code);

				$this->model_setting_event->addEvent([
					'code'        => $code,
					'description' => 'Parcora shipping',
					'trigger'     => $trigger,
					'action'      => $action,
					'status'      => true,
					'sort_order'  => 0
				]);
			}
		}
	}

	/**
	 * Uninstall
	 *
	 * The shipment table is kept so reinstalling does not lose order history.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/shipping')) {
			$this->load->model('setting/event');

			foreach (array_keys(self::EVENTS) as $code) {
				$this->model_setting_event->deleteEventByCode($code);
			}
		}
	}

	/**
	 * Webhook
	 *
	 * Subscribes the store to tracking updates; the signing secret is only
	 * returned once, so it is stored here.
	 *
	 * @return void
	 */
	public function webhook(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/parcora/shipping/parcora')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			try {
				$client = $this->client();

				if ($this->config->get('parcora_webhook_id')) {
					try {
						$client->deleteWebhookEndpoint((string)$this->config->get('parcora_webhook_id'));
					} catch (ApiException $e) {
						// Already gone on the Parcora side; register a fresh one anyway.
					}
				}

				$endpoint = $client->createWebhookEndpoint(HTTP_CATALOG . 'index.php?route=extension/parcora/shipping/parcora.webhook', self::WEBHOOK_EVENTS);

				$this->load->model('setting/setting');

				$this->model_setting_setting->editSetting('parcora_webhook', [
					'parcora_webhook_id'     => $endpoint['id'],
					'parcora_webhook_secret' => $endpoint['secret']
				]);

				$json['success'] = $this->language->get('text_webhook_success');
			} catch (ApiException $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Order
	 *
	 * Event handler for admin/view/sale/order_info/before: adds a Parcora tab
	 * to orders shipped with this extension.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function order(string &$route, array &$data): void {
		if (empty($data['order_id'])) {
			return;
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder((int)$data['order_id']);

		if (!$order_info || !Shipper::handles($order_info)) {
			return;
		}

		$this->load->language('extension/parcora/shipping/parcora', 'parcora');

		// Prefixed so the order page's own strings are not overwritten
		$tab = $this->language->all('parcora');

		$tab['shipment'] = (new ShipmentStore($this->db, DB_PREFIX))->get((int)$order_info['order_id']);
		$tab['service'] = $order_info['shipping_method']['parcora_service'];
		$tab['pickup_point'] = $order_info['shipping_method']['parcora_pickup_point'] ?? [];
		$tab['create'] = $this->url->link('extension/parcora/shipping/parcora.create', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_info['order_id'], true);
		$tab['label'] = $this->url->link('extension/parcora/shipping/parcora.label', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_info['order_id']);

		$data['tabs'][] = [
			'code'    => 'parcora',
			'title'   => $tab['heading_title'],
			'content' => $this->load->view('extension/parcora/shipping/parcora_order', $tab)
		];
	}

	/**
	 * Create
	 *
	 * Registers the shipment from the order page.
	 *
	 * @return void
	 */
	public function create(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$json = [];

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		if (!$this->user->hasPermission('modify', 'extension/parcora/shipping/parcora')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$json && (!$order_info || !Shipper::handles($order_info))) {
			$json['error'] = $this->language->get('error_order');
		}

		$store = new ShipmentStore($this->db, DB_PREFIX);

		if (!$json && !empty($store->get($order_id)['shipment_id'])) {
			$json['error'] = $this->language->get('error_shipment_exists');
		}

		if (!$json) {
			$grams = (new Weight($this->db, DB_PREFIX, $this->weight))->orderGrams($order_id) ?: (int)$this->config->get('shipping_parcora_default_weight');

			try {
				(new Shipper($this->client(), $store))->create($order_info, $grams, $this->sender(), (string)$this->config->get('shipping_parcora_label_format'));

				$json['success'] = $this->language->get('text_shipment_success');
			} catch (ApiException $e) {
				$json['error'] = $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Label
	 *
	 * Streams the carrier label for the order's shipment.
	 *
	 * @return void
	 */
	public function label(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$shipment = (new ShipmentStore($this->db, DB_PREFIX))->get((int)($this->request->get['order_id'] ?? 0));

		if (!$this->user->hasPermission('access', 'extension/parcora/shipping/parcora') || empty($shipment['shipment_id'])) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token']));
		}

		try {
			$label = $this->client()->label($shipment['shipment_id'], (string)$this->config->get('shipping_parcora_label_format'));
		} catch (ApiException $e) {
			$this->response->setOutput($e->getMessage());

			return;
		}

		$extension = strpos($label['content_type'], 'pdf') !== false ? 'pdf' : 'zpl';

		$this->response->addHeader('Content-Type: ' . $label['content_type']);
		$this->response->addHeader('Content-Disposition: inline; filename="label-' . $shipment['order_id'] . '.' . $extension . '"');
		$this->response->setOutput($label['body']);
	}

	private function client(): Client {
		return new Client((string)$this->config->get('shipping_parcora_api_key'), (string)$this->config->get('shipping_parcora_base_url'));
	}

	/**
	 * @return array<string, string>
	 */
	private function sender(): array {
		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry((int)$this->config->get('shipping_parcora_sender_country'));

		return [
			'name'     => (string)$this->config->get('shipping_parcora_sender_name'),
			'phone'    => (string)$this->config->get('shipping_parcora_sender_phone'),
			'email'    => (string)$this->config->get('shipping_parcora_sender_email'),
			'line1'    => (string)$this->config->get('shipping_parcora_sender_line1'),
			'city'     => (string)$this->config->get('shipping_parcora_sender_city'),
			'postcode' => (string)$this->config->get('shipping_parcora_sender_postcode'),
			'country'  => $country_info['iso_code_2'] ?? ''
		];
	}
}
