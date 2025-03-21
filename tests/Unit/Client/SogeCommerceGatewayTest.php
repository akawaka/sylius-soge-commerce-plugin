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

use Akawaka\SyliusSogeCommercePlugin\Client\OrderIdTransformer;
use Akawaka\SyliusSogeCommercePlugin\Client\OrderIdTransformerInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGateway;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;

final class SogeCommerceGatewayTest extends TestCase
{
    public function testCreateFormToken(): void
    {
        $gateway = new SogeCommerceGateway(
            $client = self::createMock(ClientInterface::class),
            new OrderIdTransformer(),
        );

        $method = new PaymentMethod();
        $method->setCode('my_payment_method');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('akawaka_soge_commerce');
        $gatewayConfig->setConfig([
            'user' => 'my_user',
            'password' => 'my_password',
        ]);
        $method->setGatewayConfig($gatewayConfig);

        $order = new FakeOrder(
            id: 132,
            paymentId: 1,
            customerEmail: 'user@mail.com',
            total: 4357,
        );

        $client->expects(self::once())
             ->method('request')
             ->with(
                 'POST',
                 'https://api-sogecommerce.societegenerale.eu/api-payment/V4/Charge/CreatePayment',
                 [
                     'headers' => [
                         'Authorization' => 'Basic bXlfdXNlcjpteV9wYXNzd29yZA==',
                         'Content-Type' => 'application/json',
                     ],
                     'json' => [
                         'amount' => 4357,
                         'currency' => 'EUR',
                         'orderId' => 'order-132-payment-1',
                         'customer' => [
                             'email' => 'user@mail.com',
                         ],
                         'metadata' => [
                             'method' => 'my_payment_method',
                         ],
                    ],
                ],
             )
            ->willReturn(new Response(
                body: (string) json_encode([
                    'status' => 'SUCCESS',
                    'answer' => [
                        'formToken' => 'form_token',
                    ],
                ]),
            ));

        self::assertEquals('form_token', $gateway->createFormToken($method, $order));
    }

    public function testCancelPayment(): void
    {
    }

    /**
     * @dataProvider provideIsPaymentSuccess
     */
    public function testIsPaymentSuccess(array $requestData, bool $expectedResult): void
    {
        $gateway = new SogeCommerceGateway(
            self::createMock(ClientInterface::class),
            self::createMock(OrderIdTransformerInterface::class),
        );

        self::assertEquals($expectedResult, $gateway->isPaymentSuccess($requestData));
    }

    public function provideIsPaymentSuccess(): iterable
    {
        yield [['orderStatus' => 'FAILED'], false];
        yield [['orderStatus' => 'PAID'], true];
        yield [[], false];
    }
}
