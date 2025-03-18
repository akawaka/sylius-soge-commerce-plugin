<?php

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
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * @dataProvider provideIsValidBankReturn
     *
     * @param class-string<\Throwable>|null $expectedExceptionFQCN
     */
    public function testIsValidBankReturn(
        array $config,
        array $requestData,
        ?bool $expectedResult,
        ?string $expectedExceptionFQCN,
    ): void {
        $gateway = new SogeCommerceGateway(
            self::createMock(ClientInterface::class),
            self::createMock(OrderIdTransformerInterface::class),
        );

        $method = new PaymentMethod();

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);
        $method->setGatewayConfig($gatewayConfig);

        $request = new Request();
        $request->request->replace($requestData);

        if (null !== $expectedResult) {
            self::assertEquals($expectedResult, $gateway->isValidBankReturn($method, $request));
        }

        if (null !== $expectedExceptionFQCN) {
            self::expectException($expectedExceptionFQCN);
            $gateway->isValidBankReturn($method, $request);
        }
    }

    public function provideIsValidBankReturn(): iterable
    {
        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'sha256_hmac',
                'kr-answer' => 'some_answer',
                'kr-hash' => 'some_hash',
            ],
            false,
            null,
        ];

        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'sha256_hmac',
                'kr-answer' => 'some_answer',
                'kr-hash' => '8434fd6a93d9d12f709ca5a47cea66f2b34bab2fb77c04fcf19f066b0ef139fa',
            ],
            true,
            null,
        ];

        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'invalid algorithm',
                'kr-answer' => 'some_answer',
                'kr-hash' => '8434fd6a93d9d12f709ca5a47cea66f2b34bab2fb77c04fcf19f066b0ef139fa',
            ],
            null,
            \RuntimeException::class,
        ];
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
