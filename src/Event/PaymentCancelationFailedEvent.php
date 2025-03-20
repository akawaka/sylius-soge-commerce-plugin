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
