<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Sylius\Component\Core\Model\PaymentInterface;

interface CapturePaymentHandlerInterface
{
    public function __invoke(PaymentInterface $payment): void;
}
