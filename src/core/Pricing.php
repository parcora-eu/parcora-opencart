<?php

namespace Parcora\OpenCart;

/**
 * Turns a Parcora rate into the price the buyer sees, per the merchant's
 * per-service settings: the API rate with a markup, or a fixed price, and
 * free above an order subtotal either way.
 */
class Pricing
{
    const MODE_API = 'api';

    const MODE_FIXED = 'fixed';

    /**
     * @param float $rate     the Parcora rate, already in the store currency
     * @param array $service  {mode, markup_percent, markup_amount, fixed, free_from}
     * @param float $subtotal the cart subtotal in the store currency
     */
    public static function cost(float $rate, array $service, float $subtotal): float
    {
        $freeFrom = (float) ($service['free_from'] ?? 0);

        if ($freeFrom > 0 && $subtotal >= $freeFrom) {
            return 0.0;
        }

        if (($service['mode'] ?? self::MODE_API) === self::MODE_FIXED) {
            return round((float) ($service['fixed'] ?? 0), 2);
        }

        $percent = (float) ($service['markup_percent'] ?? 0);
        $amount = (float) ($service['markup_amount'] ?? 0);

        return max(0.0, round($rate * (1 + $percent / 100) + $amount, 2));
    }
}
