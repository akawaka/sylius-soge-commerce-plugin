<?php

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
    ) {
        parent::__construct();

        $this->fakeId = $id;
        $this->fakeTotal = $total;

        $customer = new Customer();
        $customer->setEmail($customerEmail);
        $this->setCustomer($customer);

        $this->addPayment(new FakePayment($paymentId));
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
