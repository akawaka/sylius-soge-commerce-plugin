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

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class ProgressOrderStatusHandler implements ProgressOrderStatusHandlerInterface
{
    public function __construct(
        private FactoryInterface $stateMachineFactory,
    ) {
    }

    public function __invoke(OrderInterface $order, array $requestData): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, 'sylius_order_checkout');

        if ('completed' !== $stateMachine->getState()) {
            $stateMachine->apply('select_payment');
            $stateMachine->apply('complete');
        }

        $payment = $order->getPayments()->last();
        Assert::isInstanceOf($payment, PaymentInterface::class);
        $payment->setDetails([
            SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY => $requestData,
        ]);
    }
}
