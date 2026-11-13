<?php

use Parcora\OpenCart\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const BODY = '{"type":"shipment.delivered"}';

    public function testAcceptsAFreshSignatureFromTheSecret(): void
    {
        $this->assertTrue(Webhook::verify(self::BODY, $this->header(time()), self::SECRET));
    }

    public function testRejectsATamperedBody(): void
    {
        $this->assertFalse(Webhook::verify('{"type":"shipment.cancelled"}', $this->header(time()), self::SECRET));
    }

    public function testRejectsAnotherSecret(): void
    {
        $this->assertFalse(Webhook::verify(self::BODY, $this->header(time()), 'whsec_other'));
    }

    public function testRejectsAStaleTimestamp(): void
    {
        $this->assertFalse(Webhook::verify(self::BODY, $this->header(time() - 301), self::SECRET));
    }

    public function testRejectsAMalformedHeaderAndAnEmptySecret(): void
    {
        $this->assertFalse(Webhook::verify(self::BODY, 'v1=abc', self::SECRET));
        $this->assertFalse(Webhook::verify(self::BODY, $this->header(time()), ''));
    }

    private function header(int $timestamp): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . self::BODY, self::SECRET);
    }
}
