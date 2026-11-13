<?php

namespace Parcora\OpenCart;

/**
 * Builds the POST /v1/shipments payload from an OpenCart order. Both
 * OpenCart 3 and 4 return the same order keys from `getOrder()`.
 */
class ShipmentRequest
{
    /**
     * @param array $order       an OpenCart order row (getOrder), with the
     *                           decoded `shipping_method` array of this module
     * @param int   $weightGrams total parcel weight
     * @param array $sender      {name, phone, email, line1, city, postcode, country}
     */
    public static function build(array $order, int $weightGrams, array $sender, string $labelFormat = ''): array
    {
        $method = $order['shipping_method'];

        $receiver = [
            'name' => trim($order['shipping_firstname'] . ' ' . $order['shipping_lastname']),
            'phone' => $order['telephone'],
            'email' => $order['email'],
        ];

        if (!empty($method['parcora_pickup_point']['id'])) {
            $receiver['pickup_point_id'] = $method['parcora_pickup_point']['id'];
        } else {
            $receiver += [
                'line1' => $order['shipping_address_1'],
                'line2' => $order['shipping_address_2'],
                'city' => $order['shipping_city'],
                'region' => $order['shipping_zone'],
                'postcode' => $order['shipping_postcode'],
                'country' => $order['shipping_iso_code_2'],
            ];
        }

        $payload = [
            'carrier' => $method['parcora_carrier'],
            'service' => $method['parcora_service'],
            'sender' => self::filled($sender),
            'receiver' => self::filled($receiver),
            'parcels' => [[
                'weight_grams' => max(1, $weightGrams),
                'reference' => 'Order #' . $order['order_id'],
            ]],
            'metadata' => ['opencart_order_id=' . $order['order_id']],
        ];

        if ($labelFormat !== '') {
            $payload['options'] = ['label_format' => $labelFormat];
        }

        return $payload;
    }

    /** The API rejects empty strings, so blank optional fields are left out. */
    private static function filled(array $fields): array
    {
        return array_filter(array_map('trim', array_map('strval', $fields)), 'strlen');
    }
}
