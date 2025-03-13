<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Client;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;

interface SogeCommerceGatewayInterface
{
    public const METADATA_METHOD = 'method';

    public const PAYMENT_DETAILS_REQUEST_DATA_KEY = 'sogeCommerceRequestData';

    public const PAYMENT_DETAILS_STATUS_KEY = 'paymentStatus';

    public function createFormToken(PaymentMethodInterface $method, OrderInterface $order): string;

    public function isValidBankReturn(PaymentMethodInterface $method, Request $request): bool;

    public function cancelPayment(PaymentInterface $payment): void;

    public function isPaymentSuccess(array $requestData): bool;
}
