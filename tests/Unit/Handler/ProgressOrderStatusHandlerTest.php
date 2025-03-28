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
use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;

final class ProgressOrderStatusHandlerTest extends TestCase
{
    public function testInvokeWhenStateNotCompleted(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachineFactory = self::createMock(FactoryInterface::class);
        $stateMachineFactory->expects(self::once())
            ->method('get')
            ->with($order, 'sylius_order_checkout')
            ->willReturn($stateMachine = self::createMock(StateMachineInterface::class))
        ;

        $stateMachine->expects(self::exactly(2))
            ->method('getState')
            ->willReturn('shipping_selected')
            ->willReturnOnConsecutiveCalls('shipping_selected', 'payment_selected');

        $stateMachine->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(fn (string $transition) => match (true) {
                'select_payment' == $transition => true,
                'complete' == $transition => true,
                default => throw new \LogicException(),
            })
        ;

        (new ProgressOrderStatusHandler($stateMachineFactory))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }

    public function testInvokeWhenStateNotCompletedAndSkip(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachineFactory = self::createMock(FactoryInterface::class);
        $stateMachineFactory->expects(self::once())
            ->method('get')
            ->with($order, 'sylius_order_checkout')
            ->willReturn($stateMachine = self::createMock(StateMachineInterface::class))
        ;

        $stateMachine->expects(self::exactly(2))
            ->method('getState')
            ->willReturn('shipping_selected')
            ->willReturnOnConsecutiveCalls('shipping_selected', 'completed');

        $stateMachine->expects(self::exactly(1))
            ->method('apply')
            ->willReturnCallback(fn (string $transition) => match (true) {
                'select_payment' == $transition => true,
                default => throw new \LogicException(),
            })
        ;

        (new ProgressOrderStatusHandler($stateMachineFactory))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }

    public function testInvokeWhenStateCompleted(): void
    {
        $order = new Order();
        $order->addPayment($payment = new Payment());

        $stateMachineFactory = self::createMock(FactoryInterface::class);
        $stateMachineFactory->expects(self::once())
            ->method('get')
            ->with($order, 'sylius_order_checkout')
            ->willReturn($stateMachine = self::createMock(StateMachineInterface::class))
        ;

        $stateMachine->expects(self::exactly(2))
            ->method('getState')
            ->willReturn('completed')
        ;

        $stateMachine->expects(self::exactly(0))
            ->method('apply')
        ;

        (new ProgressOrderStatusHandler($stateMachineFactory))->__invoke($order, ['foo' => 'some data']);

        self::assertEquals(['sogeCommerceRequestData' => ['foo' => 'some data']], $payment->getDetails());
    }
}
