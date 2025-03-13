<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Client;

interface OrderIdTransformerInterface
{
    public function transform(string $orderId, string $paymentId): string;

    public function retrieve(string $value): string;
}
