<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

final class PaymentGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'akawaka_soge_commerce',
            'payum.factory_title' => 'Soge Commerce',
        ]);
    }
}
