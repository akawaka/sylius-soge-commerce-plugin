<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Client;

use Sylius\Component\Core\Model\Payment;

final class FakePayment extends Payment
{
    private int $fakeId;

    public function __construct(
        int $id,
    ) {
        parent::__construct();

        $this->fakeId = $id;
    }

    public function getId(): int
    {
        return $this->fakeId;
    }
}
