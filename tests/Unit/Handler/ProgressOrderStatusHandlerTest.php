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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Handler;

use Akawaka\SyliusSogeCommercePlugin\Handler\ProgressOrderStatusHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;

final class ProgressOrderStatusHandlerTest extends TestCase
{
    public function testInvokeWhenStateNotCompleted(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachine = self::createMock(StateMachineInterface::class);

        $stateMachine->expects(self::exactly(2))
            ->method('can')
            ->willReturnCallback(fn (object $subject, string $graph, string $transition) => match ($transition) {
                'select_payment' => true,
                'complete' => true,
                default => throw new \LogicException(),
            });

        $stateMachine->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(fn (object $subject, string $graph, string $transition) => match (true) {
                'select_payment' === $transition => null,
                'complete' === $transition => null,
                default => throw new \LogicException(),
            });

        (new ProgressOrderStatusHandler($stateMachine))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }

    public function testInvokeWhenStateNotCompletedAndSkip(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachine = self::createMock(StateMachineInterface::class);

        $stateMachine->expects(self::exactly(2))
            ->method('can')
            ->willReturnCallback(fn (object $subject, string $graph, string $transition) => match ($transition) {
                'select_payment' => true,
                'complete' => false,
                default => throw new \LogicException(),
            });

        $stateMachine->expects(self::exactly(1))
            ->method('apply')
            ->with($order, 'sylius_order_checkout', 'select_payment');

        (new ProgressOrderStatusHandler($stateMachine))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }

    public function testInvokeWhenStateCompleted(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachine = self::createMock(StateMachineInterface::class);

        $stateMachine->expects(self::exactly(2))
            ->method('can')
            ->willReturn(false);

        $stateMachine->expects(self::never())
            ->method('apply');

        (new ProgressOrderStatusHandler($stateMachine))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }
}
