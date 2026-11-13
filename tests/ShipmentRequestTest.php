<?php

use Parcora\OpenCart\ShipmentRequest;
use PHPUnit\Framework\TestCase;

class ShipmentRequestTest extends TestCase
{
    private const SENDER = [
        'name' => 'Demo Shop SIA', 'phone' => '', 'email' => 'shop@example.test',
        'line1' => 'Brivibas iela 1', 'city' => 'Riga', 'postcode' => 'LV-1010', 'country' => 'LV',
    ];

    public function testShipsToThePickupPointInsteadOfTheAddress(): void
    {
        $payload = ShipmentRequest::build($this->order([
            'parcora_service' => 'dpd.locker',
            'parcora_carrier' => 'dpd_baltic',
            'parcora_pickup_point' => ['id' => 'pp_123', 'name' => 'DPD Riga locker 1'],
        ]), 1200, self::SENDER);

        $this->assertSame('dpd_baltic', $payload['carrier']);
        $this->assertSame('dpd.locker', $payload['service']);
        $this->assertSame(['name' => 'Anna Berzina', 'phone' => '+37121111111', 'email' => 'anna@example.test', 'pickup_point_id' => 'pp_123'], $payload['receiver']);
        $this->assertSame([['weight_grams' => 1200, 'reference' => 'Order #7']], $payload['parcels']);
        $this->assertArrayNotHasKey('options', $payload);
    }

    public function testShipsToTheAddressWithoutBlankFields(): void
    {
        $payload = ShipmentRequest::build($this->order([
            'parcora_service' => 'omniva.courier',
            'parcora_carrier' => 'omniva',
        ]), 0, self::SENDER, 'pdf_label');

        $this->assertSame([
            'name' => 'Anna Berzina', 'phone' => '+37121111111', 'email' => 'anna@example.test',
            'line1' => 'Elizabetes iela 10', 'city' => 'Riga', 'region' => 'Rīga', 'postcode' => 'LV-1011', 'country' => 'LV',
        ], $payload['receiver']);

        // The API rejects empty strings, so the blank sender phone is dropped
        $this->assertArrayNotHasKey('phone', $payload['sender']);
        // A weightless order still sends a valid parcel
        $this->assertSame(1, $payload['parcels'][0]['weight_grams']);
        $this->assertSame(['label_format' => 'pdf_label'], $payload['options']);
    }

    private function order(array $method): array
    {
        return [
            'order_id' => 7,
            'telephone' => '+37121111111',
            'email' => 'anna@example.test',
            'shipping_firstname' => 'Anna',
            'shipping_lastname' => 'Berzina',
            'shipping_address_1' => 'Elizabetes iela 10',
            'shipping_address_2' => '',
            'shipping_city' => 'Riga',
            'shipping_zone' => 'Rīga',
            'shipping_postcode' => 'LV-1011',
            'shipping_iso_code_2' => 'LV',
            'shipping_method' => $method,
        ];
    }
}
