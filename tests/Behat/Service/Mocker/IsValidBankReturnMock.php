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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Behat\Service\Mocker;

use Akawaka\SyliusSogeCommercePlugin\Client\IsValidRequestInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;

final class IsValidBankReturnMock implements IsValidRequestInterface
{
    public function __invoke(PaymentMethodInterface $method, Request $request): bool
    {
        return true;
    }
}
