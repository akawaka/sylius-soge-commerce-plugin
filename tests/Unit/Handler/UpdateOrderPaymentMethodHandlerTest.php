<?php

/*
 * This file is part of akawaka/sylius-soge-commerce-plugin
 *
 * AKAWAKA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Handler;

use Akawaka\SyliusSogeCommercePlugin\Handler\UpdateOrderPaymentMethodHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;

final class UpdateOrderPaymentMethodHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $method = new PaymentMethod();

        (new UpdateOrderPaymentMethodHandler())->__invoke($method, $order);

        self::assertEquals($method, $payment->getMethod());
    }
}
