<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Client;

use Akawaka\SyliusSogeCommercePlugin\Client\OrderIdTransformer;
use PHPUnit\Framework\TestCase;

final class OrderIdTransformerTest extends TestCase
{
    /**
     * @dataProvider provideTransform
     */
    public function testTransform(string $orderId, string $paymentId, string $expectedResult): void
    {
        self::assertEquals($expectedResult, (new OrderIdTransformer())->transform($orderId, $paymentId));
    }

    public function provideTransform(): iterable
    {
        yield ['1', '1', 'order-1-payment-1'];
        yield ['5', '1', 'order-5-payment-1'];
        yield ['11', '12', 'order-11-payment-12'];
    }

    /**
     * @dataProvider provideRetrieve
     */
    public function testRetrieve(string $value, string $expectedResult): void
    {
        self::assertEquals($expectedResult, (new OrderIdTransformer())->retrieve($value));
    }

    public function provideRetrieve(): iterable
    {
        yield ['order-1-payment-1', '1'];
        yield ['order-123-payment-1', '123'];
    }
}
