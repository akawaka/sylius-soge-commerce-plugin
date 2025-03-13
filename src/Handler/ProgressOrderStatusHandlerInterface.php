<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Sylius\Component\Core\Model\OrderInterface;

interface ProgressOrderStatusHandlerInterface
{
    public function __invoke(OrderInterface $order, array $requestData): void;
}
