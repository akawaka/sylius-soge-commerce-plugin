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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Payum\Action;

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Akawaka\SyliusSogeCommercePlugin\Event\PaymentCancelationFailedEvent;
use Akawaka\SyliusSogeCommercePlugin\Exception\FailedToCancelPaymentException;
use Akawaka\SyliusSogeCommercePlugin\Payum\Action\StatusAction;
use Payum\Core\Request\GetHumanStatus;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class StatusActionTest extends TestCase
{
    public function testItMarksTheRequestCapturedWhenThePaidAmountMatchesTheOrderTotal(): void
    {
        $action = new StatusAction(
            $gateway = self::createMock(SogeCommerceGatewayInterface::class),
            self::createMock(EventDispatcherInterface::class),
        );
        $gateway->expects(self::never())->method('cancelPayment');

        $request = new GetHumanStatus($this->createPayment(1000, ['orderDetails' => ['orderTotalAmount' => 1000]]));

        $action->execute($request);

        self::assertTrue($request->isCaptured());
    }

    public function testItMarksTheRequestFailedAndCancelsWhenThePaidAmountDiffersFromTheOrderTotal(): void
    {
        $action = new StatusAction(
            $gateway = self::createMock(SogeCommerceGatewayInterface::class),
            self::createMock(EventDispatcherInterface::class),
        );

        $payment = $this->createPayment(2000, ['orderDetails' => ['orderTotalAmount' => 1000]]);
        $gateway->expects(self::once())->method('cancelPayment')->with($payment);

        $request = new GetHumanStatus($payment);
        $action->execute($request);

        self::assertTrue($request->isFailed());
    }

    public function testItFailsCleanlyWithoutCrashingWhenThePaidAmountCannotBeRead(): void
    {
        // Regression: when the cart is changed mid-payment Sylius regenerates the payment, so the
        // new payment no longer carries the SogeCommerce payload. Reading the paid amount used to
        // throw "Expected an integer. Got: NULL" and return a 500; it must now fail cleanly.
        $action = new StatusAction(
            $gateway = self::createMock(SogeCommerceGatewayInterface::class),
            self::createMock(EventDispatcherInterface::class),
        );

        $payment = $this->createPaymentWithDetails(2000, []);
        $gateway->expects(self::once())->method('cancelPayment')->with($payment);

        $request = new GetHumanStatus($payment);
        $action->execute($request);

        self::assertTrue($request->isFailed());
    }

    public function testItLeavesTheAmountUntouchedWhenCancelFailsAndThePaidAmountCannotBeRead(): void
    {
        $action = new StatusAction(
            $gateway = self::createMock(SogeCommerceGatewayInterface::class),
            $eventDispatcher = self::createMock(EventDispatcherInterface::class),
        );

        $order = self::createMock(OrderInterface::class);
        $order->method('getTotal')->willReturn(2000);
        $payment = self::createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $gateway->method('cancelPayment')->willThrowException(new FailedToCancelPaymentException());
        $payment->expects(self::never())->method('setAmount');
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'CANCEL_FAILED' === ($details[SogeCommerceGatewayInterface::PAYMENT_DETAILS_STATUS_KEY] ?? null),
        ));
        $eventDispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(PaymentCancelationFailedEvent::class));

        $request = new GetHumanStatus($payment);
        $action->execute($request);

        self::assertTrue($request->isFailed());
    }

    /**
     * @param array<string, mixed> $sogeRequestData
     */
    private function createPayment(int $orderTotal, array $sogeRequestData): PaymentInterface
    {
        return $this->createPaymentWithDetails($orderTotal, [
            SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY => $sogeRequestData,
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createPaymentWithDetails(int $orderTotal, array $details): PaymentInterface
    {
        $order = self::createMock(OrderInterface::class);
        $order->method('getTotal')->willReturn($orderTotal);

        $payment = self::createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }
}
