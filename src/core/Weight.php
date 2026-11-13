<?php

namespace Parcora\OpenCart;

/**
 * Parcel weights in grams, from OpenCart's own weight classes. Takes the DB
 * and the `weight` library, which match in OpenCart 3 and 4.
 */
class Weight
{
    /** @var object */
    private $db;

    /** @var string */
    private $prefix;

    /** @var object */
    private $weight;

    public function __construct($db, string $prefix, $weight)
    {
        $this->db = $db;
        $this->prefix = $prefix;
        $this->weight = $weight;
    }

    /** Converts a weight in the given OpenCart weight class to whole grams. */
    public function grams(float $value, int $weightClassId): int
    {
        return (int) round($this->weight->convert($value, $weightClassId, $this->gramClassId()));
    }

    /** Total shippable weight of an order's products, in grams. */
    public function orderGrams(int $orderId): int
    {
        $rows = $this->db->query("SELECT `op`.`quantity`, `p`.`weight`, `p`.`weight_class_id`, `p`.`shipping` FROM `" . $this->prefix . "order_product` `op`
            LEFT JOIN `" . $this->prefix . "product` `p` ON (`p`.`product_id` = `op`.`product_id`)
            WHERE `op`.`order_id` = '" . $orderId . "'")->rows;

        $grams = 0;

        foreach ($rows as $row) {
            if ($row['weight_class_id'] !== null && $row['shipping']) {
                $grams += $this->grams((float) $row['weight'] * (int) $row['quantity'], (int) $row['weight_class_id']);
            }
        }

        return $grams;
    }

    private function gramClassId(): int
    {
        // A kilogram is 1000 of this class, whatever the store named it.
        $kilogram = $this->db->query("SELECT `value` FROM `" . $this->prefix . "weight_class` `wc`
            LEFT JOIN `" . $this->prefix . "weight_class_description` `wcd` ON (`wc`.`weight_class_id` = `wcd`.`weight_class_id`)
            WHERE `wcd`.`unit` = 'kg' LIMIT 1")->row;

        $value = $kilogram ? (float) $kilogram['value'] * 1000 : 1000.0;

        $gram = $this->db->query("SELECT `weight_class_id` FROM `" . $this->prefix . "weight_class` WHERE ABS(`value` - " . $value . ") < 0.0001 LIMIT 1")->row;

        if (!$gram) {
            throw new \RuntimeException('Add a gram weight class (1000 per kilogram) to use Parcora.');
        }

        return (int) $gram['weight_class_id'];
    }
}
