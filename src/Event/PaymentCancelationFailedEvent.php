<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Event;

use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiException;
use Sylius\Component\Payment\Model\PaymentInterface;

class PaymentCancelationFailedEvent
{
    public function __construct(
        public SogeCommerceApiException $exception,
        public PaymentInterface $payment,
    ) {
    }
}
