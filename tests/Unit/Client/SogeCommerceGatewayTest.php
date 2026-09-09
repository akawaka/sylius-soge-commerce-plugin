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
use Akawaka\SyliusSogeCommercePlugin\Exception\FailedToCancelPaymentException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Address;
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
            currencyCode: 'EUR',
            number: 'ABC123',
            firstname: 'John',
            lastname: 'Doe',
            phone: '+33123456789',
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
                             'reference' => null, // client id
                             'email' => 'user@mail.com',
                             'billingDetails' => [
                                 'firstName' => 'John',
                                 'lastName' => 'Doe',
                                 'phoneNumber' => '+33123456789',
                                 'address' => '',
                                 'zipCode' => '',
                                 'city' => '',
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

    /**
     * @dataProvider providePhoneNumbersToSanitize
     */
    public function testCreateFormTokenOnlySendsTheDigitsOfThePhoneNumbers(?string $rawPhoneNumber, ?string $expectedPhoneNumber): void
    {
        // Soge Commerce rejects the whole form token request ("invalid customer shipping phone
        // number") as soon as a phone number carries free text, so only the phone digits may be sent.
        $gateway = new SogeCommerceGateway(
            $client = self::createMock(ClientInterface::class),
            new OrderIdTransformer(),
        );

        $order = new FakeOrder(
            id: 132,
            paymentId: 1,
            customerEmail: 'user@mail.com',
            total: 4357,
            currencyCode: 'EUR',
            firstname: 'John',
            lastname: 'Doe',
            phone: $rawPhoneNumber,
        );
        $shippingAddress = new Address();
        $shippingAddress->setPhoneNumber($rawPhoneNumber);
        $order->setShippingAddress($shippingAddress);

        $client->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static function (array $options) use ($expectedPhoneNumber): bool {
                    self::assertSame($expectedPhoneNumber, $options['json']['customer']['billingDetails']['phoneNumber']);
                    self::assertSame($expectedPhoneNumber, $options['json']['customer']['shippingDetails']['phoneNumber']);

                    return true;
                }),
            )
            ->willReturn(new Response(
                body: (string) json_encode([
                    'status' => 'SUCCESS',
                    'answer' => [
                        'formToken' => 'form_token',
                    ],
                ]),
            ));

        self::assertEquals('form_token', $gateway->createFormToken($this->createSogeCommerceMethod(), $order));
    }

    public function providePhoneNumbersToSanitize(): iterable
    {
        yield 'digits only are sent as is' => ['0612345678', '0612345678'];
        yield 'spaces are removed' => ['06 12 34 56 78', '0612345678'];
        yield 'dots are removed' => ['06.12.34.56.78', '0612345678'];
        yield 'the international prefix is kept' => ['+33 6 12 34 56 78', '+33612345678'];
        yield 'free text after the number is dropped (store clerk convention "phone / e-mail")' => ['0612345678 / jane.doe42@example.com', '0612345678'];
        yield 'free text before the number is skipped' => ['Tél : 0612345678', '0612345678'];
        yield 'too few digits are sent as null' => ['12345', null];
        yield 'text without any phone number is sent as null' => ['n/a', null];
        yield 'null stays null' => [null, null];
    }

    /**
     * @dataProvider providePaymentDetailsWithoutTransaction
     *
     * @param array<string, mixed> $details
     */
    public function testCancelPaymentRefusesAPaymentWithoutSogeCommerceTransaction(array $details): void
    {
        // Used to crash on "Undefined array key" plus a generic assertion when the payment never
        // went through Soge Commerce; callers can only recover from a SogeCommerceApiException.
        $gateway = new SogeCommerceGateway(
            $client = self::createMock(ClientInterface::class),
            new OrderIdTransformer(),
        );
        $client->expects(self::never())->method('request');

        $payment = new FakePayment(1);
        $payment->setMethod($this->createSogeCommerceMethod());
        $payment->setDetails($details);

        $this->expectException(FailedToCancelPaymentException::class);

        $gateway->cancelPayment($payment);
    }

    public function providePaymentDetailsWithoutTransaction(): iterable
    {
        yield 'no Soge Commerce payload at all' => [['cartToken' => null]];
        yield 'payload without transactions' => [['sogeCommerceRequestData' => ['orderDetails' => ['orderTotalAmount' => 1000]]]];
        yield 'transaction without uuid' => [['sogeCommerceRequestData' => ['transactions' => [['uuid' => null]]]]];
    }

    private function createSogeCommerceMethod(): PaymentMethod
    {
        $method = new PaymentMethod();
        $method->setCode('my_payment_method');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('akawaka_soge_commerce');
        $gatewayConfig->setConfig([
            'user' => 'my_user',
            'password' => 'my_password',
        ]);
        $method->setGatewayConfig($gatewayConfig);

        return $method;
    }
}
