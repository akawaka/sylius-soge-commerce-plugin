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
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;

final class SogeCommerceGatewayTest extends TestCase
{
    public function testCreateFormToken(): void
    {
        $client = self::createMock(ClientInterface::class);
        $requestFactory = self::createMock(RequestFactoryInterface::class);
        $streamFactory = self::createMock(StreamFactoryInterface::class);

        $gateway = new SogeCommerceGateway(
            $client,
            new OrderIdTransformer(),
            $requestFactory,
            $streamFactory,
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
            currencyCode: 'EUR',
            number: 'ABC123',
            firstname: 'John',
            lastname: 'Doe',
            phone: '+33123456789',
        );

        $capturedHeaders = [];
        $request = self::createMock(RequestInterface::class);
        $request->method('withHeader')
            ->willReturnCallback(function (string $name, string $value) use (&$capturedHeaders, $request) {
                $capturedHeaders[$name] = $value;

                return $request;
            });
        $request->method('withBody')->willReturnSelf();

        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'https://api-sogecommerce.societegenerale.eu/api-payment/V4/Charge/CreatePayment')
            ->willReturn($request);

        $capturedJson = null;
        $stream = self::createMock(StreamInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStream')
            ->willReturnCallback(function (string $json) use (&$capturedJson, $stream) {
                $capturedJson = $json;

                return $stream;
            });

        $responseBody = self::createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn((string) json_encode([
            'status' => 'SUCCESS',
            'answer' => [
                'formToken' => 'form_token',
            ],
        ]));

        $response = self::createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);

        $client->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        self::assertEquals('form_token', $gateway->createFormToken($method, $order));

        self::assertSame(
            [
                'Authorization' => 'Basic bXlfdXNlcjpteV9wYXNzd29yZA==',
                'Content-Type' => 'application/json',
            ],
            $capturedHeaders,
        );

        self::assertNotNull($capturedJson);
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'amount' => 4357,
                'currency' => 'EUR',
                'orderId' => 'order-132-payment-1',
                'customer' => [
                    'reference' => null,
                    'email' => 'user@mail.com',
                    'billingDetails' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'phoneNumber' => '+33123456789',
                        'address' => null,
                        'zipCode' => null,
                        'city' => null,
                    ],
                    'shippingDetails' => [
                        'firstName' => null,
                        'lastName' => null,
                        'phoneNumber' => null,
                        'address' => null,
                        'zipCode' => null,
                        'city' => null,
                    ],
                ],
                'metadata' => [
                    'method' => 'my_payment_method',
                ],
            ]),
            $capturedJson,
        );
    }

    /**
     * @dataProvider provideIsPaymentSuccess
     */
    public function testIsPaymentSuccess(array $requestData, bool $expectedResult): void
    {
        $gateway = new SogeCommerceGateway(
            self::createMock(ClientInterface::class),
            self::createMock(OrderIdTransformerInterface::class),
            self::createMock(RequestFactoryInterface::class),
            self::createMock(StreamFactoryInterface::class),
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
