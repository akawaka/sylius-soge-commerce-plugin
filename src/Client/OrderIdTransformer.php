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

final class OrderIdTransformer implements OrderIdTransformerInterface
{
    public function transform(string $orderId, string $paymentId): string
    {
        return sprintf('order-%s-payment-%s', $orderId, $paymentId);
    }

    public function retrieve(string $value): string
    {
        if (false === preg_match('/^order-(?<id>.+)-payment-.*$/', $value, $matches)) {
            throw new \RuntimeException('Provided value is not a valid order id.');
        }

        return $matches['id'];
    }

    public function retrievePayment(string $value): string
    {
        if (false === preg_match('/^order-.*-payment-(?<id>.+)$/', $value, $matches)) {
            throw new \RuntimeException('Provided value is not a valid order id.');
        }

        return $matches['id'];
    }
}
