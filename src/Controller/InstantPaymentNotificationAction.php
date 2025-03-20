<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Controller;

use Akawaka\SyliusSogeCommercePlugin\Client\IsValidRequestInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\OrderIdTransformerInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Akawaka\SyliusSogeCommercePlugin\Handler\CapturePaymentHandlerInterface;
use Akawaka\SyliusSogeCommercePlugin\Handler\ProgressOrderStatusHandlerInterface;
use Akawaka\SyliusSogeCommercePlugin\Handler\UpdateOrderPaymentMethodHandlerInterface;
use Doctrine\Persistence\ObjectManager;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface as ModelPaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

final class InstantPaymentNotificationAction extends AbstractController
{
    public function __construct(
        private IsValidRequestInterface $isValidRequest,
        private SogeCommerceGatewayInterface $gateway,
        private OrderIdTransformerInterface $orderIdTransformer,
        private UpdateOrderPaymentMethodHandlerInterface $updateOrderPaymentMethodHandler,
        private ProgressOrderStatusHandlerInterface $progressOrderStatusHandler,
        private CapturePaymentHandlerInterface $capturePaymentHandler,
        private OrderRepositoryInterface $orderRepository,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ObjectManager $em,
    ) {
    }

    /**
     * This action is similar to Akawaka\SyliusSogeCommercePlugin\Controller\SmartFormAfterSubmitAction,
     * but it's initiated by the bank server. Consequently, responses and checks differ.
     * Unlike the other controller, this request captures the payment using the CapturePaymentHandler.
     */
    public function __invoke(Request $request): Response
    {
        $requestData = $this->getRequestData($request);

        $method = $this->getPaymentMethod($requestData);
        if (false === $this->isValidRequest->__invoke($method, $request)) {
            throw $this->createAccessDeniedException();
        }

        $order = $this->getOrder($requestData);
        $this->updateOrderPaymentMethodHandler->__invoke($method, $order);

        if (false === $this->gateway->isPaymentSuccess($requestData)) {
            $this->em->flush();

            return new Response();
        }

        try {
            $this->progressOrderStatusHandler->__invoke($order, $requestData);

            // The payment should be captured manually for the IPN action.
            // Also, we make sure to pass the correct payment.
            $this->capturePaymentHandler->__invoke($this->getPayment($order, $requestData));

            $this->em->flush();
        } catch (SMException) {
            throw new UnprocessableEntityHttpException();
        }

        return new Response();
    }

    private function getRequestData(Request $request): array
    {
        $requestData = json_decode((string) $request->request->get('kr-answer'), true);
        Assert::isArray($requestData);

        return $requestData;
    }

    private function getPaymentMethod(array $requestData): PaymentMethodInterface
    {
        Assert::isArray($requestData['transactions']);
        Assert::isArray($requestData['transactions'][0]);

        $methodCode = $requestData['transactions'][0]['metadata'][SogeCommerceGatewayInterface::METADATA_METHOD] ?? null;
        Assert::string($methodCode);

        $method = $this->paymentMethodRepository->findOneBy(['code' => $methodCode]);
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        return $method;
    }

    private function getOrder(array $requestData): OrderInterface
    {
        $orderId = $requestData['orderDetails']['orderId'] ?? null;
        Assert::string($orderId);

        $id = $this->orderIdTransformer->retrieve($orderId);

        $order = $this->orderRepository->findOneBy(['id' => $id]);
        if (null === $order) {
            throw $this->createNotFoundException();
        }

        Assert::isInstanceOf($order, OrderInterface::class);

        return $order;
    }

    private function getPayment(OrderInterface $order, array $requestData): PaymentInterface
    {
        $orderId = $requestData['orderDetails']['orderId'] ?? null;
        Assert::string($orderId);

        $id = $this->orderIdTransformer->retrievePayment($orderId);

        $payment = $order->getPayments()->filter(function (ModelPaymentInterface $payment) use ($id): bool {
            return ((string) $payment->getId()) === $id;
        })->first();

        if (false === $payment) {
            throw $this->createNotFoundException();
        }

        Assert::isInstanceOf($payment, PaymentInterface::class);

        return $payment;
    }
}
