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

namespace Akawaka\SyliusSogeCommercePlugin\Client;

interface OrderIdTransformerInterface
{
    public function transform(string $orderId, string $paymentId): string;

    public function retrieve(string $value): string;

    public function retrievePayment(string $value): string;
}
