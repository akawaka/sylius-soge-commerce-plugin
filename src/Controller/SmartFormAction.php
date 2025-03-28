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

namespace Akawaka\SyliusSogeCommercePlugin\Controller;

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class SmartFormAction extends AbstractController
{
    public function __construct(
        private SogeCommerceGatewayInterface $gateway,
    ) {
    }

    public function __invoke(
        PaymentMethodInterface $method,
        OrderInterface $order,
    ): Response {
        return $this->render('@AkawakaSyliusSogeCommercePlugin/Shop/Checkout/_smartForm.html.twig', [
            'formToken' => $this->gateway->createFormToken(
                $method,
                $order,
            ),
        ]);
    }
}
