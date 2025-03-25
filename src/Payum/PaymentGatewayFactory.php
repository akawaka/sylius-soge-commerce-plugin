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

namespace Akawaka\SyliusSogeCommercePlugin\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

final class PaymentGatewayFactory extends GatewayFactory
{
    public const FACTORY_NAME = 'akawaka_soge_commerce';

    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => self::FACTORY_NAME,
            'payum.factory_title' => 'Soge Commerce',
        ]);
    }
}
