<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface UpdateOrderPaymentMethodHandlerInterface
{
    public function __invoke(PaymentMethodInterface $method, OrderInterface $order): void;
}
