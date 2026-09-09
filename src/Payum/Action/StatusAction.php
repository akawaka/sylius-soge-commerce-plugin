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

namespace Akawaka\SyliusSogeCommercePlugin\Payum\Action;

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Akawaka\SyliusSogeCommercePlugin\Event\PaymentCancelationFailedEvent;
use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Generic;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;

final class StatusAction implements ActionInterface
{
    public function __construct(
        private SogeCommerceGatewayInterface $gateway,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Since the user paid the order before it has been completed (because the user can pay
     * directly on the payment selection page), a check is needed to compare the order total
     * against the actual amount paid.
     */
    public function execute(mixed $request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);
        Assert::isInstanceOf($request, GetStatusInterface::class);
        Assert::isInstanceOf($request, Generic::class);

        $payment = $request->getFirstModel();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        if ($this->isAmountValid($payment)) {
            $request->markCaptured();

            return;
        }

        $request->markFailed();

        // A payment that never went through Soge Commerce (e.g. the payment selection form was
        // submitted although the smart form could not be displayed) carries no transaction to
        // cancel: asking the API to cancel it would only crash the return page.
        if (!$this->hasSogeCommercePayload($payment)) {
            return;
        }

        try {
            $this->gateway->cancelPayment($payment);
        } catch (SogeCommerceApiException $e) {
            // This event should be listened to send an email or re-try to cancel the payment
            $this->eventDispatcher->dispatch(new PaymentCancelationFailedEvent($e, $payment));

            // Let's make sure the payment amount is true to what the user actually paid
            $realPaidAmount = $this->getRealPaidAmount($payment);
            if (null !== $realPaidAmount) {
                $payment->setAmount($realPaidAmount);
            }
            $payment->setDetails(array_merge([
                SogeCommerceGatewayInterface::PAYMENT_DETAILS_STATUS_KEY => 'CANCEL_FAILED',
            ], $payment->getDetails()));
        }
    }

    public function supports(mixed $request): bool
    {
        Assert::isInstanceOf($request, Generic::class);

        return
            $request instanceof GetStatusInterface &&
            $request->getFirstModel() instanceof PaymentInterface
        ;
    }

    private function hasSogeCommercePayload(PaymentInterface $payment): bool
    {
        return is_array($payment->getDetails()[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY] ?? null);
    }

    private function isAmountValid(PaymentInterface $payment): bool
    {
        $orderAmount = $payment->getOrder()?->getTotal();
        if (null === $orderAmount) {
            return false;
        }

        return $this->getRealPaidAmount($payment) === $orderAmount;
    }

    /**
     * Returns the amount actually paid at SogeCommerce, or null when it cannot be read from the
     * payment details (e.g. the payment was regenerated after the cart changed mid-payment, so it
     * no longer carries the SogeCommerce payload). Callers must treat null as an invalid amount
     * rather than crashing.
     */
    private function getRealPaidAmount(PaymentInterface $payment): ?int
    {
        $amount = $payment->getDetails()[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY]['orderDetails']['orderTotalAmount'] ?? null;

        return is_int($amount) ? $amount : null;
    }
}
