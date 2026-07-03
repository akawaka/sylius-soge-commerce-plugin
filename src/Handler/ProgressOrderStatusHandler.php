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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Webmozart\Assert\Assert;

final class ProgressOrderStatusHandler implements ProgressOrderStatusHandlerInterface
{
    private const GRAPH = 'sylius_order_checkout';

    public function __construct(
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function __invoke(OrderInterface $order, array $requestData): void
    {
        if ($this->stateMachine->can($order, self::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)) {
            $this->stateMachine->apply($order, self::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        if ($this->stateMachine->can($order, self::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($order, self::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        }

        $payment = $order->getLastPayment();
        Assert::notNull($payment);
        $payment->setDetails([
            SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY => $requestData,
        ]);
    }
}
