<?php
namespace Opencart\Catalog\Model\Extension\Parcora\Shipping;

require_once DIR_EXTENSION . 'parcora/system/library/parcora/autoload.php';

use Parcora\OpenCart\ApiException;
use Parcora\OpenCart\Client;
use Parcora\OpenCart\Pricing;
use Parcora\OpenCart\Weight;

/**
 * Class Parcora
 *
 * Can be called from $this->load->model('extension/parcora/shipping/parcora');
 *
 * @package Opencart\Catalog\Model\Extension\Parcora\Shipping
 */
class Parcora extends \Opencart\System\Engine\Model {
	/**
	 * Rates are cached in the session for this long, so reopening the shipping
	 * modal does not call the API again.
	 */
	private const CACHE_TTL = 600;

	/**
	 * Get Quote
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/parcora/shipping/parcora');

		// Geo Zone
		$this->load->model('localisation/geo_zone');

		$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('shipping_parcora_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

		if ($this->config->get('shipping_parcora_geo_zone_id') && !$results) {
			return [];
		}

		$services = array_filter((array)$this->config->get('shipping_parcora_services'), function ($service) {
			return !empty($service['enabled']);
		});

		if (!$services) {
			return [];
		}

		try {
			$rates = $this->getRates($address);
		} catch (ApiException $e) {
			$this->log->write('Parcora rates: ' . $e->getMessage());

			return [];
		}

		$tax_class_id = (int)$this->config->get('shipping_parcora_tax_class_id');
		$store_currency = (string)$this->config->get('config_currency');
		$subtotal = $this->cart->getSubTotal();

		$quote_data = [];

		foreach ($rates as $rate) {
			if (!isset($services[$rate['service']]) || !$this->currency->has($rate['currency'])) {
				continue;
			}

			$service = $services[$rate['service']];

			$amount = $this->currency->convert($rate['amount_minor'] / 100, $rate['currency'], $store_currency);
			$cost = Pricing::cost($amount, $service, $subtotal);
			// Service labels are generic ("Parcel locker"), so the carrier name leads
			$title = ($service['title'] ?? '') !== '' ? $service['title'] : trim(($service['carrier'] ?? '') . ' ' . $rate['service_label']);

			// OpenCart splits the code on '.', which Parcora service codes contain
			$key = str_replace('.', '_', $rate['service']);

			$quote_data[$key] = [
				'code'                => 'parcora.' . $key,
				'name'                => $title,
				'cost'                => $cost,
				'tax_class_id'        => $tax_class_id,
				'text'                => $this->currency->format($this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency']),
				'parcora_title'       => $title,
				'parcora_service'     => $rate['service'],
				'parcora_carrier'     => $rate['carrier'],
				'parcora_delivers_to' => $rate['delivers_to'] ?? 'address'
			];
		}

		if (!$quote_data) {
			return [];
		}

		uasort($quote_data, function ($a, $b) {
			return $a['cost'] <=> $b['cost'];
		});

		return [
			'code'       => 'parcora',
			'name'       => $this->language->get('heading_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_parcora_sort_order'),
			'error'      => false
		];
	}

	/**
	 * @param array<string, mixed> $address
	 *
	 * @throws ApiException
	 *
	 * @return list<array<string, mixed>>
	 */
	private function getRates(array $address): array {
		$this->load->model('localisation/country');

		$sender_country = $this->model_localisation_country->getCountry((int)$this->config->get('shipping_parcora_sender_country'));

		$grams = (new Weight($this->db, DB_PREFIX, $this->weight))->grams($this->cart->getWeight(), (int)$this->config->get('config_weight_class_id'));

		$payload = [
			'sender'   => [
				'city'     => (string)$this->config->get('shipping_parcora_sender_city'),
				'postcode' => (string)$this->config->get('shipping_parcora_sender_postcode'),
				'country'  => $sender_country['iso_code_2'] ?? ''
			],
			'receiver' => array_filter([
				'line1'    => (string)$address['address_1'],
				'city'     => (string)$address['city'],
				'postcode' => (string)$address['postcode'],
				'country'  => (string)$address['iso_code_2']
			], 'strlen'),
			'parcels'  => [['weight_grams' => $grams ?: max(1, (int)$this->config->get('shipping_parcora_default_weight'))]]
		];

		$key = md5(json_encode($payload));

		$cache = $this->session->data['parcora_rates'] ?? [];

		if (isset($cache['key']) && $cache['key'] == $key && $cache['time'] > time() - self::CACHE_TTL) {
			return $cache['rates'];
		}

		$client = new Client((string)$this->config->get('shipping_parcora_api_key'), (string)$this->config->get('shipping_parcora_base_url'));

		$rates = $client->rates($payload);

		$this->session->data['parcora_rates'] = ['key' => $key, 'time' => time(), 'rates' => $rates];

		return $rates;
	}
}
