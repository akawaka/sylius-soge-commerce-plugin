<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class UpdateOrderPaymentMethodHandler implements UpdateOrderPaymentMethodHandlerInterface
{
    public function __invoke(PaymentMethodInterface $method, OrderInterface $order): void
    {
        $payment = $order->getPayments()->last();
        Assert::isInstanceOf($payment, PaymentInterface::class);
        $payment->setMethod($method);
    }
}
