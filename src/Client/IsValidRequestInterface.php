<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Client;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;

interface IsValidRequestInterface
{
    public function __invoke(PaymentMethodInterface $method, Request $request): bool;
}
