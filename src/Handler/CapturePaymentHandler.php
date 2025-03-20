<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Handler;

use Payum\Core\Payum;
use Sylius\Bundle\PayumBundle\Factory\GetStatusFactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class CapturePaymentHandler implements CapturePaymentHandlerInterface
{
    public function __construct(
        private Payum $payum,
        private GetStatusFactoryInterface $getStatusRequestFactory,
    ) {
    }

    public function __invoke(PaymentInterface $payment): void
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $token = $this->payum->getTokenFactory()->createCaptureToken(
            $gatewayConfig->getGatewayName(),
            $payment,
            'sylius_shop_homepage',
            [],
        );

        $status = $this->getStatusRequestFactory->createNewWithModel($token);
        $this->payum->getGateway($token->getGatewayName())->execute($status);
    }
}
