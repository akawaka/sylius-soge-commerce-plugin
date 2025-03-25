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
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
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

        if (OrderCheckoutStates::STATE_COMPLETED !== $stateMachine->getState()) {
            $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        if (OrderCheckoutStates::STATE_COMPLETED !== $stateMachine->getState()) {
            $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);
        }

        $payment = $order->getLastPayment();
        Assert::notNull($payment);
        $payment->setDetails([
            SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY => $requestData,
        ]);
    }
}
