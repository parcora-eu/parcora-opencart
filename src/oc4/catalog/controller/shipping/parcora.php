<?php
namespace Opencart\Catalog\Controller\Extension\Parcora\Shipping;

require_once DIR_EXTENSION . 'parcora/system/library/parcora/autoload.php';

use Parcora\OpenCart\ApiException;
use Parcora\OpenCart\Client;
use Parcora\OpenCart\Shipper;
use Parcora\OpenCart\ShipmentStore;
use Parcora\OpenCart\Webhook;
use Parcora\OpenCart\Weight;

/**
 * Class Parcora
 *
 * Pickup point selection at checkout, automatic shipment creation and the
 * tracking webhook.
 *
 * @package Opencart\Catalog\Controller\Extension\Parcora\Shipping
 */
class Parcora extends \Opencart\System\Engine\Controller {
	/**
	 * Checkout
	 *
	 * Event handler for catalog/view/checkout/shipping_method/after: adds the
	 * pickup point picker to the shipping method modal.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $data
	 * @param string               $output
	 *
	 * @return void
	 */
	public function checkout(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('shipping_parcora_status')) {
			return;
		}

		$this->load->language('extension/parcora/shipping/parcora');

		$config = [
			'points' => $this->url->link('extension/parcora/shipping/parcora.points', 'language=' . $this->config->get('config_language'), true),
			'point'  => $this->url->link('extension/parcora/shipping/parcora.point', 'language=' . $this->config->get('config_language'), true),
			'text'   => [
				'choose'   => $this->language->get('text_choose_point'),
				'search'   => $this->language->get('text_search'),
				'loading'  => $this->language->get('text_loading'),
				'none'     => $this->language->get('text_no_points'),
				'required' => $this->language->get('error_point_required')
			]
		];

		$output .= '<script type="text/javascript">window.parcora = ' . json_encode($config) . ';</script>';
		$output .= '<script src="extension/parcora/catalog/view/javascript/parcora.js?v=0.1.0" type="text/javascript"></script>';
	}

	/**
	 * Points
	 *
	 * Pickup points for a Parcora service, near the shipping address city
	 * unless the buyer searches for something else.
	 *
	 * @return void
	 */
	public function points(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$json = [];

		$quote = $this->getQuote((string)($this->request->get['code'] ?? ''));

		if (!$quote || $quote['parcora_delivers_to'] != 'pickup_point') {
			$json['error'] = $this->language->get('error_method');
		}

		if (!$json) {
			$query = [
				'carrier' => $quote['parcora_carrier'],
				'country' => $this->session->data['shipping_address']['iso_code_2'],
				'limit'   => 50
			];

			$search = trim((string)($this->request->get['q'] ?? ''));

			if ($search === '') {
				$search = (string)$this->session->data['shipping_address']['city'];
			}

			try {
				$client = $this->client();

				$points = mb_strlen($search) >= 2 ? $client->pickupPoints($query + ['q' => $search]) : [];

				// A city the registry spells differently still gets a list to pick from
				if (!$points && empty($this->request->get['q'])) {
					$points = $client->pickupPoints($query);
				}
			} catch (ApiException $e) {
				$this->log->write('Parcora pickup points: ' . $e->getMessage());

				$points = null;

				$json['error'] = $this->language->get('error_points');
			}

			if ($points !== null) {
				$json['points'] = [];

				// Only points shown to the buyer can be chosen
				$this->session->data['parcora_points'] = [];

				foreach ($points as $point) {
					$address = trim($point['address']['line1'] . ', ' . $point['address']['postcode'] . ' ' . $point['address']['city'], ', ');

					$this->session->data['parcora_points'][$point['id']] = [
						'id'      => $point['id'],
						'name'    => $point['name'],
						'address' => $address
					];

					$json['points'][] = $this->session->data['parcora_points'][$point['id']];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Point
	 *
	 * Attaches the chosen pickup point to the shipping method, so OpenCart
	 * carries it into the order like any other part of the method.
	 *
	 * @return void
	 */
	public function point(): void {
		$this->load->language('extension/parcora/shipping/parcora');

		$json = [];

		$code = (string)($this->request->post['code'] ?? '');
		$id = (string)($this->request->post['id'] ?? '');

		$quote = $this->getQuote($code);

		if (!$quote || $quote['parcora_delivers_to'] != 'pickup_point') {
			$json['error'] = $this->language->get('error_method');
		} elseif (!isset($this->session->data['parcora_points'][$id])) {
			$json['error'] = $this->language->get('error_point');
		}

		if (!$json) {
			$point = $this->session->data['parcora_points'][$id];
			$key = substr($code, strlen('parcora.'));

			$quote['name'] = $quote['parcora_title'] . ' — ' . htmlspecialchars($point['name'] . ', ' . $point['address'], ENT_QUOTES, 'UTF-8');
			$quote['parcora_pickup_point'] = $point;

			$this->session->data['shipping_methods']['parcora']['quote'][$key] = $quote;

			if (isset($this->session->data['shipping_method']['code']) && $this->session->data['shipping_method']['code'] == $code) {
				$this->session->data['shipping_method'] = $quote;
			}

			$json['name'] = $quote['name'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * History
	 *
	 * Event handler for catalog/model/checkout/order.addHistory/after: creates
	 * the shipment when the order reaches the configured status. A failure is
	 * recorded on the order page and never interrupts the order itself.
	 *
	 * @param string       $route
	 * @param array<mixed> $args
	 *
	 * @return void
	 */
	public function history(string &$route, array &$args): void {
		$order_id = (int)$args[0];
		$order_status_id = (int)$args[1];

		if (!$this->config->get('shipping_parcora_create_status_id') || $order_status_id != $this->config->get('shipping_parcora_create_status_id')) {
			return;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		$store = new ShipmentStore($this->db, DB_PREFIX);

		if (!$order_info || !Shipper::handles($order_info) || !empty($store->get($order_id)['shipment_id'])) {
			return;
		}

		$grams = (new Weight($this->db, DB_PREFIX, $this->weight))->orderGrams($order_id) ?: (int)$this->config->get('shipping_parcora_default_weight');

		try {
			(new Shipper($this->client(), $store))->create($order_info, $grams, $this->sender(), (string)$this->config->get('shipping_parcora_label_format'));
		} catch (ApiException $e) {
			$this->log->write('Parcora shipment for order ' . $order_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Webhook
	 *
	 * Receives tracking updates from Parcora:
	 * index.php?route=extension/parcora/shipping/parcora.webhook
	 *
	 * @return void
	 */
	public function webhook(): void {
		$payload = (string)file_get_contents('php://input');
		$signature = (string)($this->request->server['HTTP_WEBHOOK_SIGNATURE'] ?? '');

		if (!Webhook::verify($payload, $signature, (string)$this->config->get('parcora_webhook_secret'))) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');
			$this->response->setOutput('Invalid signature');

			return;
		}

		$event = json_decode($payload, true);
		$shipment = $event['data']['object'] ?? [];

		$store = new ShipmentStore($this->db, DB_PREFIX);

		$row = isset($shipment['id']) ? $store->findByShipmentId((string)$shipment['id']) : [];

		if ($row) {
			$store->saveStatus((int)$row['order_id'], (string)($shipment['status'] ?? ''), (string)($shipment['tracking_number'] ?? ''));

			if ($event['type'] == 'shipment.delivered' && $this->config->get('shipping_parcora_delivered_status_id')) {
				$this->load->language('extension/parcora/shipping/parcora');

				$this->load->model('checkout/order');

				$this->model_checkout_order->addHistory((int)$row['order_id'], (int)$this->config->get('shipping_parcora_delivered_status_id'), $this->language->get('text_delivered'));
			}
		}

		$this->response->setOutput('OK');
	}

	/**
	 * The Parcora quote for a shipping method code from the current checkout.
	 *
	 * @return array<string, mixed>
	 */
	private function getQuote(string $code): array {
		if (strpos($code, 'parcora.') !== 0 || empty($this->session->data['shipping_address']['iso_code_2'])) {
			return [];
		}

		return $this->session->data['shipping_methods']['parcora']['quote'][substr($code, strlen('parcora.'))] ?? [];
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
