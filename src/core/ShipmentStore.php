<?php

namespace Parcora\OpenCart;

/**
 * The module's own table linking an OpenCart order to its Parcora shipment.
 * Takes OpenCart's DB object, which has the same API in OpenCart 3 and 4.
 */
class ShipmentStore
{
    /** @var object */
    private $db;

    /** @var string */
    private $table;

    public function __construct($db, string $prefix)
    {
        $this->db = $db;
        $this->table = '`' . $prefix . 'parcora_shipment`';
    }

    public function install(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS " . $this->table . " (
            `order_id` INT(11) NOT NULL,
            `shipment_id` VARCHAR(64) NOT NULL DEFAULT '',
            `carrier` VARCHAR(32) NOT NULL DEFAULT '',
            `service` VARCHAR(100) NOT NULL DEFAULT '',
            `tracking_number` VARCHAR(100) NOT NULL DEFAULT '',
            `tracking_url` VARCHAR(255) NOT NULL DEFAULT '',
            `status` VARCHAR(32) NOT NULL DEFAULT '',
            `error` TEXT NOT NULL,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`order_id`),
            KEY `shipment_id` (`shipment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function get(int $orderId): array
    {
        return $this->db->query("SELECT * FROM " . $this->table . " WHERE `order_id` = '" . $orderId . "'")->row;
    }

    public function findByShipmentId(string $shipmentId): array
    {
        return $this->db->query("SELECT * FROM " . $this->table . " WHERE `shipment_id` = '" . $this->db->escape($shipmentId) . "'")->row;
    }

    /** Stores a created shipment, clearing any earlier failure. */
    public function saveShipment(int $orderId, array $shipment): void
    {
        $this->upsert($orderId, [
            'shipment_id' => $shipment['id'],
            'carrier' => $shipment['carrier'] ?? '',
            'service' => $shipment['service'] ?? '',
            'tracking_number' => $shipment['tracking_number'] ?? '',
            'tracking_url' => $shipment['tracking_url'] ?? '',
            'status' => $shipment['status'] ?? '',
            'error' => '',
        ]);
    }

    public function saveError(int $orderId, string $error): void
    {
        $this->upsert($orderId, ['error' => $error]);
    }

    public function saveStatus(int $orderId, string $status, string $trackingNumber = ''): void
    {
        $fields = ['status' => $status];

        if ($trackingNumber !== '') {
            $fields['tracking_number'] = $trackingNumber;
        }

        $this->upsert($orderId, $fields);
    }

    private function upsert(int $orderId, array $fields): void
    {
        // `error` is TEXT NOT NULL with no default, so a new row needs a value.
        $fields += ['error' => ''];

        $set = [];

        foreach ($fields as $column => $value) {
            $set[] = "`" . $column . "` = '" . $this->db->escape((string) $value) . "'";
        }

        $set = implode(', ', $set);

        $this->db->query("INSERT INTO " . $this->table . " SET `order_id` = '" . $orderId . "', " . $set . ", `date_added` = NOW(), `date_modified` = NOW()
            ON DUPLICATE KEY UPDATE " . $set . ", `date_modified` = NOW()");
    }
}
