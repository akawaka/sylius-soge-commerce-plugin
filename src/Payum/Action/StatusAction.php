<?php

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
        } else {
            $request->markFailed();

            try {
                $this->gateway->cancelPayment($payment);
            } catch (SogeCommerceApiException $e) {
                // This event should be listened to send an email or re-try to cancel the payment
                $this->eventDispatcher->dispatch(new PaymentCancelationFailedEvent($e, $payment));

                // Let's make sure the payment amount is true to what the user actually paid
                $payment->setAmount($this->getRealPaidAmount($payment));
                $payment->setDetails(array_merge([
                    SogeCommerceGatewayInterface::PAYMENT_DETAILS_STATUS_KEY => 'CANCEL_FAILED',
                ], $payment->getDetails()));
            }
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

    private function isAmountValid(PaymentInterface $payment): bool
    {
        $orderAmount = $payment->getOrder()?->getTotal();
        if (null === $orderAmount) {
            return false;
        }

        return $this->getRealPaidAmount($payment) === $orderAmount;
    }

    private function getRealPaidAmount(PaymentInterface $payment): int
    {
        $amount = $payment->getDetails()[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY]['orderDetails']['orderTotalAmount'] ?? null;
        Assert::integer($amount);

        return $amount;
    }
}
