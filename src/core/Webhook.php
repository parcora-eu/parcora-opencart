<?php

namespace Parcora\OpenCart;

/**
 * Verifies the `Webhook-Signature` header: `t=<unix>,v1=<hmac>`, where
 * v1 = HMAC-SHA256(secret, "{t}.{raw body}"). Same scheme as parcora-php.
 */
class Webhook
{
    const TOLERANCE = 300;

    public static function verify(string $payload, string $header, string $secret): bool
    {
        if ($secret === '' || !preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $matches)) {
            return false;
        }

        $expected = hash_hmac('sha256', $matches[1] . '.' . $payload, $secret);

        return hash_equals($expected, $matches[2]) && abs(time() - (int) $matches[1]) <= self::TOLERANCE;
    }
}
