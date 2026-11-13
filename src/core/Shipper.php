<?php

namespace Parcora\OpenCart;

/**
 * Registers an order's shipment with Parcora and records the outcome, so the
 * admin order page shows either the shipment or why it failed.
 */
class Shipper
{
    /** @var Client */
    private $client;

    /** @var ShipmentStore */
    private $store;

    public function __construct(Client $client, ShipmentStore $store)
    {
        $this->client = $client;
        $this->store = $store;
    }

    /** Whether the order was placed with a Parcora shipping method. */
    public static function handles(array $order): bool
    {
        return !empty($order['shipping_method']['parcora_service']);
    }

    /**
     * @param array $sender {name, phone, email, line1, city, postcode, country}
     *
     * @throws ApiException
     */
    public function create(array $order, int $weightGrams, array $sender, string $labelFormat = ''): array
    {
        $orderId = (int) $order['order_id'];
        $payload = ShipmentRequest::build($order, $weightGrams, $sender, $labelFormat);

        // The same payload never registers twice; a corrected one gets a new key.
        $key = 'opencart-' . $orderId . '-' . md5(json_encode($payload));

        try {
            $shipment = $this->client->createShipment($payload, $key);
        } catch (ApiException $e) {
            $this->store->saveError($orderId, $e->getMessage());

            throw $e;
        }

        $shipment['service'] = $payload['service'];

        $this->store->saveShipment($orderId, $shipment);

        return $this->store->get($orderId);
    }
}
