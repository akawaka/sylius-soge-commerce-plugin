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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Client;

use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;

final class FakeOrder extends Order
{
    private int $fakeId;

    private int $fakeTotal;

    public function __construct(
        int $id,
        int $paymentId,
        string $customerEmail,
        int $total,
        string $currencyCode,
    ) {
        parent::__construct();

        $this->fakeId = $id;
        $this->fakeTotal = $total;

        $customer = new Customer();
        $customer->setEmail($customerEmail);
        $this->setCustomer($customer);

        $this->addPayment(new FakePayment($paymentId));

        $this->setCurrencyCode($currencyCode);
    }

    public function getId(): int
    {
        return $this->fakeId;
    }

    public function getTotal(): int
    {
        return $this->fakeTotal;
    }
}
