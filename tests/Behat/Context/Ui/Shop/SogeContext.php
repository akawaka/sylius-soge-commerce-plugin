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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use FriendsOfBehat\SymfonyExtension\Driver\SymfonyDriver;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Webmozart\Assert\Assert;

final class SogeContext extends MinkContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @When /^I confirm my order with soge commerce payment$/
     */
    public function iConfirmMyOrderWithSogeCommercePayment(): void
    {
        /** @var SymfonyDriver $driver */
        $driver = $this->getSession()->getDriver();

        $method = $this->sharedStorage->get('payment_method');
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $cart = $this->orderRepository->findLatestCart();
        Assert::isInstanceOf($cart, OrderInterface::class);

        $orderId = sprintf(
            'order-%s-payment-%s',
            $cart->getId(),
            $cart->getLastPayment()?->getId(),
        );
        $orderTotal = $cart->getTotal();

        $driver->getClient()->request(
            'POST',
            'https://127.0.0.1:8080/en_US/order/soge-commerce-after-submit',
            [
                'kr-answer' => json_encode([
                    'orderStatus' => 'PAID',
                    'orderDetails' => [
                        'orderId' => $orderId,
                        'orderTotalAmount' => $orderTotal,
                    ],
                    'transactions' => [[
                        'metadata' => [
                            'method' => $method->getCode(),
                        ],
                    ]],
                ]),
            ],
        );
    }
}
