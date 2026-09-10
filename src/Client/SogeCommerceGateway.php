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

namespace Akawaka\SyliusSogeCommercePlugin\Client;

use Akawaka\SyliusSogeCommercePlugin\Exception\FailedToCancelPaymentException;
use Akawaka\SyliusSogeCommercePlugin\Exception\InvalidPaymentMethodException;
use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiException;
use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiNotActivatedException;
use Akawaka\SyliusSogeCommercePlugin\Payum\PaymentGatewayFactory;
use GuzzleHttp\ClientInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class SogeCommerceGateway implements SogeCommerceGatewayInterface
{
    /** Size of the customer phone number fields on the Soge Commerce API. */
    private const PHONE_NUMBER_MAX_LENGTH = 32;

    /** Below this many digits the value is not a phone number, it is sent as null instead. */
    private const PHONE_NUMBER_MIN_DIGITS = 6;

    public function __construct(
        private ClientInterface $client,
        private OrderIdTransformerInterface $orderIdTransformer,
    ) {
    }

    public function createFormToken(PaymentMethodInterface $method, OrderInterface $order): string
    {
        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        if (PaymentGatewayFactory::FACTORY_NAME !== $gatewayConfig->getFactoryName()) {
            throw new InvalidPaymentMethodException(sprintf('Method with code "%s" is not a valid "%s" method.', $method->getCode() ?? '', PaymentGatewayFactory::FACTORY_NAME));
        }

        $config = $gatewayConfig->getConfig();
        $user = $config['user'] ?? '';
        Assert::string($user);
        $password = $config['password'] ?? '';
        Assert::string($password);

        $authorization = base64_encode(sprintf('%s:%s', $user, $password));

        $payment = $order->getPayments()->last();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $response = $this->client->request(
            'POST',
            'https://api-sogecommerce.societegenerale.eu/api-payment/V4/Charge/CreatePayment',
            [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $authorization),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => $order->getTotal(),
                    'currency' => $order->getCurrencyCode(),
                    'orderId' => $this->orderIdTransformer->transform((string) $order->getId(), (string) $payment->getId()),
                    'customer' => [
                        'reference' => $order->getCustomer()?->getId(),
                        'email' => $order->getCustomer()?->getEmail(),
                        'billingDetails' => [
                            'firstName' => $this->sanitizeString($order->getBillingAddress()?->getFirstName()),
                            'lastName' => $this->sanitizeString($order->getBillingAddress()?->getLastName()),
                            'phoneNumber' => $this->sanitizePhoneNumber($order->getBillingAddress()?->getPhoneNumber()),
                            'address' => $this->sanitizeString($order->getBillingAddress()?->getStreet()),
                            'zipCode' => $this->sanitizeString($order->getBillingAddress()?->getPostcode()),
                            'city' => $this->sanitizeString($order->getBillingAddress()?->getCity()),
                        ],
                        'shippingDetails' => [
                            'firstName' => $this->sanitizeString($order->getShippingAddress()?->getFirstName()),
                            'lastName' => $this->sanitizeString($order->getShippingAddress()?->getLastName()),
                            'phoneNumber' => $this->sanitizePhoneNumber($order->getShippingAddress()?->getPhoneNumber()),
                            'address' => $this->sanitizeString($order->getShippingAddress()?->getStreet()),
                            'zipCode' => $this->sanitizeString($order->getShippingAddress()?->getPostcode()),
                            'city' => $this->sanitizeString($order->getShippingAddress()?->getCity()),
                        ],
                    ],
                    'metadata' => [
                        SogeCommerceGatewayInterface::METADATA_METHOD => $method->getCode(),
                    ],
                ],
            ],
        );

        $data = json_decode((string) $response->getBody(), true);
        Assert::isArray($data);

        if (($data['status'] ?? null) !== 'SUCCESS') {
            $errorMessage = $data['answer']['errorMessage'] ?? null;
            Assert::string($errorMessage);

            throw new SogeCommerceApiException(sprintf('Error when creating formToken: "%s"', $errorMessage));
        }

        Assert::isArray($data['answer']);
        $formToken = $data['answer']['formToken'];
        Assert::string($formToken);

        return $formToken;
    }

    /**
     * Cancels a payment via the SogeCommerce API.
     *
     * Official documentation:
     * https://sogecommerce.societegenerale.eu/doc/en-EN/rest/V4.0/api/playground/Transaction/Cancel
     *
     * This method sends a POST request to the Transaction/Cancel endpoint to cancel an existing transaction,
     * identified by its UUID. It throws an exception if the cancellation fails or if the API is not activated.
     */
    public function cancelPayment(PaymentInterface $payment): void
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        if (PaymentGatewayFactory::FACTORY_NAME !== $gatewayConfig->getFactoryName()) {
            throw new InvalidPaymentMethodException(sprintf('Method with code "%s" is not a valid "%s" method.', $method->getCode() ?? '', PaymentGatewayFactory::FACTORY_NAME));
        }

        $config = $gatewayConfig->getConfig();
        $user = $config['user'] ?? '';
        Assert::string($user);
        $password = $config['password'] ?? '';
        Assert::string($password);

        $authorization = base64_encode(sprintf('%s:%s', $user, $password));

        // A payment that never went through Soge Commerce has no transaction to cancel: fail with
        // the API exception callers already handle instead of a PHP warning on the missing payload.
        $uuid = $payment->getDetails()[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY]['transactions'][0]['uuid'] ?? null;
        if (!is_string($uuid) || '' === $uuid) {
            throw new FailedToCancelPaymentException('The payment carries no Soge Commerce transaction to cancel.');
        }

        $response = $this->client->request(
            'POST',
            'https://api-sogecommerce.societegenerale.eu/api-payment/V4/Transaction/Cancel',
            [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $authorization),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'uuid' => $uuid,
                ],
            ],
        );

        $data = json_decode((string) $response->getBody(), true);
        Assert::isArray($data);

        // if the api is not activated (error code PSP_100, see https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/api/errors_psp.html)
        if (($data['status'] ?? null) === 'ERROR' && ($data['answer']['errorCode'] ?? null) === 'PSP_100') {
            throw new SogeCommerceApiNotActivatedException();
        }

        if (($data['status'] ?? null) !== 'SUCCESS') {
            throw new FailedToCancelPaymentException();
        }
    }

    /**
     * @see https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/kb/payment_done.html Doc on existing status.
     */
    public function isPaymentSuccess(array $requestData): bool
    {
        return ($requestData['orderStatus'] ?? null) === 'PAID';
    }

    private function sanitizeString(?string $value): ?string
    {
        return null !== $value ? trim($value) : null;
    }

    /**
     * Soge Commerce refuses the whole form token request ("invalid customer shipping phone number")
     * as soon as a phone number carries anything but digits, e.g. "06 12 34 56 78 / mail@example.com"
     * typed by a store clerk in the address book. Only the first phone-looking sequence is kept, with
     * its separators removed; a value without a usable number is sent as null, which the API accepts.
     */
    private function sanitizePhoneNumber(?string $value): ?string
    {
        if (null === $value || 1 !== preg_match('/\+?\d[\d\s.\-()]*/', $value, $matches)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $matches[0]) ?? '';
        if (strlen($digits) < self::PHONE_NUMBER_MIN_DIGITS) {
            return null;
        }

        $phoneNumber = (str_starts_with($matches[0], '+') ? '+' : '') . $digits;

        return strlen($phoneNumber) <= self::PHONE_NUMBER_MAX_LENGTH ? $phoneNumber : null;
    }
}
