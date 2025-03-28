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

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class UpdateOrderPaymentMethodHandler implements UpdateOrderPaymentMethodHandlerInterface
{
    public function __invoke(PaymentMethodInterface $method, OrderInterface $order): void
    {
        $payment = $order->getLastPayment();
        Assert::notNull($payment);
        $payment->setMethod($method);
    }
}
