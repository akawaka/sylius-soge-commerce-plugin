<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Client;

use Akawaka\SyliusSogeCommercePlugin\Exception\FailedToCancelPaymentException;
use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiNotActivatedException;
use GuzzleHttp\ClientInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class SogeCommerceGateway implements SogeCommerceGatewayInterface
{
    public function __construct(
        private ClientInterface $client,
        private OrderIdTransformerInterface $orderIdTransformer,
    ) {
    }

    public function createFormToken(PaymentMethodInterface $method, OrderInterface $order): string
    {
        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        if ($gatewayConfig->getFactoryName() !== 'akawaka_soge_commerce') {
            throw new \LogicException(sprintf('Method with code "%s" is not a valid "akawaka_soge_commerce" method.', $method->getCode() ?? ''));
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
                    'currency' => 'EUR', // todo
                    // 'currency' => $order->getCurrencyCode(),
                    'orderId' => $this->orderIdTransformer->transform((string) $order->getId(), (string) $payment->getId()),
                    'customer' => [
                        'email' => $order->getCustomer()?->getEmail(),
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

            throw new \RuntimeException(sprintf('Error when creating formToken: "%s"', $errorMessage));
        }

        Assert::isArray($data['answer']);
        $formToken = $data['answer']['formToken'];
        Assert::string($formToken);

        return $formToken;
    }

    public function isValidBankReturn(PaymentMethodInterface $method, Request $request): bool
    {
        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $hashAlgorithm = $request->request->get('kr-hash-algorithm');
        Assert::string($hashAlgorithm);
        if ($hashAlgorithm !== 'sha256_hmac') {
            throw new \RuntimeException(sprintf('Unsuported "%s" hash algorithm', $hashAlgorithm));
        }

        $key = $gatewayConfig->getConfig()['hmac_sha_256_key'] ?? null;
        Assert::string($key);

        $answer = str_replace('\/', '/', (string) $request->request->get('kr-answer'));

        return hash_hmac('sha256', $answer, $key) === $request->request->get('kr-hash');
    }

    public function cancelPayment(PaymentInterface $payment): void
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        if ($gatewayConfig->getFactoryName() !== 'akawaka_soge_commerce') {
            throw new \LogicException(sprintf('Method with code "%s" is not a valid "akawaka_soge_commerce" method.', $method->getCode() ?? ''));
        }

        $config = $gatewayConfig->getConfig();
        $user = $config['user'] ?? '';
        Assert::string($user);
        $password = $config['password'] ?? '';
        Assert::string($password);

        $authorization = base64_encode(sprintf('%s:%s', $user, $password));

        $details = $payment->getDetails();
        Assert::isArray($details[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY]);
        $transactions = $details[SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY]['transactions'];
        Assert::isArray($transactions);
        Assert::isArray($transactions[0]);

        $response = $this->client->request(
            'POST',
            'https://api-sogecommerce.societegenerale.eu/api-payment/V4/Charge/PaymentOrder/Cancel',
            [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $authorization),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'paymentOrderId' => $transactions[0]['uuid'],
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
     * See https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/kb/payment_done.html
     */
    public function isPaymentSuccess(array $requestData): bool
    {
        return ($requestData['orderStatus'] ?? null) === 'PAID';
    }
}
