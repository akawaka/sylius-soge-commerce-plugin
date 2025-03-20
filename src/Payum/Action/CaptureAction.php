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

use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;

final class CaptureAction implements ActionInterface
{
    public function execute(mixed $request): void
    {
        // While this action does nothing because the capture is done on the
        // payment method selection page, it is still needed so Payum does not
        // throw an exception.
    }

    public function supports(mixed $request): bool
    {
        return
            $request instanceof Capture &&
            $request->getFirstModel() instanceof PaymentInterface
        ;
    }
}
