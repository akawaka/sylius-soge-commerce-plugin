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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Behat\Service\Mocker;

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

final class SogeCommerceGatewayMock implements SogeCommerceGatewayInterface
{
    public function __construct(
        private SogeCommerceGatewayInterface $baseGateway,
    ) {
    }

    public function createFormToken(PaymentMethodInterface $method, OrderInterface $order): string
    {
        return 'token';
    }

    public function cancelPayment(PaymentInterface $payment): void
    {
        throw new \LogicException('This method is not implemented.');
    }

    public function isPaymentSuccess(array $requestData): bool
    {
        return $this->baseGateway->isPaymentSuccess($requestData);
    }
}
