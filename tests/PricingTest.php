<?php

use Parcora\OpenCart\Pricing;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function testAddsPercentAndFixedMarkupToTheRate(): void
    {
        $cost = Pricing::cost(10.0, ['mode' => 'api', 'markup_percent' => '10', 'markup_amount' => '1.50'], 50.0);

        $this->assertSame(12.5, $cost);
    }

    public function testFixedModeIgnoresTheRate(): void
    {
        $this->assertSame(2.99, Pricing::cost(10.0, ['mode' => 'fixed', 'fixed' => '2.99', 'markup_percent' => '50'], 50.0));
    }

    public function testIsFreeFromTheThresholdInEitherMode(): void
    {
        $this->assertSame(0.0, Pricing::cost(10.0, ['mode' => 'api', 'free_from' => '100'], 100.0));
        $this->assertSame(0.0, Pricing::cost(10.0, ['mode' => 'fixed', 'fixed' => '5', 'free_from' => '100'], 120.0));
        $this->assertSame(10.0, Pricing::cost(10.0, ['mode' => 'api', 'free_from' => '100'], 99.99));
    }

    public function testZeroThresholdNeverMakesItFree(): void
    {
        $this->assertSame(10.0, Pricing::cost(10.0, ['mode' => 'api', 'free_from' => '0'], 1000.0));
    }

    public function testANegativeMarkupNeverGoesBelowZero(): void
    {
        $this->assertSame(0.0, Pricing::cost(3.0, ['mode' => 'api', 'markup_amount' => '-5'], 10.0));
    }

    public function testMissingSettingsMeanThePlainRate(): void
    {
        $this->assertSame(4.49, Pricing::cost(4.49, [], 10.0));
    }
}
